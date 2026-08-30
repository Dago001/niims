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
if ($_SESSION['role'] == "SuperAdmin") {
    include 'sidebar-superadmin.php';
}
include 'menu-footer-buttons.php';
include 'top-navigation.php';

$id = isset($_GET['id']) ? $_GET['id'] : '';

$sqluse = "SELECT emp.serviceNo, emp.surname, emp.firstName, emp.signature, emp.middleName, emp.bloodType, emp2.currentRank
          FROM tbl_emppersonal emp
          INNER JOIN tbl_employment emp2 ON emp.serviceNo = emp2.serviceNo
          WHERE emp.serviceNo = ?";
$stmt = mysqli_prepare($conn, $sqluse);
mysqli_stmt_bind_param($stmt, "s", $id);
mysqli_stmt_execute($stmt);
$retrieve = mysqli_stmt_get_result($stmt);
$numb = mysqli_num_rows($retrieve);
while ($foundk = mysqli_fetch_array($retrieve)) {
    $serviceNo = $foundk['serviceNo'];
    $surname = $foundk['surname'];
    $firstname = $foundk['firstName'];
    $middlename = $foundk['middleName'];
    $sign = $foundk['signature'];
    $rank = $foundk['currentRank'];
    $bloodType = $foundk['bloodType'];
}
?>

<!-- page content -->
<div class="right_col" role="main">
    <div class="">
        <div class="page-title">
            <div class="title_left">
                <h3>Update Records</h3>
            </div>

            <div class="title_right">
                <div class="col-md-5 col-sm-5 col-xs-12 form-group pull-right top_search">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Search for...">
                    </div>
                </div>
            </div>
        </div>
        
        <style>
            .required {
                color: red;
            }
        </style>
        
        <div class="clearfix"></div>

        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="x_panel">
                <div class="x_title">
                    <h2>Update <small>Personal Information</small></h2>
                    <ul class="nav navbar-right panel_toolbox">
                        <li><a class="collapse-link"><i class="fa fa-chevron-up"></i></a></li>
                        <li><a class="close-link"><i class="fa fa-close"></i></a></li>
                    </ul>
                    <div class="clearfix"></div>
                </div>
                <div class="x_content">
                    <p>NB: Not all fields are editable</p>
                    <div>
                        <form method="post" action="updaterank" class="form-horizontal form-label-left">
                            <fieldset>
                                <div>
                                    <div class="form-group">
                                        <label class="control-label col-md-3 col-sm-3 col-xs-12" for="serviceNo">Service No <span class="required">*</span>
                                        </label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <input type="text" name="serviceNo" required="required" class="form-control col-md-7 col-xs-12" value="<?php echo $serviceNo; ?>" readonly>
                                            <!-- Hidden field for redirect -->
                                            <input type="hidden" name="redirect_serviceNo" value="<?php echo $serviceNo; ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="control-label col-md-3 col-sm-3 col-xs-12" for="last-name">Surname <span class="required">*</span>
                                        </label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <input type="text" name="lname" required="required" class="form-control col-md-7 col-xs-12" value="<?php echo $surname; ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="control-label col-md-3 col-sm-3 col-xs-12" for="first-name">First Name <span class="required">*</span>
                                        </label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <input type="text" name="fname" required="required" class="form-control col-md-7 col-xs-12" value="<?php echo $firstname; ?>" readonly>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="middle_name" class="control-label col-md-3 col-sm-3 col-xs-12">Middle Name</label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <input id="mname" class="form-control col-md-7 col-xs-12" type="text" name="mname" value="<?php echo $middlename; ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="previousRank" class="control-label col-md-3 col-sm-3 col-xs-12">Previous Rank</label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <input id="previousrank" class="form-control col-md-7 col-xs-12" type="text" name="previousrank" value="<?php echo $rank; ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="currentRank" class="control-label col-md-3 col-sm-3 col-xs-12">Current Rank </label>
                                        <div class="col-md-6 col-sm-6 col-xs-12">
                                            <select name="currentRank" class="form-control col-md-7 col-xs-12">
                                                <option value="">--Select Rank--</option>
                                                <option value="Comptroller General">CGIS</option>
                                                <option value="Dep. Comptroller General">DCG</option>
                                                <option value="Asst. Comptroller General">ACG</option> 
                                                <option value="Comptroller">CIS</option>
                                                <option value="Deputy Comptroller">DCI</option>
                                                <option value="Assistant Comptroller">ACI</option>
                                                <option value="Chief Superintendent">CSI</option>
                                                <option value="Superintendent">SI</option>
                                                <option value="Deputy Superintendent">DSI</option>
                                                <option value="Asst. Superintendent 1">ASI-1</option>
                                                <option value="Asst. Superintendent 2">ASI-2</option>
                                                <option value="Inspector">II</option>
                                                <option value="Assistant Inspector">AII</option>
                                                <option value="Chief Immigration Asst.">CIA</option>
                                                <option value="Senior Immigration Asst.">SIA</option>
                                                <option value="Immigration Asst. 1">IA-1</option>
                                                <option value="Immigration Asst. 2">IA-2</option>
                                                <option value="Immigration Asst. 3">IA-3</option>
                                            </select>
                                        </div>
                                    </div>                                                           
                                </div>
                            </fieldset>
                           
                            <fieldset>
                                <div>
                                    <div class="border-top">
                                        <div class="card-body">
                                            <button type="submit" name="editRank" class="btn btn-primary">
                                                Submit
                                            </button>
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