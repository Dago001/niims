<?php 
session_start();
if (!isset($_SESSION['username'])) {
    require_once('index.php');
    exit;
}

require_once('db/db.php');

if ($_SESSION['role'] == "Admin") {
    include 'sidebar-admin.php';
}

if ($_SESSION['role'] == "Supervisor") {
    include 'sidebar-supervisor.php';
}

include 'menu-footer-buttons.php';
include 'top-navigation.php';

$id = isset($_GET['id']) ? $_GET['id'] : '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['updateQualification'])) {
    // Sanitize and get form data
    $serviceNo = mysqli_real_escape_string($conn, $_POST['serviceNo']);
    $qualification = mysqli_real_escape_string($conn, $_POST['qualification']);
    $institution = mysqli_real_escape_string($conn, $_POST['institution']);
    $certificate_number = mysqli_real_escape_string($conn, $_POST['certificate_number']);
    $date_obtained = mysqli_real_escape_string($conn, $_POST['date_obtained']);
    
    // Check if record exists
    $check_sql = "SELECT * FROM tbl_qualification WHERE serviceNo = '$serviceNo'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        // Update existing record
        $update_sql = "UPDATE tbl_qualification SET 
                       qualification = '$qualification',
                       institution = '$institution',
                       certificate_number = '$certificate_number',
                       date_obtained = '$date_obtained'
                       WHERE serviceNo = '$serviceNo'";
        
        if (mysqli_query($conn, $update_sql)) {
            // Success message and redirect to search parameter
            echo "<script>
                alert('Qualification record updated successfully!');
                window.location.href = 'enrolment-update?search=$serviceNo';
            </script>";
            exit;
        } else {
            echo "<script>alert('Error updating record: " . mysqli_error($conn) . "');</script>";
        }
    } else {
        // Insert new record
        $insert_sql = "INSERT INTO tbl_qualification (serviceNo, qualification, institution, certificate_number, date_obtained) 
                       VALUES ('$serviceNo', '$qualification', '$institution', '$certificate_number', '$date_obtained')";
        
        if (mysqli_query($conn, $insert_sql)) {
            // Success message and redirect to search parameter
            echo "<script>
                alert('Qualification record created successfully!');
                window.location.href = 'enrolment-update?search=$serviceNo';
            </script>";
            exit;
        } else {
            echo "<script>alert('Error creating record: " . mysqli_error($conn) . "');</script>";
        }
    }
}

// Fetch personal info for display
$personal_sql = "SELECT serviceNo, surname, firstName, middleName FROM tbl_emppersonal WHERE serviceNo = '$id'";
$personal_result = mysqli_query($conn, $personal_sql);
$personal_data = mysqli_fetch_assoc($personal_result);

// Fetch qualification data if exists
$qual_sql = "SELECT * FROM tbl_qualification WHERE serviceNo = '$id'";
$qual_result = mysqli_query($conn, $qual_sql);

if (mysqli_num_rows($qual_result) > 0) {
    $qual_data = mysqli_fetch_assoc($qual_result);
    $qualification_value = htmlspecialchars($qual_data['qualification']);
    $institution_value = htmlspecialchars($qual_data['institution']);
    $certificate_number_value = htmlspecialchars($qual_data['certificate_number']);
    $date_obtained_value = htmlspecialchars($qual_data['date_obtained']);
} else {
    $qualification_value = '';
    $institution_value = '';
    $certificate_number_value = '';
    $date_obtained_value = '';
}
?>

