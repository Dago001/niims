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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['updateFinance'])) {
    // Sanitize and get form data
    $serviceNo = mysqli_real_escape_string($conn, $_POST['serviceNo']);
    $ippis_no = mysqli_real_escape_string($conn, $_POST['ippis_no']);
    $bank = mysqli_real_escape_string($conn, $_POST['bank']);
    $accountNo = mysqli_real_escape_string($conn, $_POST['accountNo']);
    $pfa = mysqli_real_escape_string($conn, $_POST['pfa']);
    $pensionNo = mysqli_real_escape_string($conn, $_POST['pensionNo']);
    $nhf_no = mysqli_real_escape_string($conn, $_POST['nhf_no']);
    
    // Check if record exists
    $check_sql = "SELECT * FROM tbl_finance WHERE serviceNo = '$serviceNo'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        // Fetch existing record to preserve unchanged values
        $existing_record = mysqli_fetch_assoc($check_result);
        
        // Prepare update fields - only update if new value is provided
        $update_fields = [];
        
        // Update IPPIS number if provided (not empty)
        if (!empty($ippis_no)) {
            $update_fields[] = "IPPIS_No = '$ippis_no'";
        }
        
        // Update bank only if a selection is made (not empty string)
        if ($bank !== '') {
            $update_fields[] = "bank = '$bank'";
        } else {
            // Keep existing bank value
            $bank = $existing_record['bank'];
        }
        
        // Update account number if provided (not empty)
        if (!empty($accountNo)) {
            $update_fields[] = "accountNo = '$accountNo'";
        }
        
        // Update PFA only if a selection is made (not empty string)
        if ($pfa !== '') {
            $update_fields[] = "PFA = '$pfa'";
        } else {
            // Keep existing PFA value
            $pfa = $existing_record['PFA'];
        }
        
        // Update pension number if provided (not empty)
        if (!empty($pensionNo)) {
            $update_fields[] = "pensionNo = '$pensionNo'";
        }
        
        // Update NHF number if provided (not empty)
        if (!empty($nhf_no)) {
            $update_fields[] = "NHF_No = '$nhf_no'";
        }
        
        // Only update if there are fields to update
        if (!empty($update_fields)) {
            $update_sql = "UPDATE tbl_finance SET " . implode(', ', $update_fields) . 
                         " WHERE serviceNo = '$serviceNo'";
            
            if (mysqli_query($conn, $update_sql)) {
                // Success message and redirect to search parameter
                echo "<script>
                    alert('Finance record updated successfully!');
                    window.location.href = 'enrolment-update?search=$serviceNo';
                </script>";
                exit;
            } else {
                echo "<script>alert('Error updating record: " . mysqli_error($conn) . "');</script>";
            }
        } else {
            // No changes made
            echo "<script>
                alert('No changes were made.');
                window.location.href = 'enrolment-update?search=$serviceNo';
            </script>";
            exit;
        }
    } else {
        // Insert new record - handle empty bank and PFA for new records
        $bank_value = ($bank !== '') ? "'$bank'" : "NULL";
        $pfa_value = ($pfa !== '') ? "'$pfa'" : "NULL";
        
        $insert_sql = "INSERT INTO tbl_finance (serviceNo, IPPIS_No, bank, accountNo, PFA, pensionNo, NHF_No) 
                       VALUES ('$serviceNo', '$ippis_no', $bank_value, '$accountNo', $pfa_value, '$pensionNo', '$nhf_no')";
        
        if (mysqli_query($conn, $insert_sql)) {
            // Success message and redirect to search parameter
            echo "<script>
                alert('Finance record created successfully!');
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

// Fetch finance data if exists
$finance_sql = "SELECT * FROM tbl_finance WHERE serviceNo = '$id'";
$finance_result = mysqli_query($conn, $finance_sql);

if (mysqli_num_rows($finance_result) > 0) {
    $finance_data = mysqli_fetch_assoc($finance_result);
    $ippis_no_value = htmlspecialchars($finance_data['IPPIS_No']);
    $bank_value = htmlspecialchars($finance_data['bank']);
    $accountNo_value = htmlspecialchars($finance_data['accountNo']);
    $pfa_value = htmlspecialchars($finance_data['PFA']);
    $pensionNo_value = htmlspecialchars($finance_data['pensionNo']);
    $nhf_no_value = htmlspecialchars($finance_data['NHF_No']);
} else {
    $ippis_no_value = '';
    $bank_value = '';
    $accountNo_value = '';
    $pfa_value = '';
    $pensionNo_value = '';
    $nhf_no_value = '';
}

// List of Nigerian banks for dropdown
$banks = [
    "Access Bank", "Citibank", "Ecobank Nigeria", "Fidelity Bank", 
    "First Bank of Nigeria", "First City Monument Bank", "Globus Bank", 
    "Guaranty Trust Bank", "Heritage Bank", "Jaiz Bank", "Keystone Bank", 
    "Polaris Bank", "Providus Bank", "Stanbic IBTC Bank", "Standard Chartered", 
    "Sterling Bank", "SunTrust Bank", "Titan Trust Bank", "Union Bank of Nigeria", 
    "United Bank for Africa", "Unity Bank", "Wema Bank", "Zenith Bank"
];

// Updated list of PFAs as requested
$pfas = [
    "Access ARM Pensions Limited",
    "CardinalStone Pensions Limited",
    "Citizens Pensions Limited",
    "Crusader Sterling Pensions Limited",
    "FCMB Pensions Limited",
    "Fidelity Pension Managers Limited",
    "First Guarantee Pension Limited",
    "Guaranty Trust Pension Managers Limited",
    "IEI-Anchor Pension Managers Limited",
    "Leadway Pensure PFA Limited",
    "NLPC Pension Fund Administrators Limited",
    "NPF Pensions Limited",
    "NUPEMCO",
    "Norrenberger Pensions Limited",
    "Oak Pensions Limited",
    "Pensions Alliance Limited (PAL Pensions)",
    "Premium Pension Limited",
    "Radix Pension Managers Limited",
    "Stanbic IBTC Pension Managers Limited",
    "Tangerine APT Pensions Limited",
    "Trustfund Pensions Plc",
    "Veritas Glanvills Pensions Limited"
];
?>

<!-- page content -->
<div class="right_col" role="main">
    <div class="">
        <div class="page-title">
            <div class="title_left">
                <h3>Update Records</h3>
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
                    <h2><small>Finance Information</small></h2>
                    <ul class="nav navbar-right panel_toolbox">
                        <li><a class="collapse-link"><i class="fa fa-chevron-up"></i></a></li>
                    </ul>
                    <div class="clearfix"></div>
                </div>
                
                <div class="x_content">
                    <p>You can edit all fields below</p>
                    <p><small><em></em></small></p>
                    
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
                        
                        <!-- Finance Information -->
                        <fieldset>
                            <h3>Finance Information</h3>
                            
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12" for="ippis_no">IPPIS Number</label>
                                <div class="col-md-6 col-sm-6 col-xs-12">
                                    <input type="text" name="ippis_no" 
                                           class="form-control col-md-7 col-xs-12" 
                                           value="<?php echo $ippis_no_value; ?>"
                                           placeholder="IPPIS Number (Letters and Numbers allowed)">
                                    <div class="invalid-feedback">Please enter valid IPPIS number</div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12" for="bank">Bank</label>
                                <div class="col-md-6 col-sm-6 col-xs-12">
                                    <select name="bank" class="form-control col-md-7 col-xs-12">
                                        <option value="">-- Select Bank (Leave blank to keep existing) --</option>
                                        <?php foreach ($banks as $bank): ?>
                                            <option value="<?php echo htmlspecialchars($bank); ?>" 
                                                <?php echo ($bank_value == $bank) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($bank); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12" for="accountNo">Account Number</label>
                                <div class="col-md-6 col-sm-6 col-xs-12">
                                    <input type="text" name="accountNo" 
                                           class="form-control col-md-7 col-xs-12" 
                                           value="<?php echo $accountNo_value; ?>"
                                           placeholder="Bank Account Number"
                                           maxlength="10">
                                    <div class="invalid-feedback">Account number must be 10 digits</div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12" for="pfa">PFA (Pension Fund Administrator)</label>
                                <div class="col-md-6 col-sm-6 col-xs-12">
                                    <select name="pfa" class="form-control col-md-7 col-xs-12">
                                        <option value="">-- Select PFA (Leave blank to keep existing) --</option>
                                        <?php foreach ($pfas as $pfa): ?>
                                            <option value="<?php echo htmlspecialchars($pfa); ?>" 
                                                <?php echo ($pfa_value == $pfa) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($pfa); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12" for="pensionNo">Pension Number</label>
                                <div class="col-md-6 col-sm-6 col-xs-12">
                                    <input type="text" name="pensionNo" 
                                           class="form-control col-md-7 col-xs-12" 
                                           value="<?php echo $pensionNo_value; ?>"
                                           placeholder="Pension Number">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12" for="nhf_no">NHF Number</label>
                                <div class="col-md-6 col-sm-6 col-xs-12">
                                    <input type="text" name="nhf_no" 
                                           class="form-control col-md-7 col-xs-12" 
                                           value="<?php echo $nhf_no_value; ?>"
                                           placeholder="National Housing Fund Number">
                                </div>
                            </div>
                        </fieldset>
                        
                        <!-- Submit Buttons -->
                        <div class="border-top">
                            <div class="card-body">
                                <button type="submit" name="updateFinance" class="btn btn-primary">
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
    const accountNo = document.querySelector('input[name="accountNo"]');
    const ippisNo = document.querySelector('input[name="ippis_no"]');
    let isValid = true;
    
    // Clear previous error states
    document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    
    // Validate account number if provided (must be 10 digits)
    if (accountNo.value.trim()) {
        const accountRegex = /^\d{10}$/;
        if (!accountRegex.test(accountNo.value)) {
            accountNo.classList.add('is-invalid');
            isValid = false;
        }
    }
    
    // Validate IPPIS number if provided (alphanumeric allowed)
    if (ippisNo.value.trim()) {
        // Remove any hyphens for validation
        const cleanValue = ippisNo.value.replace(/-/g, '');
        // Allow letters and numbers
        const ippisRegex = /^[A-Za-z0-9]+$/;
        if (!ippisRegex.test(cleanValue)) {
            ippisNo.classList.add('is-invalid');
            ippisNo.setCustomValidity("IPPIS number can only contain letters and numbers.");
            isValid = false;
        } else {
            ippisNo.setCustomValidity("");
        }
    }
    
    if (!isValid) {
        alert("Please correct the errors in the form before submitting.");
        return false;
    }
    
    return true;
}

// Real-time validation
document.addEventListener('DOMContentLoaded', function() {
    const accountNo = document.querySelector('input[name="accountNo"]');
    const ippisNo = document.querySelector('input[name="ippis_no"]');
    
    // Validate account number format
    if (accountNo) {
        accountNo.addEventListener('input', function() {
            // Allow only numbers
            this.value = this.value.replace(/\D/g, '');
            
            // Validate length
            if (this.value.length === 10 || this.value === '') {
                this.classList.remove('is-invalid');
            } else {
                this.classList.add('is-invalid');
            }
        });
        
        // Validate on blur
        accountNo.addEventListener('blur', function() {
            if (this.value && this.value.length !== 10) {
                this.classList.add('is-invalid');
                alert("Account number must be exactly 10 digits.");
            }
        });
    }
    
    // IPPIS number formatting and validation
    if (ippisNo) {
        ippisNo.addEventListener('input', function() {
            // Convert to uppercase
            this.value = this.value.toUpperCase();
            
            // Remove all non-alphanumeric characters except hyphens
            let value = this.value.replace(/[^A-Za-z0-9\-]/g, '');
            
            // Format with hyphens (example: ABC-12345-X)
            // This is optional formatting - you can adjust based on your IPPIS format needs
            if (value.length > 3 && value.length <= 8) {
                value = value.substring(0, 3) + '' + value.substring(3);
            } else if (value.length > 8) {
                value = value.substring(0, 3) + '' + value.substring(3, 8) + '' + value.substring(8, 9);
            }
            
            this.value = value;
            
            // Clear validation
            this.classList.remove('is-invalid');
            this.setCustomValidity("");
        });
        
        // Validate IPPIS format on blur
        ippisNo.addEventListener('blur', function() {
            if (this.value.trim()) {
                // Remove hyphens for validation
                const cleanValue = this.value.replace(/-/g, '');
                const ippisRegex = /^[A-Z0-9]+$/;
                
                if (!ippisRegex.test(cleanValue)) {
                    this.classList.add('is-invalid');
                    this.setCustomValidity("IPPIS number can only contain letters and numbers.");
                } else {
                    this.classList.remove('is-invalid');
                    this.setCustomValidity("");
                }
            }
        });
    }
    
    // Format pension number
    const pensionNo = document.querySelector('input[name="pensionNo"]');
    if (pensionNo) {
        pensionNo.addEventListener('input', function() {
            // Convert to uppercase
            this.value = this.value.toUpperCase();
        });
    }
    
    // Format NHF number
    const nhfNo = document.querySelector('input[name="nhf_no"]');
    if (nhfNo) {
        nhfNo.addEventListener('input', function() {
            // Convert to uppercase
            this.value = this.value.toUpperCase();
        });
    }
});
</script>

<?php include 'footer-enrol.php'; ?>