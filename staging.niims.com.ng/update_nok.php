<?php 
session_start();

if (!isset($_SESSION['username'])) {
    require_once('index.php');
    exit;
}

require_once ('db/db.php');
if ($_SESSION['role'] == "DataEntry") {
    include 'sidebar-dataentry.php';
}
if ($_SESSION['role'] == "Admin") {
    include 'sidebar-admin.php';
}
if ($_SESSION['role'] == "SuperAdmin") {
    include 'sidebar-superadmin.php';
}
include 'menu-footer-buttons.php';
include 'top-navigation.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['updateNOK'])) {
    // Sanitize and get form data
    $serviceNo = mysqli_real_escape_string($conn, $_POST['serviceNo']);
    $nok = mysqli_real_escape_string($conn, $_POST['nok']);
    $nok_relationship = mysqli_real_escape_string($conn, $_POST['nok_relationship']);
    $nok_phone = mysqli_real_escape_string($conn, $_POST['nok_phone']);
    
    // Update query for next of kin table
    $update_sql = "UPDATE tbl_nok SET 
                    nok = '$nok',
                    nok_relationship = '$nok_relationship',
                    nok_phone = '$nok_phone'
                    WHERE serviceNo = '$serviceNo'";
    
    if (mysqli_query($conn, $update_sql)) {
        // Success message and redirect to search parameter
        echo "<script>
                alert('Next of Kin record updated successfully!');
                window.location.href = 'enrolment-update?search=$serviceNo';
              </script>";
        exit;
    } else {
        echo "<script>alert('Error updating record: " . mysqli_error($conn) . "');</script>";
    }
}

$id = $_GET['id'];

$sqluse = "SELECT emp.serviceNo, emp.surname, emp.firstName, emp.middleName, nok.nok, nok.nok_phone,
nok.nok_relationship
FROM tbl_emppersonal emp INNER JOIN tbl_nok nok 
ON emp.serviceNo = nok.serviceNo
WHERE emp.serviceNo = $id";
$retrieve = mysqli_query($conn, $sqluse);
$numb = mysqli_num_rows($retrieve); 
while ($foundk = mysqli_fetch_array($retrieve)) {
    $serviceNo = $foundk['serviceNo'];
    $surname = $foundk['surname'];
    $firstname = $foundk['firstName'];
    $middlename = $foundk['middleName'];
    $nok = $foundk['nok'];
    $nok_relationship = $foundk['nok_relationship'];
    $nok_phone = $foundk['nok_phone'];
}
?>

