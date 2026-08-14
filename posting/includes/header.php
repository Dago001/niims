<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>NIS Personnel Posting Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
    body { 
        padding-top: 0 !important;
        margin: 0 !important;
        min-height: 100vh;
        background-color: #f8f9fa;
    }
    .top-header {
        background: linear-gradient(135deg, #27ae60 0%, #2c3e50 100%);
        padding: 0.5rem 0;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        position: relative;
        z-index: 1000;
    }
    .top-header .container-fluid {
        padding-left: 0.75rem;
        padding-right: 0.75rem;
    }
    .top-header .logo-container img {
        height: 60px;
        width: auto;
        transition: transform 0.3s ease;
    }
    .top-header .logo-container img:hover {
        transform: scale(1.05);
    }
    .system-title {
        color: white;
        font-size: 1.4rem;
        margin: 0;
        font-weight: 600;
        text-align: center;
        flex-grow: 1;
        line-height: 1.2;
        padding: 0 0.5rem;
    }
    .logo-container.me-3 {
        margin-right: 0.5rem !important;
    }
    .d-flex.justify-content-between {
        gap: 0.5rem;
        align-items: center;
        flex-wrap: wrap;
    }
    .header-nav {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        margin-left: auto;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    .header-nav a {
        color: white;
        text-decoration: none;
        padding: 0.5rem 0.75rem;
        border-radius: 4px;
        transition: background-color 0.3s ease;
        font-weight: 500;
        font-size: 0.9rem;
        white-space: nowrap;
        display: flex;
        align-items: center;
    }
    .header-nav a:hover {
        background-color: rgba(255,255,255,0.1);
    }
    .header-nav a.active {
        background-color: rgba(255,255,255,0.2);
    }
    
    /* Mobile-specific improvements */
    @media (max-width: 768px) {
        .top-header {
            padding: 0.4rem 0;
        }
        .top-header .container-fluid {
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }
        .system-title {
            font-size: 1.1rem;
            order: 3;
            flex-basis: 100%;
            margin-top: 0.5rem;
            text-align: center;
        }
        .d-flex.justify-content-between {
            justify-content: space-between;
        }
        .header-nav {
            gap: 0.25rem;
            margin-left: 0;
            order: 2;
        }
        .header-nav a {
            padding: 0.4rem 0.6rem;
            font-size: 0.85rem;
        }
        .header-nav a i {
            margin-right: 0.25rem;
        }
        .logo-container {
            order: 1;
        }
        .top-header .logo-container img {
            height: 50px;
        }
    }
    
    /* Extra small devices */
    @media (max-width: 576px) {
        .system-title {
            font-size: 1rem;
        }
        .header-nav a span {
            display: none;
        }
        .header-nav a i {
            margin-right: 0;
            font-size: 1rem;
        }
        .header-nav a {
            padding: 0.5rem;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            justify-content: center;
        }
        .top-header .logo-container img {
            height: 45px;
        }
    }
    
    /* For very small screens */
    @media (max-width: 400px) {
        .system-title {
            font-size: 0.9rem;
        }
        .header-nav a {
            width: 36px;
            height: 36px;
            padding: 0.4rem;
        }
        .top-header .logo-container img {
            height: 40px;
        }
    }
</style>
</head>
<body>
    <div class="top-header">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <div class="logo-container me-3">
                    <img src="assets/images/logo.png" alt="NIS Logo">
                </div>
                <h1 class="system-title">NIS Personnel Posting Management System</h1>
                <div class="header-nav">
                    <?php if (isLoggedIn()): ?>
                        <a href="dashboard" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                            <i class="fas fa-tachometer-alt me-1"></i>
                            <span>Dashboard</span>
                        </a>
                        <a href="edit_profile" class="<?php echo basename($_SERVER['PHP_SELF']) == 'edit_profile.php' ? 'active' : ''; ?>">
                            <i class="fas fa-user me-1"></i>
                            <span>Profile</span>
                        </a>
                        <a href="logout" class="text-danger">
                            <i class="fas fa-sign-out-alt me-1"></i>
                            <span>Logout</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>