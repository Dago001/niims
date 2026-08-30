<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index");
    die();
}

require_once('db/db.php');

// Include appropriate sidebar based on role
if ($_SESSION['role'] == "Admin") {
    include 'sidebar-admin.php';
}
if ($_SESSION['role'] == "SuperAdmin") {
    include 'sidebar-superadmin.php';
}
if ($_SESSION['role'] == "Supervisor") {
    include 'sidebar-supervisor.php';
}
if ($_SESSION['role'] == "DataEntry") {
    include 'sidebar-dataentry.php';
}
if ($_SESSION['role'] == "Biometric") {
    include 'sidebar-biometric.php';
}
if ($_SESSION['role'] == "Report") {
    include 'sidebar-view.php';
}
if ($_SESSION['role'] == "CGI") {
    include 'sidebar-cgi.php';
}

include 'menu-footer-buttons.php';
include 'top-navigation.php';

// Security: the account being viewed/edited is always the CURRENTLY
// LOGGED-IN user, taken only from the session — never from a query string
// or POST field. That is what stops this from being an IDOR that lets one
// account read or modify another (e.g. ?id=<someone else>).
$user_id = $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "SELECT user_id, firstname, lastname, email_address, user_role, photo, google_auth_enabled, is_active, must_change_password FROM tbl_users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "s", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$account = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$account) {
    die("Account not found.");
}

// CSRF token for the update form below
if (empty($_SESSION['profile_csrf_token'])) {
    $_SESSION['profile_csrf_token'] = bin2hex(random_bytes(32));
}

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['profile_csrf_token'], $_POST['csrf_token'])) {
        $errorMessage = "Security token invalid. Please refresh the page and try again.";
    } else {
        // 1. Handle Photo Upload
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['profile_photo']['tmp_name'];
            $fileName = $_FILES['profile_photo']['name'];
            $fileSize = $_FILES['profile_photo']['size'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExtensions = array('jpg', 'jpeg', 'png');

            if (!in_array($fileExtension, $allowedExtensions)) {
                $errorMessage = "Upload Failed: Only JPG, JPEG, and PNG files are allowed.";
            } elseif ($fileSize > 5 * 1024 * 1024) { // 5MB limit
                $errorMessage = "Upload Failed: Photo size must be less than 5MB.";
            } else {
                // SECURITY: Validate actual file MIME type using finfo (not just extension)
                // This prevents PHP scripts being uploaded with a .jpg extension
                $allowedMimes = ['image/jpeg', 'image/png'];
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $detectedMime = $finfo->file($fileTmpPath);
                if (!in_array($detectedMime, $allowedMimes)) {
                    $errorMessage = "Upload Failed: File content does not match an allowed image type.";
                } else {
                $uploadDir = 'upload/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $newFileName = $user_id . '.' . $fileExtension;
                $dest_path = $uploadDir . $newFileName;

                // Delete any old files with different extensions
                foreach ($allowedExtensions as $ext) {
                    $oldFile = $uploadDir . $user_id . '.' . $ext;
                    if (file_exists($oldFile) && $oldFile !== $dest_path) {
                        @unlink($oldFile);
                    }
                }

                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    $update_photo_stmt = mysqli_prepare($conn, "UPDATE tbl_users SET photo = ? WHERE user_id = ?");
                    mysqli_stmt_bind_param($update_photo_stmt, "ss", $dest_path, $user_id);
                    if (mysqli_stmt_execute($update_photo_stmt)) {
                        $successMessage = "Profile photo updated successfully.";
                        $account['photo'] = $dest_path;
                        $_SESSION['photo'] = $dest_path;
                    } else {
                        $errorMessage = "Failed to update profile photo in database.";
                    }
                    mysqli_stmt_close($update_photo_stmt);
                } else {
                    $errorMessage = "Failed to save uploaded file.";
                }
            }
        }
    }

        // 2. Handle Other Profile Details Update
        if (isset($_POST['updateOwnProfile'])) {
            $firstname = trim($_POST['firstname'] ?? '');
            $lastname = trim($_POST['lastname'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if ($firstname === '' || $lastname === '') {
                $errorMessage = "First and last name are required.";
            } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errorMessage = "Please enter a valid email address.";
            } else {
                $firstname = mb_substr($firstname, 0, 50);
                $lastname = mb_substr($lastname, 0, 50);
                $email = mb_substr($email, 0, 100);

                // Note: user_role is deliberately not editable here — changing
                // one's own role would be a self-privilege-escalation path, and
                // stays restricted to the admin-only User Management flow.
                $update_stmt = mysqli_prepare($conn, "UPDATE tbl_users SET firstname = ?, lastname = ?, email_address = ? WHERE user_id = ?");
                mysqli_stmt_bind_param($update_stmt, "ssss", $firstname, $lastname, $email, $user_id);

                if (mysqli_stmt_execute($update_stmt)) {
                    $successMessage = "Profile updated successfully.";
                    $account['firstname'] = $firstname;
                    $account['lastname'] = $lastname;
                    $account['email_address'] = $email;
                    // Keep the session in sync with what's now stored, since
                    // top-navigation.php and the rest of the app display these.
                    $_SESSION['username'] = $lastname;
                    $_SESSION['email'] = $email;
                } else {
                    $errorMessage = "Failed to update profile. Please try again.";
                }
                mysqli_stmt_close($update_stmt);
            }
        }
    }
}