<!-- page content -->
<div class="right_col" role="main">
    <div class="">
        <div class="page-title">
            <div class="title_left">
                <h3>Update Next of Kin Record</h3>
            </div>

            <div class="title_right">
                <div class="col-md-5 col-sm-5 col-xs-12 form-group pull-right top_search">
                    <div class="input-group">
                        
                    </div>
                </div>
            </div>
        </div>
        <div class="clearfix"></div>

        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="x_panel">
                <div class="x_title">
                    <h2><small>Next of Kin</small></h2>
                    <ul class="nav navbar-right panel_toolbox">
                        <li><a class="collapse-link"><i class="fa fa-chevron-up"></i></a></li>
                        <li><a class="close-link"><i class="fa fa-close"></i></a></li>
                    </ul>
                    <div class="clearfix"></div>
                </div>
                <div class="x_content">
                    <!-- Smart Wizard -->
                    <p>NB: Next of Kin Name and Phone Number are required.</p>
                    <div>
                        
                        <form method="post" action="" class="form-horizontal form-label-left" onsubmit="return validateForm()">
                            <fieldset>
                                <div>
                                    <div class="form-group">
                                        <label class="control-label col-md-3 col-sm-3 col-xs-12" for="serviceNo">Service No <span class="required">*</span></label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <input type="text" name="serviceNo" required="required" class="form-control col-md-7 col-xs-12" value="<?php echo htmlspecialchars($serviceNo); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="control-label col-md-3 col-sm-3 col-xs-12" for="last-name">Surname <span class="required">*</span></label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <input type="text" name="lname" required="required" class="form-control col-md-7 col-xs-12" value="<?php echo htmlspecialchars($surname); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="control-label col-md-3 col-sm-3 col-xs-12" for="first-name">First Name <span class="required">*</span></label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <input type="text" name="fname" required="required" class="form-control col-md-7 col-xs-12" value="<?php echo htmlspecialchars($firstname); ?>" readonly>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="middle_name" class="control-label col-md-3 col-sm-3 col-xs-12">Middle Name / Initial</label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <input id="mname" class="form-control col-md-7 col-xs-12" type="text" name="mname" value="<?php echo htmlspecialchars($middlename); ?>" readonly>
                                        </div>
                                    </div>
                                    
                                    <h3>Next of Kin Information</h3>
                                    <div class="form-group">
                                        <label for="nok" class="col-sm-3 text-end control-label col-form-label">Next of Kin Name <span class="required">*</span></label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <input type="text" class="form-control col-md-7 col-xs-12" name="nok" value="<?php echo htmlspecialchars($nok); ?>" required/>
                                            <div class="invalid-feedback">Only alphabets, apostrophes ('), hyphens (-) and spaces are allowed, and the first letter must be capital.</div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="nok_relationship" class="col-sm-3 text-end control-label col-form-label">Relationship</label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <select name="nok_relationship" class="form-control col-md-7 col-xs-12">
                                                <option value="">-- Select Relationship --</option>
                                                <option value="Husband" <?php echo ($nok_relationship == 'Husband') ? 'selected' : ''; ?>>Husband</option>
                                                <option value="Wife" <?php echo ($nok_relationship == 'Wife') ? 'selected' : ''; ?>>Wife</option>
                                                <option value="Father" <?php echo ($nok_relationship == 'Father') ? 'selected' : ''; ?>>Father</option>
                                                <option value="Mother" <?php echo ($nok_relationship == 'Mother') ? 'selected' : ''; ?>>Mother</option>
                                                <option value="Son" <?php echo ($nok_relationship == 'Son') ? 'selected' : ''; ?>>Son</option>
                                                <option value="Daughter" <?php echo ($nok_relationship == 'Daughter') ? 'selected' : ''; ?>>Daughter</option>
                                                <option value="Brother" <?php echo ($nok_relationship == 'Brother') ? 'selected' : ''; ?>>Brother</option>
                                                <option value="Sister" <?php echo ($nok_relationship == 'Sister') ? 'selected' : ''; ?>>Sister</option>
                                                <option value="Uncle" <?php echo ($nok_relationship == 'Uncle') ? 'selected' : ''; ?>>Uncle</option>
                                                <option value="Aunt" <?php echo ($nok_relationship == 'Aunt') ? 'selected' : ''; ?>>Aunt</option>
                                                <option value="Cousin" <?php echo ($nok_relationship == 'Cousin') ? 'selected' : ''; ?>>Cousin</option>
                                                <option value="Relative" <?php echo ($nok_relationship == 'Relative') ? 'selected' : ''; ?>>Relative</option>
                                                <option value="Friend" <?php echo ($nok_relationship == 'Friend') ? 'selected' : ''; ?>>Friend</option>
                                                <option value="Other" <?php echo ($nok_relationship == 'Other') ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="nok_phone" class="col-sm-3 text-end control-label col-form-label">NOK Phone Number <span class="required">*</span></label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <input type="text" class="form-control col-md-7 col-xs-12" name="nok_phone" value="<?php echo htmlspecialchars($nok_phone); ?>" required/>
                                            <div class="invalid-feedback">Only numbers are allowed, and it must not exceed 11 digits.</div>
                                        </div>
                                    </div>
                                </div>
                            </fieldset>
                            
                            <fieldset>
                                <div>
                                    <div class="border-top">
                                        <div class="card-body">
                                            <button type="submit" name="updateNOK" class="btn btn-primary">
                                                Submit
                                            </button>
                                            <a href="enrolment-update?search=<?php echo $serviceNo; ?>" class="btn btn-secondary">Cancel</a>
                                        </div>
                                    </div>                               
                                </div>
                            </fieldset>
                        </form>
                    </div>
                    <!-- End SmartWizard Content -->
                </div>
            </div>
        </div>
    </div>
</div>
<!-- /page content -->

<!-- footer content -->
<?php include 'footer-enrol.php'; ?>

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

<script>
// Function to validate names (Next of Kin Name)
function validateName(input) {
    // Allow alphabets, space, hyphen, single quote, and dot
    input.value = input.value.replace(/[^a-zA-Z\s'\-.]/g, '');
    
    // Capitalize the first letter of each word
    input.value = input.value.replace(/\b\w/g, (char) => char.toUpperCase());
    
    input.setCustomValidity("");
    input.classList.remove('is-invalid');
}

// Function to validate phone numbers (NOK Phone Number)
function validatePhone(input) {
    // Remove non-numeric characters
    input.value = input.value.replace(/[^0-9]/g, '');
    
    if (input.value.length > 11) {
        input.value = input.value.substring(0, 11);
    }
    
    input.setCustomValidity("");
    input.classList.remove('is-invalid');
}

// Attach oninput event listeners to all relevant fields
document.addEventListener('DOMContentLoaded', function () {
    // Next of Kin Name field
    const nokField = document.querySelector('input[name="nok"]');
    if (nokField) {
        nokField.addEventListener('input', () => validateName(nokField));
    }

    // NOK Phone field
    const nokPhoneField = document.querySelector('input[name="nok_phone"]');
    if (nokPhoneField) {
        nokPhoneField.addEventListener('input', () => validatePhone(nokPhoneField));
    }
});

// Function to validate the entire form before submission
function validateForm() {
    const nokField = document.querySelector('input[name="nok"]');
    const nokPhoneField = document.querySelector('input[name="nok_phone"]');

    let isValid = true;

    // Validate Next of Kin Name field (required)
    if (nokField && nokField.value.trim() === '') {
        nokField.classList.add('is-invalid');
        nokField.setCustomValidity("Next of Kin Name is required.");
        isValid = false;
    } else if (nokField) {
        nokField.classList.remove('is-invalid');
        nokField.setCustomValidity("");
    }

    // Validate NOK Phone field (required)
    if (nokPhoneField && nokPhoneField.value.trim() === '') {
        nokPhoneField.classList.add('is-invalid');
        nokPhoneField.setCustomValidity("NOK Phone Number is required.");
        isValid = false;
    } else if (nokPhoneField) {
        nokPhoneField.classList.remove('is-invalid');
        nokPhoneField.setCustomValidity("");
    }

    if (!isValid) {
        // Only show alert if there are validation errors
        setTimeout(() => {
            alert("Please fill all required fields correctly before submitting.");
        }, 100);
    }

    return isValid;
}
</script>