<?php
// reset-password.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if session is already started, if not start it
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

// Validate token
$valid_token = validateResetToken($token);
if (!$valid_token && !isset($_POST['new_password'])) {
    $error = "Invalid or expired reset token. Please request a new password reset link.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($new_password) || empty($confirm_password)) {
            $error = "Please fill in all fields";
        } elseif ($new_password !== $confirm_password) {
            $error = "Passwords do not match";
        } elseif (strlen($new_password) < 8) {
            $error = "Password must be at least 8 characters long";
        } else {
            if (resetUserPassword($token, $new_password)) {
                $success = "Password has been reset successfully. You can now <a href='login' style='color: #2E7D32; font-weight: bold; text-decoration: none;'>login</a> with your new password.";
            } else {
                $error = "Failed to reset password. Please try again.";
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Nigeria Immigration Service</title>
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
    }
    .reset-container {
        background: rgba(255, 255, 255, 0.98);
        border-radius: 10px;
        padding: 2rem;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
        width: 100%;
        max-width: 450px;
    }
    .logo-container {
        text-align: center;
        margin-bottom: 1.5rem;
    }
    .logo-container img {
        max-width: 120px;
        height: auto;
        margin-bottom: 1rem;
    }
    .error-message {
        background-color: rgba(220, 53, 69, 0.9);
        color: white;
        padding: 12px;
        border-radius: 5px;
        margin-bottom: 1rem;
        text-align: center;
        border-left: 4px solid #dc3545;
    }
    .success-message {
        background-color: rgba(40, 167, 69, 0.9);
        color: white;
        padding: 12px;
        border-radius: 5px;
        margin-bottom: 1rem;
        text-align: center;
        border-left: 4px solid #28a745;
    }
    .success-message a {
        color: white;
        text-decoration: underline;
        font-weight: bold;
    }
    .success-message a:hover {
        color: #e8f5e9;
    }
    .password-requirements {
        font-size: 0.85rem;
        color: #666;
        margin-top: -10px;
        margin-bottom: 15px;
        text-align: center;
    }
    .btn-reset {
        background-color: var(--primary-green);
        border: none;
        padding: 12px;
        font-weight: bold;
        width: 100%;
        margin-top: 1rem;
        transition: background-color 0.3s ease;
    }
    .btn-reset:hover {
        background-color: var(--secondary-green);
    }
    .form-control {
        border-radius: 5px;
        padding: 10px;
        margin-bottom: 1rem;
        font-size: 16px;
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
        z-index: 2;
    }
    .password-toggle:hover {
        color: var(--primary-green);
    }
    .back-link {
        color: var(--primary-green);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        transition: color 0.3s ease;
    }
    .back-link:hover {
        color: var(--secondary-green);
        text-decoration: underline;
    }

    /* Footer styling */
    .footer {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        text-align: center;
        padding: 10px 0;
        background: transparent;
        color: rgba(255, 255, 255, 0.8);
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

    /* Mobile responsiveness */
    @media (max-width: 768px) {
        body {
            padding: 15px;
        }
        .reset-container {
            padding: 1.5rem;
            max-width: 90%;
        }
        .logo-container img {
            max-width: 100px;
        }
    }

    @media (max-width: 480px) {
        body {
            padding: 10px;
        }
        .reset-container {
            padding: 1.2rem;
            max-width: 95%;
        }
        .logo-container img {
            max-width: 80px;
        }
    }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="logo-container">
            <img src="assets/images/logo.png" alt="NIS Logo">
            <h4 class="text-center mb-3">Set New Password</h4>
            <?php if ($valid_token && !$success): ?>
                <p class="text-muted text-center">Please enter your new password below.</p>
            <?php endif; ?>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (!$success && $valid_token): ?>
            <form method="POST" id="resetForm">
                <div class="mb-3 password-container">
                    <label for="new_password" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" 
                           required minlength="8" placeholder="Enter new password (min. 8 characters)">
                    <i class="bi bi-eye-slash password-toggle" id="toggleNewPassword"></i>
                </div>
                <div class="mb-3 password-container">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                           required minlength="8" placeholder="Confirm new password">
                    <i class="bi bi-eye-slash password-toggle" id="toggleConfirmPassword"></i>
                </div>
                <div class="password-requirements">
                    <small>✓ Password must be at least 8 characters long</small>
                </div>
                <button type="submit" class="btn btn-primary btn-reset">
                    <i class="bi bi-key-fill"></i> Reset Password
                </button>
            </form>
        <?php endif; ?>
        
        <div class="text-center mt-4">
            <a href="login" class="back-link">
                <i class="bi bi-arrow-left"></i> Back to Login
            </a>
        </div>
        
        <?php if (!$valid_token && !$success && $error): ?>
            <div class="text-center mt-3">
                <p class="text-muted">Need a new reset link? 
                    <a href="login?action=forgot_password" class="back-link">Request another</a>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>All Rights Reserved by Nigeria Immigration Service. Designed and Developed by
           <a href="https://www.immigration.gov.ng">NIS Web Team</a>.</p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility for new password field
        document.getElementById('toggleNewPassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('new_password');
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

        // Toggle password visibility for confirm password field
        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('confirm_password');
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

        // Form validation
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            const password = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Clear previous errors
            document.querySelectorAll('.is-invalid').forEach(el => {
                el.classList.remove('is-invalid');
            });
            
            let isValid = true;
            
            if (password.length < 8) {
                document.getElementById('new_password').classList.add('is-invalid');
                isValid = false;
            }
            
            if (password !== confirmPassword) {
                document.getElementById('confirm_password').classList.add('is-invalid');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                
                if (password.length < 8) {
                    alert('Password must be at least 8 characters long!');
                } else if (password !== confirmPassword) {
                    alert('Passwords do not match!');
                }
            }
        });

        // Real-time password matching indicator
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            } else {
                this.classList.remove('is-valid', 'is-invalid');
            }
        });

        // Add Bootstrap validation styles
        const style = document.createElement('style');
        style.textContent = `
            .is-valid {
                border-color: #28a745 !important;
                background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2328a745' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
                background-repeat: no-repeat;
                background-position: right calc(0.375em + 0.1875rem) center;
                background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
            }
            .is-invalid {
                border-color: #dc3545 !important;
                background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 3.6.4.4.4-.4'/%3e%3cpath d='M6 7v1'/%3e%3c/svg%3e");
                background-repeat: no-repeat;
                background-position: right calc(0.375em + 0.1875rem) center;
                background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>