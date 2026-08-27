<?php

session_start();

    
  if (!isset($_SESSION['username'])) {
      //require_once('login.php');
      //exit;
        header("Location: index");
        die();
  }
  require_once ('db/db.php');
  require_once ('functions/posting_helper.php');

  if ($_SESSION['role'] == "Admin") 
  {
    include 'sidebar-admin.php';
  }
  if ($_SESSION['role'] == "Supervisor")
  {
    include 'sidebar-supervisor.php';
  }

  include 'menu-footer-buttons.php';
  include 'top-navigation.php';

      $sqluse = "SELECT emp.serviceNo, emp.surname, emp.firstName, emp.phone, emp.middleName, emp.bloodType,emp2.currentRank,emp2.presentPosting, emp2.directorate, emp2.unit, emp2.posting_other
      FROM tbl_emppersonal emp INNER JOIN tbl_employment emp2
      ON emp.serviceNo = emp2.serviceNo";
      $retrieve = mysqli_query($conn, $sqluse);
      $numb = mysqli_num_rows($retrieve); 
      while ($foundk = mysqli_fetch_array($retrieve)) {
          $profile = $foundk['serviceNo'];
          $posting = $foundk['presentPosting'];
          $surname = $foundk['surname'];
          $firstname = $foundk['firstName'];
          $phone = $foundk['phone'];
          $rank = $foundk['currentRank'];
        }
?>
     <div class="right_col" role="main">
          <div class="">
            <div class="page-title">
              <div class="title_left">
                <h3>Personnel Enrolment</h3>
              </div>

              <div class="title_right">
                <div class="col-md-5 col-sm-5 col-xs-12 form-group pull-right top_search">
                  <div class="input-group">
                    <input type="text" class="form-control" placeholder="Search for...">
                    <span class="input-group-btn">
                              <button class="btn btn-default" type="button">Go!</button>
                          </span>
                  </div>
                </div>
              </div>
            </div>
            <div class="clearfix"></div>
          <!-- Start Page Content -->
          <!-- ============================================================== -->
          <div class="row">              
              <div class="card">
                <div class="card-body">
                  <h5 class="card-title">Enrolled Officers</h5>
                  <div class="table-responsive">
                    <table
                      id="zero_config"
                      class="table table-striped table-bordered"
                    >
                      <thead>
                        <tr>
                          <th></th>
                          <th>Service No.</th>
                          <th>Full Name</th>
                          <th>Rank</th>
                          <th>Current Posting</th>
                          <!--<th>Phone No.</th>  -->                        
                          <th>Blood Type</th>
                          <!--<th>Action</th> -->
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                        foreach ($retrieve as $ret) : ?>
                        <tr>
                          <td><img src="upload/<?php echo $ret['serviceNo']; ?>.png"
                          style="width: 20px;"></td>
                          <td><?php echo $ret['serviceNo']; ?></td>
                          <td><?php echo strtoupper($ret['surname'] . ' ' . $ret['middleName'] . ' ' . $ret['firstName']); ?></td>
                          <td><?php echo $ret['currentRank']; ?></td>
                          <td><?php echo htmlspecialchars(formatPresentPosting(
                              $ret['presentPosting'],
                              $ret['directorate'] ?? '',
                              $ret['unit'] ?? '',
                              $ret['posting_other'] ?? ''
                          )); ?></td>
                          <!--<td><?php //echo $ret['phone']; ?></td>-->
                          <td><?php echo $ret['bloodType']; ?></td>
                           <!--<td> <button class="btn btn-success" type="submit" 
                            onclick="editUser(<?php //echo $ret['serviceNo']; ?>)"
                          ><i class="fa fa-eye fs-3 mb-1 font-16"></i></button>                          
                          </td>-->
                        </tr>
                       <?php endforeach; ?>
                      </tbody>
                     
                    </table>
                    
                  </div>
                </div>
              </div>
            </div>
          </div>
         
        <!-- ============================================================== -->
        <!-- End Container fluid  -->
        <!-- ============================================================== -->
        <!-- ============================================================== -->
        <!-- footer -->
        <!-- ============================================================== -->
      
         <script>
          function editUser(id) {
              window.location.href = 'view_employee?id=' + id;
          }
        </script>
        <?php include 'footer_tables.php'; ?>