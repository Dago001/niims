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
    include 'sidebar-superadmin.php';
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

  $forcedPasswordChange = !empty($_SESSION['must_change_password']);
  ?>
  <style>
    .forced-password-alert {
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        color: #856404;
        border-radius: 6px;
        padding: 12px 16px;
        margin-bottom: 15px;
        font-size: 13px;
    }
    .forced-password-alert i {
        margin-right: 6px;
    }
    .password-requirements {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 10px 14px;
        margin-top: 8px;
        font-size: 12px;
    }
    .pw-req {
        color: #94a3b8;
        padding: 2px 0;
    }
    .pw-req-icon:before {
        content: "\25CB";
        margin-right: 6px;
        display: inline-block;
        width: 12px;
    }
    .pw-req.valid {
        color: #059669;
        font-weight: 600;
    }
    .pw-req.valid .pw-req-icon:before {
        content: "\2713";
    }
    .password-mismatch {
        color: #dc2626;
        font-size: 12px;
        margin-top: 4px;
        display: none;
    }
  </style>
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
          <?php if ($forcedPasswordChange): ?>
          <div class="forced-password-alert">
            <i class="fa fa-exclamation-triangle"></i>
            <strong>Password change required.</strong> For security reasons you must set a new password before you can continue using the system.
          </div>
          <?php endif; ?>
          <div class="row">
            <div class="col-md-6">
              <div class="card">
                <form class="form-horizontal" enctype="multipart/form-data" action="updates" method="POST" id="changePasswordForm">
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
                        for="new_password"
                        class="col-sm-3 text-end control-label col-form-label"
                        >New Password</label
                      >
                      <div class="col-sm-9">
                        <input
                          type="password"
                          class="form-control"
                          name="new_password"
                          id="new_password"
                          placeholder="New Password"
                          autocomplete="new-password"
                        />
                        <div class="password-requirements" id="passwordRequirements" style="display:none;">
                          <div class="pw-req" data-rule="length"><span class="pw-req-icon"></span> At least 8 characters</div>
                          <div class="pw-req" data-rule="upper"><span class="pw-req-icon"></span> One uppercase letter</div>
                          <div class="pw-req" data-rule="lower"><span class="pw-req-icon"></span> One lowercase letter</div>
                          <div class="pw-req" data-rule="number"><span class="pw-req-icon"></span> One number</div>
                          <div class="pw-req" data-rule="special"><span class="pw-req-icon"></span> One special character</div>
                        </div>
                      </div>
                    </div>

                    <div class="form-group row">
                      <label
                        for="confirm_password"
                        class="col-sm-3 text-end control-label col-form-label"
                        >Confirm Password</label
                      >
                      <div class="col-sm-9">
                        <input
                          type="password"
                          class="form-control"
                          name="confirm_password"
                          id="confirm_password"
                          placeholder="Confirm Password"
                          autocomplete="new-password"
                        />
                        <div class="password-mismatch" id="passwordMismatch">Passwords do not match.</div>
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
                <script>
                  function checkPasswordRequirements(password) {
                    var rules = {
                      length: password.length >= 8,
                      upper: /[A-Z]/.test(password),
                      lower: /[a-z]/.test(password),
                      number: /[0-9]/.test(password),
                      special: /[^A-Za-z0-9]/.test(password)
                    };
                    Object.keys(rules).forEach(function(key) {
                      var el = document.querySelector('.pw-req[data-rule="' + key + '"]');
                      if (el) el.classList.toggle('valid', rules[key]);
                    });
                    return Object.keys(rules).every(function(key) { return rules[key]; });
                  }

                  var newPasswordField = document.getElementById('new_password');
                  var confirmPasswordField = document.getElementById('confirm_password');
                  var passwordRequirementsBox = document.getElementById('passwordRequirements');
                  var passwordMismatchNote = document.getElementById('passwordMismatch');

                  newPasswordField.addEventListener('input', function() {
                    if (this.value.length > 0) {
                      passwordRequirementsBox.style.display = 'block';
                      checkPasswordRequirements(this.value);
                    } else {
                      passwordRequirementsBox.style.display = 'none';
                    }
                  });

                  document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
                    var newPassword = newPasswordField.value;
                    var confirmPassword = confirmPasswordField.value;

                    if (!newPassword) {
                      e.preventDefault();
                      alert('Please enter a new password.');
                      return;
                    }

                    if (!checkPasswordRequirements(newPassword)) {
                      e.preventDefault();
                      passwordRequirementsBox.style.display = 'block';
                      alert('Password does not meet all requirements. Please check the checklist below the password field.');
                      return;
                    }

                    if (newPassword !== confirmPassword) {
                      e.preventDefault();
                      passwordMismatchNote.style.display = 'block';
                      return;
                    }
                    passwordMismatchNote.style.display = 'none';
                  });
                </script>
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
       