<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/config.php';

// Set PDO to throw exceptions on errors
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Verify table structure
try {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    error_log("Tables in database: " . print_r($tables, true));
    
    foreach ($tables as $table) {
        $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
        error_log("$table columns: " . print_r($columns, true));
    }
} catch (PDOException $e) {
    error_log("Database structure verification failed: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transactionStarted = false;
    
    try {
        // Validate and sanitize inputs
        $service_number = htmlspecialchars(trim($_POST['service_number']), ENT_QUOTES, 'UTF-8');
        if (!preg_match('/^\d{1,5}$/', $service_number)) {
            throw new Exception("Service Number must contain only digits and be between 1-5 characters in length.");
        }

        // Check if service number already exists before transaction
        $check_stmt = $pdo->prepare("SELECT COUNT(*) as count, surname, firstname FROM personnel WHERE service_number = ?");
        $check_stmt->execute([$service_number]);
        $existing_record = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_record && $existing_record['count'] > 0) {
            $existing_name = $existing_record['surname'] . ' ' . $existing_record['firstname'];
            throw new Exception("Service Number <strong>{$service_number}</strong> is already assigned to <strong>{$existing_name}</strong>. Please use a different service number or verify the existing record.");
        }

        $personnel_data = [
            'service_number' => $service_number,
            'surname' => strtoupper(htmlspecialchars(trim($_POST['surname']), ENT_QUOTES, 'UTF-8')),
            'firstname' => strtoupper(htmlspecialchars(trim($_POST['firstname']), ENT_QUOTES, 'UTF-8')),
            'middlename' => strtoupper(htmlspecialchars(trim($_POST['middlename']), ENT_QUOTES, 'UTF-8')),
            'date_of_birth' => $_POST['date_of_birth'],
            'gender' => strtoupper(htmlspecialchars(trim($_POST['gender']), ENT_QUOTES, 'UTF-8')),
            'rank' => htmlspecialchars(trim($_POST['rank']), ENT_QUOTES, 'UTF-8')
        ];

        $pdo->beginTransaction();
        $transactionStarted = true;

        // Insert personnel data - FIXED: escaped 'rank' with backticks
        $sql = "INSERT INTO personnel (service_number, surname, firstname, middlename, date_of_birth, gender, `rank`) 
                VALUES (:service_number, :surname, :firstname, :middlename, :date_of_birth, :gender, :rank)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($personnel_data);

        // Get the personnel_id of the newly inserted record
        $personnel_id = $pdo->lastInsertId();

        // Process postings
        if (isset($_POST['posting_type']) && is_array($_POST['posting_type'])) {
            // First, get the formation_id for each posting
            $formation_stmt = $pdo->prepare("SELECT id FROM formations WHERE name = ?");
            
            $posting_stmt = $pdo->prepare("
                INSERT INTO postings (personnel_id, formation_id, date_of_posting, posting_status) 
                VALUES (:personnel_id, :formation_id, :date_of_posting, :posting_status)
            ");

            foreach ($_POST['posting_type'] as $index => $posting_type) {
                // Skip if required fields are empty
                if (empty($_POST['formation'][$index]) || empty($_POST['date_of_posting'][$index])) {
                    continue;
                }

                // Get formation_id
                $formation_stmt->execute([strtoupper($_POST['formation'][$index])]);
                $formation = $formation_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$formation) {
                    // If formation doesn't exist, create it
                    $new_formation_stmt = $pdo->prepare("
                        INSERT INTO formations (name, location_type) 
                        VALUES (?, 'COMMAND')
                    ");
                    $new_formation_stmt->execute([strtoupper($_POST['formation'][$index])]);
                    $formation_id = $pdo->lastInsertId();
                } else {
                    $formation_id = $formation['id'];
                }

                $posting_data = [
                    'personnel_id' => $personnel_id,
                    'formation_id' => $formation_id,
                    'date_of_posting' => $_POST['date_of_posting'][$index],
                    'posting_status' => $posting_type === 'Current' ? 'Active' : 'Completed'
                ];
                
                $posting_stmt->execute($posting_data);
            }
        }

        $pdo->commit();
        $success = "Personnel record has been successfully been created and posting order submitted!";
        
        // Store success flag to show the "Add Another" button
        $_SESSION['show_add_another'] = true;
        
    } catch (PDOException $e) {
        // Only rollback if transaction was started
        if ($transactionStarted) {
            try {
                $pdo->rollBack();
            } catch (PDOException $rollbackEx) {
                error_log("Rollback failed: " . $rollbackEx->getMessage());
            }
        }
        
        // Handle specific database errors professionally
        if ($e->getCode() == 23000) {
            if (strpos($e->getMessage(), 'service_number') !== false) {
                $service_num = isset($_POST['service_number']) ? htmlspecialchars($_POST['service_number']) : '';
                $error = "Service Number <strong>{$service_num}</strong> is already registered in the system. This service number cannot be duplicated. Please verify the number or contact the system administrator for assistance.";
            } else {
                $error = "Database integrity constraint violation. The operation could not be completed due to data conflict.";
            }
        } else {
            $error = "A database error occurred while processing your request. Please try again or contact system support if the issue persists.";
            error_log("Database Error: " . $e->getMessage());
        }
        
    } catch (Exception $e) {
        // Only rollback if transaction was started
        if ($transactionStarted) {
            try {
                $pdo->rollBack();
            } catch (PDOException $rollbackEx) {
                error_log("Rollback failed: " . $rollbackEx->getMessage());
            }
        }
        error_log("Error: " . $e->getMessage());
        $error = $e->getMessage();
        
        // Store error details for debugging
        $_SESSION['error_details'] = [
            'message' => $e->getMessage(),
            'post_data' => $_POST
        ];
    }
}

// Add after the POST processing try-catch block
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add this debugging code
    error_log('Form submitted. POST data: ' . print_r($_POST, true));
    
    // Check if the form was submitted but no processing occurred
    if (!isset($success) && !isset($error)) {
        $error = "Form submission could not be processed. Please try again.";
        error_log('Form submission failed - no success/error set');
    }
}

