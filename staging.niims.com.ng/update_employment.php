<?php 
session_start();

if (!isset($_SESSION['username'])) {
    require_once('index.php');
    exit;
}

require_once ('db/db.php');

if ($_SESSION['role'] == "Admin") {
    include 'sidebar-admin.php';
}
if ($_SESSION['role'] == "SuperAdmin") {
    include 'sidebar-superadmin.php';
}
if ($_SESSION['role'] == "Supervisor") {
    include 'sidebar-supervisor.php';
}
include 'menu-footer-buttons.php';
include 'top-navigation.php';

// Get the service number from URL parameter
$id = isset($_GET['id']) ? $_GET['id'] : '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['regEmployee'])) {
    // Sanitize and get form data
    $serviceNo = mysqli_real_escape_string($conn, $id);
    $dofa = mysqli_real_escape_string($conn, $_POST['dofa']);
    $dopa = mysqli_real_escape_string($conn, $_POST['dopa']);
    $currentRank = mysqli_real_escape_string($conn, $_POST['currentRank']);
    $presentPosting = mysqli_real_escape_string($conn, $_POST['presentPosting']);
    
    // Check if the employee already exists in tbl_employment
    $check_sql = "SELECT * FROM tbl_employment WHERE serviceNo = '$serviceNo'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        // Fetch existing record to preserve unchanged values
        $existing_record = mysqli_fetch_assoc($check_result);
        
        // Prepare update fields - only update if new value is provided
        $update_fields = [];
        $has_changes = false;
        
        // Update Date of First Appointment only if provided (not empty)
        if (!empty($dofa)) {
            $update_fields[] = "dofa = '$dofa'";
            $has_changes = true;
        } else {
            // Keep existing dofa value
            $dofa = $existing_record['dofa'];
        }
        
        // Update Date of Present Appointment only if provided (not empty)
        if (!empty($dopa)) {
            $update_fields[] = "dopa = '$dopa'";
            $has_changes = true;
        } else {
            // Keep existing dopa value
            $dopa = $existing_record['dopa'];
        }
        
        // Update Current Rank only if a selection is made (not empty string)
        if ($currentRank !== '') {
            $update_fields[] = "currentRank = '$currentRank'";
            $has_changes = true;
        } else {
            // Keep existing rank value
            $currentRank = $existing_record['currentRank'];
        }
        
        // Update Present Posting only if a selection is made (not empty string)
        if ($presentPosting !== '') {
            $update_fields[] = "presentPosting = '$presentPosting'";
            $has_changes = true;
        } else {
            // Keep existing posting value
            $presentPosting = $existing_record['presentPosting'];
        }
        
        // Date validation: dopa should not be earlier than dofa
        if (strtotime($dopa) < strtotime($dofa)) {
            echo "<script>alert('Date of Present Appointment cannot be earlier than Date of First Appointment.');</script>";
        } else {
            // Only update if there are fields to update
            if ($has_changes) {
                $update_sql = "UPDATE tbl_employment SET " . implode(', ', $update_fields) . 
                             " WHERE serviceNo = '$serviceNo'";
                
                if (mysqli_query($conn, $update_sql)) {
                    // Success message and redirect to search parameter
                    echo "<script>
                            alert('Employment record updated successfully!');
                            window.location.href = 'enrolment-update?search=$serviceNo';
                          </script>";
                    exit;
                } else {
                    echo "<script>alert('Error updating record: " . mysqli_error($conn) . "');</script>";
                }
            } else {
                // No changes made - redirect to search parameter
                echo "<script>
                        alert('No changes were made.');
                        window.location.href = 'enrolment-update?search=$serviceNo';
                      </script>";
                exit;
            }
        }
    } else {
        // Insert new record - all fields are required for new records
        if (empty($dofa) || empty($dopa) || empty($currentRank) || empty($presentPosting)) {
            echo "<script>alert('All fields are required for new records.');</script>";
        } else {
            // Validate dates for new records
            if (strtotime($dopa) < strtotime($dofa)) {
                echo "<script>alert('Date of Present Appointment cannot be earlier than Date of First Appointment.');</script>";
            } else {
                // Insert new record
                $insert_sql = "INSERT INTO tbl_employment (serviceNo, dofa, dopa, currentRank, presentPosting) 
                               VALUES ('$serviceNo', '$dofa', '$dopa', '$currentRank', '$presentPosting')";
                
                if (mysqli_query($conn, $insert_sql)) {
                    // Success message and redirect to search parameter
                    echo "<script>
                            alert('Employment record created successfully!');
                            window.location.href = 'enrolment-update?search=$serviceNo';
                          </script>";
                    exit;
                } else {
                    echo "<script>alert('Error creating record: " . mysqli_error($conn) . "');</script>";
                }
            }
        }
    }
}