$photoSrc = 'upload/default.png';
if (!empty($account['photo']) && file_exists($account['photo'])) {
    $photoSrc = $account['photo'];
} elseif (file_exists('upload/' . $user_id . '.png')) {
    $photoSrc = 'upload/' . $user_id . '.png';
} elseif (file_exists('images/' . $user_id . '.jpg')) {
    $photoSrc = 'images/' . $user_id . '.jpg';
}
?>
<style>
    .profile-card {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        background: #fff;
        margin-top: 15px;
    }
    .profile-card .card-body { padding: 24px; }
    .profile-photo-wrap { text-align: center; margin-bottom: 20px; }
    .profile-photo-wrap img {
        width: 110px; height: 110px; border-radius: 50%; object-fit: cover;
        border: 3px solid #e2e8f0;
    }
    .profile-readonly-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
    .profile-readonly-row:last-child { border-bottom: none; }
    .profile-readonly-label { color: #64748b; font-weight: 600; }
    .profile-readonly-value { color: #1e293b; font-weight: 600; }
    .badge-yes { color: #059669; }
    .badge-no { color: #dc2626; }
</style>
<div class="right_col" role="main">
  <div class="">
    <div class="page-title">
      <div class="title_left"><h3>My Profile</h3></div>
    </div>
    <div class="clearfix"></div>

    <?php if ($successMessage): ?>
    <div class="alert alert-success" style="border-radius: 6px;"><i class="fa fa-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?></div>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
    <div class="alert alert-danger" style="border-radius: 6px;"><i class="fa fa-exclamation-triangle"></i> <?php echo htmlspecialchars($errorMessage); ?></div>
    <?php endif; ?>

    <div class="row">
      <div class="col-md-4">
        <div class="profile-card">
          <div class="card-body">
            <div class="profile-photo-wrap">
              <img src="<?php echo htmlspecialchars($photoSrc); ?>" alt="Profile Photo" onerror="this.onerror=null; this.src='upload/default.png';" style="margin-bottom:12px;">
              <form method="POST" action="profile" enctype="multipart/form-data" style="margin-top: 10px;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['profile_csrf_token']); ?>">
                <div style="position: relative; overflow: hidden; display: inline-block;">
                  <button class="btn btn-xs btn-primary" type="button" onclick="document.getElementById('profile_photo_input').click();">
                    <i class="fa fa-camera"></i> Change Photo
                  </button>
                  <input type="file" id="profile_photo_input" name="profile_photo" accept="image/png, image/jpeg, image/jpg" style="display: none;" onchange="this.form.submit();">
                </div>
              </form>
            </div>
            <div class="profile-readonly-row">
              <span class="profile-readonly-label">Service No.</span>
              <span class="profile-readonly-value"><?php echo htmlspecialchars($account['user_id']); ?></span>
            </div>
            <div class="profile-readonly-row">
              <span class="profile-readonly-label">Role</span>
              <span class="profile-readonly-value"><?php echo htmlspecialchars($account['user_role']); ?></span>
            </div>
            <div class="profile-readonly-row">
              <span class="profile-readonly-label">Account Status</span>
              <span class="profile-readonly-value <?php echo (!isset($account['is_active']) || $account['is_active'] == 1) ? 'badge-yes' : 'badge-no'; ?>">
                <?php echo (!isset($account['is_active']) || $account['is_active'] == 1) ? 'Active' : 'Inactive'; ?>
              </span>
            </div>
            <div class="profile-readonly-row">
              <span class="profile-readonly-label">Google Authenticator</span>
              <span class="profile-readonly-value <?php echo !empty($account['google_auth_enabled']) ? 'badge-yes' : 'badge-no'; ?>">
                <?php echo !empty($account['google_auth_enabled']) ? 'Enabled' : 'Not Set Up'; ?>
              </span>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-8">
        <div class="profile-card">
          <form method="POST" action="profile">
            <div class="card-body">
              <h4 class="card-title" style="font-weight: 600; color: #334155; margin-bottom: 20px;">
                <i class="fa fa-user" style="color: #0e7a2e;"></i> Account Details
              </h4>

              <div class="form-group row">
                <label class="col-sm-3 text-end control-label col-form-label">First Name *</label>
                <div class="col-sm-9">
                  <input type="text" class="form-control" name="firstname" maxlength="50" required
                         value="<?php echo htmlspecialchars($account['firstname']); ?>" />
                </div>
              </div>

              <div class="form-group row">
                <label class="col-sm-3 text-end control-label col-form-label">Last Name *</label>
                <div class="col-sm-9">
                  <input type="text" class="form-control" name="lastname" maxlength="50" required
                         value="<?php echo htmlspecialchars($account['lastname']); ?>" />
                </div>
              </div>

              <div class="form-group row">
                <label class="col-sm-3 text-end control-label col-form-label">Email</label>
                <div class="col-sm-9">
                  <input type="email" class="form-control" name="email" maxlength="100"
                         value="<?php echo htmlspecialchars($account['email_address']); ?>" />
                </div>
              </div>

              <div class="form-group row">
                <label class="col-sm-3 text-end control-label col-form-label">Password</label>
                <div class="col-sm-9">
                  <a href="changePassword" class="btn btn-default"><i class="fa fa-key"></i> Change Password</a>
                </div>
              </div>
            </div>

            <div class="border-top" style="padding: 15px 24px; background-color: #f8fafc;">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['profile_csrf_token']); ?>">
              <button type="submit" name="updateOwnProfile" class="btn btn-success">
                <i class="fa fa-save"></i> Save Changes
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include 'footer.php'; ?>
