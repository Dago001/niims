<?php
require_once '../includes/auth.php';
require_once '../includes/permissions.php';

// Only super admin can access this page
if (!isLoggedIn() || !isSuperAdmin()) {
    header('Location: ../dashboard.php');
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$error = $success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_role'])) {
        $user_id = (int)$_POST['user_id'];
        $new_role = (int)$_POST['role_id'];
        
        try {
            $stmt = $pdo->prepare("UPDATE users SET role_id = ? WHERE id = ?");
            $stmt->execute([$new_role, $user_id]);
            $success = "User role updated successfully";
        } catch (PDOException $e) {
            $error = "Error updating user role: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_user'])) {
        $user_id = (int)$_POST['user_id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $success = "User deleted successfully";
        } catch (PDOException $e) {
            $error = "Error deleting user: " . $e->getMessage();
        }
    } elseif (isset($_POST['create_user'])) {
        $username = trim($_POST['username']);
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $role_id = (int)$_POST['role_id'];
        $zone_id = isset($_POST['zone_id']) ? (int)$_POST['zone_id'] : null;
        $command_id = isset($_POST['command_id']) ? (int)$_POST['command_id'] : null;
        
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, full_name, email, password, role_id, zone_id, command_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, $full_name, $email, $hashed_password, $role_id, $zone_id, $command_id]);
            $success = "User created successfully";
        } catch (PDOException $e) {
            $error = "Error creating user: " . $e->getMessage();
        }
    } elseif (isset($_POST['create_posting'])) {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $zone_id = (int)$_POST['zone_id'];
        $command_id = isset($_POST['command_id']) ? (int)$_POST['command_id'] : null;
        $formation_id = isset($_POST['formation_id']) ? (int)$_POST['formation_id'] : null;
        $user_id = (int)$_POST['user_id'];
        
        try {
            $stmt = $pdo->prepare("INSERT INTO postings (title, description, zone_id, command_id, formation_id, created_by, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$title, $description, $zone_id, $command_id, $formation_id, $user_id]);
            $success = "Posting created successfully";
        } catch (PDOException $e) {
            $error = "Error creating posting: " . $e->getMessage();
        }
    } elseif (isset($_POST['confirm_posting'])) {
        $posting_id = (int)$_POST['posting_id'];
        
        try {
            $stmt = $pdo->prepare("UPDATE postings SET status = 'confirmed', confirmed_at = NOW() WHERE id = ?");
            $stmt->execute([$posting_id]);
            $success = "Posting confirmed successfully";
        } catch (PDOException $e) {
            $error = "Error confirming posting: " . $e->getMessage();
        }
    }
}

