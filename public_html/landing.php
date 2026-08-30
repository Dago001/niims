<?php 
session_start();


  require_once ('db/db.php');

  if ($_SESSION['role'] == "DataEntry") {
    include 'sidebar-dataentry.php';
  }
    if ($_SESSION['role'] == "Biometric") {
    include 'sidebar-biometric.php';
  }
  if ($_SESSION['role'] == "Supervisor") {
    include 'sidebar-supervisor.php';
  }
  if ($_SESSION['role'] == "Report") {
    include 'sidebar-view.php';
  }
  if ($_SESSION['role'] == "SuperAdmin") {
    include 'sidebar-superadmin.php';
  }
   if ($_SESSION['role'] == "CGI") {
    include 'sidebar-cgi.php';
  }
  
  include 'menu-footer-buttons.php';
  include 'top-navigation.php';
$id = $_SESSION['user_id'];
$sqluse = "SELECT * FROM tbl_emppersonal WHERE user_id='$id'";
$retrieve = mysqli_query($conn, $sqluse);
$numb = mysqli_num_rows($retrieve); 


  
?>
       
        <!-- page content -->
        <div class="right_col" role="main">
          <!-- top tiles -->
          
          
          <br />

          <div class="row">


            <div class="col-md-4 col-sm-4 col-xs-12">
              <div class="x_panel tile fixed_height_320">
                <div class="x_title">
                  <h3>Enrolment</h3>
                  <ul class="nav navbar-right panel_toolbox">
                 
                    
                    
                  </ul>
                  <div class="clearfix"></div>
                </div>
                <div class="x_content">
                  <p>
                    All fields are compulsory and should be captured. Ensure the data entered are duly reviewed before submission of form
                  </p>
                  <p>
                    All personnel capture on the portal should have their enrolment slip printed and duly signed.
                  </p>      
                </div>
              </div>
            </div>

            <div class="col-md-4 col-sm-4 col-xs-12">
              <div class="x_panel tile fixed_height_320 overflow_hidden">
                <div class="x_title">
                  <h3>Biometrics</h3>
                  <ul class="nav navbar-right panel_toolbox">
                   
                  </ul>
                  <div class="clearfix"></div>
                </div>
                <div class="x_content">
                
                  <p>
                    Signature of personnel must be captured with the frame provided on the signature pad to avoid images extending out of approved signature box.
                  </p>
                  <p>
                    Personnel must be in their complete uniform with ranks and name tags clearly shown.
                  </p>  
                </div>
              </div>
            </div>


            <div class="col-md-4 col-sm-4 col-xs-12">
              <div class="x_panel tile fixed_height_320">
                <div class="x_title">
                  <h3><i class="fa fa-users"></i>Enrolled Users</h3>
                  <ul class="nav navbar-right panel_toolbox">
                   
                    
                  </ul>
                  <div class="clearfix"></div>
                </div>
                <div class="x_content">
                    <p style="text-align: centre;">Number of personnel you successfully enrolled:</p>
                    <p style="text-align: centre;"><h2>&nbsp;&nbsp;&nbsp;&nbsp;<?php echo $numb; ?> &nbsp;</h2></p>
                  <div class="dashboard-widget-content">
                    
                  </div>
                </div>
              </div>
            </div>

          </div>


          
        </div>
        <!-- /page content -->
<?php 
include 'footer.php';
?>