<?php 
   session_start();
  
  if (!isset($_SESSION['username'])) {
      require_once('login.php');
      exit;
  }
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
                    
                  </div>
                </div>
              </div>
            </div>
            <div class="clearfix"></div>
        <!-- ============================================================== -->
        <!-- Bread crumb and right sidebar toggle -->
        <!-- ============================================================== -->
        <div class="page-breadcrumb">
          <div class="row">
            <div class="col-12 d-flex no-block align-items-center">
              <h4 class="page-title">Failed Upload</h4>
              <div class="ms-auto text-end">
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">Message</a></li>
                    <li class="breadcrumb-item active" aria-current="page">
                      
                      <script>
                            $(document).ready(function() {
                                $("#success-modal").modal("show");
                            });
                        </script>
                        <!-- Modal -->
                        <div id="success-modal" class="modal fade" role="dialog">
                          <div class="modal-dialog">
                            <!-- Modal content-->
                            <div class="modal-content">
                              <div class="modal-header">
                                <h4 class="modal-title">Failed!</h4>
                              </div>
                              <div class="modal-body">
                                <p>Record not uploaded!.</p>
                              </div>
                              <div class="modal-footer">
                                <button type="button" class="btn btn-success" onclick="redirect()">OK</button>
                              </div>
                            </div>
                          </div>
                        </div>
                        <script>
                            function redirect() {
                                window.location.href = "enrol_employee";
                            }
                        </script>
                    </li>
                  </ol>
                </nav>
              </div>
            </div>
          </div>
        </div>
        <!-- ============================================================== -->
        <!-- End Bread crumb and right sidebar toggle -->
        <!-- ============================================================== -->
        <!-- ============================================================== -->
        <!-- Container fluid  -->
        <!-- ============================================================== -->
        <div class="container-fluid">
          <!-- ============================================================== -->
          <!-- Start Page Content -->
          <!-- ============================================================== -->
          <div class="row">              
              <div class="card">
                <div class="card-body">                
                  <?php echo "Duplicate Record found!"; ?>
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
        <?php include 'footer.php'; ?>