<?php 
session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index");
    die();
}

// Check if user has permission to create users
$allowed_roles = ['Admin', 'SuperAdmin', 'Supervisor'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    die("Access Denied. You are not authorized to access this area.");
}

require_once ('db/db.php');

// Include appropriate sidebar based on role
if ($_SESSION['role'] == "SuperAdmin") {
    include 'sidebar-superadmin.php';
} elseif ($_SESSION['role'] == "Admin") {
    include 'sidebar-admin.php';
} elseif ($_SESSION['role'] == "Supervisor") {
    include 'sidebar-supervisor.php';
}


include 'menu-footer-buttons.php';
include 'top-navigation.php';

// Which tab is active — "manage" is used when arriving from an action
// (reset password, reset 2FA, activate/deactivate, profile edit) that
// used to land on the standalone edit-user page.
$activeTab = (isset($_GET['tab']) && $_GET['tab'] === 'manage') ? 'manage' : 'create';

// ======= MANAGE USERS: activate / deactivate =======
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_id']) && isset($_POST['action'])) {
    $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);
    $status = ($_POST['action'] == 'activate') ? 1 : 0;

    if (isset($_POST['current_page'])) {
        $_SESSION['datatable_page'] = $_POST['current_page'];
    }

    $update_sql = "UPDATE tbl_users SET is_active = '$status' WHERE user_id = '$user_id'";
    if (mysqli_query($conn, $update_sql)) {
        $_SESSION['status_success'] = true;
        $_SESSION['status_message'] = "User status updated successfully!";
    } else {
        $_SESSION['status_error'] = true;
        $_SESSION['status_message'] = "Failed to update user status.";
    }
    header("Location: createuser?tab=manage");
    exit();
}

// Check for success/error messages
$userCreated = false;
$newUserData = null;
$errorMessage = '';
$warningMessage = '';

if (isset($_SESSION['user_created']) && $_SESSION['user_created'] === true) {
    $userCreated = true;
    $newUserData = $_SESSION['new_user'];
    unset($_SESSION['user_created']);
    unset($_SESSION['new_user']);
}

if (isset($_SESSION['error'])) {
    $errorMessage = $_SESSION['error'];
    unset($_SESSION['error']);
}

if (isset($_SESSION['warning'])) {
    $warningMessage = $_SESSION['warning'];
    unset($_SESSION['warning']);
}

// Get form data for repopulation
$formData = [];
if (isset($_SESSION['form_data'])) {
    $formData = $_SESSION['form_data'];
}

// ======= MANAGE USERS: user list =======
$check_column_sql = "SHOW COLUMNS FROM tbl_users LIKE 'is_active'";
$column_check = mysqli_query($conn, $check_column_sql);
$has_is_active_column = mysqli_num_rows($column_check) > 0;

