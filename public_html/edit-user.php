<?php 
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index");
    die();
}

require_once ('db/db.php');

// Include appropriate sidebar based on role
if ($_SESSION['role'] == "Admin") {
  include 'sidebar-admin.php';
}
if ($_SESSION['role'] == "Supervisor") {
  include 'sidebar-supervisor.php';
}
if ($_SESSION['role'] == "SuperAdmin") {
  include 'sidebar-superadmin.php';
}

include 'menu-footer-buttons.php';
include 'top-navigation.php';

// Handle activation/deactivation if POST request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle activation/deactivation
    if (isset($_POST['user_id']) && isset($_POST['action'])) {
        $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);
        $status = ($_POST['action'] == 'activate') ? 1 : 0;
        
        // Store the current page in session before redirecting
        if (isset($_POST['current_page'])) {
            $_SESSION['datatable_page'] = $_POST['current_page'];
        }
        
        $update_sql = "UPDATE tbl_users SET is_active = '$status' WHERE user_id = '$user_id'";
        if (mysqli_query($conn, $update_sql)) {
            $_SESSION['status_success'] = true;
            $_SESSION['status_message'] = "User status updated successfully!";
            echo "<script>window.location.href = 'edit-user';</script>";
            exit();
        } else {
            $_SESSION['status_error'] = true;
            $_SESSION['status_message'] = "Failed to update user status.";
            echo "<script>window.location.href = 'edit-user';</script>";
            exit();
        }
    }
}

// Check if is_active column exists
$check_column_sql = "SHOW COLUMNS FROM tbl_users LIKE 'is_active'";
$column_check = mysqli_query($conn, $check_column_sql);
$has_is_active_column = mysqli_num_rows($column_check) > 0;

// Query to get users - include is_active if column exists
if ($has_is_active_column) {
    $sqluse = "SELECT *, is_active FROM tbl_users ORDER BY lastname, firstname";
} else {
    $sqluse = "SELECT *, 1 as is_active FROM tbl_users ORDER BY lastname, firstname";
}

$retrieve = mysqli_query($conn, $sqluse);
?>
<style>
    /* Sleek User Management Styles */
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
    
    /* Modern Badges */
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
    
    /* Table Enhancements */
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
    
    /* Sleek Actions Buttons */
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
    
    /* Current user badge */
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

      <div class="right_col" role="main">
        <div class="">
          <div class="page-title">
            <div class="title_left">
              <h3>Application Users</h3>
            </div>
          </div>
          <div class="clearfix"></div>
          
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
          
          <!-- ============================================================== -->
          <!-- Start Page Content -->
          <!-- ============================================================== -->
          <div class="row">              
              <div class="col-md-12">
                  <div class="user-table-card">
                    <div class="card-body">
                      <?php if (!$has_is_active_column): ?>
                        <div class="alert alert-info">
                          <i class="fa fa-info-circle"></i> Note: The 'is_active' column already exists in your database. User status management is now enabled.
                        </div>
                      <?php endif; ?>
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
                                  // Display role with appropriate badge
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
                                    <form method="POST" action="" class="d-inline" id="statusForm<?php echo $ret['user_id']; ?>">
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
        </div>

        <!-- Include SweetAlert2 -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        
        <script>
          let dataTable;
          
          function editUser(id) {
              window.location.href = 'edit?id=' + id;
          }
          
          function confirmStatusChange(action, userName, userId) {
              // Get current page from DataTable
              if (dataTable) {
                  var currentPage = dataTable.page();
                  document.getElementById('currentPage' + userId).value = currentPage;
              }
              
              var title = action === 'activate' 
                  ? 'Activate User?' 
                  : 'Deactivate User?';
              
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
              // Get current page from DataTable
              if (dataTable) {
                  var currentPage = dataTable.page();
                  document.getElementById('resetCurrentPage' + userId).value = currentPage;
              }
              
              // Special warning for SuperAdmin
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
                        'Password will be reset to: <strong>password123</strong><br><br>' +
                        'User will need to login with this default password.' +
                        extraWarning,
                  icon: 'warning',
                  showCancelButton: true,
                  confirmButtonColor: '#3085d6',
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
          
          // Show SweetAlert messages from session
          document.addEventListener('DOMContentLoaded', function() {
              <?php if (isset($_SESSION['reset_success'])): ?>
                  Swal.fire({
                      icon: 'success',
                      title: 'Success!',
                      html: '<?php echo addslashes($_SESSION['reset_message']); ?>',
                      showConfirmButton: true,
                      timer: 5000,
                      timerProgressBar: true,
                      customClass: {
                          popup: 'animated bounceIn'
                      }
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
              
              // ========== PROFILE UPDATE SWEET ALERT ==========
              <?php if (isset($_SESSION['profile_update_success'])): ?>
                  Swal.fire({
                      icon: 'success',
                      title: 'Profile Updated!',
                      html: '<?php echo addslashes($_SESSION['profile_update_message']); ?><br><br>' +
                            '<strong>User ID: <?php echo isset($_SESSION['profile_updated_user_id']) ? $_SESSION['profile_updated_user_id'] : ''; ?></strong>',
                      confirmButtonColor: '#3085d6',
                      confirmButtonText: 'OK',
                      timer: 4000,
                      timerProgressBar: true,
                      showClass: {
                          popup: 'animate__animated animate__fadeInDown'
                      },
                      hideClass: {
                          popup: 'animate__animated animate__fadeOutUp'
                      }
                  }).then((result) => {
                      <?php 
                          unset($_SESSION['profile_update_success']);
                          unset($_SESSION['profile_update_message']);
                          unset($_SESSION['profile_updated_user_id']);
                      ?>
                  });
              <?php endif; ?>
              
              <?php if (isset($_SESSION['profile_update_error'])): ?>
                  Swal.fire({
                      icon: 'error',
                      title: 'Update Failed!',
                      text: '<?php echo addslashes($_SESSION['profile_update_message']); ?>',
                      confirmButtonColor: '#d33',
                      confirmButtonText: 'OK',
                      showClass: {
                          popup: 'animate__animated animate__shakeX'
                      }
                  }).then((result) => {
                      <?php 
                          unset($_SESSION['profile_update_error']);
                          unset($_SESSION['profile_update_message']);
                      ?>
                  });
              <?php endif; ?>
          });
          
          // Initialize DataTables
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
            
            // Restore page from session
            <?php if (isset($_SESSION['datatable_page'])): ?>
                var savedPage = <?php echo json_encode($_SESSION['datatable_page']); ?>;
                if (dataTable && savedPage !== null && savedPage !== '') {
                    setTimeout(function() {
                        dataTable.page(parseInt(savedPage)).draw('page');
                    }, 100);
                }
                <?php unset($_SESSION['datatable_page']); ?>
            <?php endif; ?>
            
            // Handle form submission for status forms
            $('form[id^="statusForm"]').on('submit', function(e) {
                if (dataTable) {
                    var currentPage = dataTable.page();
                    $(this).find('input[name="current_page"]').val(currentPage);
                }
                return true;
            });
          });
        </script>

<?php include 'footer_tables.php'; ?>