// If we have a service number, try to fetch existing data
if ($id) {
    $fetch_sql = "SELECT * FROM tbl_employment WHERE serviceNo = '$id'";
    $fetch_result = mysqli_query($conn, $fetch_sql);
    
    if (mysqli_num_rows($fetch_result) > 0) {
        $existing_data = mysqli_fetch_assoc($fetch_result);
        $dofa_value = htmlspecialchars($existing_data['dofa']);
        $dopa_value = htmlspecialchars($existing_data['dopa']);
        $currentRank_value = htmlspecialchars($existing_data['currentRank']);
        $presentPosting_value = htmlspecialchars($existing_data['presentPosting']);
    } else {
        $dofa_value = '';
        $dopa_value = '';
        $currentRank_value = '';
        $presentPosting_value = '';
    }
} else {
    $dofa_value = '';
    $dopa_value = '';
    $currentRank_value = '';
    $presentPosting_value = '';
}
?>

<!-- page content -->
<div class="right_col" role="main">
    <div class="">
        <div class="page-title">
            <div class="title_left">
                <h3>Update Record Employment</h3>
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
                    <h2><small>Employee Record</small></h2>
                    <ul class="nav navbar-right panel_toolbox"></ul>
                    <div class="clearfix"></div>
                </div>
                <div class="x_content">
                    <!-- Smart Wizard -->
                    <p>NB: All fields are compulsory for new records. For updates, leave fields empty to keep existing values.</p>
                    <div class="form_wizard wizard_horizontal">
                        
                        <form method="post" action="" class="form-horizontal form-label-left" onsubmit="return validateForm()">
                            <?php if ($id): ?>
                                <input type="hidden" name="serviceNo" value="<?php echo htmlspecialchars($id); ?>">
                            <?php endif; ?>
                            
                            <fieldset>
                                <div class="form-group">
                                    <label class="control-label col-md-3 col-sm-3 col-xs-12">Service No</label>
                                    <div class="col-md-6 col-sm-6 col-xs-12">
                                        <input type="text" class="form-control col-md-7 col-xs-12" value="<?php echo htmlspecialchars($id); ?>" readonly>
                                        <small></small>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="control-label col-md-3 col-sm-3 col-xs-12">Date Of First Appointment <span class="required">*</span></label>
                                    <div class="col-md-6 col-sm-6 col-xs-12">
                                        <input name="dofa" class="date-picker form-control col-md-7 col-xs-12" type="date" value="<?php echo $dofa_value; ?>">
                                        <small><em></em></small>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="control-label col-md-3 col-sm-3 col-xs-12">Date Of Present Appointment <span class="required">*</span></label>
                                    <div class="col-md-6 col-sm-6 col-xs-12">
                                        <input name="dopa" class="form-control col-md-7 col-xs-12" type="date" value="<?php echo $dopa_value; ?>">
                                        <small><em></em></small>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="currentRank" class="control-label col-md-3 col-sm-3 col-xs-12">Current Rank <span class="required">*</span></label>
                                    <div class="col-md-6 col-sm-6 col-xs-12">
                                        <select name="currentRank" class="form-control col-md-7 col-xs-12">
                                            <option value="">--Select Rank (Leave blank to keep existing)--</option>
                                            <option value="Comptroller General" <?php echo ($currentRank_value == 'Comptroller General') ? 'selected' : ''; ?>>CGIS</option>
                                            <option value="Dep. Comptroller General" <?php echo ($currentRank_value == 'Dep. Comptroller General') ? 'selected' : ''; ?>>DCG</option>
                                            <option value="Asst. Comptroller General" <?php echo ($currentRank_value == 'Asst. Comptroller General') ? 'selected' : ''; ?>>ACG</option> 
                                            <option value="Comptroller" <?php echo ($currentRank_value == 'Comptroller') ? 'selected' : ''; ?>>CIS</option>
                                            <option value="Deputy Comptroller" <?php echo ($currentRank_value == 'Deputy Comptroller') ? 'selected' : ''; ?>>DCI</option>
                                            <option value="Assistant Comptroller" <?php echo ($currentRank_value == 'Assistant Comptroller') ? 'selected' : ''; ?>>ACI</option>
                                            <option value="Chief Superintendent" <?php echo ($currentRank_value == 'Chief Superintendent') ? 'selected' : ''; ?>>CSI</option>
                                            <option value="Superintendent" <?php echo ($currentRank_value == 'Superintendent') ? 'selected' : ''; ?>>SI</option>
                                            <option value="Deputy Superintendent" <?php echo ($currentRank_value == 'Deputy Superintendent') ? 'selected' : ''; ?>>DSI</option>
                                            <option value="Asst. Superintendent 1" <?php echo ($currentRank_value == 'Asst. Superintendent 1') ? 'selected' : ''; ?>>ASI-1</option>
                                            <option value="Asst. Superintendent 2" <?php echo ($currentRank_value == 'Asst. Superintendent 2') ? 'selected' : ''; ?>>ASI-2</option>
                                            <option value="Inspector" <?php echo ($currentRank_value == 'Inspector') ? 'selected' : ''; ?>>II</option>
                                            <option value="Assistant Inspector" <?php echo ($currentRank_value == 'Assistant Inspector') ? 'selected' : ''; ?>>AII</option>
                                            <option value="Chief Immigration Asst." <?php echo ($currentRank_value == 'Chief Immigration Asst.') ? 'selected' : ''; ?>>CIA</option>
                                            <option value="Senior Immigration Asst." <?php echo ($currentRank_value == 'Senior Immigration Asst.') ? 'selected' : ''; ?>>SIA</option>
                                            <option value="Immigration Asst. 1" <?php echo ($currentRank_value == 'Immigration Asst. 1') ? 'selected' : ''; ?>>IA-1</option>
                                            <option value="Immigration Asst. 2" <?php echo ($currentRank_value == 'Immigration Asst. 2') ? 'selected' : ''; ?>>IA-2</option>
                                            <option value="Immigration Asst. 3" <?php echo ($currentRank_value == 'Immigration Asst. 3') ? 'selected' : ''; ?>>IA-3</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="cono1" class="control-label col-md-3 col-sm-3 col-xs-12">Present Posting <span class="required">*</span></label>
                                    <div class="col-md-6 col-sm-6 col-xs-12">
                                        <select name="presentPosting" class="form-control col-md-7 col-xs-12 shadow-none">
                                            <option value="">--Select Place of Present Posting (Leave blank to keep existing)--</option>
                                            <option value="Other" <?php echo ($presentPosting_value == 'Other') ? 'selected' : ''; ?>>Other</option>
                                            <option value="Service Headquarters" <?php echo ($presentPosting_value == 'Service Headquarters') ? 'selected' : ''; ?>>Service Headquarters (SHQ)</option>
                                            <option value="Zone A Lagos" <?php echo ($presentPosting_value == 'Zone A Lagos') ? 'selected' : ''; ?>>Zone 'A' Lagos</option>
                                            <option value="Zone B Kaduna" <?php echo ($presentPosting_value == 'Zone B Kaduna') ? 'selected' : ''; ?>>Zone 'B' Kaduna</option>
                                            <option value="Zone C Bauchi" <?php echo ($presentPosting_value == 'Zone C Bauchi') ? 'selected' : ''; ?>>Zone 'C' Bauchi</option>
                                            <option value="Zone D Minna" <?php echo ($presentPosting_value == 'Zone D Minna') ? 'selected' : ''; ?>>Zone 'D' Minna</option>
                                            <option value="Zone E Owerri" <?php echo ($presentPosting_value == 'Zone E Owerri') ? 'selected' : ''; ?>>Zone 'E' Owerri</option>
                                            <option value="Zone F Ibadan" <?php echo ($presentPosting_value == 'Zone F Ibadan') ? 'selected' : ''; ?>>Zone 'F' Ibadan</option>
                                            <option value="Zone G Benin City" <?php echo ($presentPosting_value == 'Zone G Benin City') ? 'selected' : ''; ?>>Zone' G' Benin City</option>
                                            <option value="Zone H Makurdi" <?php echo ($presentPosting_value == 'Zone H Makurdi') ? 'selected' : ''; ?>>Zone 'H' Makurdi</option>
                                            <option value="FCT" <?php echo ($presentPosting_value == 'FCT') ? 'selected' : ''; ?>>FCT Command</option>
                                            <option value="Abia" <?php echo ($presentPosting_value == 'Abia') ? 'selected' : ''; ?>>Abia State Command</option>
                                            <option value="Adamawa" <?php echo ($presentPosting_value == 'Adamawa') ? 'selected' : ''; ?>>Adamawa State Command</option>
                                            <option value="Akwa Ibom" <?php echo ($presentPosting_value == 'Akwa Ibom') ? 'selected' : ''; ?>>Akwa Ibom State Command</option>
                                            <option value="Anambra" <?php echo ($presentPosting_value == 'Anambra') ? 'selected' : ''; ?>>Anambra State Command</option>
                                            <option value="Bauchi" <?php echo ($presentPosting_value == 'Bauchi') ? 'selected' : ''; ?>>Bauchi State Command</option>
                                            <option value="Bayelsa" <?php echo ($presentPosting_value == 'Bayelsa') ? 'selected' : ''; ?>>Bayelsa State Command</option>
                                            <option value="Benue" <?php echo ($presentPosting_value == 'Benue') ? 'selected' : ''; ?>>Benue State Command</option>
                                            <option value="Borno" <?php echo ($presentPosting_value == 'Borno') ? 'selected' : ''; ?>>Borno State Command</option>
                                            <option value="Cross River" <?php echo ($presentPosting_value == 'Cross River') ? 'selected' : ''; ?>>Cross River State Command</option>
                                            <option value="Delta" <?php echo ($presentPosting_value == 'Delta') ? 'selected' : ''; ?>>Delta State Command</option>
                                            <option value="Ebonyi" <?php echo ($presentPosting_value == 'Ebonyi') ? 'selected' : ''; ?>>Ebonyi State Command</option>
                                            <option value="Edo" <?php echo ($presentPosting_value == 'Edo') ? 'selected' : ''; ?>>Edo State Command</option>
                                            <option value="Ekiti" <?php echo ($presentPosting_value == 'Ekiti') ? 'selected' : ''; ?>>Ekiti State Command</option>
                                            <option value="Enugu" <?php echo ($presentPosting_value == 'Enugu') ? 'selected' : ''; ?>>Enugu State Command</option>
                                            <option value="Gombe" <?php echo ($presentPosting_value == 'Gombe') ? 'selected' : ''; ?>>Gombe State Command</option>
                                            <option value="Imo" <?php echo ($presentPosting_value == 'Imo') ? 'selected' : ''; ?>>Imo State Command</option>
                                            <option value="Jigawa" <?php echo ($presentPosting_value == 'Jigawa') ? 'selected' : ''; ?>>Jigawa State Command</option>
                                            <option value="Kaduna" <?php echo ($presentPosting_value == 'Kaduna') ? 'selected' : ''; ?>>Kaduna State Command</option>
                                            <option value="Kano" <?php echo ($presentPosting_value == 'Kano') ? 'selected' : ''; ?>>Kano State Command</option>
                                            <option value="Katsina" <?php echo ($presentPosting_value == 'Katsina') ? 'selected' : ''; ?>>Katsina State Command</option>
                                            <option value="Kebbi" <?php echo ($presentPosting_value == 'Kebbi') ? 'selected' : ''; ?>>Kebbi State Command</option>
                                            <option value="Kogi" <?php echo ($presentPosting_value == 'Kogi') ? 'selected' : ''; ?>>Kogi State Command</option>
                                            <option value="Kwara" <?php echo ($presentPosting_value == 'Kwara') ? 'selected' : ''; ?>>Kwara State Command</option>
                                            <option value="Lagos" <?php echo ($presentPosting_value == 'Lagos') ? 'selected' : ''; ?>>Lagos State Command</option>
                                            <option value="Nasarawa" <?php echo ($presentPosting_value == 'Nasarawa') ? 'selected' : ''; ?>>Nasarawa State Command</option>
                                            <option value="Niger" <?php echo ($presentPosting_value == 'Niger') ? 'selected' : ''; ?>>Niger State Command</option>
                                            <option value="Ogun" <?php echo ($presentPosting_value == 'Ogun') ? 'selected' : ''; ?>>Ogun State Command</option>
                                            <option value="Ondo" <?php echo ($presentPosting_value == 'Ondo') ? 'selected' : ''; ?>>Ondo State Command</option>
                                            <option value="Osun" <?php echo ($presentPosting_value == 'Osun') ? 'selected' : ''; ?>>Osun State Command</option>
                                            <option value="Oyo" <?php echo ($presentPosting_value == 'Oyo') ? 'selected' : ''; ?>>Oyo State Command</option>
                                            <option value="Plateau" <?php echo ($presentPosting_value == 'Plateau') ? 'selected' : ''; ?>>Plateau State Command</option>
                                            <option value="Rivers" <?php echo ($presentPosting_value == 'Rivers') ? 'selected' : ''; ?>>Rivers State Command</option>
                                            <option value="Sokoto" <?php echo ($presentPosting_value == 'Sokoto') ? 'selected' : ''; ?>>Sokoto State Command</option>
                                            <option value="Taraba" <?php echo ($presentPosting_value == 'Taraba') ? 'selected' : ''; ?>>Taraba State Command</option>
                                            <option value="Yobe" <?php echo ($presentPosting_value == 'Yobe') ? 'selected' : ''; ?>>Yobe State Command</option>
                                            <option value="Zamfara" <?php echo ($presentPosting_value == 'Zamfara') ? 'selected' : ''; ?>>Zamfara State Command</option>
                                            <option value="LABPC" <?php echo ($presentPosting_value == 'LABPC') ? 'selected' : ''; ?>>Lagos Border Patrol Command</option>
                                            <option value="Ikoyi Passport Office" <?php echo ($presentPosting_value == 'Ikoyi Passport Office') ? 'selected' : ''; ?>>Ikoyi Passport Office</option>
                                            <option value="FTZE Lekki" <?php echo ($presentPosting_value == 'FTZE Lekki') ? 'selected' : ''; ?>>Free Trade Zone Lekki</option>
                                            <option value="MMIA" <?php echo ($presentPosting_value == 'MMIA') ? 'selected' : ''; ?>>Murtala Mohammed International Airport (MMIA)</option>
                                            <option value="MAKIA" <?php echo ($presentPosting_value == 'MAKIA') ? 'selected' : ''; ?>>Mallam Aminu Kano International Airport (MAKIA)</option>
                                            <option value="PHIA" <?php echo ($presentPosting_value == 'PHIA') ? 'selected' : ''; ?>>PortHarcourt International Airport (PHIA)</option>
                                            <option value="AIIA" <?php echo ($presentPosting_value == 'AIIA') ? 'selected' : ''; ?>>Akanu Ibiam International Airport (AIIA)</option>
                                            <option value="NAIA" <?php echo ($presentPosting_value == 'NAIA') ? 'selected' : ''; ?>>Nnamdi Azikiwe International Airport (NAIA)</option>
                                            <option value="Lagos Passport Command" <?php echo ($presentPosting_value == 'Lagos Passport Command') ? 'selected' : ''; ?>>Lagos Passport Command</option>  
                                            <option value="Seme Border Command" <?php echo ($presentPosting_value == 'Seme Border Command') ? 'selected' : ''; ?>>Seme Border Command</option> 
                                            <option value="Seme Border Patrol" <?php echo ($presentPosting_value == 'Seme Border Patrol') ? 'selected' : ''; ?>>Seme Border Patrol</option>
                                            <option value="Idiroko Border Command" <?php echo ($presentPosting_value == 'Idiroko Border Command') ? 'selected' : ''; ?>>Idiroko Border Command</option>
                                            <option value="Illela Border Command" <?php echo ($presentPosting_value == 'Illela Border Command') ? 'selected' : ''; ?>>Illela Border Command</option>
                                            <option value="Jibiya Border Command" <?php echo ($presentPosting_value == 'Jibiya Border Command') ? 'selected' : ''; ?>>Jibiya Border Command</option>
                                            <option value="ITSK" <?php echo ($presentPosting_value == 'ITSK') ? 'selected' : ''; ?>>Immigration Training School Kano</option>
                                            <option value="ICSC" <?php echo ($presentPosting_value == 'ICSC') ? 'selected' : ''; ?>>Immigration Command and Staff College</option>
                                            <option value="NITSA" <?php echo ($presentPosting_value == 'NITSA') ? 'selected' : ''; ?>>Nigeria Immigration Training School Ahoada</option>
                                            <option value="NITSOL" <?php echo ($presentPosting_value == 'NITSOL') ? 'selected' : ''; ?>>Nigeria Immigration Training School Orlu</option>
                                            <option value="Mfum Border Command" <?php echo ($presentPosting_value == 'Mfum Border Command') ? 'selected' : ''; ?>>Mfum Border Command</option>
                                            <option value="River Marine Command" <?php echo ($presentPosting_value == 'River Marine Command') ? 'selected' : ''; ?>>River Marine Command</option>
                                        </select>
                                    </div>
                                </div> 
                                
                                <div class="border-top">
                                    <div class="card-body">
                                        <button type="submit" name="regEmployee" class="btn btn-primary">
                                            Submit
                                        </button>
                                        <?php if ($id): ?>
                                            <a href="enrolment-update?search=<?php echo htmlspecialchars($id); ?>" class="btn btn-secondary">Cancel</a>
                                        <?php endif; ?>
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
</style>

<script>
// Function to validate the form before submission
function validateForm() {
    const dofa = document.querySelector('input[name="dofa"]');
    const dopa = document.querySelector('input[name="dopa"]');
    
    // Check if dopa is not earlier than dofa when both are provided
    if (dofa.value && dopa.value) {
        if (new Date(dopa.value) < new Date(dofa.value)) {
            alert("Date of Present Appointment cannot be earlier than Date of First Appointment.");
            dopa.focus();
            return false;
        }
    }
    
    return true;
}

// Add event listener for date validation
document.addEventListener('DOMContentLoaded', function() {
    const dofa = document.querySelector('input[name="dofa"]');
    const dopa = document.querySelector('input[name="dopa"]');
    
    if (dofa && dopa) {
        dofa.addEventListener('change', function() {
            if (dopa.value && new Date(dopa.value) < new Date(this.value)) {
                alert("Date of Present Appointment cannot be earlier than Date of First Appointment.");
                this.focus();
            }
        });
        
        dopa.addEventListener('change', function() {
            if (dofa.value && new Date(this.value) < new Date(dofa.value)) {
                alert("Date of Present Appointment cannot be earlier than Date of First Appointment.");
                this.focus();
            }
        });
    }
});
</script>