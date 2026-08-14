<?php
session_start();
  if (!isset($_SESSION['username'])) {
      header('Location: index');
      exit;
    }
require_once ('db/db.php');
        $sqluse = "SELECT emp.serviceNo, emp.surname, emp.firstName, emp.middleName, emp.bloodType,emp2.currentRank
        FROM tbl_emppersonal emp INNER JOIN tbl_employment emp2 
        ON emp.serviceNo = emp2.serviceNo";
              $retrieve = mysqli_query($conn, $sqluse);
              $numb = mysqli_num_rows($retrieve); 
         while ($foundk = mysqli_fetch_array($retrieve)) {
                  $profile = $foundk['serviceNo'];
                  $surname = $foundk['surname'];
                  $firstname = $foundk['firstName'];
                  $rank = $foundk['currentRank'];
        }
        
    if ($_SESSION['role'] == "DataEntry")
      {
        include 'sidebar-dataentry.php';
      }
    if ($_SESSION['role'] == "Admin")
      {
        include 'sidebar-admin.php';
      }
       if ($_SESSION['role'] == "Posting")
      {
        include 'sidebar-posting.php';
      }
include 'menu-footer-buttons.php';
include 'top-navigation.php';


?> 
        <div class="right_col" role="main">
        <div class="">
          <div class="page-title">
            <div class="title_left">
              <h3>Enrolled Personnel</h3>
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
                  <h5 class="card-title">Personnel Posting</h5>
                  <div class="table-responsive">
                    <table
                      id="zero_config"
                      class="table table-striped table-bordered"
                    >
                      <thead>
                        <tr>
                          
                          <th>Service No.</th>
                          <th>Surname</th>
                          <th>First Name</th>
                          <th>Middle Name</th>
                          <th>Rank</th>
                          
                          <th>Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                        foreach ($retrieve as $ret) : ?>
                        <tr>
                          
                          <td><?php echo $ret['serviceNo']; ?></td>
                          <td><?php echo $ret['surname']; ?></td>
                          <td><?php echo $ret['firstName']; ?></td>
                          <td><?php echo $ret['middleName']; ?></td>
                          <td><?php echo $ret['currentRank']; ?></td>
                          
                          <td>                            
                          <button class="btn btn-success" type="submit" 
                            onclick="editUser(<?php echo $ret['serviceNo']; ?>)"
                          ><i class="fa fa-camera fs-3 mb-1 font-16"></i></button></td>
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
          <!-- End PAge Content -->
          <!-- ============================================================== -->
          <!-- ============================================================== -->
          <!-- Right sidebar -->
          <!-- ============================================================== -->
          <!-- .right-sidebar -->
          <!-- ============================================================== -->
          <!-- End Right sidebar -->
          <!-- ============================================================== -->
        </div>
        <!-- ============================================================== -->
        <!-- End Container fluid  -->
        <!-- ============================================================== -->
        <!-- ============================================================== -->
        <!-- footer -->
        <!-- ============================================================== -->
        <script>
          function editUser(id) {
              window.location.href = 'newposting?id=' + id;
          }
        </script>
        <?php include 'footer_tables.php'; ?>
        