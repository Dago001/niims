<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index");
    die();
}

require_once('db/db.php');

if ($_SESSION['role'] == "Admin") {
    include 'sidebar-admin.php';
}

if ($_SESSION['role'] == "CGI") {
    include 'sidebar-cgi.php';
}

include 'menu-footer-buttons.php';
include 'top-navigation.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coming Soon - CGI Personnel System</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Loading indicator */
        .loading-spinner {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255, 255, 255, 0.9);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
            z-index: 9999;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #27ae60;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Overlay for loading */
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.1);
            z-index: 9998;
        }
        
        /* Coming Soon Container */
        .coming-soon-container {
            min-height: 70vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        
        .coming-soon-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.1);
            padding: 60px 40px;
            text-align: center;
            max-width: 800px;
            width: 100%;
            border: 1px solid rgba(39, 174, 96, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .coming-soon-card:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #27ae60, #219a52, #1e8749);
        }
        
        .coming-soon-icon {
            font-size: 80px;
            color: #27ae60;
            margin-bottom: 30px;
            display: inline-block;
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            width: 150px;
            height: 150px;
            line-height: 150px;
            border-radius: 50%;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }
        
        .coming-soon-title {
            font-size: 3rem;
            color: #2c3e50;
            margin-bottom: 20px;
            font-weight: 700;
            background: linear-gradient(135deg, #2c3e50, #34495e);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .coming-soon-subtitle {
            font-size: 1.5rem;
            color: #27ae60;
            margin-bottom: 30px;
            font-weight: 600;
        }
        
        .coming-soon-message {
            font-size: 1.2rem;
            color: #666;
            line-height: 1.8;
            margin-bottom: 40px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .countdown-container {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            padding: 30px;
            margin: 40px 0;
            border: 1px solid #e0e0e0;
        }
        
        .countdown-title {
            font-size: 1.2rem;
            color: #2c3e50;
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .countdown-timer {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .countdown-item {
            background: white;
            border-radius: 12px;
            padding: 20px 15px;
            min-width: 100px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border: 2px solid #e8f5e9;
        }
        
        .countdown-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #27ae60;
            font-family: 'Courier New', monospace;
            margin-bottom: 5px;
        }
        
        .countdown-label {
            font-size: 0.9rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        
        .progress-container {
            margin: 40px 0;
            text-align: left;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .progress-bar {
            height: 12px;
            background: #e0e0e0;
            border-radius: 6px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #27ae60, #219a52);
            border-radius: 6px;
            width: 75%;
            position: relative;
            animation: progressFill 2s ease-in-out;
        }
        
        @keyframes progressFill {
            from { width: 0; }
        }
        
        .feature-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 40px 0;
        }
        
        .feature-item {
            background: #f8f9fa;
            padding: 25px 20px;
            border-radius: 12px;
            text-align: center;
            border-left: 4px solid #27ae60;
            transition: all 0.3s;
        }
        
        .feature-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .feature-icon {
            font-size: 40px;
            color: #27ae60;
            margin-bottom: 15px;
        }
        
        .feature-title {
            font-size: 1.1rem;
            color: #2c3e50;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .feature-desc {
            font-size: 0.9rem;
            color: #666;
            line-height: 1.6;
        }
        
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 40px;
            flex-wrap: wrap;
        }
        
        .btn-primary {
            padding: 15px 30px;
            background: linear-gradient(135deg, #27ae60, #219a52);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #219a52, #1e8749);
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(39, 174, 96, 0.3);
        }
        
        .btn-secondary {
            padding: 15px 30px;
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #7f8c8d, #6c7b7d);
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(149, 165, 166, 0.3);
        }
        
        .notification-section {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-radius: 15px;
            padding: 25px;
            margin: 40px 0;
            border-left: 4px solid #2196f3;
        }
        
        .notification-title {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #1976d2;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .notification-text {
            color: #0d47a1;
            line-height: 1.6;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .coming-soon-card {
                padding: 40px 20px;
            }
            
            .coming-soon-title {
                font-size: 2.5rem;
            }
            
            .coming-soon-subtitle {
                font-size: 1.3rem;
            }
            
            .countdown-item {
                min-width: 80px;
                padding: 15px 10px;
            }
            
            .countdown-value {
                font-size: 2rem;
            }
            
            .feature-list {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-primary, .btn-secondary {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 576px) {
            .coming-soon-title {
                font-size: 2rem;
            }
            
            .coming-soon-icon {
                width: 120px;
                height: 120px;
                line-height: 120px;
                font-size: 60px;
            }
            
            .countdown-timer {
                gap: 10px;
            }
            
            .countdown-item {
                min-width: 70px;
                padding: 12px 8px;
            }
            
            .countdown-value {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="overlay" id="overlay"></div>
    
    <!-- Loading Spinner -->
    <div class="loading-spinner" id="loadingSpinner">
        <div class="spinner"></div>
        <p>Loading...</p>
    </div>
    
    <div class="right_col" role="main">
        <div class="">
            <div class="page-title">
                <div class="title_left">
                    <h3>Coming Soon</h3>
                </div>
            </div>
            <div class="clearfix"></div>
            
            <!-- Coming Soon Content -->
            <div class="coming-soon-container">
                <div class="coming-soon-card">
                    <div class="coming-soon-icon">
                        <i class="fas fa-cogs"></i>
                    </div>
                    
                    <h1 class="coming-soon-title">Coming Soon</h1>
                    <h2 class="coming-soon-subtitle">We are working on this page</h2>
                    
                    <div class="coming-soon-message">
                        <!--We're working hard to bring you an enhanced personnel analytics system with powerful features -->
                        <!--for data analysis, reporting, and visualization. This module will provide deeper insights into -->
                        <!--your personnel data and help make informed decisions.-->
                    </div>
                    
                    <!-- Countdown Timer -->
                    <!--<div class="countdown-container">-->
                    <!--    <div class="countdown-title">-->
                    <!--        <i class="fas fa-clock"></i>-->
                    <!--        Estimated Launch Time-->
                    <!--    </div>-->
                    <!--    <div class="countdown-timer" id="countdownTimer">-->
                    <!--        <div class="countdown-item">-->
                    <!--            <div class="countdown-value" id="days">00</div>-->
                    <!--            <div class="countdown-label">Days</div>-->
                    <!--        </div>-->
                    <!--        <div class="countdown-item">-->
                    <!--            <div class="countdown-value" id="hours">00</div>-->
                    <!--            <div class="countdown-label">Hours</div>-->
                    <!--        </div>-->
                    <!--        <div class="countdown-item">-->
                    <!--            <div class="countdown-value" id="minutes">00</div>-->
                    <!--            <div class="countdown-label">Minutes</div>-->
                    <!--        </div>-->
                    <!--        <div class="countdown-item">-->
                    <!--            <div class="countdown-value" id="seconds">00</div>-->
                    <!--            <div class="countdown-label">Seconds</div>-->
                    <!--        </div>-->
                    <!--    </div>-->
                    <!--</div>-->
                    
                    <!-- Development Progress -->
                    <!--<div class="progress-container">-->
                    <!--    <div class="progress-label">-->
                    <!--        <span>Development Progress</span>-->
                    <!--        <span id="progressPercentage">75%</span>-->
                    <!--    </div>-->
                    <!--    <div class="progress-bar">-->
                    <!--        <div class="progress-fill" id="progressFill"></div>-->
                    <!--    </div>-->
                    <!--</div>-->
                    
                    <!-- Notification Section -->
                    <!--<div class="notification-section">-->
                    <!--    <div class="notification-title">-->
                    <!--        <i class="fas fa-info-circle"></i>-->
                    <!--        Important Notice-->
                    <!--    </div>-->
                    <!--    <div class="notification-text">-->
                    <!--        While this module is being developed, you can continue to use all existing features of the -->
                    <!--        CGI Personnel System. The new analytics module will integrate seamlessly with your current -->
                    <!--        workflows and provide additional capabilities for data analysis and reporting.-->
                    <!--    </div>-->
                    <!--</div>-->
                    
                    <!-- Features Preview -->
                    <!--<div class="feature-list">-->
                    <!--    <div class="feature-item">-->
                    <!--        <div class="feature-icon">-->
                    <!--            <i class="fas fa-chart-line"></i>-->
                    <!--        </div>-->
                    <!--        <div class="feature-title">Advanced Analytics</div>-->
                    <!--        <div class="feature-desc">-->
                    <!--            Comprehensive statistical analysis and trend visualization for personnel data-->
                    <!--        </div>-->
                    <!--    </div>-->
                        
                    <!--    <div class="feature-item">-->
                    <!--        <div class="feature-icon">-->
                    <!--            <i class="fas fa-file-export"></i>-->
                    <!--        </div>-->
                    <!--        <div class="feature-title">Smart Reporting</div>-->
                    <!--        <div class="feature-desc">-->
                    <!--            Generate detailed reports with custom filters and export options-->
                    <!--        </div>-->
                    <!--    </div>-->
                        
                    <!--    <div class="feature-item">-->
                    <!--        <div class="feature-icon">-->
                    <!--            <i class="fas fa-bell"></i>-->
                    <!--        </div>-->
                    <!--        <div class="feature-title">Real-time Alerts</div>-->
                    <!--        <div class="feature-desc">-->
                    <!--            Get notifications for important personnel changes and updates-->
                    <!--        </div>-->
                    <!--    </div>-->
                    <!--</div>-->
                    
                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <a href="cgi_view" class="btn-primary">
                            <i class="fas fa-arrow-left"></i>
                            Back to Personnel List
                        </a>
                        
                        <a href="mainpage" class="btn-secondary">
                            <i class="fas fa-tachometer-alt"></i>
                            Go to Dashboard
                        </a>
                        
                        <a href="myenrolments" class="btn-secondary">
                            <i class="fas fa-tachometer-alt"></i>
                            View My Enrolments
                        </a>
                    </div>
                    
                    <!-- Contact Information -->
                    <!--<div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e0e0e0;">-->
                    <!--    <p style="color: #666; font-size: 0.9rem;">-->
                    <!--        <i class="fas fa-envelope"></i> For questions or suggestions, contact: -->
                    <!--        <a href="mailto:admin@cgisystem.com" style="color: #27ae60; font-weight: 600;">-->
                    <!--            admin@cgisystem.com-->
                    <!--        </a>-->
                    <!--    </p>-->
                    <!--</div>-->
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Show/hide loading
        function showLoading() {
            document.getElementById('overlay').style.display = 'block';
            document.getElementById('loadingSpinner').style.display = 'block';
        }
        
        function hideLoading() {
            document.getElementById('overlay').style.display = 'none';
            document.getElementById('loadingSpinner').style.display = 'none';
        }
        
        // Reset loading spinner on back/forward browser navigation
        window.addEventListener('pageshow', function(event) {
            hideLoading();
        });
        
        // Set launch date (30 days from now)
        const launchDate = new Date();
        launchDate.setDate(launchDate.getDate() + 30);
        launchDate.setHours(9, 0, 0, 0); // Set to 9 AM
        
        // Countdown timer function
        function updateCountdown() {
            const now = new Date().getTime();
            const distance = launchDate - now;
            
            // Calculate days, hours, minutes, seconds
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            // Update display
            document.getElementById('days').textContent = days.toString().padStart(2, '0');
            document.getElementById('hours').textContent = hours.toString().padStart(2, '0');
            document.getElementById('minutes').textContent = minutes.toString().padStart(2, '0');
            document.getElementById('seconds').textContent = seconds.toString().padStart(2, '0');
            
            // Update progress bar
            const totalDays = 30;
            const daysPassed = totalDays - days;
            const progressPercentage = Math.min(100, Math.floor((daysPassed / totalDays) * 100));
            
            document.getElementById('progressPercentage').textContent = progressPercentage + '%';
            document.getElementById('progressFill').style.width = progressPercentage + '%';
            
            // If countdown is over
            if (distance < 0) {
                clearInterval(countdownInterval);
                document.getElementById('countdownTimer').innerHTML = `
                    <div style="text-align: center; padding: 20px;">
                        <i class="fas fa-check-circle" style="font-size: 50px; color: #27ae60; margin-bottom: 15px;"></i>
                        <h3 style="color: #27ae60; margin: 0;">Module is now available!</h3>
                        <p style="color: #666; margin-top: 10px;">Refresh the page to access the new features.</p>
                    </div>
                `;
            }
        }
        
        // Notify me function
        function notifyMe() {
            showLoading();
            
            setTimeout(() => {
                hideLoading();
                
                // Create notification
                const notification = document.createElement('div');
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: linear-gradient(135deg, #27ae60, #219a52);
                    color: white;
                    padding: 15px 20px;
                    border-radius: 8px;
                    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                    z-index: 10000;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    animation: slideIn 0.3s ease-out;
                `;
                
                notification.innerHTML = `
                    <i class="fas fa-check-circle" style="font-size: 20px;"></i>
                    <div>
                        <strong>Notification Request Saved!</strong>
                        <div style="font-size: 0.9rem; opacity: 0.9;">
                            We'll notify you when the module is ready.
                        </div>
                    </div>
                `;
                
                document.body.appendChild(notification);
                
                // Remove notification after 5 seconds
                setTimeout(() => {
                    notification.style.animation = 'slideOut 0.3s ease-in';
                    setTimeout(() => {
                        document.body.removeChild(notification);
                    }, 300);
                }, 5000);
            }, 1000);
        }
        
        // Add CSS for animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
        
        // Handle page load
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize countdown timer
            updateCountdown();
            const countdownInterval = setInterval(updateCountdown, 1000);
            
            // Hide loading after page loads
            setTimeout(function() {
                hideLoading();
            }, 500);
            
            // Add loading to button clicks
            document.querySelectorAll('.btn-primary, .btn-secondary').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    if (!this.getAttribute('href') || this.getAttribute('href') === '#') {
                        return;
                    }
                    showLoading();
                });
            });
            
            // Add keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Escape to go back
                if (e.key === 'Escape') {
                    window.history.back();
                }
                // B to go back to personnel list
                if (e.key === 'b' && e.altKey) {
                    e.preventDefault();
                    window.location.href = 'cgi_view';
                }
                // D to go to dashboard
                if (e.key === 'd' && e.altKey) {
                    e.preventDefault();
                    window.location.href = 'dashboard';
                }
                // N to notify me
                if (e.key === 'n' && e.altKey) {
                    e.preventDefault();
                    notifyMe();
                }
            });
        });
    </script>
    
    <?php include 'footer_tables.php'; ?>
</body>
</html>