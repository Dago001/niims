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
      <div class="right_col" role="main">
        <div class="">
          <div class="page-title">
            <div class="title_left">
              <h3>Application Users</h3>
            </div>
          </div>
          <div class="clearfix"></div>
          
          <!-- ============================================================== -->
          <!-- Start Page Content -->
          <!-- ============================================================== -->
          <div class="row">              
              <div class="card">
                <div class="card-body">
                  <h5 class="card-title"></h5>
                  <?php if (!$has_is_active_column): ?>
                    <div class="alert alert-info">
                      <i class="fa fa-info-circle"></i> Note: The 'is_active' column already exists in your database. User status management is now enabled.
                    </div>
                  <?php endif; ?>
                  <div class="table-responsive">
                    <table id="zero_config" class="table table-striped table-bordered">
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
                              $roleBadgeClass = '';
                              $roleIcon = '';
                              switch($userRole) {
                                  case 'SuperAdmin':
                                      $roleBadgeClass = 'badge-warning';
                                      $roleIcon = '<i class="fa fa-shield"></i> ';
                                      break;
                                  case 'Admin':
                                      $roleBadgeClass = 'badge-primary';
                                      $roleIcon = '<i class="fa fa-user-shield"></i> ';
                                      break;
                                  case 'Supervisor':
                                      $roleBadgeClass = 'badge-success';
                                      $roleIcon = '<i class="fa fa-user-check"></i> ';
                                      break;
                                  case 'DataEntry':
                                      $roleBadgeClass = 'badge-secondary';
                                      $roleIcon = '<i class="fa fa-keyboard"></i> ';
                                      break;
                                  case 'Report':
                                      $roleBadgeClass = 'badge-info';
                                      $roleIcon = '<i class="fa fa-chart-bar"></i> ';
                                      break;
                                  default:
                                      $roleBadgeClass = 'badge-light';
                                      $roleIcon = '';
                              }
                            ?>
                            <span class="badge <?php echo $roleBadgeClass; ?>">
                              <?php echo $roleIcon . htmlspecialchars($userRole); ?>
                            </span>
                          </td>
                          <td>
                            <?php 
                              if ($is_active == 1) {
                                echo '<span class="badge badge-success">Active</span>';
                              } else {
                                echo '<span class="badge badge-danger">Inactive</span>';
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
                              <span class="text-muted"><i class="fa fa-user"></i> Current User</span>
                            <?php else: ?>
                              <div class="btn-group">
                                <!-- Activation/Deactivation Forms -->
                                <form method="POST" action="" class="d-inline" id="statusForm<?php echo $ret['user_id']; ?>">
                                  <input type="hidden" name="user_id" value="<?php echo $ret['user_id']; ?>">
                                  <input type="hidden" name="current_page" id="currentPage<?php echo $ret['user_id']; ?>" value="">
                                  <?php if ($is_active == 1): ?>
                                    <input type="hidden" name="action" value="deactivate">
                                    <button type="submit" class="btn btn-warning btn-sm" 
                                            onclick="return confirmStatusChange('deactivate', '<?php echo htmlspecialchars($ret['firstname'] . ' ' . $ret['lastname']); ?>', <?php echo $ret['user_id']; ?>)">
                                      <i class="fa fa-ban"></i> Deactivate
                                    </button>
                                  <?php else: ?>
                                    <input type="hidden" name="action" value="activate">
                                    <button type="submit" class="btn btn-success btn-sm" 
                                            onclick="return confirmStatusChange('activate', '<?php echo htmlspecialchars($ret['firstname'] . ' ' . $ret['lastname']); ?>', <?php echo $ret['user_id']; ?>)">
                                      <i class="fa fa-check"></i> Activate
                                    </button>
                                  <?php endif; ?>
                                </form>
                                
                                <!-- Reset Password Form -->
                                <form method="POST" action="reset-password" class="d-inline ml-1" id="resetForm<?php echo $ret['user_id']; ?>">
                                  <input type="hidden" name="user_id" value="<?php echo $ret['user_id']; ?>">
                                  <input type="hidden" name="current_page" id="resetCurrentPage<?php echo $ret['user_id']; ?>" value="">
                                  <button type="submit" class="btn btn-info btn-sm" 
                                          onclick="return confirmResetPassword('<?php echo htmlspecialchars(addslashes($ret['firstname'] . ' ' . $ret['lastname'])); ?>', '<?php echo htmlspecialchars($userRole); ?>', <?php echo $ret['user_id']; ?>)">
                                    <i class="fa fa-refresh"></i> Reset Password
                                  </button>
                                </form>
                                
                                <!-- Edit Button -->
                                <button class="btn btn-primary btn-sm ml-1" type="button" onclick="editUser(<?php echo $ret['user_id']; ?>)">
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