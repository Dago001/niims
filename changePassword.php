<?php 
   session_start();
  
  if (!isset($_SESSION['username'])) {
      //require_once('login.php');
      //exit;
        header("Location: index");
        die();
  }
  require_once ('db/db.php');
  if ($_SESSION['role'] == "Admin") 
  {
    include 'sidebar-admin.php';
  }
  if ($_SESSION['role'] == "Supervisor")
  {
    include 'sidebar-supervisor.php';
  }
  
  if ($_SESSION['role'] == "SuperAdmin")
  {
    include 'sidebar.php';
  }
   if ($_SESSION['role'] == "DataEntry")
  {
    include 'sidebar-dataentry.php';
  }
   if ($_SESSION['role'] == "Biometric")
  {
    include 'sidebar-biometric.php';
  }
  include 'menu-footer-buttons.php';
  include 'top-navigation.php';
  ?>
  <div class="right_col" role="main">
        <div class="">
          <div class="page-title">
            <div class="title_left">
              <h3>User Administration</h3>
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
          <!-- ============================================================== -->
          <!-- Start Page Content -->
          <!-- ============================================================== -->
          <div class="row">
            <div class="col-md-6">
              <div class="card">
                <form class="form-horizontal" enctype="multipart/form-data" action="updates" method="POST">
                  <div class="card-body">
                    <h4 class="card-title">Password Management</h4>
                    <div class="form-group row">
                      <label
                        for="serviceNo"
                        class="col-sm-3 text-end control-label col-form-label"
                        >Service No.</label
                      >
                      <div class="col-sm-9">
                        
                        <input
                          type="text"
                          class="form-control"
                          value="<?php echo $_SESSION['user_id']; ?>"
                          id="serviceNo"
                          name="serviceNo"
                          placeholder="NIS Service No."
                          readonly
                        />
                      </div>                      
                    </div>
                 


                    <div class="form-group row">
                      <label
                        for="password"
                        class="col-sm-3 text-end control-label col-form-label"
                        >New Password</label
                      >
                      <div class="col-sm-9">
                        <input
                          type="password"
                          class="form-control"
                          name="new_password"
                          id="new_password"
                          placeholder="New_Password"
                        />
                      </div>
                    </div>


                    
</div>            
                  <div class="border-top">
                    <div class="card-body">
                      <button type="submit" name="updatePassword" class="btn btn-success">
                        Reset Password
                      </button>
                    </div>
                  </div>
                </form>
              </div>
             
            </div>
            
            </div>
          </div>
          <!-- editor -->
          
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
        <?php
          include 'footer.php';
        ?>
        <!-- ============================================================== -->
       