<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isLoggedIn()) {
    header('Location: dashboard');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $username = sanitizeInput($_POST['username']);
        $password = sanitizeInput($_POST['password']);
        
        if (loginUser($username, $password)) {
            header('Location: dashboard');
            exit();
        } else {
            $error = "Invalid username or password";
        }
    } catch (Exception $e) {
        $error = "System Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NIS Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
    :root {
        --primary-green: #2E7D32;
        --secondary-green: #43A047;
        --light-green: #81C784;
    }
    body {
        margin: 0;
        padding: 0;
        min-height: 100vh;
        background: linear-gradient(rgba(46, 125, 50, 0.85), rgba(67, 160, 71, 0.9)), url('assets/images/tech_building.jpg');
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'Segoe UI', Arial, sans-serif;
        padding: 20px;
        position: relative; /* For footer positioning */
    }
    .login-container {
        background: rgba(255, 255, 255, 0.98);
        border-radius: 10px;
        padding: 2rem;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
        width: 100%;
        max-width: 400px;
        margin: 0 auto;
        z-index: 10; /* Ensure it's above footer */
    }
    .logo-container {
        text-align: center;
        margin-bottom: 2rem;
    }
    .logo-container img {
        max-width: 150px;
        height: auto;
        margin-bottom: 1rem;
    }
    .form-control {
        border-radius: 5px;
        padding: 10px;
        margin-bottom: 1rem;
        font-size: 16px;
    }
    .btn-login {
        background-color: var(--primary-green);
        border: none;
        padding: 12px;
        font-weight: bold;
        width: 100%;
        margin-top: 1rem;
        transition: background-color 0.3s ease;
    }
    .btn-login:hover {
        background-color: var(--secondary-green);
    }
    .error-message {
        background-color: rgba(220, 53, 69, 0.9);
        color: white;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 1rem;
        text-align: center;
    }
    .password-container {
        position: relative;
    }
    .password-toggle {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #666;
        transition: color 0.3s ease;
    }
    .password-toggle:hover {
        color: var(--primary-green);
    }

    /* Footer styling */
    .footer {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        text-align: center;
        padding: 10px 0;
        background: transparent; /* Remove white background */
        color: rgba(255, 255, 255, 0.8); /* Semi-transparent white text */
        font-size: 0.9rem;
        z-index: 5;
    }
    .footer a {
        color: rgba(255, 255, 255, 0.9);
        text-decoration: none;
        transition: color 0.3s ease;
    }
    .footer a:hover {
        color: white;
        text-decoration: underline;
    }

    /* Mobile-specific adjustments */
    @media (max-width: 768px) {
        body {
            padding: 15px;
            align-items: center;
        }
        .login-container {
            padding: 1.5rem;
            margin: 0 auto;
            max-width: 90%;
        }
        .logo-container img {
            max-width: 120px;
        }
        .footer {
            font-size: 0.8rem;
            padding: 8px 0;
        }
    }

    @media (max-width: 480px) {
        body {
            padding: 10px;
        }
        .login-container {
            padding: 1.2rem;
            max-width: 95%;
        }
        .logo-container img {
            max-width: 100px;
        }
        .footer {
            font-size: 0.75rem;
            padding: 5px 0;
        }
    }

    /* Prevent horizontal scrolling on mobile */
    html, body {
        max-width: 100%;
        overflow-x: hidden;
    }
</style>
</head>
<body>
    <div class="login-container">
        <div class="logo-container">
            <img src="assets/images/logo.png" alt="NIS Logo">
        </div>
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
            </div>
            <div class="mb-3 password-container">
                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                <i class="bi bi-eye-slash password-toggle" id="togglePassword"></i>
            </div>
            <button type="submit" class="btn btn-primary btn-login">Sign In</button>
        </form>
    </div>

    <!-- Footer placed directly in the body for proper positioning -->
    <div class="footer">
        <p>All Rights Reserved by Nigeria Immigration Service. Designed and Developed by
           <a href="https://www.immigration.gov.ng">NIS Web Team</a>.</p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this;
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            }
        });
    </script>
</body>
</html>