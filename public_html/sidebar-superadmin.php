
<!DOCTYPE html>
<html lang="en">
  <head>
    
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <!-- Tell the browser to be responsive to screen width -->
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta
      name="keywords"
      content="immigration, admin dashboard,enrolment, ID card"
    />
    <meta
      name="description"
      content="NIIMS is an in-house solution built and deployed by the Web Team of the Nigeria Immigration Service led by ACI P Odeh. Other members of the team includes JT Vihishima, G Almustapha, Dagogo Gift, Adanma Eze, Taofeeq Lasisi, Usman Baffa"
    />
	<link rel="icon" href="images/favicon.ico" type="image/ico" />
 

    <title>NIS Identity Management System </title>

    <!-- Bootstrap -->
    <link href="vendors/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="vendors/font-awesome/css/font-awesome.min.css" rel="stylesheet">
    <!-- NProgress -->
    <link href="vendors/nprogress/nprogress.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.js"></script>
    <!-- iCheck -->
    <link href="vendors/iCheck/skins/flat/green.css" rel="stylesheet">
	
    <!-- bootstrap-progressbar -->
    <link href="vendors/bootstrap-progressbar/css/bootstrap-progressbar-3.3.4.min.css" rel="stylesheet">
    <!-- JQVMap -->
    <link href="vendors/jqvmap/dist/jqvmap.min.css" rel="stylesheet"/>
    <!-- bootstrap-daterangepicker -->
    <link href="vendors/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet">
  
    <!-- Custom Theme Style -->
    <link href="build/css/custom.css" rel="stylesheet">
    <script src="js/lga.js"></script>


  </head>
 
  <body class="nav-md">
    <div class="container body">
      <div class="main_container">
        <div class="col-md-3 left_col">
          <div class="left_col scroll-view">
            <div class="navbar nav_title" style="border: 0;">
              <a href="mainpage" class="site_title">
              <img
                  src="assets/images/logo.png"
                  alt="homepage"
                  class="light-logo"
                  width="30"
                />  
              <span>NIIMS</span></a>
            </div>

            <div class="clearfix"></div>

            <!-- menu profile quick info -->
            <div class="profile clearfix">
              <div class="profile_pic">
                <?php
                $sidebarPhotoSrc = 'upload/default.png';
                if (!empty($_SESSION['photo']) && file_exists($_SESSION['photo'])) {
                    $sidebarPhotoSrc = $_SESSION['photo'];
                } elseif (file_exists('upload/' . $_SESSION['user_id'] . '.png')) {
                    $sidebarPhotoSrc = 'upload/' . $_SESSION['user_id'] . '.png';
                } elseif (file_exists('images/' . $_SESSION['user_id'] . '.jpg')) {
                    $sidebarPhotoSrc = 'images/' . $_SESSION['user_id'] . '.jpg';
                }
                ?>
                <img src="<?php echo htmlspecialchars($sidebarPhotoSrc); ?>" alt="..." class="img-circle profile_img" onerror="this.onerror=null; this.src='upload/default.png';">
              </div>
              <div class="profile_info">
                <span>Welcome,</span>
                <h2><?php echo $_SESSION['username']; ?></h2>
              </div>
            </div>
            <!-- /menu profile quick info -->

            <br />

            <!-- sidebar menu -->
            <div id="sidebar-menu" class="main_menu_side hidden-print main_menu">
              <div class="menu_section">
                <h3>Super Admin Menu</h3>
                <ul class="nav side-menu">
                  <li><a><i class="fa fa-home"></i> Home <span class="fa fa-chevron-down"></span></a>
                    <ul class="nav child_menu">
                      <li><a href="mainpage.php">Dashboard</a></li>
                     
                    </ul>
                  </li>
                  <li><a><i class="fa fa-database"></i> Enrolment <span class="fa fa-chevron-down"></span></a>
                    <ul class="nav child_menu">
                    <li><a href="enrol_employee.php">New Enrolment</a></li>
                      <li><a href="cgi_view.php">View All Enrolment</a></li>
                      <li><a href="myenrolments.php">View My Enrolment</a></li>
                      <li><a href="print-enrolment.php">Print Enrolment Slip</a></li>
                      
                    </ul>
                  </li>
                  <li><a><i class="fa fa-hand-o-up"></i> Biometrics <span class="fa fa-chevron-down"></span></a>
                    <ul class="nav child_menu">
                      
                      <li><a href="photocapture.php">Photo Capture</a></li>
                      <li><a href="signaturecapture.php">Signature</a></li>                                            
                    </ul>
                  </li>
                  <li><a><i class="fa fa-book"></i> Update Records <span class="fa fa-chevron-down"></span></a>
                    <ul class="nav child_menu">
                      <li><a href="update_rank.php">Update Rank</a></li>
                      <li><a href="enrolment-update.php">Update Staff Record</a></li>
                      <li><a href="https://niims.immigration.gov.ng/nisposting/login.php" target="_blank">Posting Order</a></li>
                      
                    </ul>
                  </li>
                  <li><a><i class="fa fa-laptop"></i> Card Management <span class="fa fa-chevron-down"></span></a>
                    <ul class="nav child_menu">

                      <li><a href="print-tables.php">Print Card</a></li>
                      <li><a href="bulk_print.php">Bulk Print</a></li>
                      <li><a href="printed-cards.php">Print History</a></li>
                    </ul>
                  </li>
                  <li><a><i class="fa fa-user"></i> User Management <span class="fa fa-chevron-down"></span></a>
                    <ul class="nav child_menu">
                      <li><a href="createuser.php">Create User</a></li>
                      <li><a href="audit_logs.php">Audit Logs</a></li>
                      <li><a href="db_backup.php">Database Backup</a></li>
                    </ul>
                  </li>
              
               
                  <li><a><i class="fa fa-bar-chart-o"></i> Reports <span class="fa fa-chevron-down"></span></a>
                    <ul class="nav child_menu">
                        <li><a href="nominal_roll.php">Nominal Roll</a></li>
                        <li><a href="nominal_roll_retired.php">Retired Nominal Roll</a></li>
                      <li><a href="employeeChart.php">Employee</a></li>
                      <li><a href="printed-cards-report.php">Cards Report</a></li>
                      <li><a href="comingsoon.php">Posting</a></li>
                      <li><a href="comingsoon.php">Distribution</a></li>
                    </ul>
                  </li>
                 
                </ul>
              </div>
              

            </div>
            <!-- /sidebar menu -->
