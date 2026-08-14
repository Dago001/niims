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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create User</title>
    
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
    </style>
</head>
<body>
  <div class="right_col" role="main">
    <div class="">
      <div class="page-title">
        <div class="title_left">
          <h3>Create User</h3>
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
      
      <div class="row">
        <div class="col-md-6">
          <div class="create-user-card">
            <form class="form-horizontal" enctype="multipart/form-data" action="user" method="POST" id="userForm">
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
                      <div class="input-group-append">
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword" style="border-top-left-radius: 0; border-bottom-left-radius: 0; height: 100%;">
                          <i class="fas fa-eye"></i>
                        </button>
                      </div>
                    </div>
                    <small class="text-muted">Leave empty to auto-generate a password</small>
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
    </div>
  </div>
  
  <?php include 'footer.php'; ?>
  
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
  </script>
</body>
</html>