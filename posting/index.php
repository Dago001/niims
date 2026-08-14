<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NIS Personnel Posting Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-green: #2E7D32;
            --secondary-green: #43A047;
            --light-green: #81C784;
        }
        body {
            background: linear-gradient(rgba(46, 125, 50, 0.85), rgba(67, 160, 71, 0.9)), url('assets/images/tech_building.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
            font-family: 'Segoe UI', Arial, sans-serif;
            padding: 15px;
        }
        .landing-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            padding: 2rem 1.5rem;
            margin: 1rem auto;
            max-width: 95%;
        }
        .logo-container img {
            max-height: 120px;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
            transition: transform 0.3s ease;
        }
        .logo-container img:hover {
            transform: scale(1.05);
        }
        .system-title {
            color: var(--primary-green);
            font-weight: 700;
            letter-spacing: 0.5px;
            font-size: 1.8rem;
            line-height: 1.2;
        }
        .system-subtitle {
            color: var(--secondary-green);
            font-size: 1.3rem;
            font-weight: 500;
            margin-bottom: 1rem;
        }
        .description {
            color: #555;
            font-size: 1.1rem;
            margin: 1.5rem 0;
            line-height: 1.5;
        }
        .login-btn {
            background: linear-gradient(45deg, var(--primary-green), var(--secondary-green));
            border: none;
            padding: 0.8rem 2.5rem;
            font-size: 1.1rem;
            border-radius: 50px;
            box-shadow: 0 4px 15px rgba(46, 125, 50, 0.2);
            transition: all 0.3s ease;
            display: inline-block;
            margin: 1rem 0;
        }
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(46, 125, 50, 0.3);
            background: linear-gradient(45deg, var(--secondary-green), var(--primary-green));
            color: white;
            text-decoration: none;
        }
        .features {
            margin-top: 2rem;
        }
        .feature-item {
            padding: 1rem 0.5rem;
            color: #666;
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }
        .feature-item i {
            color: var(--primary-green);
            margin-right: 0.5rem;
            font-size: 1.2rem;
        }
        .copyright {
            margin-top: 2rem;
            color: #777;
            font-size: 0.9rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
            background: transparent !important; /* Remove white background */
        }
        
        /* Mobile-specific adjustments */
        @media (max-width: 768px) {
            .landing-card {
                padding: 1.5rem 1rem;
                margin: 0.5rem auto;
            }
            .logo-container img {
                max-height: 100px;
            }
            .system-title {
                font-size: 1.5rem;
            }
            .system-subtitle {
                font-size: 1.1rem;
            }
            .description {
                font-size: 1rem;
                margin: 1rem 0;
            }
            .feature-item {
                font-size: 0.95rem;
                padding: 0.8rem 0.3rem;
            }
            .login-btn {
                padding: 0.7rem 2rem;
                font-size: 1rem;
                width: 100%;
                max-width: 280px;
            }
        }
        
        @media (max-width: 576px) {
            .system-title {
                font-size: 1.3rem;
            }
            .system-subtitle {
                font-size: 1rem;
            }
            .feature-item {
                font-size: 0.9rem;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container d-flex align-items-center min-vh-100">
        <div class="row justify-content-center w-100">
            <div class="col-12 col-md-10 col-lg-8">
                <div class="landing-card text-center">
                    <div class="logo-container mb-4">
                        <img src="assets/images/logo.png" alt="Company Logo" class="img-fluid">
                    </div>
                    <h1 class="system-title mb-3">NIS Personnel Posting Management System</h1>
                    <h2 class="system-subtitle mb-3">(NISPPMS)</h2>
                    
                    
                    <div class="features row justify-content-center">
                        <div class="col-12 col-md-4 feature-item">
                            <i class="fas fa-shield-alt"></i> Secure Management
                        </div>
                        <div class="col-12 col-md-4 feature-item">
                            <i class="fas fa-sync"></i> Real-time Updates
                        </div>
                        <div class="col-12 col-md-4 feature-item">
                            <i class="fas fa-users"></i> Personnel Tracking
                        </div>
                    </div>

                    <div class="mt-4">
                        <a href="./login.php" class="btn btn-primary login-btn">
                            <i class="fas fa-sign-in-alt me-2"></i>Login to System
                        </a>
                    </div>
                    
                    <div class="copyright mt-5">
                        <p class="mb-0">All Rights Reserved by Nigeria Immigration Service. Designed and Developed by
                  <a href="https://www.immigration.gov.ng">NIS Web Team</a>.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>