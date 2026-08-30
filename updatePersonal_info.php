<?php 
session_start();

if (!isset($_SESSION['username'])) {
    require_once('index.php');
    exit;
}

require_once ('db/db.php');
if ($_SESSION['role'] == "Supervisor") {
    include 'sidebar-supervisor.php';
}
if ($_SESSION['role'] == "Admin") {
    include 'sidebar-admin.php';
}
include 'menu-footer-buttons.php';
include 'top-navigation.php';

$id = $_GET['id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['updatePersonal'])) {
    // Sanitize and get form data
    $serviceNo = mysqli_real_escape_string($conn, $_POST['serviceNo']);
    $lname = mysqli_real_escape_string($conn, $_POST['lname']);
    $fname = mysqli_real_escape_string($conn, $_POST['fname']);
    $mname = mysqli_real_escape_string($conn, $_POST['mname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $state = mysqli_real_escape_string($conn, $_POST['state_of_origin']);
    $lga = mysqli_real_escape_string($conn, $_POST['lga']);
    $town = mysqli_real_escape_string($conn, $_POST['town']);
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $maiden_name = mysqli_real_escape_string($conn, $_POST['maiden_name']);
    $maritalStatus = mysqli_real_escape_string($conn, $_POST['marital_status']);
    $genotype = mysqli_real_escape_string($conn, $_POST['genotype']);
    $bloodType = mysqli_real_escape_string($conn, $_POST['bloodType']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $dob = mysqli_real_escape_string($conn, $_POST['dob']); // ADDED: Date of Birth
    
    // Update query - INCLUDING Date of Birth
    $update_sql = "UPDATE tbl_emppersonal SET 
                    surname = '$lname',
                    firstName = '$fname',
                    middleName = '$mname',
                    email = '$email',
                    state_of_origin = '$state',
                    lga = '$lga',
                    town = '$town',
                    gender = '$gender',
                    maiden_name = '$maiden_name',
                    marital_status = '$maritalStatus',
                    genotype = '$genotype',
                    bloodType = '$bloodType',
                    phone = '$phone',
                    dob = '$dob'  -- ADDED: Date of Birth field
                    WHERE serviceNo = '$serviceNo'";
    
    if (mysqli_query($conn, $update_sql)) {
        // Success message and redirect to search parameter
        echo "<script>
                alert('Record updated successfully!');
                window.location.href = 'enrolment-update?search=$serviceNo';
              </script>";
        exit;
    } else {
        echo "<script>alert('Error updating record: " . mysqli_error($conn) . "');</script>";
    }
}

// Fetch current data
$sqluse = "SELECT * FROM tbl_emppersonal WHERE serviceNo=$id";
$retrieve = mysqli_query($conn, $sqluse);
$numb = mysqli_num_rows($retrieve); 
while ($foundk = mysqli_fetch_array($retrieve)) {
    $serviceNo = $foundk['serviceNo'];
    $surname = $foundk['surname'];
    $firstname = $foundk['firstName'];
    $email = $foundk['email'];
    $state = $foundk['state_of_origin'];
    $lga = $foundk['lga'];
    $dob = $foundk['dob'];
    $town = $foundk['town'];
    $gender = $foundk['gender'];
    $middlename = $foundk['middleName'];
    $maiden_name = $foundk['maiden_name'];
    $maritalStatus = $foundk['marital_status'];
    $sign = $foundk['signature'];
    $genotype = $foundk['genotype'];
    $bloodType = $foundk['bloodType'];
    $phone = $foundk['phone'];
}
?>

<!-- page content -->
<div class="right_col" role="main">
    <div class="">
        <div class="page-title">
            <div class="title_left">
                <h3>Update Personal Records</h3>
            </div>

            <div class="title_right">
                <div class="col-md-5 col-sm-5 col-xs-12 form-group pull-right top_search">
                </div>
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
                    <h2><small>Update Record</small></h2>
                    <ul class="nav navbar-right panel_toolbox">
                        <li><a class="collapse-link"><i class="fa fa-chevron-up"></i></a></li>
                    </ul>
                    <div class="clearfix"></div>
                </div>
                <div class="x_content">
                    <!-- Smart Wizard -->
                    <p>You can edit all fields below</p>
                    <div class="form_wizard wizard_horizontal">
                        
                        <form method="post" action="" class="form-horizontal form-label-left" onsubmit="return validateForm()">
                            <fieldset>
                                <div id="step-1">
                                    <div class="form-group">
                                        <label class="control-label col-md-3 col-sm-3 col-xs-12" for="serviceNo">Service No <span class="required">*</span></label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <input type="text" name="serviceNo" required="required" class="form-control col-md-7 col-xs-12" value="<?php echo htmlspecialchars($serviceNo); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="control-label col-md-3 col-sm-3 col-xs-12" for="last-name">Surname <span class="required">*</span></label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <input type="text" name="lname" required="required" class="form-control col-md-7 col-xs-12" value="<?php echo htmlspecialchars($surname); ?>">
                                            <div class="invalid-feedback">Only alphabets, apostrophes (') and hyphens (-) are allowed, and the first letter must be capital.</div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="control-label col-md-3 col-sm-3 col-xs-12" for="first-name">First Name <span class="required">*</span></label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <input type="text" name="fname" required="required" class="form-control col-md-7 col-xs-12" value="<?php echo htmlspecialchars($firstname); ?>">
                                            <div class="invalid-feedback">Only alphabets, apostrophes (') and hyphens (-) are allowed, and the first letter must be capital.</div>
                                        </div>
                                    </div>
                                    
                                    <!-- MIDDLE NAME - MADE OPTIONAL -->
                                    <div class="form-group">
                                        <label for="middle_name" class="control-label col-md-3 col-sm-3 col-xs-12">Middle Name</label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <input id="mname" class="form-control col-md-7 col-xs-12" type="text" name="mname" value="<?php echo htmlspecialchars($middlename); ?>">
                                            <div class="invalid-feedback">Only alphabets, apostrophes (') and hyphens (-) are allowed, and the first letter must be capital.</div>
                                        </div>
                                    </div>
                                    
                                    <!-- DATE OF BIRTH - NOW UPDATABLE -->
                                    <div class="form-group">
                                        <label class="control-label col-md-3 col-sm-3 col-xs-12">Date Of Birth <span class="required">*</span></label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <input name="dob" class="date-picker form-control col-md-7 col-xs-12" required="required" type="date" value="<?php echo htmlspecialchars($dob); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="marital_status" class="col-sm-3 text-end control-label col-form-label">Marital Status</label>
                                        <div class="control-label col-md-3 col-sm-3 col-xs-12">
                                            <select name="marital_status" class="form-control shadow-none" required="">
                                                <option value="<?php echo htmlspecialchars($maritalStatus); ?>" selected><?php echo htmlspecialchars($maritalStatus); ?></option>
                                                <option value="Single">Single</option>
                                                <option value="Married">Married</option>
                                                <option value="Divorced">Divorced</option>
                                                <option value="Widower">Widower</option>
                                                <option value="Widow">Widow</option>
                                                <option value="Seperated">Seperated</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <!-- MAIDEN NAME - MADE OPTIONAL -->
                                    <div class="form-group">
                                        <label for="maiden_name" class="control-label col-md-3 col-sm-3 col-xs-12">Maiden Name</label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <input name="maiden_name" class="form-control col-md-7 col-xs-12" type="text" value="<?php echo htmlspecialchars($maiden_name); ?>">
                                            <div class="invalid-feedback">Only alphabets, apostrophes (') and hyphens (-) are allowed, and the first letter must be capital.</div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="control-label col-md-3 col-sm-3 col-xs-12">Gender</label>
                                        <div class="control-label col-md-3 col-sm-3 col-xs-12">
                                            <select name="gender" class="form-control shadow-none" required="">
                                                <option value="<?php echo htmlspecialchars($gender); ?>" selected><?php echo htmlspecialchars($gender); ?></option>
                                                <option value="Male">Male</option>
                                                <option value="Female">Female</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="state_of_origin" class="control-label col-md-3 col-sm-3 col-xs-12">State of Origin</label>
                                        <div class="control-label col-md-3 col-sm-3 col-xs-12">
                                            <select name="state_of_origin" id="state" class="form-control">
                                                <option value="<?php echo htmlspecialchars($state); ?>" selected><?php echo htmlspecialchars($state); ?></option>
                                                <option value="Abuja FCT">Abuja FCT</option>
                                                <option value="Abia">Abia</option>
                                                <option value="Adamawa">Adamawa</option>
                                                <option value="Akwa Ibom">Akwa Ibom</option>
                                                <option value="Anambra">Anambra</option>
                                                <option value="Bauchi">Bauchi</option>
                                                <option value="Bayelsa">Bayelsa</option>
                                                <option value="Benue">Benue</option>
                                                <option value="Borno">Borno</option>
                                                <option value="Cross River">Cross River</option>
                                                <option value="Delta">Delta</option>
                                                <option value="Ebonyi">Ebonyi</option>
                                                <option value="Edo">Edo</option>
                                                <option value="Ekiti">Ekiti</option>
                                                <option value="Enugu">Enugu</option>
                                                <option value="Gombe">Gombe</option>
                                                <option value="Imo">Imo</option>
                                                <option value="Jigawa">Jigawa</option>
                                                <option value="Kaduna">Kaduna</option>
                                                <option value="Kano">Kano</option>
                                                <option value="Katsina">Katsina</option>
                                                <option value="Kebbi">Kebbi</option>
                                                <option value="Kogi">Kogi</option>
                                                <option value="Kwara">Kwara</option>
                                                <option value="Lagos">Lagos</option>
                                                <option value="Nassarawa">Nassarawa</option>
                                                <option value="Niger">Niger</option>
                                                <option value="Ogun">Ogun</option>
                                                <option value="Ondo">Ondo</option>
                                                <option value="Osun">Osun</option>
                                                <option value="Oyo">Oyo</option>
                                                <option value="Plateau">Plateau</option>
                                                <option value="Rivers">Rivers</option>
                                                <option value="Sokoto">Sokoto</option>
                                                <option value="Taraba">Taraba</option>
                                                <option value="Yobe">Yobe</option>
                                                <option value="Zamfara">Zamfara</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="lga" id="lga" class="control-label col-md-3 col-sm-3 col-xs-12">LGA</label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <input name="lga" class="form-control col-md-7 col-xs-12" type="text" value="<?php echo htmlspecialchars($lga); ?>">
                                            <div class="invalid-feedback">Only alphabets, apostrophes (') and hyphens (-) are allowed, and the first letter must be capital.</div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="town" class="control-label col-md-3 col-sm-3 col-xs-12">Town</label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <input name="town" class="form-control col-md-7 col-xs-12" type="text" value="<?php echo htmlspecialchars($town); ?>">
                                            <div class="invalid-feedback">Only alphabets, apostrophes (') and hyphens (-) are allowed, and the first letter must be capital.</div>
                                        </div>
                                    </div>
                                    
                                    <!-- EMAIL ADDRESS - MADE OPTIONAL -->
                                    <div class="form-group">
                                        <label for="email" class="control-label col-md-3 col-sm-3 col-xs-12">Email Address</label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <input type="text" class="form-control" name="email" placeholder="Email Address" value="<?php echo htmlspecialchars($email); ?>">
                                            <div class="invalid-feedback">Please enter a valid email address.</div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="cono1" class="control-label col-md-3 col-sm-3 col-xs-12">Phone No</label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <input type="text" class="form-control" name="phone" placeholder="Phone Number" value="<?php echo htmlspecialchars($phone); ?>">
                                            <div class="invalid-feedback">Only numbers are allowed, and it must not exceed 11 digits.</div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="bloodGroup" class="col-sm-3 text-end control-label col-form-label">Blood Group</label>
                                        <div class="control-label col-md-3 col-sm-3 col-xs-12">
                                            <select name="bloodType" class="form-control shadow-none" required="">
                                                <option value="<?php echo htmlspecialchars($bloodType); ?>" selected><?php echo htmlspecialchars($bloodType); ?></option>
                                                <option value="A+">A+</option>
                                                <option value="A-">A-</option>
                                                <option value="B+">B+</option>
                                                <option value="B-">B-</option>
                                                <option value="AB+">AB+</option>
                                                <option value="AB-">AB-</option>
                                                <option value="O+">O+</option>
                                                <option value="O-">O-</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="genotype" class="col-sm-3 text-end control-label col-form-label">Genotype</label>
                                        <div class="control-label col-md-3 col-sm-3 col-xs-12">
                                            <select name="genotype" class="form-control shadow-none" required="">
                                                <option value="<?php echo htmlspecialchars($genotype); ?>" selected><?php echo htmlspecialchars($genotype); ?></option>
                                                <option value="AA">AA</option>
                                                <option value="AS">AS</option>
                                                <option value="AC">AC</option>
                                                <option value="SS">SS</option>
                                                <option value="SC">SC</option>
                                                <option value="CC">CC</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </fieldset>
                            <fieldset>
                                <div id="step-4">
                                    <div class="border-top">
                                        <div class="card-body">
                                            <button type="submit" name="updatePersonal" class="btn btn-primary">
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

<script>
// Function to validate names (Surname, First Name, Middle Name, Town, LGA, Maiden Name)
function validateName(input) {
    // Allow alphabets, space, hyphen, single quote, and dot
    input.value = input.value.replace(/[^a-zA-Z\s'\-.]/g, '');
    
    // Capitalize the first letter of each word
    input.value = input.value.replace(/\b\w/g, (char) => char.toUpperCase());
    
    input.setCustomValidity("");
    input.classList.remove('is-invalid');
}

// Function to validate phone numbers (Phone No)
function validatePhone(input) {
    // Remove non-numeric characters
    input.value = input.value.replace(/[^0-9]/g, '');
    
    if (input.value.length > 11) {
        input.value = input.value.substring(0, 11);
    }
    
    input.setCustomValidity("");
    input.classList.remove('is-invalid');
}

// Function to validate email addresses (OPTIONAL - only validate if provided)
function validateEmail(input) {
    if (!input.value.trim()) {
        input.setCustomValidity("");
        input.classList.remove('is-invalid');
        return;
    }
    
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/; // Basic email format validation
    if (!regex.test(input.value)) {
        input.setCustomValidity("Please enter a valid email address.");
        input.classList.add('is-invalid'); // Add class to highlight invalid input
    } else {
        input.setCustomValidity("");
        input.classList.remove('is-invalid'); // Remove class if input is valid
    }
}

// Function to validate Date of Birth (not in future)
function validateDOB(input) {
    if (!input.value) {
        input.setCustomValidity("Please select Date of Birth.");
        input.classList.add('is-invalid');
        return;
    }
    
    const today = new Date();
    const dob = new Date(input.value);
    
    if (dob > today) {
        input.setCustomValidity("Date of Birth cannot be in the future.");
        input.classList.add('is-invalid');
    } else {
        input.setCustomValidity("");
        input.classList.remove('is-invalid');
    }
}

// Attach oninput event listeners to all relevant fields
document.addEventListener('DOMContentLoaded', function () {
    // Name fields (Surname, First Name, Middle Name, Town, LGA, Maiden Name)
    const nameFields = document.querySelectorAll('input[name="lname"], input[name="fname"], input[name="mname"], input[name="town"], input[name="lga"], input[name="maiden_name"]');
    nameFields.forEach(input => {
        input.addEventListener('input', () => validateName(input));
    });

    // Phone field
    const phoneField = document.querySelector('input[name="phone"]');
    if (phoneField) {
        phoneField.addEventListener('input', () => validatePhone(phoneField));
    }

    // Email field
    const emailField = document.querySelector('input[name="email"]');
    if (emailField) {
        emailField.addEventListener('input', () => validateEmail(emailField));
    }
    
    // Date of Birth field
    const dobField = document.querySelector('input[name="dob"]');
    if (dobField) {
        dobField.addEventListener('change', () => validateDOB(dobField));
    }
});

// Function to validate the entire form before submission
function validateForm() {
    const lname = document.querySelector('input[name="lname"]');
    const fname = document.querySelector('input[name="fname"]');
    const emailField = document.querySelector('input[name="email"]');
    const dobField = document.querySelector('input[name="dob"]');

    let isValid = true;

    // Required names check
    if (lname && !lname.value.trim()) {
        lname.classList.add('is-invalid');
        isValid = false;
    }
    if (fname && !fname.value.trim()) {
        fname.classList.add('is-invalid');
        isValid = false;
    }

    // Validate email field - only if provided (optional)
    if (emailField && emailField.value.trim()) {
        validateEmail(emailField);
        if (emailField.classList.contains('is-invalid')) {
            isValid = false;
        }
    }
    
    // Validate Date of Birth (required)
    if (dobField) {
        validateDOB(dobField);
        if (dobField.classList.contains('is-invalid')) {
            isValid = false;
        }
    }

    return isValid;
}
</script>