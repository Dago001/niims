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

// Validate and sanitize ID parameter
$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '';

if (empty($id)) {
    $_SESSION['profile_update_error'] = true;
    $_SESSION['profile_update_message'] = "Invalid user ID.";
    header("Location: edit-user");
    exit();
}

$sqluse = "SELECT user_id, firstname, lastname, email_address, user_role, password, photo
           FROM tbl_users
           WHERE user_id = '$id'";
    
$retrieve = mysqli_query($conn, $sqluse);
$numb = mysqli_num_rows($retrieve); 

if ($numb == 0) {
    $_SESSION['profile_update_error'] = true;
    $_SESSION['profile_update_message'] = "User not found.";
    header("Location: edit-user");
    exit();
}

if ($numb > 0) {
    $foundk = mysqli_fetch_array($retrieve);
    $user_id = $foundk['user_id'];
    $lastname = $foundk['lastname'];
    $firstname = $foundk['firstname'];
    $email_address = $foundk['email_address'];
    $user_role = $foundk['user_role'];
    $password = $foundk['password'];
    $photo = isset($foundk['photo']) ? $foundk['photo'] : '';
}
  
include 'menu-footer-buttons.php';
include 'top-navigation.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - <?php echo htmlspecialchars($firstname . ' ' . $lastname); ?></title>
    <!-- SweetAlert CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- SweetAlert JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        .role-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .role-superadmin { background: #fff3cd; color: #856404; border: 1px solid #ffc107; }
        .role-admin { background: #d1ecf1; color: #0c5460; border: 1px solid #3498db; }
        .role-supervisor { background: #d4edda; color: #155724; border: 1px solid #27ae60; }
        .role-dataentry { background: #e2e3e5; color: #383d41; border: 1px solid #95a5a6; }
        .role-report { background: #e8daef; color: #6c3483; border: 1px solid #9b59b6; }
        .role-cgi { background: #fce4ec; color: #880e4f; border: 1px solid #e91e63; }
        .role-posting { background: #e0f2f1; color: #004d40; border: 1px solid #009688; }
        .role-info-box { padding: 12px 15px; border-radius: 6px; margin-top: 10px; font-size: 13px; display: none; }
        .role-info-box.show { display: block; }
        .superadmin-warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px 15px; border-radius: 6px; margin-bottom: 20px; font-size: 13px; color: #856404; }
        .superadmin-warning i { font-size: 18px; margin-right: 8px; }
    </style>
</head>
<body>
<div class="right_col" role="main">
    <div class="">
        <div class="page-title">
            <div class="title_left">
                <h3>Edit User Profile</h3>
            </div>
            <div class="title_right">
                <div class="col-md-5 col-sm-5 col-xs-12 form-group pull-right top_search">
                    <div class="input-group"></div>
                </div>
            </div>
        </div>
        
        <div class="clearfix"></div>
        
        <div class="row">
            <div class="col-md-8 col-sm-12">
                <div class="card">
                    <form class="form-horizontal" enctype="multipart/form-data" action="updates" method="POST" id="updateForm">
                        <div class="card-body">
                            <h4 class="card-title">
                                <i class="fa fa-user-edit"></i> Update User Profile
                                <?php if ($user_role == 'SuperAdmin'): ?>
                                    <span class="role-badge role-superadmin"><i class="fa fa-shield"></i> Super Admin</span>
                                <?php elseif ($user_role == 'Admin'): ?>
                                    <span class="role-badge role-admin"><i class="fa fa-user-shield"></i> Admin</span>
                                <?php elseif ($user_role == 'Supervisor'): ?>
                                    <span class="role-badge role-supervisor"><i class="fa fa-user-check"></i> Supervisor</span>
                                <?php elseif ($user_role == 'DataEntry'): ?>
                                    <span class="role-badge role-dataentry"><i class="fa fa-keyboard"></i> Data Entry</span>
                                <?php elseif ($user_role == 'Report'): ?>
                                    <span class="role-badge role-report"><i class="fa fa-chart-bar"></i> Report Viewer</span>
                                <?php elseif ($user_role == 'CGI'): ?>
                                    <span class="role-badge role-cgi"><i class="fa fa-user-tie"></i> CGI</span>
                                <?php elseif ($user_role == 'Posting'): ?>
                                    <span class="role-badge role-posting"><i class="fa fa-map-marker-alt"></i> Posting</span>
                                <?php endif; ?>
                            </h4>
                            
                            <?php if ($user_role == 'SuperAdmin'): ?>
                            <div class="superadmin-warning">
                                <i class="fa fa-shield-alt"></i>
                                <strong>Super Admin Account:</strong> You are editing a Super Admin account. Please exercise caution when modifying this user's details.
                            </div>
                            <?php endif; ?>
                            
                            <div class="form-group row">
                                <label for="serviceNo" class="col-sm-3 text-end control-label col-form-label">Service No.</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control" id="serviceNo" name="serviceNo"
                                           value="<?php echo htmlspecialchars($user_id); ?>" readonly />
                                </div>                      
                            </div>
                            
                            <div class="form-group row">
                                <label for="fname" class="col-sm-3 text-end control-label col-form-label">First Name *</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control" name="fname" id="fname" 
                                           value="<?php echo htmlspecialchars($firstname); ?>" required />
                                </div>
                            </div>
                            
                            <div class="form-group row">
                                <label for="lname" class="col-sm-3 text-end control-label col-form-label">Last Name *</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control" name="lname" id="lname" 
                                           value="<?php echo htmlspecialchars($lastname); ?>" required />
                                </div>
                            </div>
                            
                            <div class="form-group row">
                                <label for="userRole" class="col-sm-3 text-end control-label col-form-label">User Role *</label>
                                <div class="col-sm-6">
                                    <select name="userRole" class="form-select" id="userRole" required onchange="showRoleInfo()">
                                        <option value="">Select Role</option>
                                        <option value="SuperAdmin" <?php echo ($user_role == 'SuperAdmin') ? 'selected' : ''; ?>>Super Admin</option>
                                        <option value="Admin" <?php echo ($user_role == 'Admin') ? 'selected' : ''; ?>>Administrator</option>
                                        <option value="Supervisor" <?php echo ($user_role == 'Supervisor') ? 'selected' : ''; ?>>Supervisor</option>
                                        <option value="DataEntry" <?php echo ($user_role == 'DataEntry') ? 'selected' : ''; ?>>Data Entry</option>
                                        <option value="Report" <?php echo ($user_role == 'Report') ? 'selected' : ''; ?>>Report Viewer</option>
                                        <option value="CGI" <?php echo ($user_role == 'CGI') ? 'selected' : ''; ?>>CGI</option>
                                        <option value="Posting" <?php echo ($user_role == 'Posting') ? 'selected' : ''; ?>>Posting</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group row" id="roleInfoRow" style="display: none;">
                                <div class="col-sm-9 offset-sm-3">
                                    <div id="roleInfoBox" class="role-info-box"></div>
                                </div>
                            </div>
                            
                            <div class="form-group row">
                                <label for="email" class="col-sm-3 text-end control-label col-form-label">Email (Optional)</label>
                                <div class="col-sm-9">
                                    <input type="email" class="form-control" name="email" id="email"
                                           value="<?php echo htmlspecialchars($email_address); ?>" />
                                </div>
                            </div>
                            
                            <div class="form-group row">
                                <label for="password" class="col-sm-3 text-end control-label col-form-label">Password (Optional)</label>
                                <div class="col-sm-9">
                                    <input type="password" class="form-control" name="password" id="password" 
                                           placeholder="Enter new password (leave blank to keep current)" />
                                </div>
                            </div>
                            
                            <?php if (!empty($photo)): ?>
                            <div class="form-group row">
                                <label class="col-sm-3 text-end control-label col-form-label">Current Photo</label>
                                <div class="col-sm-9">
                                    <img src="<?php echo htmlspecialchars($photo); ?>" alt="Current Photo" 
                                         style="max-width: 150px; max-height: 150px; border: 1px solid #ddd; padding: 3px;" 
                                         class="img-thumbnail">
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="form-group row">
                                <label for="photo" class="col-sm-3 text-end control-label col-form-label">New Photo (Optional)</label>
                                <div class="col-sm-6">
                                    <input name="photo" class="form-control" id="photo" type="file" 
                                           accept=".jpg,.jpeg,.png" />
                                    <small class="text-muted">JPG or PNG only (Max 5MB)</small>
                                </div>
                            </div>
                            
                            <div class="border-top">
                                <div class="card-body">
                                    <button type="submit" name="updateProfile" class="btn btn-success" id="updateBtn">
                                        <i class="fa fa-save"></i> Update Profile
                                    </button>
                                    <a href="edit-user" class="btn btn-secondary">
                                        <i class="fa fa-arrow-left"></i> Back to Users List
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showRoleInfo() {
    const roleSelect = document.getElementById('userRole');
    const roleInfoRow = document.getElementById('roleInfoRow');
    const roleInfoBox = document.getElementById('roleInfoBox');
    const selectedRole = roleSelect.value;
    
    const roleInfo = {
        'SuperAdmin': { bg: '#fff3cd', border: '#ffc107', text: '#856404', icon: 'fa-shield-alt', title: 'Super Admin', description: 'Full system access with all privileges including user management, system settings, audit logs, and database operations.' },
        'Admin': { bg: '#d1ecf1', border: '#3498db', text: '#0c5460', icon: 'fa-user-shield', title: 'Administrator', description: 'Administrative access with enrolment, card management, user creation, and reporting capabilities.' },
        'Supervisor': { bg: '#d4edda', border: '#27ae60', text: '#155724', icon: 'fa-user-check', title: 'Supervisor', description: 'Supervisory access with enrolment review, card printing, and report viewing permissions.' },
        'DataEntry': { bg: '#e2e3e5', border: '#95a5a6', text: '#383d41', icon: 'fa-keyboard', title: 'Data Entry', description: 'Limited access for data entry operations including enrolment and biometrics capture.' },
        'Report': { bg: '#e8daef', border: '#9b59b6', text: '#6c3483', icon: 'fa-chart-bar', title: 'Report Viewer', description: 'Read-only access to view reports, nominal rolls, and card production statistics.' },
        'CGI': { bg: '#fce4ec', border: '#e91e63', text: '#880e4f', icon: 'fa-user-tie', title: 'CGI', description: 'Comptroller General of Immigration access level with high-level oversight capabilities.' },
        'Posting': { bg: '#e0f2f1', border: '#009688', text: '#004d40', icon: 'fa-map-marker-alt', title: 'Posting', description: 'Access to manage personnel posting orders and deployment records.' }
    };
    
    if (selectedRole && roleInfo[selectedRole]) {
        const info = roleInfo[selectedRole];
        roleInfoBox.innerHTML = '<div style="background: ' + info.bg + '; border-left: 4px solid ' + info.border + '; padding: 12px 15px; border-radius: 6px;"><strong style="color: ' + info.text + ';"><i class="fa ' + info.icon + '"></i> ' + info.title + '</strong><p style="margin: 5px 0 0; color: ' + info.text + '; opacity: 0.85;">' + info.description + '</p></div>';
        roleInfoBox.classList.add('show');
        roleInfoRow.style.display = 'block';
    } else {
        roleInfoBox.classList.remove('show');
        roleInfoRow.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    showRoleInfo();
    
    <?php if (isset($_SESSION['profile_update_error'])): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: '<?php echo addslashes($_SESSION['profile_update_message']); ?>',
            confirmButtonColor: '#d33',
            confirmButtonText: 'OK'
        }).then(() => {
            <?php unset($_SESSION['profile_update_error']); unset($_SESSION['profile_update_message']); ?>
        });
    <?php endif; ?>
});
</script>

<?php include 'footer.php'; ?>
</body>
</html>