// Get all users with their roles
$users = [];
try {
    $stmt = $pdo->query("
        SELECT u.*, r.name as role_name, z.name as zone_name, c.name as command_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        LEFT JOIN zones z ON u.zone_id = z.id
        LEFT JOIN commands c ON u.command_id = c.id
        ORDER BY u.username
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching users: " . $e->getMessage();
}

// Get all roles
$roles = [];
try {
    $stmt = $pdo->query("SELECT * FROM roles ORDER BY name");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching roles: " . $e->getMessage();
}

// Get all zones
$zones = [];
try {
    $stmt = $pdo->query("SELECT * FROM zones ORDER BY name");
    $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching zones: " . $e->getMessage();
}

// Get all commands
$commands = [];
try {
    $stmt = $pdo->query("SELECT * FROM commands ORDER BY name");
    $commands = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching commands: " . $e->getMessage();
}

// Get all formations
$formations = [];
try {
    $stmt = $pdo->query("SELECT * FROM formations ORDER BY name");
    $formations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching formations: " . $e->getMessage();
}

// Get all postings
$postings = [];
try {
    $stmt = $pdo->query("
        SELECT p.*, u.username as created_by_name, z.name as zone_name, 
               c.name as command_name, f.name as formation_name
        FROM postings p
        LEFT JOIN users u ON p.created_by = u.id
        LEFT JOIN zones z ON p.zone_id = z.id
        LEFT JOIN commands c ON p.command_id = c.id
        LEFT JOIN formations f ON p.formation_id = f.id
        ORDER BY p.created_at DESC
    ");
    $postings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching postings: " . $e->getMessage();
}

include '../includes/header.php';
?>

<div class="container mt-4">
    <h2>Super Administrator Dashboard</h2>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <!-- User Management Section -->
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">User Management</h3>
        </div>
        <div class="card-body">
            <!-- Create User Form -->
            <div class="mb-4">
                <h5>Create New User</h5>
                <form method="post" class="row g-3">
                    <div class="col-md-3">
                        <input type="text" name="username" class="form-control" placeholder="Username" required>
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="full_name" class="form-control" placeholder="Full Name" required>
                    </div>
                    <div class="col-md-3">
                        <input type="email" name="email" class="form-control" placeholder="Email" required>
                    </div>
                    <div class="col-md-3">
                        <input type="password" name="password" class="form-control" placeholder="Password" required>
                    </div>
                    <div class="col-md-3">
                        <select name="role_id" class="form-control" required>
                            <option value="">Select Role</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="zone_id" class="form-control">
                            <option value="">Select Zone (Optional)</option>
                            <?php foreach ($zones as $zone): ?>
                                <option value="<?php echo $zone['id']; ?>"><?php echo htmlspecialchars($zone['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="command_id" class="form-control">
                            <option value="">Select Command (Optional)</option>
                            <?php foreach ($commands as $command): ?>
                                <option value="<?php echo $command['id']; ?>"><?php echo htmlspecialchars($command['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" name="create_user" class="btn btn-success">Create User</button>
                    </div>
                </form>
            </div>

            <!-- Users Table -->
            <table class="table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Current Role</th>
                        <th>Zone/Command</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['role_name']); ?></td>
                        <td>
                            <?php 
                                if ($user['zone_name']) echo "Zone: " . htmlspecialchars($user['zone_name']);
                                if ($user['command_name']) echo " | Command: " . htmlspecialchars($user['command_name']);
                            ?>
                        </td>
                        <td>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <select name="role_id" class="form-control form-control-sm d-inline-block w-auto">
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo $role['id']; ?>" <?php echo $user['role_id'] == $role['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($role['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="update_role" class="btn btn-primary btn-sm">Update Role</button>
                            </form>
                            
                            <?php if ($user['role_name'] !== 'superadmin'): ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" name="delete_user" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Posting Management Section -->
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">Posting Management</h3>
        </div>
        <div class="card-body">
            <!-- Create Posting Form -->
            <div class="mb-4">
                <h5>Create New Posting</h5>
                <form method="post">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <input type="text" name="title" class="form-control" placeholder="Posting Title" required>
                        </div>
                        <div class="col-md-6">
                            <select name="user_id" class="form-control" required>
                                <option value="">Select User</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['role_name']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <textarea name="description" class="form-control" placeholder="Posting Description" rows="3" required></textarea>
                        </div>
                        <div class="col-md-4">
                            <select name="zone_id" class="form-control" required>
                                <option value="">Select Zone</option>
                                <?php foreach ($zones as $zone): ?>
                                    <option value="<?php echo $zone['id']; ?>"><?php echo htmlspecialchars($zone['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <select name="command_id" class="form-control">
                                <option value="">Select Command (Optional)</option>
                                <?php foreach ($commands as $command): ?>
                                    <option value="<?php echo $command['id']; ?>"><?php echo htmlspecialchars($command['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <select name="formation_id" class="form-control">
                                <option value="">Select Formation (Optional)</option>
                                <?php foreach ($formations as $formation): ?>
                                    <option value="<?php echo $formation['id']; ?>"><?php echo htmlspecialchars($formation['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <button type="submit" name="create_posting" class="btn btn-success">Create Posting</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Postings Table -->
            <h5>All Postings</h5>
            <table class="table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Zone/Command/Formation</th>
                        <th>Created By</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($postings as $posting): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($posting['title']); ?></td>
                        <td><?php echo htmlspecialchars($posting['description']); ?></td>
                        <td>
                            <?php 
                                echo htmlspecialchars($posting['zone_name']);
                                if ($posting['command_name']) echo " → " . htmlspecialchars($posting['command_name']);
                                if ($posting['formation_name']) echo " → " . htmlspecialchars($posting['formation_name']);
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($posting['created_by_name']); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $posting['status'] == 'confirmed' ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst($posting['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($posting['status'] == 'pending'): ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="posting_id" value="<?php echo $posting['id']; ?>">
                                    <button type="submit" name="confirm_posting" class="btn btn-success btn-sm">Confirm</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">Confirmed on <?php echo date('M j, Y', strtotime($posting['confirmed_at'])); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>