<!-- page content -->
<div class="right_col" role="main">
    <div class="">
        <div class="page-title">
            <div class="title_left">
                <h3>Update Qualification Records</h3>
            </div>
        </div>
        
        <style>
            .required {
                color: red;
            }
            .is-invalid {
                border-color: #dc3545 !important;
            }
            .invalid-feedback {
                display: none;
                width: 100%;
                margin-top: 0.25rem;
                font-size: 0.875em;
                color: #dc3545;
            }
            .is-invalid ~ .invalid-feedback {
                display: block;
            }
        </style>
        
        <div class="clearfix"></div>
        
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="x_panel">
                <div class="x_title">
                    <h2><small>Update Qualification</small></h2>
                    <ul class="nav navbar-right panel_toolbox">
                        <li><a class="collapse-link"><i class="fa fa-chevron-up"></i></a></li>
                    </ul>
                    <div class="clearfix"></div>
                </div>
                
                <div class="x_content">
                    <p>You can edit all fields below</p>
                    
                    <form method="post" action="" class="form-horizontal form-label-left" onsubmit="return validateForm()">
                        <!-- Service No and Personal Info -->
                        <fieldset>
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12" for="serviceNo">Service No <span class="required">*</span></label>
                                <div class="col-md-6 col-sm-6 col-xs-12">
                                    <input type="text" name="serviceNo" required="required" 
                                           class="form-control col-md-7 col-xs-12" 
                                           value="<?php echo htmlspecialchars($id); ?>" readonly>
                                </div>
                            </div>
                            
                            <?php if ($personal_data): ?>
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12">Personnel Name</label>
                                <div class="col-md-6 col-sm-6 col-xs-12">
                                    <input type="text" class="form-control col-md-7 col-xs-12" 
                                           value="<?php echo htmlspecialchars($personal_data['surname'] . ', ' . $personal_data['firstName'] . ' ' . $personal_data['middleName']); ?>" 
                                           readonly>
                                </div>
                            </div>
                            <?php endif; ?>
                        </fieldset>
                        
                        <!-- Qualification Information -->
                        <fieldset>
                            <h3>Qualification Information</h3>
                            
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12" for="qualification">Qualification <span class="required">*</span></label>
                                <div class="col-md-6 col-sm-6 col-xs-12">
                                    <input type="text" name="qualification" required="required" 
                                           class="form-control col-md-7 col-xs-12" 
                                           value="<?php echo $qualification_value; ?>"
                                           placeholder="e.g., B.Sc. Computer Science">
                                    <div class="invalid-feedback">Please enter qualification</div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12" for="institution">Institution <span class="required">*</span></label>
                                <div class="col-md-6 col-sm-6 col-xs-12">
                                    <input type="text" name="institution" required="required" 
                                           class="form-control col-md-7 col-xs-12" 
                                           value="<?php echo $institution_value; ?>"
                                           placeholder="e.g., University of Lagos">
                                    <div class="invalid-feedback">Please enter institution name</div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12" for="certificate_number">Certificate Number</label>
                                <div class="col-md-6 col-sm-6 col-xs-12">
                                    <input type="text" name="certificate_number" 
                                           class="form-control col-md-7 col-xs-12" 
                                           value="<?php echo $certificate_number_value; ?>"
                                           placeholder="Certificate number if available">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12" for="date_obtained">Date Obtained <span class="required">*</span></label>
                                <div class="col-md-6 col-sm-6 col-xs-12">
                                    <input type="date" name="date_obtained" required="required" 
                                           class="form-control col-md-7 col-xs-12" 
                                           value="<?php echo $date_obtained_value; ?>">
                                    <div class="invalid-feedback">Please select date obtained</div>
                                </div>
                            </div>
                        </fieldset>
                        
                        <!-- Submit Buttons -->
                        <div class="border-top">
                            <div class="card-body">
                                <button type="submit" name="updateQualification" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Submit
                                </button>
                                <a href="enrolment-update?search=<?php echo htmlspecialchars($id); ?>" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- /page content -->

<script>
// Function to validate the form
function validateForm() {
    const qualification = document.querySelector('input[name="qualification"]');
    const institution = document.querySelector('input[name="institution"]');
    const dateObtained = document.querySelector('input[name="date_obtained"]');
    let isValid = true;
    
    // Clear previous error states
    document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    
    // Validate qualification
    if (!qualification.value.trim()) {
        qualification.classList.add('is-invalid');
        isValid = false;
    }
    
    // Validate institution
    if (!institution.value.trim()) {
        institution.classList.add('is-invalid');
        isValid = false;
    }
    
    // Validate date obtained
    if (!dateObtained.value) {
        dateObtained.classList.add('is-invalid');
        isValid = false;
    }
    
    if (!isValid) {
        alert("Please fill all required fields correctly before submitting.");
        return false;
    }
    
    // Validate date is not in future
    const today = new Date();
    const selectedDate = new Date(dateObtained.value);
    if (selectedDate > today) {
        alert("Date obtained cannot be in the future.");
        dateObtained.classList.add('is-invalid');
        dateObtained.focus();
        return false;
    }
    
    return true;
}

// Function to validate text fields (Qualification, Institution)
function validateText(input) {
    // Allow alphabets, space, hyphen, single quote, and dot
    input.value = input.value.replace(/[^a-zA-Z\s'\-.]/g, '');
    
    // Capitalize the first letter of each word
    input.value = input.value.replace(/\b\w/g, (char) => char.toUpperCase());
}

// Function to validate and capitalize the certificate number
function validateCertificateNumber(input) {
    // Allow letters, numbers, hyphen, slash, underscore, apostrophe, dot, space, and some special chars
    input.value = input.value.replace(/[^a-zA-Z0-9\-\/_\.' @#$%&*]/g, '');

    // Convert letters to uppercase
    input.value = input.value.toUpperCase();
}

// Real-time validation
document.addEventListener('DOMContentLoaded', function() {
    const qualification = document.querySelector('input[name="qualification"]');
    const institution = document.querySelector('input[name="institution"]');
    const certificateNumber = document.querySelector('input[name="certificate_number"]');
    const dateObtained = document.querySelector('input[name="date_obtained"]');
    
    if (qualification) {
        qualification.addEventListener('input', function() {
            validateText(this);
            if (this.value.trim()) {
                this.classList.remove('is-invalid');
            }
        });
    }
    
    if (institution) {
        institution.addEventListener('input', function() {
            validateText(this);
            if (this.value.trim()) {
                this.classList.remove('is-invalid');
            }
        });
    }

    if (certificateNumber) {
        certificateNumber.addEventListener('input', function() {
            validateCertificateNumber(this);
        });
    }
    
    if (dateObtained) {
        dateObtained.addEventListener('change', function() {
            const today = new Date();
            const selectedDate = new Date(this.value);
            
            if (selectedDate > today) {
                this.classList.add('is-invalid');
                alert("Date obtained cannot be in the future.");
                this.value = '';
            } else if (this.value) {
                this.classList.remove('is-invalid');
            }
        });
    }
});
</script>

<?php include 'footer-enrol.php'; ?>