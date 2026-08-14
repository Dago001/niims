<?php 
  session_start();
  
  if (!isset($_SESSION['username'])) {
      header('Location: login');
      exit;
  }
  require_once ('db/db.php');
  if ($_SESSION['role'] == "DataEntry")
  {
    include 'sidebar-dataentry.php';
  }
  if ($_SESSION['role'] == "Admin")
  {
    include 'sidebar-admin.php';
  }
  include 'menu-footer-buttons.php';
  include 'top-navigation.php';

  $sqluse = "SELECT * FROM tbl_emppersonal";
  $sqluser = "SELECT * FROM tbl_users";
  $sqluse2 = "SELECT * FROM tbl_emppersonal WHERE gender='male'";
  $sqluse3 = "SELECT * FROM tbl_emppersonal WHERE gender='female'";
  $retrieve = mysqli_query($conn, $sqluse);
  $user_ret = mysqli_query($conn, $sqluser);
  $retrieve2 = mysqli_query($conn, $sqluse2);
  $retrieve3 = mysqli_query($conn, $sqluse3);
  $numb = mysqli_num_rows($retrieve); 
  $numb2 = mysqli_num_rows($retrieve2); 
  $numb3 = mysqli_num_rows($retrieve3);
  $numb4 = mysqli_num_rows($user_ret);

  ?>

        <!-- page content -->
        <div class="right_col" role="main">
          <div class="">
            <div class="page-title">
              <div class="title_left">
                <h3>NIIMS</h3>
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
            <div class="clearfix">

            </div>

              <!-- <div class="row"> -->

                <div class="col-md-12 col-sm-12 col-xs-12">
                  <div class="x_panel">
                    <div class="x_title">
                      <h2>Reports <small>Employee Report</small></h2>
                      <div class="clearfix"></div>
                    </div>
                    <div class="x_content">
                      <!-- Smart Wizard -->
                      <p>NB: All fields are compulsory.</p>
                      <canvas id="myChart" style="width:70%;max-width:400px"></canvas>

                      <script>
                        const xValues = ["Male", "Female",];
                        const yValues = [<?php echo $numb2; ?>, <?php echo $numb3; ?>];
                        const barColors = ["green", "blue"];

                        new Chart("myChart", {
                        type: "pie",
                        data: {
                        labels: xValues,
                        datasets: [{
                        backgroundColor: barColors,
                        data: yValues
                        }]
                        },
                        options: {
                        title: {
                        display: true,
                        text: "Gender Distribution"
                        }
                        }
                        });
                      </script>
                    </div>
                  </div>
                </div>
              <!-- </div> -->
         <!--  </div>
        </div> -->
        <!-- /page content -->

        <!-- footer content -->
        
        <?php include 'footer-enrol.php'; ?>
          