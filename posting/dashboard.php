<?php
require_once 'includes/auth.php';
require_once 'includes/permissions.php';
require_once 'includes/config.php'; // This includes your database connection

if (!isLoggedIn()) {
    header('Location: login');
    exit();
}

// Check if connection is successful
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Get user information
try {
    $stmt = $pdo->prepare("
        SELECT u.*, r.name as role_name, u.photo as profile_photo
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    // Initialize default values
    $totalPersonnel = 0;
    $totalUsers = 0;
    $recentActivities = [];

    // Get monthly posting trends
    $monthlyTrends = $pdo->query("
        SELECT DATE_FORMAT(date_of_posting, '%Y-%m') as month,
               COUNT(*) as count
        FROM postings
        WHERE date_of_posting >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(date_of_posting, '%Y-%m')
        ORDER BY month ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Get zonal distribution
    $zonalDistribution = $pdo->query("
        SELECT z.name as zone, COUNT(p.id) as count
        FROM zones z
        LEFT JOIN formations f ON f.zone = z.name
        LEFT JOIN postings p ON p.formation_id = f.id
        WHERE p.posting_status = 'Active'
        GROUP BY z.name
        ORDER BY z.name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $recentActivities = $pdo->query("
        SELECT al.*, u.username 
        FROM activity_logs al
        JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 5
    ")->fetchAll();
} catch (PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
// Set defaults if queries failed
if (!isset($user) || !$user) {
    $user = [
        'photo_path' => '',
        'username' => isset($_SESSION['username']) ? $_SESSION['username'] : 'User',
        'full_name' => isset($_SESSION['username']) ? $_SESSION['username'] : 'User',
        'role_name' => '',
    ];
}
if (!isset($totalPersonnel)) $totalPersonnel = 0;
if (!isset($totalUsers)) $totalUsers = 0;
if (!isset($recentActivities) || !is_array($recentActivities)) $recentActivities = [];

// Get statistics by location type
$locationTypeStats = $conn->query("
    SELECT 
        f.location_type,
        COUNT(DISTINCT p.id) as total_postings,
        SUM(CASE WHEN p.posting_status = 'Active' THEN 1 ELSE 0 END) as active_postings,
        SUM(CASE WHEN p.posting_status = 'Completed' THEN 1 ELSE 0 END) as completed_postings,
        SUM(CASE WHEN p.posting_status = 'Pending' THEN 1 ELSE 0 END) as pending_postings
    FROM formations f
    LEFT JOIN postings p ON f.id = p.formation_id
    GROUP BY f.location_type
");

// Get top 5 locations with most postings
$topLocations = $conn->query("
    SELECT 
        f.name,
        COUNT(p.id) as total_postings
    FROM formations f
    LEFT JOIN postings p ON f.id = p.formation_id
    GROUP BY f.id, f.name
    ORDER BY total_postings DESC
    LIMIT 5
");
?>

<?php include 'includes/header.php'; ?>
<!-- Add Chart.js with specific version -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<!-- Add these responsive styles in the head section -->
<style>
    @media (max-width: 768px) {
        /* Sidebar adjustments */
        .col-md-3 {
            margin-bottom: 20px;
        }

        /* Make cards full width on mobile */
        .card {
            margin-bottom: 15px;
        }

        /* Adjust chart containers */
        canvas {
            max-height: 300px !important;
        }

        /* Make tables scrollable horizontally */
        .table-responsive {
            display: block;
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Adjust statistics widgets */
        .col-md-4 {
            margin-bottom: 15px;
        }

        /* Adjust padding and margins */
        .container-fluid {
            padding: 10px;
        }

        .card-body {
            padding: 15px;
        }

        /* Profile section adjustments */
        .rounded-circle {
            width: 80px !important;
            height: 80px !important;
        }
    }

    /* Small devices specific styles */
    @media (max-width: 576px) {
        .system-title {
            font-size: 1.25rem;
        }

        .card-title {
            font-size: 1.1rem;
        }

        .btn {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }

        /* Stack buttons vertically */
        .d-flex {
            flex-direction: column;
        }

        .btn-group {
            display: flex;
            flex-direction: column;
        }

        .btn-group > .btn {
            margin-bottom: 5px;
        }
    }
</style>

<!-- Modify the main container structure -->
<div class="container-fluid mt-4">
    <div class="row">
        <!-- Sidebar toggle for mobile -->
        <div class="d-lg-none mb-3">
            <button class="btn btn-success w-100" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar">
                <i class="fas fa-bars"></i> Toggle Menu
            </button>
        </div>

        <!-- Sidebar with User Profile -->
        <div class="col-md-3" id="sidebar">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <?php if (!empty($user['profile_photo'])): ?>
                        <img src="uploads/profile_photos/<?php echo htmlspecialchars($user['profile_photo']); ?>" 
                             alt="Profile" class="rounded-circle mb-3" style="width: 120px; height: 120px; object-fit: cover;"
                             id="profileImage">
                    <?php else: ?>
                        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center mx-auto mb-3" 
                             style="width: 120px; height: 120px; font-size: 48px;"
                             id="defaultProfileImage">
                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <script>
                        // Function to check for profile image updates
                        function checkProfileImage() {
                            fetch('get_profile_photo.php')
                                .then(response => response.json())
                                .then(data => {
                                    if (data.profile_photo) {
                                        const img = document.getElementById('profileImage');
                                        const defaultImg = document.getElementById('defaultProfileImage');
                                        
                                        if (defaultImg) defaultImg.style.display = 'none';
                                        if (!img) {
                                            const newImg = document.createElement('img');
                                            newImg.id = 'profileImage';
                                            newImg.className = 'rounded-circle mb-3';
                                            newImg.style.width = '120px';
                                            newImg.style.height = '120px';
                                            newImg.style.objectFit = 'cover';
                                            newImg.alt = 'Profile';
                                            defaultImg.parentNode.insertBefore(newImg, defaultImg);
                                        }
                                        const imgSrc = 'uploads/profile_photos/' + data.profile_photo + '?t=' + new Date().getTime();
                                        if (img && img.src !== imgSrc) {
                                            img.src = imgSrc;
                                        }
                                    }
                                })
                                .catch(error => console.error('Error checking profile image:', error));
                        }

                        // Check for updates every 5 seconds
                        setInterval(checkProfileImage, 5000);
                    </script>
                    <h5 class="mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h5>
                    <p class="text-muted mb-2"><?php echo htmlspecialchars($user['role_name']); ?></p>
                    
                </div>
            </div>

            <!-- Main Navigation -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Main Menu</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="#" class="list-group-item list-group-item-action">
                        <i class="fas fa-home me-2"></i> Home
                    </a>
                    <a href="dashboard" class="list-group-item list-group-item-action active bg-success">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                    <a href="form" class="list-group-item list-group-item-action">
                        <i class="fas fa-user-plus me-2"></i> Add Personnel
                    </a>
                    <a href="posting_order" class="list-group-item list-group-item-action">
                        <i class="fas fa-exchange-alt me-2"></i> Posting Order
                    </a>
                    <a href="search" class="list-group-item list-group-item-action">
                        <i class="fas fa-search me-2"></i> Search Records
                    </a>
                    <!-- <a href="search" class="list-group-item list-group-item-action">
                        <i class="fas fa-list me-2"></i> View All Records
                    </a> -->
                    <a href="export" class="list-group-item list-group-item-action">
                        <i class="fas fa-file-export me-2"></i> Export Records
                    </a>
                    <?php if (function_exists('isSuperAdmin') && isSuperAdmin()): ?>
                        <div class="list-group-item bg-light">
                            <strong><i class="fas fa-cogs me-2"></i> Administration</strong>
                        </div>
                        <a href="admin/users.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-users-cog me-2"></i> User Management
                        </a>
                        <a href="admin/roles.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-user-tag me-2"></i> Role Management
                        </a>
                        <a href="admin/settings.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-cog me-2"></i> System Settings
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- User Actions -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">User Actions</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="edit_profile" class="list-group-item list-group-item-action">
                        <i class="fas fa-user-edit me-2"></i> Edit Profile
                    </a>
                    <a href="change_password" class="list-group-item list-group-item-action">
                        <i class="fas fa-key me-2"></i> Change Password
                    </a>
                    <a href="logout" class="list-group-item list-group-item-action text-danger">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <div class="row">
                <!-- Statistics Widgets -->
                <div class="col-md-4 mb-4">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase mb-2">Total Personnel</h6>
                                    <h2 class="mb-0" id="totalPersonnel">0</h2>
                                </div>
                                <div class="rounded-circle bg-white p-3">
                                    <i class="fas fa-users fa-2x text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-4">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase mb-2">System Users</h6>
                                    <h2 class="mb-0" id="totalUsers">0</h2>
                                </div>
                                <div class="rounded-circle bg-white p-3">
                                    <i class="fas fa-user-shield fa-2x text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-4">
                    <div class="card bg-info text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase mb-2">Quick Search</h6>
                                    <form action="search" method="GET">
                                        <div class="input-group">
                                            <input type="text" class="form-control form-control-sm" 
                                                   placeholder="Service Number" name="service_number">
                                            <button class="btn btn-light btn-sm" type="submit">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                <div class="rounded-circle bg-white p-3">
                                    <i class="fas fa-search fa-2x text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analytics Charts -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header text-white" style="background-color: #198754;">
                            <h5 class="mb-0">Personnel Distribution by Formation</h5>
                            <div class="spinner-border text-white d-none" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <canvas id="formationChart" width="400" height="300"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header text-white" style="background-color: #198754;">
                            <h5 class="mb-0">Rank Distribution</h5>
                            <div class="spinner-border text-success d-none" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <canvas id="rankChart" width="400" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header text-white" style="background-color: #198754;">
                            <h5 class="mb-0">Gender Distribution</h5>
                            <div class="spinner-border text-success d-none" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <canvas id="genderChart" width="400" height="300"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header text-white" style="background-color: #198754;">
                            <h5 class="mb-0">Age Distribution</h5>
                            <div class="spinner-border text-success d-none" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <canvas id="ageChart" width="400" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="card mb-4">
                <div class="card-header text-white" style="background-color: #198754;">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Activities</h5>
                        <a href="activity_logs" class="btn btn-sm btn-outline-light">View All</a>
                    </div>
                </div>
                <div class="list-group list-group-flush" id="recentActivitiesList">
                    <div class="list-group-item text-muted">Loading activities...</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <!-- <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Personnel Management</h5>
                            <div class="list-group">
                                <a href="form" class="list-group-item list-group-item-action">
                                    <i class="fas fa-user-plus me-2"></i> Add New Personnel
                                </a>
                                <a href="search" class="list-group-item list-group-item-action">
                                    <i class="fas fa-list me-2"></i> View All Records
                                </a>
                                <a href="#" class="list-group-item list-group-item-action">
                                    <i class="fas fa-file-export me-2"></i> Export Records
                                </a>
                            </div>
                        </div>
                    </div>
                </div> -->

                <?php if (hasPermission('manage_users')): ?>
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Administration</h5>
                            <div class="list-group">
                                <a href="admin/users.php" class="list-group-item list-group-item-action">
                                    <i class="fas fa-users-cog me-2"></i> Manage Users
                                </a>
                                <a href="admin/roles.php" class="list-group-item list-group-item-action">
                                    <i class="fas fa-user-tag me-2"></i> Manage Roles
                                </a>
                                <a href="admin/settings.php" class="list-group-item list-group-item-action">
                                    <i class="fas fa-cog me-2"></i> System Settings
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Location Statistics -->
            <div class="row">
                <!-- Location Type Statistics -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Postings by Formation Type</h5>
                        </div>
                        <div class="card-body">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Formation Type</th>
                                        <th>Total</th>
                                        <th>Active</th>
                                        <th>Completed</th>
                                        <th>Pending</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = $locationTypeStats->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['location_type']); ?></td>
                                        <td><?php echo $row['total_postings']; ?></td>
                                        <td><?php echo $row['active_postings']; ?></td>
                                        <td><?php echo $row['completed_postings']; ?></td>
                                        <td><?php echo $row['pending_postings']; ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Top Locations -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Top 5 Posting Locations</h5>
                        </div>
                        <div class="card-body">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Location</th>
                                        <th>Total Postings</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = $topLocations->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><?php echo $row['total_postings']; ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Posting Statistics Visualization -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Posting Statistics Visualization</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="postingsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function editProfile() {
    // Implement profile editing functionality
    window.location.href = 'edit_profile';
}

// Charts will be populated with real-time data

// Chart color palette
// Store chart instances
let charts = {
    formation: null,
    rank: null,
    gender: null,
    age: null
};

const chartColors = [
    '#1cc88a', '#28a745', '#20c997', '#f6c23e', '#e74a3b',
    '#4e73df', '#36b9cc', '#858796', '#5a5c69', '#2c9faf'
];

// Function to create or update chart
function createOrUpdateChart(elementId, data, type = 'pie') {
    const ctx = document.getElementById(elementId);
    if (!ctx) return;

    const spinner = ctx.closest('.card').querySelector('.spinner-border');
    if (spinner) spinner.classList.remove('d-none');

    const chartConfig = {
        type: type,
        data: {
            labels: data.labels,
            datasets: [{
                data: data.values,
                backgroundColor: chartColors,
                borderWidth: 1,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    };

    if (charts[elementId]) {
        // Update existing chart
        charts[elementId].data = chartConfig.data;
        charts[elementId].update();
    } else {
        // Create new chart
        charts[elementId] = new Chart(ctx.getContext('2d'), chartConfig);
    }

    if (spinner) spinner.classList.add('d-none');
}

// Function to update summary cards
function updateSummaryCards(summary) {
    document.getElementById('totalPersonnel').textContent = summary.total_personnel.toLocaleString();
    document.getElementById('totalUsers').textContent = summary.total_users.toLocaleString();
}

// Function to update recent activities
function updateRecentActivities(activities) {
    const container = document.getElementById('recentActivitiesList');
    if (!container) return;

    const activitiesHTML = activities.map(activity => `
        <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1">${activity.action}</h6>
                    <small class="text-muted">By ${activity.username}</small>
                </div>
                <small class="text-muted">${new Date(activity.created_at).toLocaleString()}</small>
            </div>
        </div>
    `).join('');

    container.innerHTML = activitiesHTML || '<div class="list-group-item text-muted">No recent activities.</div>';
}

// Function to fetch and update all dashboard data
async function updateDashboard() {
    try {
        const spinner = document.getElementById('dashboardSpinner');
        if (spinner) spinner.classList.remove('d-none');

        const response = await fetch('get_analytics_data.php', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Cache-Control': 'no-cache'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const text = await response.text(); // Get response as text first
        
        try {
            const data = JSON.parse(text); // Try to parse the JSON
            
            if (!data.success) {
                throw new Error(data.error || 'Unknown error occurred');
            }

            // Update summary cards
            document.getElementById('totalPersonnel').textContent = 
                (data.data.summary.total_personnel || 0).toLocaleString();
            document.getElementById('totalUsers').textContent = 
                (data.data.summary.total_users || 0).toLocaleString();

            // Update charts only if data exists
            if (data.data.formations && data.data.formations.length > 0) {
                createOrUpdateChart('formationChart', {
                    labels: data.data.formations.map(f => f.formation_type),
                    values: data.data.formations.map(f => parseInt(f.count) || 0)
                });
            }

            if (data.data.ranks && data.data.ranks.length > 0) {
                createOrUpdateChart('rankChart', {
                    labels: data.data.ranks.map(r => r.rank),
                    values: data.data.ranks.map(r => parseInt(r.count) || 0)
                });
            }

            if (data.data.gender && data.data.gender.length > 0) {
                createOrUpdateChart('genderChart', {
                    labels: data.data.gender.map(g => g.gender),
                    values: data.data.gender.map(g => parseInt(g.count) || 0)
                });
            }

            if (data.data.age && data.data.age.length > 0) {
                createOrUpdateChart('ageChart', {
                    labels: data.data.age.map(a => a.age_group),
                    values: data.data.age.map(a => parseInt(a.count) || 0)
                });
            }

            // Update recent activities
            if (data.data.recent_activities) {
                updateRecentActivities(data.data.recent_activities);
            }

        } catch (parseError) {
            console.error('JSON Parse Error:', parseError);
            console.log('Raw response:', text);
            throw new Error('Invalid JSON response from server');
        }

    } catch (error) {
        console.error('Error updating dashboard:', error);
        showErrorMessage('Failed to update dashboard data: ' + error.message);
    } finally {
        const spinner = document.getElementById('dashboardSpinner');
        if (spinner) spinner.classList.add('d-none');
    }
}

// Add error handler for chart creation
function createOrUpdateChart(elementId, data) {
    try {
        const ctx = document.getElementById(elementId);
        if (!ctx) {
            console.error(`Canvas element ${elementId} not found`);
            return;
        }

        if (charts[elementId]) {
            charts[elementId].data.labels = data.labels;
            charts[elementId].data.datasets[0].data = data.values;
            charts[elementId].update();
        } else {
            charts[elementId] = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.values,
                        backgroundColor: chartColors
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    } catch (error) {
        console.error(`Error creating/updating chart ${elementId}:`, error);
    }
}

// Function to show error message
function showErrorMessage(message) {
    const alertContainer = document.getElementById('alertContainer');
    if (alertContainer) {
        alertContainer.innerHTML = `
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
    }
}

// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
    // Add alert container at the top of the dashboard
    const containerFluid = document.querySelector('.container-fluid');
    if (containerFluid) {
        containerFluid.insertAdjacentHTML('afterbegin', '<div id="alertContainer" class="mb-4"></div>');
    }

    // Add loading indicator
    const dashboardContainer = document.querySelector('.container-fluid');
    if (dashboardContainer) {
        dashboardContainer.insertAdjacentHTML('afterbegin', `
            <div id="dashboardSpinner" class="text-center d-none">
                <div class="loading-pulse">
                    <span class="visually-hidden">Updating dashboard...</span>
                </div>
            </div>
        `);
    }

    // Initial dashboard update
    updateDashboard();
    
    // Update dashboard every 30 seconds
    setInterval(updateDashboard, 30 * 1000);
});

// In your dashboard.php JavaScript
function updateCharts(data) {
    // Your existing chart updates...

    // Update location chart
    const locationData = data.locations;
    const locationLabels = locationData.map(item => item.location);
    const locationCounts = locationData.map(item => item.count);

    if (locationChart) {  // Assuming locationChart is your chart instance
        locationChart.data.labels = locationLabels;
        locationChart.data.datasets[0].data = locationCounts;
        locationChart.update();
    }
}

// Prepare data for chart
<?php 
$locationTypeStats->data_seek(0);
$labels = [];
$activeData = [];
$completedData = [];
$pendingData = [];

while($row = $locationTypeStats->fetch_assoc()) {
    $labels[] = $row['location_type'];
    $activeData[] = $row['active_postings'];
    $completedData[] = $row['completed_postings'];
    $pendingData[] = $row['pending_postings'];
}
?>

// Create the chart
const ctx = document.getElementById('postingsChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($labels); ?>,
        datasets: [{
            label: 'Active',
            data: <?php echo json_encode($activeData); ?>,
            backgroundColor: 'rgba(75, 192, 192, 0.5)',
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 1
        },
        {
            label: 'Completed',
            data: <?php echo json_encode($completedData); ?>,
            backgroundColor: 'rgba(54, 162, 235, 0.5)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        },
        {
            label: 'Pending',
            data: <?php echo json_encode($pendingData); ?>,
            backgroundColor: 'rgba(255, 206, 86, 0.5)',
            borderColor: 'rgba(255, 206, 86, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        },
        plugins: {
            title: {
                display: true,
                text: 'Posting Distribution by Formation Type'
            }
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>