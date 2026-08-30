<?php 
 session_start();
    
  if (!isset($_SESSION['username'])) {
      require_once('index.php');
      exit;
  }

  require_once ('db/db.php');

  if ($_SESSION['role'] == "Admin")
  {
    include 'sidebar-admin.php';
  }
  if ($_SESSION['role'] == "SuperAdmin")
  {
    include 'sidebar-superadmin.php';
  }
  include 'menu-footer-buttons.php';
  include 'top-navigation.php';

            $id = isset($_GET['id']) ? $_GET['id'] : '';

            $sqluse = "SELECT emp.serviceNo, emp.surname, emp.firstName, emp.middleName, emp2.currentRank, emp2.presentPosting
            FROM tbl_emppersonal emp INNER JOIN tbl_employment emp2
            ON emp.serviceNo = emp2.serviceNo
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
                $currentposting = $foundk['presentPosting'];
                $rank = $foundk['currentRank'];
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
            <div class="clearfix">

            </div>

              <!-- <div class="row"> -->

                <div class="col-md-12 col-sm-12 col-xs-12">
                  <div class="x_panel">
                    <div class="x_title">
                      <h2>Update <small>Staff Posting</small></h2>
                      <ul class="nav navbar-right panel_toolbox">
                        <li><a class="collapse-link"><i class="fa fa-chevron-up"></i></a>
                        </li>
                        <!-- <li class="dropdown">
                          <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false"><i class="fa fa-wrench"></i></a>
                          <ul class="dropdown-menu" role="menu">
                            <li><a href="#">Settings 1</a>
                            </li>
                            <li><a href="#">Settings 2</a>
                            </li>
                          </ul>
                        </li> -->
                        <li><a class="close-link"><i class="fa fa-close"></i></a>
                        </li>
                      </ul>
                      <div class="clearfix"></div>
                    </div>
                    <div class="x_content">
                      <!-- Smart Wizard -->
                      <p>NB: Not all fields are editable</p>
                      <div>
                       
                        <form method="post" action="updateposting" class="form-horizontal form-label-left">
                          <fieldset>
                            <div>
                            
                                <div class="form-group">
                                  <label class="control-label col-md-3 col-sm-3 col-xs-12" for="serviceNo">Service No <span class="required">*</span>
                                  </label>
                                  <div class="col-md-6 col-sm-6 col-xs-12">
                                    <input type="text" name="serviceNo" required="required" class="form-control col-md-7 col-xs-12" value="<?php echo $serviceNo; ?>" readonly>
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
                                  <label for="currentRank" class="control-label col-md-3 col-sm-3 col-xs-12">Rank</label>
                                  <div class="col-md-6 col-sm-6 col-xs-12">
                                    <input id="currentrank" class="form-control col-md-7 col-xs-12" type="text" name="currentrank" value="<?php echo $rank; ?>" readonly>
                                  </div>
                                </div>
                                <div class="form-group">
                                  <label for="currentPosting" class="control-label col-md-3 col-sm-3 col-xs-12">Current Posting</label>
                                  <div class="col-md-6 col-sm-6 col-xs-12">
                                    <input id="currentposting" class="form-control col-md-7 col-xs-12" type="text" name="currentposting" value="<?php echo $currentposting; ?>" readonly>
                                  </div>
                                </div>
                                <div class="form-group">
                                      <label for="currentRank" class="control-label col-md-3 col-sm-3 col-xs-12">New Posting </label>
                                      <div class="col-md-6 col-sm-6 col-xs-12">
                                    <select name="presentPosting"  class="form-control col-md-7 col-xs-12 shadow-none" required="required">
                                                <option value="">--Select Place of Present Posting--</option>
                                                <option value="Other">Other</option>
                                                <option value="Service Headquarters">Service Headquarters (SHQ)</option>
                                                <option value="Zone A Lagos">Zone ‘A’ Lagos</option>
                                                <option value="Zone B Kaduna">Zone ‘B’ Kaduna</option>
                                                <option value="Zone C Bauchi">Zone ‘C’ Bauchi</option>
                                                <option value="Zone D Minna">Zone ‘D’ Minna</option>
                                                <option value="Zone E Owerri">Zone ‘E’ Owerri</option>
                                                <option value="Zone F Ibadan">Zone ‘F’ Ibadan</option>
                                                <option value="Zone G Benin City">Zone’ G’ Benin City</option>
                                                <option value="Zone H Makurdi">Zone ‘H’ Makurdi</option>
                                                <option value="FCT Command">FCT Command</option>
                                                <option value="Abia State Command">Abia State Command</option>
                                                <option value="Adamawa State Command">Adamawa State Command</option>
                                                <option value="Akwa Ibom State Command">Akwa Ibom State Command</option>
                                                <option value="Anambra State Command">Anambra State Command</option>
                                                <option value="Bauchi State Command">Bauchi State Command</option>
                                                <option value="Bayelsa State Command">Bayelsa State Command</option>
                                                <option value="Benue State Command">Benue State Command</option>
                                                <option value="Borno State Command">Borno State Command</option>
                                                <option value="Cross River State Command">Cross River State Command</option>
                                                <option value="Delta State Command">Delta State Command</option>
                                                <option value="Ebonyi State Command">Ebonyi State Command</option>
                                                <option value="Edo State Command">Edo State Command</option>
                                                <option value="Ekiti State Command">Ekiti State Command</option>
                                                <option value="Enugu State Command">Enugu State Command</option>
                                                <option value="Gombe State Command">Gombe State Command</option>
                                                <option value="Imo State Command">Imo State Command</option>
                                                <option value="Jigawa State Command">Jigawa State Command</option>
                                                <option value="Kaduna State Command">Kaduna State Command</option>
                                                <option value="Kano State Command">Kano State Command</option>
                                                <option value="Katsina State Command">Katsina State Command</option>
                                                <option value="Kebbi State Command">Kebbi State Command</option>
                                                <option value="Kogi State Command">Kogi State Command</option>
                                                <option value="Kwara State Command">Kwara State Command</option>
                                                <option value="Lagos State Command">Lagos State Command</option>
                                                <option value="Nasarawa State Command">Nasarawa State Command</option>
                                                <option value="Niger State Command">Niger State Command</option>
                                                <option value="Ogun State Command">Ogun State Command</option>
                                                <option value="Ondo State Command">Ondo State Command</option>
                                                <option value="Osun State Command">Osun State Command</option>
                                                <option value="Oyo State Command">Oyo State Command</option>
                                                <option value="Plateau State Command">Plateau State Command</option>
                                                <option value="Rivers State Command">Rivers State Command</option>
                                                <option value="Sokoto State Command">Sokoto State Command</option>
                                                <option value="Taraba State Command">Taraba State Command</option>
                                                <option value="Yobe State Command">Yobe State Command</option>
                                                <option value="Zamfara State Command">Zamfara State Command</option>
                                                <option value="Lagos Border Patrol Command">Lagos Border Patrol Command</option>
                                                <option value="Ikoyi Passport Command">Ikoyi Passport Command</option>
                                                <option value="MMIA">Murtala Mohammed International Airport (MMIA)</option>
                                                <option value="MAKIA">Mallam Aminu Kano International Airport (MAKIA)</option>
                                                <option value="PHIA">PortHarcourt International Airport (PHIA)</option>
                                                <option value="AIIA">Akanu Ibiam International Airport (AIIA)</option>
                                                <option value="NAIA">Nnamdi Azikiwe International Airport (NAIA)</option>
                                                <option value="Lagos Seaport & Marine Command">Lagos Seaport & Marine Command</option>
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
                                
                               
                            </div>
                            </fieldset>
                           
                            
                       <fieldset>
                              <div >
                                    <div class="border-top">
                                      <div class="card-body">
                                        <button type="submit" name="editPosting" class="btn btn-primary">
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
              <!-- </div> -->
         <!--  </div>
        </div> -->
        <!-- /page content -->

        <!-- footer content -->
        <?php include 'footer-enrol.php'; ?>
          