if ($has_is_active_column) {
    $sqluse = "SELECT *, is_active FROM tbl_users ORDER BY lastname, firstname";
} else {
    $sqluse = "SELECT *, 1 as is_active FROM tbl_users ORDER BY lastname, firstname";
}
$retrieve = mysqli_query($conn, $sqluse);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .swal2-popup { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; border-radius: 12px; }
        .swal2-title { color: #059669; font-weight: 600; font-size: 24px; }
        .swal2-confirm { background-color: #059669 !important; border-color: #047857 !important; }
        .swal2-cancel { background-color: #3b82f6 !important; border-color: #2563eb !important; }
        
        /* Sleek Create User Form Styles */
        .create-user-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            background: #fff;
            margin-top: 15px;
        }
        
        .create-user-card .card-body {
            padding: 24px;
        }
        
        .form-group.row {
            margin-bottom: 20px;
        }
        
        .col-form-label {
            font-weight: 600;
            color: #475569;
            font-size: 13px;
            text-align: right;
            padding-top: 8px;
        }
        
        .form-control, .form-select {
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            padding: 8px 12px;
            font-size: 13px;
            color: #334155;
            transition: all 0.2s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }
        
        .btn-create {
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            padding: 8px 18px;
            background-color: #059669;
            color: #fff;
            border: 1px solid transparent;
            transition: all 0.2s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-create:hover {
            background-color: #047857;
        }
        
        .btn-reset {
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            padding: 8px 18px;
            background-color: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
            transition: all 0.2s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-reset:hover {
            background-color: #e2e8f0;
            color: #334155;
        }
        
        /* Modern Badges for SuperAlert */
        .role-badge {
            font-size: 11px;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 12px;
            text-transform: capitalize;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-left: 5px;
        }
        
        .role-badge-superadmin {
            background-color: #fef3c7;
            color: #d97706;
            border: 1px solid #fde68a;
        }
        
        .role-badge-admin {
            background-color: #dbeafe;
            color: #2563eb;
            border: 1px solid #bfdbfe;
        }
        
        .role-badge-supervisor {
            background-color: #d1fae5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }
        
        .role-badge-dataentry {
            background-color: #f3f4f6;
            color: #4b5563;
            border: 1px solid #e5e7eb;
        }
        
        .role-badge-report {
            background-color: #e0f2fe;
            color: #0284c7;
            border: 1px solid #bae6fd;
        }

        /* Password strength checklist */
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

        /* Tabs */
        .nav-tabs > li.active > a,
        .nav-tabs > li.active > a:hover,
        .nav-tabs > li.active > a:focus {
            color: #059669;
            font-weight: 600;
            border-bottom-color: transparent;
        }

        /* Manage Users styles (merged from the former edit-user page) */
        .user-table-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            background: #fff;
            margin-top: 15px;
        }

        .user-table-card .card-body {
            padding: 20px;
        }

        .badge-modern {
            font-size: 11px;
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 20px;
            text-transform: capitalize;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .badge-superadmin {
            background-color: #fef3c7;
            color: #d97706;
            border: 1px solid #fde68a;
        }

        .badge-admin {
            background-color: #dbeafe;
            color: #2563eb;
            border: 1px solid #bfdbfe;
        }

        .badge-supervisor {
            background-color: #d1fae5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }

        .badge-dataentry {
            background-color: #f3f4f6;
            color: #4b5563;
            border: 1px solid #e5e7eb;
        }

        .badge-report {
            background-color: #e0f2fe;
            color: #0284c7;
            border: 1px solid #bae6fd;
        }

        .badge-active {
            background-color: #ecfdf5;
            color: #10b981;
            border: 1px solid #a7f3d0;
        }

        .badge-inactive {
            background-color: #fef2f2;
            color: #ef4444;
            border: 1px solid #fecaca;
        }

        #zero_config {
            border: none !important;
        }

        #zero_config th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0 !important;
            border-top: none !important;
            border-left: none !important;
            border-right: none !important;
            padding: 12px 16px;
        }

        #zero_config td {
            padding: 12px 16px;
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0;
            border-top: none !important;
            border-left: none !important;
            border-right: none !important;
            font-size: 13px;
            color: #334155;
        }

        #zero_config tbody tr:hover {
            background-color: #f8fafc;
        }

        .btn-action-group {
            display: inline-flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .btn-action {
            border-radius: 6px;
            font-weight: 600;
            font-size: 12px;
            padding: 6px 12px;
            transition: all 0.2s ease;
            border: 1px solid transparent;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
        }

        .btn-action-deactivate {
            background-color: #fff1f2;
            color: #e11d48;
            border-color: #ffe4e6;
        }

        .btn-action-deactivate:hover {
            background-color: #ffe4e6;
            color: #be123c;
        }

        .btn-action-activate {
            background-color: #ecfdf5;
            color: #059669;
            border-color: #d1fae5;
        }

        .btn-action-activate:hover {
            background-color: #d1fae5;
            color: #047857;
        }

        .btn-action-reset {
            background-color: #f0fdfa;
            color: #0d9488;
            border-color: #ccfbf1;
        }

        .btn-action-reset:hover {
            background-color: #ccfbf1;
            color: #0f766e;
        }

        .btn-action-edit {
            background-color: #eff6ff;
            color: #2563eb;
            border-color: #dbeafe;
        }

        .btn-action-edit:hover {
            background-color: #dbeafe;
            color: #1d4ed8;
        }

        .btn-action-2fa {
            background-color: #f5f3ff;
            color: #7c3aed;
            border-color: #ede9fe;
        }

        .btn-action-2fa:hover {
            background-color: #ede9fe;
            color: #6d28d9;
        }

        .current-user-tag {
            background-color: #f8fafc;
            color: #64748b;
            border: 1px dashed #cbd5e1;
            border-radius: 6px;
            padding: 5px 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
    </style>
</head>
<body>
  <div class="right_col" role="main">
    <div class="">
      <div class="page-title">
        <div class="title_left">
          <h3>User Management</h3>
        </div>
      </div>
      <div class="clearfix"></div>

      <!-- Display messages -->
      <?php if (!empty($errorMessage)): ?>
      <div class="alert alert-danger" style="border-radius: 6px;">
        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($errorMessage); ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($warningMessage)): ?>
      <div class="alert alert-warning" style="border-radius: 6px;">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($warningMessage); ?>
      </div>
      <?php endif; ?>

      <ul class="nav nav-tabs" style="margin-bottom: 15px;">
        <li class="<?php echo $activeTab === 'create' ? 'active' : ''; ?>">
          <a href="#tab-create" data-toggle="tab"><i class="fas fa-user-plus"></i> Create User</a>
        </li>
        <li class="<?php echo $activeTab === 'manage' ? 'active' : ''; ?>">
          <a href="#tab-manage" data-toggle="tab"><i class="fas fa-users-cog"></i> Manage Users</a>
        </li>
      </ul>

      <div class="tab-content">
      <div class="tab-pane<?php echo $activeTab === 'create' ? ' active' : ''; ?>" id="tab-create">
      <div class="row">
        <div class="col-md-6">
          <div class="create-user-card">
            <form class="form-horizontal" enctype="multipart/form-data" action="user" method="POST" id="userForm">
              <input type="hidden" name="regUser" value="1">
              <div class="card-body">
                <h4 class="card-title" style="font-weight: 600; color: #334155; margin-bottom: 20px;"><i class="fas fa-user-plus" style="color: #059669;"></i> Create New User</h4>
                
                <!-- Service No -->
                <div class="form-group row">
                  <label class="col-sm-3 text-end control-label col-form-label">Service No. *</label>
                  <div class="col-sm-9">
                    <input type="text" class="form-control" name="serviceNo" placeholder="NIS Service No." 
                           value="<?php echo isset($formData['serviceNo']) ? htmlspecialchars($formData['serviceNo']) : ''; ?>" required />
                  </div>                      
                </div>
                
                <!-- First Name -->
                <div class="form-group row">
                  <label class="col-sm-3 text-end control-label col-form-label">First Name *</label>
                  <div class="col-sm-9">
                    <input type="text" class="form-control" name="fname" placeholder="First Name" 
                           value="<?php echo isset($formData['fname']) ? htmlspecialchars($formData['fname']) : ''; ?>" required />
                  </div>
                </div>
                
                <!-- Last Name -->
                <div class="form-group row">
                  <label class="col-sm-3 text-end control-label col-form-label">Last Name *</label>
                  <div class="col-sm-9">
                    <input type="text" class="form-control" name="lname" placeholder="Last Name" 
                           value="<?php echo isset($formData['lname']) ? htmlspecialchars($formData['lname']) : ''; ?>" required />
                  </div>
                </div>
                
                <!-- User Role -->
                <div class="form-group row">
                  <label class="col-sm-3 text-end control-label col-form-label">User Role *</label>
                  <div class="col-sm-6">
                    <select name="userRole" class="form-select" id="userRole" required onchange="showRoleInfo()">
                      <option value="">-- Select --</option>
                      <option value="SuperAdmin" <?php echo isset($formData['userRole']) && $formData['userRole'] == 'SuperAdmin' ? 'selected' : ''; ?>>Super Admin</option>
                      <option value="Admin" <?php echo isset($formData['userRole']) && $formData['userRole'] == 'Admin' ? 'selected' : ''; ?>>Admin</option>
                      <option value="Supervisor" <?php echo isset($formData['userRole']) && $formData['userRole'] == 'Supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                      <option value="DataEntry" <?php echo isset($formData['userRole']) && $formData['userRole'] == 'DataEntry' ? 'selected' : ''; ?>>Data Entry</option>
                      <option value="Report" <?php echo isset($formData['userRole']) && $formData['userRole'] == 'Report' ? 'selected' : ''; ?>>Report Viewer</option>
                    </select>
                  </div>
                </div>
                
                <!-- Role Info Box -->
                <div class="form-group row" id="roleInfoBox" style="display: none;">
                  <div class="col-sm-9 offset-sm-3">
                    <div id="roleInfoContent" style="font-size: 13px;"></div>
                  </div>
                </div>
                
                <!-- Email -->
                <div class="form-group row">
                  <label class="col-sm-3 text-end control-label col-form-label">Email</label>
                  <div class="col-sm-9">
                    <input type="email" class="form-control" name="email" placeholder="Email (Optional)" 
                           value="<?php echo isset($formData['email']) ? htmlspecialchars($formData['email']) : ''; ?>" />
                  </div>
                </div>
                
                <!-- Password -->
                <div class="form-group row">
                  <label class="col-sm-3 text-end control-label col-form-label">Password</label>
                  <div class="col-sm-9">
                    <div class="input-group">
                      <input type="password" class="form-control" name="password" id="password" placeholder="Leave empty for auto-generation"
                             value="<?php echo isset($formData['password']) ? htmlspecialchars($formData['password']) : ''; ?>" />
                      <span class="input-group-btn">
                        <button class="btn btn-outline-secondary" type="button" id="generatePassword" title="Generate a strong password">
                          <i class="fas fa-key"></i> Generate
                        </button>
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                          <i class="fas fa-eye"></i>
                        </button>
                      </span>
                    </div>
                    <small class="text-muted">Leave empty to auto-generate a password, or click Generate for a strong one now</small>
                    <div class="password-requirements" id="passwordRequirements" style="display:none;">
                      <div class="pw-req" data-rule="length"><span class="pw-req-icon"></span> At least 8 characters</div>
                      <div class="pw-req" data-rule="upper"><span class="pw-req-icon"></span> One uppercase letter</div>
                      <div class="pw-req" data-rule="lower"><span class="pw-req-icon"></span> One lowercase letter</div>
                      <div class="pw-req" data-rule="number"><span class="pw-req-icon"></span> One number</div>
                      <div class="pw-req" data-rule="special"><span class="pw-req-icon"></span> One special character</div>
                    </div>
                  </div>
                </div>

                <!-- Photo -->
                <div class="form-group row">
                  <label class="col-sm-3 text-end control-label col-form-label">Photo</label>
                  <div class="col-sm-6">
                    <input name="photo" class="form-control" type="file" accept=".jpg,.jpeg,.png" />
                    <small class="text-muted">Optional - Max 5MB, JPG/PNG</small>
                  </div>
                </div>
              </div>
              
              <div class="border-top" style="border-color: #f1f5f9; padding: 15px 24px; background-color: #f8fafc;">
                <button type="submit" name="regUser" class="btn-create">
                  <i class="fas fa-user-plus"></i> Create User
                </button>
                <button type="reset" class="btn-reset" style="margin-left: 8px;">Reset</button>
              </div>
            </form>
          </div>
        </div>
      </div>
      </div><!-- /#tab-create -->

      <div class="tab-pane<?php echo $activeTab === 'manage' ? ' active' : ''; ?>" id="tab-manage">

      <!-- Stats Cards Section -->
      <div class="row" style="margin-bottom: 20px;">
          <!-- Metric 1: Total Users -->
          <div class="col-md-3 col-sm-6 col-xs-12">
              <div class="tile-stats" style="background:#fff; padding:15px; border-radius:8px; border:1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 0; position: relative;">
                  <div class="icon" style="color: #4f46e5; position: absolute; right: 20px; top: 15px;"><i class="fa fa-users" style="font-size: 24px;"></i></div>
                  <div class="count" style="font-size: 28px; font-weight: 700; color: #1e293b;"><?php echo mysqli_num_rows($retrieve); ?></div>
                  <h3 style="font-size: 12px; font-weight: 600; color: #64748b; margin-top: 5px; margin-bottom: 0; text-transform: uppercase; letter-spacing: 0.5px;">Total Users</h3>
              </div>
          </div>
          <!-- Metric 2: Active Users -->
          <div class="col-md-3 col-sm-6 col-xs-12">
              <div class="tile-stats" style="background:#fff; padding:15px; border-radius:8px; border:1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 0; position: relative;">
                  <div class="icon" style="color: #10b981; position: absolute; right: 20px; top: 15px;"><i class="fa fa-user-check" style="font-size: 24px;"></i></div>
                  <div class="count" style="font-size: 28px; font-weight: 700; color: #1e293b;">
                      <?php
                          mysqli_data_seek($retrieve, 0);
                          $active_count = 0;
                          while ($r = mysqli_fetch_assoc($retrieve)) {
                              if (($r['is_active'] ?? 1) == 1) $active_count++;
                          }
                          echo $active_count;
                      ?>
                  </div>
                  <h3 style="font-size: 12px; font-weight: 600; color: #64748b; margin-top: 5px; margin-bottom: 0; text-transform: uppercase; letter-spacing: 0.5px;">Active Users</h3>
              </div>
          </div>
          <!-- Metric 3: Inactive Users -->
          <div class="col-md-3 col-sm-6 col-xs-12">
              <div class="tile-stats" style="background:#fff; padding:15px; border-radius:8px; border:1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 0; position: relative;">
                  <div class="icon" style="color: #ef4444; position: absolute; right: 20px; top: 15px;"><i class="fa fa-user-times" style="font-size: 24px;"></i></div>
                  <div class="count" style="font-size: 28px; font-weight: 700; color: #1e293b;">
                      <?php
                          mysqli_data_seek($retrieve, 0);
                          $inactive_count = 0;
                          while ($r = mysqli_fetch_assoc($retrieve)) {
                              if (($r['is_active'] ?? 1) == 0) $inactive_count++;
                          }
                          echo $inactive_count;
                      ?>
                  </div>
                  <h3 style="font-size: 12px; font-weight: 600; color: #64748b; margin-top: 5px; margin-bottom: 0; text-transform: uppercase; letter-spacing: 0.5px;">Deactivated</h3>
              </div>
          </div>
          <!-- Metric 4: Admins -->
          <div class="col-md-3 col-sm-6 col-xs-12">
              <div class="tile-stats" style="background:#fff; padding:15px; border-radius:8px; border:1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 0; position: relative;">
                  <div class="icon" style="color: #f59e0b; position: absolute; right: 20px; top: 15px;"><i class="fa fa-shield" style="font-size: 24px;"></i></div>
                  <div class="count" style="font-size: 28px; font-weight: 700; color: #1e293b;">
                      <?php
                          mysqli_data_seek($retrieve, 0);
                          $admin_count = 0;
                          while ($r = mysqli_fetch_assoc($retrieve)) {
                              $role = $r['user_role'] ?? '';
                              if ($role === 'Admin' || $role === 'SuperAdmin') $admin_count++;
                          }
                          echo $admin_count;
                      ?>
                  </div>
                  <h3 style="font-size: 12px; font-weight: 600; color: #64748b; margin-top: 5px; margin-bottom: 0; text-transform: uppercase; letter-spacing: 0.5px;">System Admins</h3>
              </div>
          </div>
      </div>

      <div class="row">
          <div class="col-md-12">
              <div class="user-table-card">
                <div class="card-body">
                  <div class="table-responsive">
                    <table id="zero_config" class="table">
                      <thead>
                        <tr>
                          <th>Service No.</th>
                          <th>Surname</th>
                          <th>First Name</th>
                          <th>Email</th>
                          <th>User Role</th>
                          <th>Status</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                        mysqli_data_seek($retrieve, 0);
                        while ($ret = mysqli_fetch_assoc($retrieve)):
                          $is_active = isset($ret['is_active']) ? $ret['is_active'] : 1;
                          $userRole = $ret['user_role'] ?? '';
                        ?>
                        <tr>
                          <td><?php echo htmlspecialchars($ret['user_id']); ?></td>
                          <td><?php echo htmlspecialchars($ret['lastname']); ?></td>
                          <td><?php echo htmlspecialchars($ret['firstname']); ?></td>
                          <td><?php echo htmlspecialchars($ret['email_address']); ?></td>
                          <td>
                            <?php
                              $roleClass = '';
                              $roleIcon = '';
                              switch($userRole) {
                                  case 'SuperAdmin':
                                      $roleClass = 'badge-superadmin';
                                      $roleIcon = '<i class="fa fa-shield"></i> ';
                                      break;
                                  case 'Admin':
                                      $roleClass = 'badge-admin';
                                      $roleIcon = '<i class="fa fa-user-shield"></i> ';
                                      break;
                                  case 'Supervisor':
                                      $roleClass = 'badge-supervisor';
                                      $roleIcon = '<i class="fa fa-user-check"></i> ';
                                      break;
                                  case 'DataEntry':
                                      $roleClass = 'badge-dataentry';
                                      $roleIcon = '<i class="fa fa-keyboard-o"></i> ';
                                      break;
                                  case 'Report':
                                      $roleClass = 'badge-report';
                                      $roleIcon = '<i class="fa fa-bar-chart"></i> ';
                                      break;
                                  default:
                                      $roleClass = 'badge-dataentry';
                                      $roleIcon = '';
                              }
                            ?>
                            <span class="badge-modern <?php echo $roleClass; ?>">
                              <?php echo $roleIcon . htmlspecialchars($userRole); ?>
                            </span>
                          </td>
                          <td>
                            <?php
                              if ($is_active == 1) {
                                echo '<span class="badge-modern badge-active"><span style="width: 6px; height: 6px; background-color: #10b981; border-radius: 50%; display: inline-block; margin-right: 4px;"></span>Active</span>';
                              } else {
                                echo '<span class="badge-modern badge-inactive"><span style="width: 6px; height: 6px; background-color: #ef4444; border-radius: 50%; display: inline-block; margin-right: 4px;"></span>Inactive</span>';
                              }
                            ?>
                          </td>
                          <td>
                            <?php
                            $is_current_user = false;
                            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $ret['user_id']) {
                                $is_current_user = true;
                            }
                            if (isset($_SESSION['email']) && $_SESSION['email'] == $ret['email_address']) {
                                $is_current_user = true;
                            }
                            ?>

                            <?php if ($is_current_user): ?>
                              <span class="current-user-tag"><i class="fa fa-user"></i> Current User</span>
                            <?php else: ?>
                              <div class="btn-action-group">
                                <!-- Activation/Deactivation Forms -->
                                <form method="POST" action="createuser?tab=manage" class="d-inline" id="statusForm<?php echo $ret['user_id']; ?>">
                                  <input type="hidden" name="user_id" value="<?php echo $ret['user_id']; ?>">
                                  <input type="hidden" name="current_page" id="currentPage<?php echo $ret['user_id']; ?>" value="">
                                  <?php if ($is_active == 1): ?>
                                    <input type="hidden" name="action" value="deactivate">
                                    <button type="submit" class="btn-action btn-action-deactivate"
                                            onclick="return confirmStatusChange('deactivate', '<?php echo htmlspecialchars($ret['firstname'] . ' ' . $ret['lastname']); ?>', <?php echo $ret['user_id']; ?>)">
                                      <i class="fa fa-ban"></i> Deactivate
                                    </button>
                                  <?php else: ?>
                                    <input type="hidden" name="action" value="activate">
                                    <button type="submit" class="btn-action btn-action-activate"
                                            onclick="return confirmStatusChange('activate', '<?php echo htmlspecialchars($ret['firstname'] . ' ' . $ret['lastname']); ?>', <?php echo $ret['user_id']; ?>)">
                                      <i class="fa fa-check"></i> Activate
                                    </button>
                                  <?php endif; ?>
                                </form>

                                <!-- Reset Password Form -->
                                <form method="POST" action="reset-password" class="d-inline" id="resetForm<?php echo $ret['user_id']; ?>">
                                  <input type="hidden" name="user_id" value="<?php echo $ret['user_id']; ?>">
                                  <input type="hidden" name="current_page" id="resetCurrentPage<?php echo $ret['user_id']; ?>" value="">
                                  <button type="submit" class="btn-action btn-action-reset"
                                          onclick="return confirmResetPassword('<?php echo htmlspecialchars(addslashes($ret['firstname'] . ' ' . $ret['lastname'])); ?>', '<?php echo htmlspecialchars($userRole); ?>', <?php echo $ret['user_id']; ?>)">
                                    <i class="fa fa-refresh"></i> Reset
                                  </button>
                                </form>

                                <!-- Reset Google Authenticator (2FA) Form -->
                                <form method="POST" action="reset-2fa" class="d-inline" id="reset2faForm<?php echo $ret['user_id']; ?>">
                                  <input type="hidden" name="user_id" value="<?php echo $ret['user_id']; ?>">
                                  <input type="hidden" name="current_page" id="reset2faCurrentPage<?php echo $ret['user_id']; ?>" value="">
                                  <button type="submit" class="btn-action btn-action-2fa"
                                          title="Reset Google Authenticator — use this if the user lost their phone"
                                          onclick="return confirmReset2FA('<?php echo htmlspecialchars(addslashes($ret['firstname'] . ' ' . $ret['lastname'])); ?>', <?php echo $ret['user_id']; ?>, <?php echo (!empty($ret['google_auth_enabled']) ? 'true' : 'false'); ?>)">
                                    <i class="fa fa-qrcode"></i> Reset 2FA
                                  </button>
                                </form>

                                <!-- Edit Button -->
                                <button class="btn-action btn-action-edit" type="button" onclick="editUser(<?php echo $ret['user_id']; ?>)">
                                  <i class="fa fa-pencil"></i> Edit
                                </button>
                              </div>
                            <?php endif; ?>
                          </td>
                        </tr>
                        <?php endwhile; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
          </div>
      </div>

      </div><!-- /#tab-manage -->
      </div><!-- /.tab-content -->
    </div>
  </div>

  <?php include 'footer_tables.php'; ?>

  <!-- SweetAlert2 JS -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

  <script>
    // Role information display
    function showRoleInfo() {
      const roleSelect = document.getElementById('userRole');
      const roleInfoBox = document.getElementById('roleInfoBox');
      const roleInfoContent = document.getElementById('roleInfoContent');
      const selectedRole = roleSelect.value;
      
      const roleInfo = {
        'SuperAdmin': {
          bg: '#fff3cd',
          border: '#ffc107',
          text: '#856404',
          icon: 'fa-shield-alt',
          title: 'Super Admin',
          description: 'Full system access with all privileges including user management, system settings, audit logs, and database operations.'
        },
        'Admin': {
          bg: '#d1ecf1',
          border: '#3498db',
          text: '#0c5460',
          icon: 'fa-user-shield',
          title: 'Admin',
          description: 'Administrative access with enrolment, card management, user creation, and reporting capabilities.'
        },
        'Supervisor': {
          bg: '#d4edda',
          border: '#27ae60',
          text: '#155724',
          icon: 'fa-user-check',
          title: 'Supervisor',
          description: 'Supervisory access with enrolment review, card printing, and report viewing permissions.'
        },
        'DataEntry': {
          bg: '#e2e3e5',
          border: '#95a5a6',
          text: '#383d41',
          icon: 'fa-keyboard',
          title: 'Data Entry',
          description: 'Limited access for data entry operations including enrolment and biometrics capture.'
        },
        'Report': {
          bg: '#e8daef',
          border: '#9b59b6',
          text: '#6c3483',
          icon: 'fa-chart-bar',
          title: 'Report Viewer',
          description: 'Read-only access to view reports, nominal rolls, and card production statistics.'
        }
      };
      
      if (selectedRole && roleInfo[selectedRole]) {
        const info = roleInfo[selectedRole];
        roleInfoContent.innerHTML = `
          <div style="background: ${info.bg}; border-left: 4px solid ${info.border}; padding: 12px 15px; border-radius: 6px;">
            <strong style="color: ${info.text};">
              <i class="fas ${info.icon}"></i> ${info.title}
            </strong>
            <p style="margin: 5px 0 0; color: ${info.text}; opacity: 0.85;">
              ${info.description}
            </p>
          </div>
        `;
        roleInfoBox.style.display = 'block';
      } else {
        roleInfoBox.style.display = 'none';
      }
    }
    
    // Show role info on page load if a role is pre-selected
    document.addEventListener('DOMContentLoaded', function() {
      showRoleInfo();
    });
    
    // Toggle password visibility
    document.getElementById('togglePassword').addEventListener('click', function() {
      const passwordInput = document.getElementById('password');
      const icon = this.querySelector('i');
      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
      } else {
        passwordInput.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
      }
    });

    // Client-side password generator — mirrors the guarantee made by
    // functions/password_helper.php's generateCompliantPassword(): one of
    // each required character class, rest random, whole thing shuffled.
    function generateStrongPassword(length = 12) {
      const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ'; // no I/O — avoids look-alikes
      const lower = 'abcdefghijkmnpqrstuvwxyz'; // no l
      const digits = '23456789'; // no 0/1
      const special = '!@#$%^&*-_=+';
      const all = upper + lower + digits + special;

      const randomChar = (chars) => chars[crypto.getRandomValues(new Uint32Array(1))[0] % chars.length];

      const chars = [randomChar(upper), randomChar(lower), randomChar(digits), randomChar(special)];
      while (chars.length < length) chars.push(randomChar(all));

      // Fisher-Yates shuffle so the guaranteed classes aren't always first
      for (let i = chars.length - 1; i > 0; i--) {
        const j = crypto.getRandomValues(new Uint32Array(1))[0] % (i + 1);
        [chars[i], chars[j]] = [chars[j], chars[i]];
      }
      return chars.join('');
    }

    document.getElementById('generatePassword').addEventListener('click', function() {
      const passwordInput = document.getElementById('password');
      const newPassword = generateStrongPassword();
      passwordInput.value = newPassword;
      passwordInput.type = 'text';
      const toggleIcon = document.querySelector('#togglePassword i');
      toggleIcon.classList.replace('fa-eye', 'fa-eye-slash');
      passwordInput.dispatchEvent(new Event('input'));
    });

    // Password requirements checklist — shared shape across every page that
    // sets a password; must match functions/password_helper.php exactly.
    function checkPasswordRequirements(password) {
      const rules = {
        length: password.length >= 8,
        upper: /[A-Z]/.test(password),
        lower: /[a-z]/.test(password),
        number: /[0-9]/.test(password),
        special: /[^A-Za-z0-9]/.test(password)
      };
      Object.keys(rules).forEach(function(key) {
        const el = document.querySelector('.pw-req[data-rule="' + key + '"]');
        if (el) el.classList.toggle('valid', rules[key]);
      });
      return Object.keys(rules).every(function(key) { return rules[key]; });
    }

    const passwordField = document.getElementById('password');
    const passwordRequirementsBox = document.getElementById('passwordRequirements');
    passwordField.addEventListener('input', function() {
      if (this.value.length > 0) {
        passwordRequirementsBox.style.display = 'block';
        checkPasswordRequirements(this.value);
      } else {
        passwordRequirementsBox.style.display = 'none';
      }
    });

    // Form validation
    document.getElementById('userForm').addEventListener('submit', function(e) {
      const requiredFields = ['serviceNo', 'fname', 'lname', 'userRole'];
      const missing = [];

      requiredFields.forEach(field => {
        const element = document.querySelector(`[name="${field}"]`);
        if (!element.value.trim()) missing.push(field);
      });

      if (missing.length > 0) {
        e.preventDefault();
        Swal.fire({
          icon: 'warning',
          title: 'Missing Information',
          html: 'Please fill all required fields.',
          confirmButtonText: 'OK'
        });
        return;
      }

      // If a password was typed manually, it must meet the policy (an
      // empty field is fine — the backend auto-generates a compliant one).
      const typedPassword = document.getElementById('password').value;
      if (typedPassword.length > 0 && !checkPasswordRequirements(typedPassword)) {
        e.preventDefault();
        passwordRequirementsBox.style.display = 'block';
        Swal.fire({
          icon: 'warning',
          title: 'Weak Password',
          html: 'The password does not meet all requirements. Please check the checklist below the password field.',
          confirmButtonText: 'OK'
        });
        return;
      }

      // Super Admin confirmation
      const selectedRole = document.getElementById('userRole').value;
      if (selectedRole === 'SuperAdmin') {
        e.preventDefault();
        Swal.fire({
          title: 'Create Super Admin?',
          html: `
            <div style="text-align: left;">
              <p><i class="fas fa-shield-alt text-warning"></i> You are about to create a <strong>Super Admin</strong> account.</p>
              <p style="color: #e74c3c;"><strong>Warning:</strong> This role has full system access including:</p>
              <ul style="text-align: left;">
                <li>User Management</li>
                <li>System Settings</li>
                <li>Database Operations</li>
                <li>Audit Logs Access</li>
              </ul>
              <p>Are you sure you want to proceed?</p>
            </div>
          `,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: '<i class="fas fa-check"></i> Yes, Create Super Admin',
          cancelButtonText: '<i class="fas fa-times"></i> Cancel',
          confirmButtonColor: '#ffc107',
          cancelButtonColor: '#6c757d',
          reverseButtons: true
        }).then((result) => {
          if (result.isConfirmed) {
            document.getElementById('userForm').submit();
          }
        });
      }
    });
    
    // Success popup
    <?php if ($userCreated && $newUserData): ?>
    document.addEventListener('DOMContentLoaded', function() {
      const role = '<?php echo addslashes($newUserData['role']); ?>';
      const roleBadge = role === 'SuperAdmin' ?
        '<span class="role-badge role-badge-superadmin"><i class="fas fa-shield-alt"></i> Super Admin</span>' :
        role === 'Admin' ?
        '<span class="role-badge role-badge-admin"><i class="fas fa-user-shield"></i> Admin</span>' :
        role === 'Supervisor' ?
        '<span class="role-badge role-badge-supervisor"><i class="fas fa-user-check"></i> Supervisor</span>' :
        role === 'DataEntry' ?
        '<span class="role-badge role-badge-dataentry"><i class="fas fa-keyboard"></i> Data Entry</span>' :
        '<span class="role-badge role-badge-report"><i class="fas fa-chart-bar"></i> Report Viewer</span>';
      
      Swal.fire({
        title: 'User Created Successfully!',
        html: `
          <div style="text-align: left;">
            <p><strong>Service No:</strong> <?php echo htmlspecialchars($newUserData['service_no']); ?></p>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($newUserData['full_name']); ?></p>
            <p><strong>Role:</strong> ${roleBadge}</p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($newUserData['email']); ?></p>
            <?php if ($newUserData['password'] != 'User-provided'): ?>
            <p><strong>Generated Password:</strong> <code style="background: #f8f9fa; padding: 3px 8px; border-radius: 4px; font-size: 16px;"><?php echo htmlspecialchars($newUserData['password']); ?></code></p>
            <p class="text-warning"><small><i class="fas fa-exclamation-triangle"></i> Save this password! It won't be shown again.</small></p>
            <?php endif; ?>
            <p class="text-info"><small><i class="fas fa-info-circle"></i> This user will be required to set a new password the first time they log in.</small></p>
          </div>
        `,
        icon: 'success',
        confirmButtonText: 'OK'
      }).then(() => {
        document.getElementById('userForm').reset();
        document.getElementById('roleInfoBox').style.display = 'none';
      });
    });
    <?php endif; ?>

    // ========== MANAGE USERS TAB (merged from the former edit-user page) ==========
    let dataTable;

    function editUser(id) {
        window.location.href = 'edit?id=' + id;
    }

    function confirmStatusChange(action, userName, userId) {
        if (dataTable) {
            var currentPage = dataTable.page();
            document.getElementById('currentPage' + userId).value = currentPage;
        }

        var title = action === 'activate' ? 'Activate User?' : 'Deactivate User?';
        var message = action === 'activate'
            ? 'Are you sure you want to activate <strong>' + userName + '</strong>? They will regain access to the platform.'
            : 'Are you sure you want to deactivate <strong>' + userName + '</strong>? They will not be able to access the platform.';
        var icon = action === 'activate' ? 'question' : 'warning';
        var confirmColor = action === 'activate' ? '#27ae60' : '#e74c3c';

        Swal.fire({
            title: title,
            html: message,
            icon: icon,
            showCancelButton: true,
            confirmButtonColor: confirmColor,
            cancelButtonColor: '#6c757d',
            confirmButtonText: action === 'activate' ? 'Yes, Activate' : 'Yes, Deactivate',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('statusForm' + userId).submit();
            }
        });

        return false;
    }

    function confirmResetPassword(userName, userRole, userId) {
        if (dataTable) {
            var currentPage = dataTable.page();
            document.getElementById('resetCurrentPage' + userId).value = currentPage;
        }

        var extraWarning = '';
        if (userRole === 'SuperAdmin') {
            extraWarning = '<div class="alert alert-warning mt-2" style="font-size: 12px;">' +
                '<i class="fa fa-shield"></i> <strong>Super Admin Account:</strong> Please ensure this action is authorized.' +
                '</div>';
        }

        Swal.fire({
            title: 'Reset Password?',
            html: 'Are you sure you want to reset password for <strong>' + userName + '</strong>?<br><br>' +
                  '<strong>Role:</strong> <span class="badge badge-' +
                  (userRole === 'SuperAdmin' ? 'warning' :
                   userRole === 'Admin' ? 'primary' :
                   userRole === 'Supervisor' ? 'success' : 'secondary') + '">' + userRole + '</span><br><br>' +
                  'A new random password will be generated and shown here after you confirm.<br><br>' +
                  'The user will be required to change it the next time they log in.' +
                  extraWarning,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0e7a2e',
            cancelButtonColor: '#d33',
            confirmButtonText: '<i class="fa fa-check"></i> Yes, reset password',
            cancelButtonText: '<i class="fa fa-times"></i> Cancel',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('resetForm' + userId).submit();
            }
        });

        return false;
    }

    function confirmReset2FA(userName, userId, is2faEnabled) {
        if (dataTable) {
            var currentPage = dataTable.page();
            document.getElementById('reset2faCurrentPage' + userId).value = currentPage;
        }

        var message = is2faEnabled
            ? 'This will clear the existing Google Authenticator setup for <strong>' + userName + '</strong>. ' +
              'The next time they log in, they will be shown a brand new QR code to scan with their authenticator app.<br><br>' +
              '<div class="alert alert-info" style="font-size: 12px; text-align: left;">' +
              '<i class="fa fa-info-circle"></i> Use this when a user has lost the phone their authenticator was set up on.' +
              '</div>'
            : '<strong>' + userName + '</strong> has not set up Google Authenticator yet, so there is nothing to reset. ' +
              'They will already be prompted to scan a QR code the next time they log in.';

        Swal.fire({
            title: 'Reset Google Authenticator?',
            html: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#7c3aed',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fa fa-qrcode"></i> Yes, reset 2FA',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('reset2faForm' + userId).submit();
            }
        });

        return false;
    }

    // Session-driven Swal messages for the Manage Users actions
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($_SESSION['reset_success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                html: '<?php echo addslashes($_SESSION['reset_message']); ?>',
                showConfirmButton: true,
                timer: 5000,
                timerProgressBar: true,
                customClass: { popup: 'animated bounceIn' }
            });
            <?php unset($_SESSION['reset_success']); unset($_SESSION['reset_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['reset_error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: '<?php echo addslashes($_SESSION['reset_message']); ?>',
                showConfirmButton: true,
                timer: 5000,
                timerProgressBar: true
            });
            <?php unset($_SESSION['reset_error']); unset($_SESSION['reset_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['status_success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: '<?php echo addslashes($_SESSION['status_message']); ?>',
                showConfirmButton: true,
                timer: 3000,
                timerProgressBar: true
            });
            <?php unset($_SESSION['status_success']); unset($_SESSION['status_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['status_error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: '<?php echo addslashes($_SESSION['status_message']); ?>',
                showConfirmButton: true,
                timer: 3000,
                timerProgressBar: true
            });
            <?php unset($_SESSION['status_error']); unset($_SESSION['status_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['profile_update_success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Profile Updated!',
                html: '<?php echo addslashes($_SESSION['profile_update_message']); ?><br><br>' +
                      '<strong>User ID: <?php echo isset($_SESSION['profile_updated_user_id']) ? $_SESSION['profile_updated_user_id'] : ''; ?></strong>',
                confirmButtonColor: '#0e7a2e',
                confirmButtonText: 'OK',
                timer: 4000,
                timerProgressBar: true
            });
            <?php
                unset($_SESSION['profile_update_success']);
                unset($_SESSION['profile_update_message']);
                unset($_SESSION['profile_updated_user_id']);
            ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['profile_update_error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Update Failed!',
                text: '<?php echo addslashes($_SESSION['profile_update_message']); ?>',
                confirmButtonColor: '#d33',
                confirmButtonText: 'OK'
            });
            <?php
                unset($_SESSION['profile_update_error']);
                unset($_SESSION['profile_update_message']);
            ?>
        <?php endif; ?>
    });

    // Initialize DataTables for the Manage Users tab. The table lives
    // inside a Bootstrap tab-pane that may start hidden (display:none),
    // so column widths are recalculated once the tab is actually shown.
    $(document).ready(function() {
        if ($.fn.DataTable.isDataTable('#zero_config')) {
            $('#zero_config').DataTable().destroy();
        }

        dataTable = $('#zero_config').DataTable({
            "pageLength": 10,
            "order": [[1, "asc"]],
            "responsive": true,
            "autoWidth": false,
            "stateSave": true,
            "stateDuration": 0,
            "stateSaveCallback": function(settings, data) {
                localStorage.setItem('DataTables_' + settings.sInstance, JSON.stringify(data));
            },
            "stateLoadCallback": function(settings) {
                return JSON.parse(localStorage.getItem('DataTables_' + settings.sInstance));
            }
        });

        <?php if (isset($_SESSION['datatable_page'])): ?>
            var savedPage = <?php echo json_encode($_SESSION['datatable_page']); ?>;
            if (dataTable && savedPage !== null && savedPage !== '') {
                setTimeout(function() {
                    dataTable.page(parseInt(savedPage)).draw('page');
                }, 100);
            }
            <?php unset($_SESSION['datatable_page']); ?>
        <?php endif; ?>

        $('form[id^="statusForm"]').on('submit', function(e) {
            if (dataTable) {
                var currentPage = dataTable.page();
                $(this).find('input[name="current_page"]').val(currentPage);
            }
            return true;
        });

        // Fix column widths if the table was initialized while its tab was hidden.
        $('a[href="#tab-manage"]').on('shown.bs.tab', function() {
            if (dataTable) {
                dataTable.columns.adjust();
            }
        });
    });
  </script>
</body>
</html>