// Check if we should show the "Add Another" button
$showAddAnother = isset($_SESSION['show_add_another']) && $_SESSION['show_add_another'];
?>

<?php include 'includes/header.php'; ?>
<link rel="stylesheet" href="assets/css/form-style.css">
<link rel="stylesheet" href="assets/css/additional-styles.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
    :root {
        --primary-color: #27ae60;
        --secondary-color: #2c3e50;
        --light-color: #3498db;
    }

    body {
        margin: 0;
        padding: 0;
        min-height: 100vh;
        background: linear-gradient(135deg, #27ae60 0%, #2c3e50 100%), url('assets/images/tech_building.jpg');
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        font-family: 'Segoe UI', Arial, sans-serif;
        padding-bottom: 100px; /* Add padding to ensure content isn't hidden behind footer */
    }

    .container {
        padding-top: 1rem;
        padding-bottom: 1rem;
        max-width: 1200px;
    }

    .card {
        background: rgba(255, 255, 255, 0.98);
        border-radius: 10px;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
        margin-bottom: 1.5rem;
        border: none;
    }

    .card-header {
        background: linear-gradient(135deg, #27ae60 0%, #2c3e50 100%);
        color: white;
        border-radius: 10px 10px 0 0 !important;
        padding: 15px 20px;
        border-bottom: none;
    }

    .card-header h5 {
        margin: 0;
        font-weight: 600;
    }

    .uppercase-input {
        text-transform: uppercase;
    }

    .posting-entry {
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
        background: #f8f9fa;
    }

    .posting-entry:last-child {
        border-bottom: 1px solid #e0e0e0;
    }

    .btn-sm {
        padding: 0.4rem 0.8rem;
        font-size: 0.85rem;
    }

    h2.display-4 {
        color: #27ae60;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .border-bottom.border-success {
        border-color: #ffffff !important;
        width: 100px;
        margin: 0 auto 1.5rem;
        height: 3px;
        border-width: 2px !important;
    }

    .btn-primary {
        background: linear-gradient(135deg, #27ae60 0%, #2c3e50 100%);
        border: none;
        transition: all 0.3s ease;
        font-weight: 600;
        padding: 12px 30px;
        font-size: 1.1rem;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #2ecc71 0%, #34495e 100%);
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
    }

    .btn-success {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        border: none;
        transition: all 0.3s ease;
        font-weight: 600;
        padding: 12px 30px;
        font-size: 1.1rem;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    .btn-success:hover {
        background: linear-gradient(135deg, #34ce57 0%, #3dd5ac 100%);
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
    }

    .form-control:focus {
        border-color: #27ae60;
        box-shadow: 0 0 0 0.2rem rgba(39, 174, 96, 0.25);
    }

    .is-invalid {
        border-color: #dc3545 !important;
    }

    .is-invalid:focus {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
    }

    .form-group {
        margin-bottom: 1.2rem;
    }

    .form-group label {
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: #2c3e50;
    }

    .required-field::after {
        content: " *";
        color: #dc3545;
    }

    /* Alert styling improvements */
    .alert {
        font-size: 0.95rem;
        padding: 0.75rem 1rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        border-left: 4px solid;
    }

    .alert-success {
        border-left-color: #28a745;
        background: #f8fff9;
    }

    .alert-danger {
        border-left-color: #dc3545;
        background: #fff5f5;
    }

    .alert-warning {
        border-left-color: #ffc107;
        background: #fffef0;
    }

    /* Unified mobile-style layout for all screen sizes */
    .card-body {
        padding: 1.5rem;
    }

    .row {
        margin-left: -8px;
        margin-right: -8px;
    }

    .col-md-6, .col-md-3, .col-md-4, .col-md-2 {
        padding-left: 8px;
        padding-right: 8px;
        margin-bottom: 10px;
    }

    .posting-entry .row {
        margin-left: -5px;
        margin-right: -5px;
    }

    .posting-entry .col-md-3,
    .posting-entry .col-md-4,
    .posting-entry .col-md-2 {
        padding-left: 5px;
        padding-right: 5px;
        margin-bottom: 15px;
    }

    .posting-entry .d-flex {
        justify-content: space-between;
        margin-top: 10px;
    }

    .posting-entry .btn {
        font-size: 0.85rem;
        padding: 0.5rem 0.8rem;
        flex: 1;
        margin: 0 3px;
    }

    .btn-lg {
        padding: 0.75rem 1.5rem;
        font-size: 1.1rem;
        width: 100%;
        margin-top: 1rem;
    }

    .text-center.mt-4.mb-4 {
        margin-top: 2rem !important;
        margin-bottom: 2rem !important;
        padding: 0 15px;
    }

    /* Professional submit section */
    .submit-section {
        position: sticky;
        bottom: 0;
        background: rgba(255, 255, 255, 0.98);
        padding: 15px 0;
        margin-top: 2rem;
        border-top: 1px solid #e0e0e0;
        z-index: 1000;
        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
        backdrop-filter: blur(5px);
    }

    .btn-submit {
        background: linear-gradient(135deg, #27ae60 0%, #2c3e50 100%);
        border: none;
        border-radius: 8px;
        padding: 12px 40px;
        font-size: 1.1rem;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        min-width: 220px;
    }

    .btn-submit:hover {
        background: linear-gradient(135deg, #2ecc71 0%, #34495e 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
    }

    .btn-submit:active {
        transform: translateY(0);
    }

    /* Add Another section */
    .add-another-section {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 10px;
        padding: 2rem;
        margin-top: 2rem;
        text-align: center;
        border: 2px dashed #27ae60;
    }

    .btn-add-another {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        border: none;
        border-radius: 8px;
        padding: 12px 40px;
        font-size: 1.1rem;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        min-width: 220px;
        color: white;
    }

    .btn-add-another:hover {
        background: linear-gradient(135deg, #34ce57 0%, #3dd5ac 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
        color: white;
    }

    /* Responsive adjustments for submit section */
    @media (max-width: 768px) {
        .submit-section {
            padding: 12px 0;
        }
        
        .btn-submit {
            padding: 10px 30px;
            font-size: 1rem;
            min-width: 200px;
        }
    }

    @media (max-width: 576px) {
        .submit-section {
            padding: 10px 0;
        }
        
        .btn-submit {
            padding: 8px 25px;
            font-size: 0.95rem;
            min-width: 180px;
        }
        
        .submit-section .text-muted {
            font-size: 0.8rem;
        }
    }

    /* Professional error styling */
    .service-number-error {
        border-color: #dc3545 !important;
        background-color: #fff5f5;
    }

    .error-help {
        font-size: 0.875rem;
        color: #6c757d;
        margin-top: 0.25rem;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .container {
            padding-left: 10px;
            padding-right: 10px;
        }
        
        .card-header h5 {
            font-size: 1.1rem;
        }
        
        h2.display-4 {
            font-size: 1.6rem;
        }
        
        .btn-lg {
            padding: 0.8rem 1.5rem;
        }
    }
    
    @media (max-width: 576px) {
        h2.display-4 {
            font-size: 1.4rem;
        }
        
        .card-body {
            padding: 1rem;
        }
        
        .form-control {
            font-size: 0.9rem;
        }
        
        .btn {
            font-size: 0.85rem;
        }
        
        .btn-lg {
            padding: 0.7rem 1.2rem;
            font-size: 1rem;
        }
    }

    @media (min-width: 1200px) {
        .container {
            padding-top: 2rem;
            padding-bottom: 2rem;
        }
    }
</style>

<div class="container">
    <div class="text-center mb-4">
        <h2 class="display-4 mb-3">Personnel Posting Order</h2>
        <div class="border-bottom border-success mx-auto"></div>
    </div>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $success; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo $error; ?>
            <?php if (strpos($error, 'Service Number') !== false): ?>
                <div class="mt-2 error-help">
                    <small>
                        <i class="fas fa-info-circle me-1"></i>
                        Please verify the service number or contact the HRM DIRECTORATE for assistance.
                    </small>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_details']) && defined('ENVIRONMENT') && ENVIRONMENT === 'development'): ?>
        <div class="alert alert-warning">
            <strong><i class="fas fa-bug me-2"></i>Debug Information:</strong>
            <pre class="mt-2"><?php echo htmlspecialchars(print_r($_SESSION['error_details'], true)); ?></pre>
        </div>
        <?php unset($_SESSION['error_details']); ?>
    <?php endif; ?>
    
    <?php if (!$showAddAnother): ?>
    <form method="POST" id="personnelForm" novalidate>
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-user me-2"></i>Personal Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <div class="form-group">
                            <label for="service_number" class="required-field">Service Number</label>
                            <input type="text" 
                                   class="form-control <?php echo (isset($error) && strpos($error, 'Service Number') !== false) ? 'service-number-error is-invalid' : ''; ?>" 
                                   id="service_number" 
                                   name="service_number" 
                                   required 
                                   placeholder="Enter service number (max 5 digits)" 
                                   maxlength="5" 
                                   pattern="[0-9]{1,5}" 
                                   title="Please enter up to 5 digits only"
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 5)"
                                   value="<?php echo isset($_POST['service_number']) ? htmlspecialchars($_POST['service_number']) : ''; ?>">
                            <div class="error-help">
                                <small>Service Number must be five(5) digits only.</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-group">
                            <label for="surname" class="required-field">Surname</label>
                            <input type="text" class="form-control uppercase-input" id="surname" name="surname" required 
                                   placeholder="Enter surname"
                                   value="<?php echo isset($_POST['surname']) ? htmlspecialchars($_POST['surname']) : ''; ?>">
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-group">
                            <label for="firstname" class="required-field">Firstname</label>
                            <input type="text" class="form-control uppercase-input" id="firstname" name="firstname" required
                                   value="<?php echo isset($_POST['firstname']) ? htmlspecialchars($_POST['firstname']) : ''; ?>">
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-group">
                            <label for="middlename">Middlename</label>
                            <input type="text" class="form-control uppercase-input" id="middlename" name="middlename"
                                   value="<?php echo isset($_POST['middlename']) ? htmlspecialchars($_POST['middlename']) : ''; ?>">
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-group">
                            <label for="date_of_birth" class="required-field">Date of Birth</label>
                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" required
                                   value="<?php echo isset($_POST['date_of_birth']) ? htmlspecialchars($_POST['date_of_birth']) : ''; ?>">
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-group">
                            <label for="rank" class="required-field">Rank</label>
                            <select class="form-control" id="rank" name="rank" required>
                                <option value="">Select Rank</option>
                                <option value="CG" <?php echo (isset($_POST['rank']) && $_POST['rank'] === 'CG') ? 'selected' : ''; ?>>CG</option>
                                <option value="DCG" <?php echo (isset($_POST['rank']) && $_POST['rank'] === 'DCG') ? 'selected' : ''; ?>>DCG</option>
                                <option value="ACG" <?php echo (isset($_POST['rank']) && $_POST['rank'] === 'ACG') ? 'selected' : ''; ?>>ACG</option>
                                <option value="CIS" <?php echo (isset($_POST['rank']) && $_POST['rank'] === 'CIS') ? 'selected' : ''; ?>>CIS</option>
                                <option value="DCI" <?php echo (isset($_POST['rank']) && $_POST['rank'] === 'DCI') ? 'selected' : ''; ?>>DCI</option>
                                <option value="ACI" <?php echo (isset($_POST['rank']) && $_POST['rank'] === 'ACI') ? 'selected' : ''; ?>>ACI</option>
                                <option value="CSI" <?php echo (isset($_POST['rank']) && $_POST['rank'] === 'CSI') ? 'selected' : ''; ?>>CSI</option>
                                <option value="SI" <?php echo (isset($_POST['rank']) && $_POST['rank'] === 'SI') ? 'selected' : ''; ?>>SI</option>
                                <option value="DSI" <?php echo (isset($_POST['rank']) && $_POST['rank'] === 'DSI') ? 'selected' : ''; ?>>DSI</option>
                                <option value="ASI-1" <?php echo (isset($_POST['rank']) && $_POST['rank'] === 'ASI-1') ? 'selected' : ''; ?>>ASI-1</option>
                                <option value="ASI-2" <?php echo (isset($_POST['rank']) && $_POST['rank'] === 'ASI-2') ? 'selected' : ''; ?>>ASI-2</option>
                                <option value="II" <?php echo (isset($_POST['rank']) && $_POST['rank'] === 'II') ? 'selected' : ''; ?>>II</option>
                                <option value="CIA" <?php echo (isset($_POST['rank']) && $_POST['rank'] === 'CIA') ? 'selected' : ''; ?>>CIA</option>
                                <option value="AII" <?php echo (isset($_POST['rank']) && $_POST['rank'] === 'AII') ? 'selected' : ''; ?>>AII</option>
                                <option value="SIA" <?php echo (isset($_POST['rank']) && $_POST['rank'] === 'SIA') ? 'selected' : ''; ?>>SIA</option>
                                <option value="IA-1" <?php echo (isset($_POST['rank']) && $_POST['rank'] === 'IA-1') ? 'selected' : ''; ?>>IA-1</option>
                                <option value="IA-2" <?php echo (isset($_POST['rank']) && $_POST['rank'] === 'IA-2') ? 'selected' : ''; ?>>IA-2</option>
                                <option value="IA-3" <?php echo (isset($_POST['rank']) && $_POST['rank'] === 'IA-3') ? 'selected' : ''; ?>>IA-3</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-group">
                            <label for="gender" class="required-field">Gender</label>
                            <select class="form-control" id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Posting Information</h5>
            </div>
            <div class="card-body" id="postingsContainer">
                <!-- Postings will be added here dynamically -->
                <div class="posting-entry mb-3">
                    <div class="row">
                        <div class="col-12">
                            <div class="form-group">
                                <label>Posting Type*</label>
                                <select class="form-control posting-type" name="posting_type[]" required>
                                    <option value="">Select Type</option>
                                    <option value="Previous">Previous Posting</option>
                                    <option value="Current">Current Posting</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label>Formation*</label>
                                <select class="form-control" name="formation[]" required>
                                    <option value="">--Select Formation--</option>
                                    <option value="Service Headquarters">Service Headquarters (SHQ)</option>
                                    <option value="Zone A Lagos">Zone 'A' Lagos</option>
                                    <option value="Zone B Kaduna">Zone 'B' Kaduna</option>
                                    <option value="Zone C Bauchi">Zone 'C' Bauchi</option>
                                    <option value="Zone D Minna">Zone 'D' Minna</option>
                                    <option value="Zone E Owerri">Zone 'E' Owerri</option>
                                    <option value="Zone F Ibadan">Zone 'F' Ibadan</option>
                                    <option value="Zone G Benin City">Zone 'G' Benin City</option>
                                    <option value="Zone H Makurdi">Zone 'H' Makurdi</option>
                                    <!-- State Commands -->
                                    <option value="Abia">Abia State Command</option>
                                    <option value="Adamawa">Adamawa State Command</option>
                                    <option value="Akwa Ibom">Akwa Ibom State Command</option>
                                    <option value="Anambra">Anambra State Command</option>
                                    <option value="Bauchi">Bauchi State Command</option>
                                    <option value="Bayelsa">Bayelsa State Command</option>
                                    <option value="Benue">Benue State Command</option>
                                    <option value="Borno">Borno State Command</option>
                                    <option value="Cross River">Cross River State Command</option>
                                    <option value="Delta">Delta State Command</option>
                                    <option value="Ebonyi">Ebonyi State Command</option>
                                    <option value="Edo">Edo State Command</option>
                                    <option value="Ekiti">Ekiti State Command</option>
                                    <option value="Enugu">Enugu State Command</option>
                                    <option value="FCT">FCT Command</option>
                                    <option value="Gombe">Gombe State Command</option>
                                    <option value="Imo">Imo State Command</option>
                                    <option value="Jigawa">Jigawa State Command</option>
                                    <option value="Kaduna">Kaduna State Command</option>
                                    <option value="Kano">Kano State Command</option>
                                    <option value="Katsina">Katsina State Command</option>
                                    <option value="Kebbi">Kebbi State Command</option>
                                    <option value="Kogi">Kogi State Command</option>
                                    <option value="Kwara">Kwara State Command</option>
                                    <option value="Lagos">Lagos State Command</option>
                                    <option value="Nasarawa">Nasarawa State Command</option>
                                    <option value="Niger">Niger State Command</option>
                                    <option value="Ogun">Ogun State Command</option>
                                    <option value="Ondo">Ondo State Command</option>
                                    <option value="Osun">Osun State Command</option>
                                    <option value="Oyo">Oyo State Command</option>
                                    <option value="Plateau">Plateau State Command</option>
                                    <option value="Rivers">Rivers State Command</option>
                                    <option value="Sokoto">Sokoto State Command</option>
                                    <option value="Taraba">Taraba State Command</option>
                                    <option value="Yobe">Yobe State Command</option>
                                    <option value="Zamfara">Zamfara State Command</option>
                                    <option value="LABPC">Lagos Border Patrol Command</option>
                                    <option value="MMIA">MMIA</option>
                                    <option value="Lagos Passport Command">Lagos Passport Command</option>
                                    <option value="Seme Border Command">Seme Border Command</option>
                                    <option value="Idiroko Border Command">Idiroko Border Command</option>
                                    <option value="Illela Border Command">Illela Border Command</option>
                                    <option value="Jibiya Border Command">Jibiya Border Command</option>
                                    <option value="ITSK">Immigration Training School Kano</option>
                                    <option value="ICSC">Immigration Command and Staff College</option>
                                    <option value="NITSA">Nigeria Immigration Training School Ahoada</option>
                                    <option value="NITSOL">Nigeria Immigration Training School Orlu</option>
                                    <option value="Mfum Border Command">Mfum Border Command</option>
                                    <option value="River Marine Command">River Marine Command</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label>Date of Posting*</label>
                                <input type="date" class="form-control" name="date_of_posting[]" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex">
                                <button type="button" class="btn btn-primary btn-sm mr-1" onclick="addPosting(this)">
                                    <i class="fas fa-plus me-1"></i>ADD POSTING
                                </button>
                                <button type="button" class="btn btn-danger btn-sm" onclick="removePosting(this)">
                                    <i class="fas fa-trash me-1"></i>REMOVE
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="submit-section">
            <div class="text-center">
                <button type="submit" class="btn btn-primary btn-submit">
                    <i class="fas fa-paper-plane me-2"></i>Submit Posting Order
                </button>
                <p class="text-muted mt-2 mb-0">
                    <small><i class="fas fa-info-circle me-1"></i>All fields marked with <span class="text-danger">*</span> are required</small>
                </p>
            </div>
        </div>
    </form>
    <?php else: ?>
        <!-- Show Add Another Personnel section after successful submission -->
        <div class="add-another-section">
            <div class="text-center">
                <h3 class="mb-3 text-success">
                    <i class="fas fa-check-circle me-2"></i>Application Submitted Successfully!
                </h3>
                <p class="mb-4 text-muted">
                    The personnel record has been successfully created and posting order submitted.
                </p>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-add-another">
                    <i class="fas fa-user-plus me-2"></i>ADD ANOTHER PERSONNEL
                </a>
                <p class="text-muted mt-3 mb-0">
                    <small>Click the button above to create another personnel record</small>
                </p>
            </div>
        </div>
        
        <?php 
        // Clear the session flag
        unset($_SESSION['show_add_another']);
        ?>
    <?php endif; ?>
</div>

<?php
// Add before the form HTML
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    echo '<div class="container mt-3">';
    echo '<div class="alert alert-info">';
    echo '<h4><i class="fas fa-code me-2"></i>Debug Information:</h4>';
    echo '<pre>';
    if (isset($_POST)) {
        print_r($_POST);
    }
    echo '</pre>';
    echo '</div></div>';
}
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('personnelForm');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Basic validation
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('is-invalid');
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            // Check for current posting
            const postingTypes = document.getElementsByName('posting_type[]');
            let hasCurrentPosting = false;
            
            for (let i = 0; i < postingTypes.length; i++) {
                if (postingTypes[i].value === 'Current') {
                    hasCurrentPosting = true;
                    break;
                }
            }
            
            if (!hasCurrentPosting) {
                alert('Please add at least one Current Posting before submitting.');
                return false;
            }
            
            // Check for duplicate current postings
            let currentPostingCount = 0;
            for (let i = 0; i < postingTypes.length; i++) {
                if (postingTypes[i].value === 'Current') {
                    currentPostingCount++;
                }
            }
            
            if (currentPostingCount > 1) {
                alert('Only one Current Posting is allowed. Please mark only one posting as Current.');
                return false;
            }
            
            if (!isValid) {
                alert('Please fill in all required fields.');
                return false;
            }
            
            // If all validations pass, submit the form
            this.submit();
        });
    }
});

function addPosting(button) {
    const postingEntry = button.closest('.posting-entry');
    const newPosting = postingEntry.cloneNode(true);
    
    // Clear the values in the cloned posting
    newPosting.querySelectorAll('select, input').forEach(input => {
        input.value = '';
        input.classList.remove('is-invalid');
    });
    
    postingEntry.parentNode.insertBefore(newPosting, postingEntry.nextSibling);
}

function removePosting(button) {
    const postingEntry = button.closest('.posting-entry');
    const allPostings = document.getElementsByClassName('posting-entry');
    
    if (allPostings.length > 1) {
        const postingType = postingEntry.querySelector('.posting-type').value;
        
        if (postingType === 'Current') {
            let currentPostingsCount = 0;
            for (let i = 0; i < allPostings.length; i++) {
                if (allPostings[i].querySelector('.posting-type').value === 'Current') {
                    currentPostingsCount++;
                }
            }
            
            if (currentPostingsCount <= 1) {
                alert('At least one Current Posting is required.');
                return;
            }
        }
        
        postingEntry.remove();
    }
}
</script>

<!--<?php include 'includes/footer.php'; ?>-->