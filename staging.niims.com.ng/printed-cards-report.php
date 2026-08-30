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
} elseif ($_SESSION['role'] == "SuperAdmin") {
    include 'sidebar-superadmin.php';
} elseif ($_SESSION['role'] == "Supervisor") {
    include 'sidebar-supervisor.php';
} elseif ($_SESSION['role'] == "CGI") {
    include 'sidebar-cgi.php';
}

include 'menu-footer-buttons.php';
include 'top-navigation.php';

// Date range filter for report
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : date('Y-m-d');
$rank_filter = isset($_GET['rank_filter']) ? trim($_GET['rank_filter']) : '';

// Build filter conditions
$where_conditions = [];
$where_params = [];
$param_types = '';

if (!empty($date_from)) {
    $where_conditions[] = "DATE(print_date) >= ?";
    $where_params[] = $date_from;
    $param_types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(print_date) <= ?";
    $where_params[] = $date_to;
    $param_types .= 's';
}

if (!empty($rank_filter)) {
    $where_conditions[] = "currentRank = ?";
    $where_params[] = $rank_filter;
    $param_types .= 's';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Overall Statistics
$overall_sql = "SELECT 
    COUNT(*) as total_prints,
    COUNT(DISTINCT serviceNo) as unique_personnel,
    COUNT(DISTINCT DATE(print_date)) as active_days,
    SUM(CASE WHEN print_method = 'single' THEN 1 ELSE 0 END) as single_prints,
    SUM(CASE WHEN print_method = 'bulk' THEN 1 ELSE 0 END) as bulk_prints,
    MIN(print_date) as first_print,
    MAX(print_date) as last_print,
    ROUND(COUNT(*) / NULLIF(COUNT(DISTINCT DATE(print_date)), 0), 1) as avg_daily_prints,
    ROUND(COUNT(DISTINCT serviceNo) / NULLIF(COUNT(*), 0) * 100, 1) as unique_ratio
    FROM tbl_printed_cards 
    $where_clause";

$stmt = mysqli_prepare($conn, $overall_sql);
if (!empty($where_params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$where_params);
}
mysqli_stmt_execute($stmt);
$overall_result = mysqli_stmt_get_result($stmt);
$overall = mysqli_fetch_assoc($overall_result);
mysqli_stmt_close($stmt);

// Today's stats
$today_sql = "SELECT 
    COUNT(*) as today_total,
    COUNT(DISTINCT serviceNo) as today_unique,
    SUM(CASE WHEN print_method = 'single' THEN 1 ELSE 0 END) as today_single,
    SUM(CASE WHEN print_method = 'bulk' THEN 1 ELSE 0 END) as today_bulk
    FROM tbl_printed_cards WHERE DATE(print_date) = CURDATE()";
$today_result = mysqli_query($conn, $today_sql);
$today = mysqli_fetch_assoc($today_result);

// This week stats
$week_sql = "SELECT 
    COUNT(*) as week_total,
    COUNT(DISTINCT serviceNo) as week_unique,
    COUNT(DISTINCT DATE(print_date)) as week_active_days
    FROM tbl_printed_cards 
    WHERE YEARWEEK(print_date, 1) = YEARWEEK(CURDATE(), 1)";
$week_result = mysqli_query($conn, $week_sql);
$week = mysqli_fetch_assoc($week_result);

// This month stats
$month_sql = "SELECT 
    COUNT(*) as month_total,
    COUNT(DISTINCT serviceNo) as month_unique
    FROM tbl_printed_cards 
    WHERE MONTH(print_date) = MONTH(CURDATE()) 
    AND YEAR(print_date) = YEAR(CURDATE())";
$month_result = mysqli_query($conn, $month_sql);
$month = mysqli_fetch_assoc($month_result);

// Daily trend - Last 30 days
$daily_sql = "SELECT 
    DATE(print_date) as date,
    COUNT(*) as total,
    COUNT(DISTINCT serviceNo) as unique_count
    FROM tbl_printed_cards 
    WHERE print_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(print_date)
    ORDER BY DATE(print_date) ASC";
$daily_result = mysqli_query($conn, $daily_sql);
$daily_labels = [];
$daily_totals = [];
$daily_uniques = [];
while ($row = mysqli_fetch_assoc($daily_result)) {
    $daily_labels[] = date('d M', strtotime($row['date']));
    $daily_totals[] = $row['total'];
    $daily_uniques[] = $row['unique_count'];
}

// Weekly trend - Last 12 weeks
$weekly_sql = "SELECT 
    YEARWEEK(print_date, 1) as week_num,
    MIN(DATE(print_date)) as week_start,
    COUNT(*) as total,
    COUNT(DISTINCT serviceNo) as unique_count
    FROM tbl_printed_cards 
    WHERE print_date >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
    GROUP BY YEARWEEK(print_date, 1)
    ORDER BY week_num ASC";
$weekly_result = mysqli_query($conn, $weekly_sql);
$weekly_labels = [];
$weekly_totals = [];
$weekly_uniques = [];
while ($row = mysqli_fetch_assoc($weekly_result)) {
    $weekly_labels[] = date('d M', strtotime($row['week_start']));
    $weekly_totals[] = $row['total'];
    $weekly_uniques[] = $row['unique_count'];
}

// Monthly trend - Last 12 months
$monthly_sql = "SELECT 
    DATE_FORMAT(print_date, '%Y-%m') as month,
    COUNT(*) as total,
    COUNT(DISTINCT serviceNo) as unique_count
    FROM tbl_printed_cards 
    WHERE print_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(print_date, '%Y-%m')
    ORDER BY month ASC";
$monthly_result = mysqli_query($conn, $monthly_sql);
$monthly_labels = [];
$monthly_totals = [];
$monthly_uniques = [];
while ($row = mysqli_fetch_assoc($monthly_result)) {
    $monthly_labels[] = date('M Y', strtotime($row['month'] . '-01'));
    $monthly_totals[] = $row['total'];
    $monthly_uniques[] = $row['unique_count'];
}

// Rank distribution
$rank_sql = "SELECT 
    currentRank,
    COUNT(*) as total,
    COUNT(DISTINCT serviceNo) as unique_count
    FROM tbl_printed_cards 
    $where_clause
    GROUP BY currentRank
    ORDER BY total DESC
    LIMIT 15";
$stmt = mysqli_prepare($conn, $rank_sql);
if (!empty($where_params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$where_params);
}
mysqli_stmt_execute($stmt);
$rank_result = mysqli_stmt_get_result($stmt);
$rank_labels = [];
$rank_totals = [];
$rank_uniques = [];
$rank_colors = [];
$colors = ['#3498db', '#e74c3c', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c', '#e67e22', '#34495e', '#95a5a6', '#27ae60', '#c0392b', '#8e44ad', '#16a085', '#d35400', '#7f8c8d'];
$color_index = 0;
while ($row = mysqli_fetch_assoc($rank_result)) {
    $rank_labels[] = $row['currentRank'];
    $rank_totals[] = $row['total'];
    $rank_uniques[] = $row['unique_count'];
    $rank_colors[] = $colors[$color_index % count($colors)];
    $color_index++;
}
mysqli_stmt_close($stmt);

// Top printed personnel
$top_personnel_sql = "SELECT 
    serviceNo,
    surname,
    firstName,
    currentRank,
    COUNT(*) as print_count,
    MAX(print_date) as last_printed
    FROM tbl_printed_cards 
    $where_clause
    GROUP BY serviceNo, surname, firstName, currentRank
    ORDER BY print_count DESC
    LIMIT 10";
$stmt = mysqli_prepare($conn, $top_personnel_sql);
if (!empty($where_params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$where_params);
}
mysqli_stmt_execute($stmt);
$top_personnel_result = mysqli_stmt_get_result($stmt);
$top_personnel = [];
while ($row = mysqli_fetch_assoc($top_personnel_result)) {
    $top_personnel[] = $row;
}
mysqli_stmt_close($stmt);

// Printed by distribution
$printed_by_sql = "SELECT 
    printed_by,
    printed_by_role,
    COUNT(*) as total
    FROM tbl_printed_cards 
    $where_clause
    GROUP BY printed_by, printed_by_role
    ORDER BY total DESC
    LIMIT 10";
$stmt = mysqli_prepare($conn, $printed_by_sql);
if (!empty($where_params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$where_params);
}
mysqli_stmt_execute($stmt);
$printed_by_result = mysqli_stmt_get_result($stmt);
$printed_by_labels = [];
$printed_by_totals = [];
while ($row = mysqli_fetch_assoc($printed_by_result)) {
    $printed_by_labels[] = $row['printed_by'] . ' (' . ($row['printed_by_role'] ?: 'N/A') . ')';
    $printed_by_totals[] = $row['total'];
}
mysqli_stmt_close($stmt);

// Hourly distribution
$hourly_sql = "SELECT 
    HOUR(print_date) as hour,
    COUNT(*) as total
    FROM tbl_printed_cards 
    $where_clause
    GROUP BY HOUR(print_date)
    ORDER BY hour ASC";
$stmt = mysqli_prepare($conn, $hourly_sql);
if (!empty($where_params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$where_params);
}
mysqli_stmt_execute($stmt);
$hourly_result = mysqli_stmt_get_result($stmt);
$hourly_labels = [];
$hourly_totals = [];
for ($i = 0; $i < 24; $i++) {
    $hourly_labels[] = sprintf('%02d:00', $i);
    $hourly_totals[$i] = 0;
}
while ($row = mysqli_fetch_assoc($hourly_result)) {
    $hourly_totals[$row['hour']] = $row['total'];
}
$hourly_totals = array_values($hourly_totals);
mysqli_stmt_close($stmt);

// Day of week distribution
$dow_sql = "SELECT 
    DAYOFWEEK(print_date) as day_num,
    COUNT(*) as total
    FROM tbl_printed_cards 
    $where_clause
    GROUP BY DAYOFWEEK(print_date)
    ORDER BY day_num ASC";
$stmt = mysqli_prepare($conn, $dow_sql);
if (!empty($where_params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$where_params);
}
mysqli_stmt_execute($stmt);
$dow_result = mysqli_stmt_get_result($stmt);
$dow_labels = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$dow_totals = [0, 0, 0, 0, 0, 0, 0];
while ($row = mysqli_fetch_assoc($dow_result)) {
    $dow_totals[$row['day_num'] - 1] = $row['total'];
}
mysqli_stmt_close($stmt);

// Get all distinct ranks for filter dropdown
$ranks_sql = "SELECT DISTINCT currentRank FROM tbl_printed_cards ORDER BY currentRank ASC";
$ranks_result = mysqli_query($conn, $ranks_sql);
?>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- Date Range Picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    
    <style>
        /* ============================================ */
        /* SAME STYLES AS printed-cards.php FOR UNIFORMITY */
        /* ============================================ */
        
        /* Stats Cards - EXACTLY like printed-cards.php */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.06);
            border: 1px solid #e0e0e0;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
        }
        
        .stat-icon i {
            font-size: 20px;
            color: white;
        }
        
        .stat-icon.total { background: linear-gradient(135deg, #3498db, #2980b9); }
        .stat-icon.today { background: linear-gradient(135deg, #27ae60, #219a52); }
        .stat-icon.week { background: linear-gradient(135deg, #f39c12, #e67e22); }
        .stat-icon.month { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
        .stat-icon.unique { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .stat-icon.ratio { background: linear-gradient(135deg, #1abc9c, #16a085); }
        
        .stat-value {
            font-size: 22px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 3px;
        }
        
        .stat-label {
            font-size: 12px;
            color: #7f8c8d;
            font-weight: 500;
        }
        
        .stat-sub {
            font-size: 10px;
            color: #95a5a6;
            margin-top: 3px;
        }
        
        /* Filter Section - EXACTLY like printed-cards.php */
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 3px 15px rgba(0,0,0,0.06);
        }
        
        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .filter-title {
            color: #2c3e50;
            font-size: 16px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-title i {
            color: #27ae60;
            font-size: 18px;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .filter-group {
            margin-bottom: 0;
        }
        
        .filter-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 6px;
            font-size: 11px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .filter-label i {
            color: #27ae60;
            font-size: 12px;
        }
        
        .filter-input, .filter-select {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 12px;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        
        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: #27ae60;
            background: white;
            box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.1);
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        
        .filter-stats {
            font-size: 12px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        /* Buttons - EXACTLY like printed-cards.php */
        .btn {
            padding: 8px 18px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #27ae60, #219a52);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #219a52, #1e8749);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #5a6268, #495057);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #333;
        }
        
        .btn-warning:hover {
            background: linear-gradient(135deg, #e0a800, #d39e00);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3);
        }
        
        /* Table Styles - EXACTLY like printed-cards.php */
        .table-responsive {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            margin-bottom: 15px;
            background: white;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        
        table thead {
            background: linear-gradient(135deg, #2c3e50, #34495e);
        }
        
        table thead th {
            color: white;
            padding: 10px 10px;
            text-align: left;
            font-weight: 600;
            font-size: 11px;
            border-bottom: 2px solid #27ae60;
        }
        
        table tbody tr {
            transition: all 0.2s;
            border-bottom: 1px solid #f0f0f0;
        }
        
        table tbody tr:hover {
            background-color: #f9f9f9;
        }
        
        table tbody td {
            padding: 8px 10px;
            font-size: 11px;
            color: #333;
            vertical-align: middle;
        }
        
        .badge-rank {
            font-size: 10px;
            background: #e3f2fd;
            color: #1976d2;
            padding: 3px 8px;
            border-radius: 8px;
            display: inline-block;
            font-weight: 500;
            border: 1px solid #bbdefb;
        }
        
        .service-no {
            font-weight: bold;
            font-size: 10px;
            font-family: 'Courier New', monospace;
            color: #2c3e50;
        }
        
        /* ============================================ */
        /* REPORT-SPECIFIC STYLES (Chart Cards etc) */
        /* ============================================ */
        
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .chart-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.06);
            border: 1px solid #e0e0e0;
        }
        
        .chart-card.full-width {
            grid-column: 1 / -1;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .chart-title {
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .chart-title i {
            color: #27ae60;
            font-size: 16px;
        }
        
        .chart-subtitle {
            font-size: 11px;
            color: #95a5a6;
        }
        
        .chart-body {
            position: relative;
            width: 100%;
            height: 300px;
        }
        
        .chart-body.large {
            height: 350px;
        }
        
        .chart-body canvas {
            width: 100% !important;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filter-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .chart-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .filter-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <div class="right_col" role="main">
        <div class="">
            <div class="page-title">
                <div class="title_left">
                    <h3 style="font-size: 20px;"><i class="fas fa-chart-pie"></i> Printed Cards Analytics Report</h3>
                </div>
                
            </div>
            <div class="clearfix"></div>
            
            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-header">
                    <h4 class="filter-title">
                        <i class="fas fa-filter"></i> Filter Report Data
                    </h4>
                    <div class="filter-stats">
                        <i class="fas fa-calendar-alt"></i>
                        Period: <?php echo date('d M Y', strtotime($date_from)); ?> - <?php echo date('d M Y', strtotime($date_to)); ?>
                    </div>
                </div>
                
                <form method="GET" action="" id="filterForm">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label class="filter-label">
                                <i class="fas fa-calendar-alt"></i> Date From
                            </label>
                            <input type="text" class="filter-input datepicker" name="date_from" 
                                   value="<?php echo htmlspecialchars($date_from); ?>" placeholder="YYYY-MM-DD">
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">
                                <i class="fas fa-calendar-alt"></i> Date To
                            </label>
                            <input type="text" class="filter-input datepicker" name="date_to" 
                                   value="<?php echo htmlspecialchars($date_to); ?>" placeholder="YYYY-MM-DD">
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">
                                <i class="fas fa-star"></i> Rank
                            </label>
                            <select class="filter-select" name="rank_filter">
                                <option value="">All Ranks</option>
                                <?php 
                                if ($ranks_result && mysqli_num_rows($ranks_result) > 0):
                                    while ($rank_row = mysqli_fetch_assoc($ranks_result)):
                                        $rank = htmlspecialchars($rank_row['currentRank']);
                                ?>
                                    <option value="<?php echo $rank; ?>" <?php echo $rank_filter == $rank ? 'selected' : ''; ?>>
                                        <?php echo $rank; ?>
                                    </option>
                                <?php 
                                    endwhile;
                                endif;
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="printed-cards-report" class="btn btn-warning">
                            <i class="fas fa-redo"></i> Reset Filters
                        </a>
                        <div class="title_right">
                    <div style="display: flex; gap: 10px;">
                        <a href="printed-cards" class="btn btn-primary">
                            <i class="fas fa-list"></i> View History
                        </a>
                        <button onclick="window.print()" class="btn btn-secondary">
                            <i class="fas fa-print"></i> Print Report
                        </button>
                    </div>
                </div>
                    </div>
                </form>
            </div>
            
            <!-- Statistics Cards - EXACTLY like printed-cards.php -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon total">
                        <i class="fas fa-print"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($overall['total_prints']); ?></div>
                    <div class="stat-label">Total Cards Printed</div>
                    <div class="stat-sub">Avg <?php echo $overall['avg_daily_prints']; ?>/day</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon today">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($today['today_total']); ?></div>
                    <div class="stat-label">Today's Prints</div>
                    <div class="stat-sub"><?php echo date('d M Y'); ?> | <?php echo number_format($today['today_unique']); ?> unique</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon week">
                        <i class="fas fa-calendar-week"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($week['week_total']); ?></div>
                    <div class="stat-label">This Week</div>
                    <div class="stat-sub"><?php echo number_format($week['week_unique']); ?> unique | <?php echo $week['week_active_days']; ?> active days</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon month">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($month['month_total']); ?></div>
                    <div class="stat-label">This Month</div>
                    <div class="stat-sub"><?php echo number_format($month['month_unique']); ?> unique personnel</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon unique">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($overall['unique_personnel']); ?></div>
                    <div class="stat-label">Unique Personnel</div>
                    <div class="stat-sub"><?php echo $overall['unique_ratio']; ?>% of total prints</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon ratio">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-value"><?php echo $overall['active_days']; ?></div>
                    <div class="stat-label">Active Days</div>
                    <div class="stat-sub">Since <?php echo date('d M Y', strtotime($overall['first_print'])); ?></div>
                </div>
            </div>
            
            <!-- Charts Grid -->
            <div class="chart-grid">
                <!-- Daily Trend -->
                <div class="chart-card full-width">
                    <div class="chart-header">
                        <div>
                            <div class="chart-title"><i class="fas fa-chart-line"></i> Daily Print Trend (Last 30 Days)</div>
                            <div class="chart-subtitle">Total prints vs Unique personnel per day</div>
                        </div>
                    </div>
                    <div class="chart-body large">
                        <canvas id="dailyTrendChart"></canvas>
                    </div>
                </div>
                
                <!-- Print Method -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div>
                            <div class="chart-title"><i class="fas fa-chart-pie"></i> Print Method Distribution</div>
                            <div class="chart-subtitle">Single vs Bulk prints</div>
                        </div>
                    </div>
                    <div class="chart-body">
                        <canvas id="methodPieChart"></canvas>
                    </div>
                </div>
                
                <!-- Rank Distribution -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div>
                            <div class="chart-title"><i class="fas fa-chart-bar"></i> Rank Distribution (Top 15)</div>
                            <div class="chart-subtitle">Total prints by rank</div>
                        </div>
                    </div>
                    <div class="chart-body">
                        <canvas id="rankBarChart"></canvas>
                    </div>
                </div>
                
                <!-- Weekly Trend -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div>
                            <div class="chart-title"><i class="fas fa-chart-line"></i> Weekly Trend (Last 12 Weeks)</div>
                            <div class="chart-subtitle">Print volume per week</div>
                        </div>
                    </div>
                    <div class="chart-body">
                        <canvas id="weeklyTrendChart"></canvas>
                    </div>
                </div>
                
                <!-- Hourly Distribution -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div>
                            <div class="chart-title"><i class="fas fa-clock"></i> Hourly Distribution</div>
                            <div class="chart-subtitle">Print activity by hour of day</div>
                        </div>
                    </div>
                    <div class="chart-body">
                        <canvas id="hourlyChart"></canvas>
                    </div>
                </div>
                
                <!-- Day of Week -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div>
                            <div class="chart-title"><i class="fas fa-calendar-check"></i> Day of Week Distribution</div>
                            <div class="chart-subtitle">Print activity by day</div>
                        </div>
                    </div>
                    <div class="chart-body">
                        <canvas id="dowChart"></canvas>
                    </div>
                </div>
                
                <!-- Monthly Trend -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div>
                            <div class="chart-title"><i class="fas fa-chart-bar"></i> Monthly Trend (Last 12 Months)</div>
                            <div class="chart-subtitle">Monthly print volume</div>
                        </div>
                    </div>
                    <div class="chart-body">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
                
                <!-- Printed By -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div>
                            <div class="chart-title"><i class="fas fa-user-edit"></i> Printed By Distribution (Top 10)</div>
                            <div class="chart-subtitle">Operators with most prints</div>
                        </div>
                    </div>
                    <div class="chart-body">
                        <canvas id="printedByChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Top 10 Personnel Table -->
            <div class="chart-card" style="margin-bottom: 20px;">
                <div class="chart-header">
                    <div>
                        <div class="chart-title"><i class="fas fa-trophy"></i> Top 10 Most Printed Personnel</div>
                        <div class="chart-subtitle">Personnel with highest reprint count</div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Service No.</th>
                                <th>Name</th>
                                <th>Rank</th>
                                <th>Print Count</th>
                                <th>Last Printed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1;
                            if (!empty($top_personnel)):
                                foreach ($top_personnel as $person):
                            ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td><span class="service-no"><?php echo htmlspecialchars($person['serviceNo']); ?></span></td>
                                <td><?php echo htmlspecialchars($person['surname'] . ' ' . $person['firstName']); ?></td>
                                <td><span class="badge-rank"><?php echo htmlspecialchars($person['currentRank']); ?></span></td>
                                <td><strong><?php echo $person['print_count']; ?></strong></td>
                                <td><?php echo date('d M Y', strtotime($person['last_printed'])); ?></td>
                            </tr>
                            <?php 
                                endforeach;
                            else:
                            ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 30px; color: #999;">
                                    <i class="fas fa-inbox" style="font-size: 30px; display: block; margin-bottom: 8px;"></i>
                                    No data available
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Initialize date pickers
        flatpickr(".datepicker", {
            dateFormat: "Y-m-d",
            allowInput: true,
            maxDate: "today"
        });
        
        // Chart.js Configuration
        Chart.defaults.font.family = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif";
        Chart.defaults.font.size = 11;
        Chart.defaults.color = '#666';
        
        // Daily Trend Line Chart
        new Chart(document.getElementById('dailyTrendChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($daily_labels); ?>,
                datasets: [{
                    label: 'Total Prints',
                    data: <?php echo json_encode($daily_totals); ?>,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointBackgroundColor: '#3498db'
                }, {
                    label: 'Unique Personnel',
                    data: <?php echo json_encode($daily_uniques); ?>,
                    borderColor: '#27ae60',
                    backgroundColor: 'rgba(39, 174, 96, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointBackgroundColor: '#27ae60'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', labels: { usePointStyle: true, padding: 20 } }
                },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
        
        // Print Method Doughnut Chart
        new Chart(document.getElementById('methodPieChart'), {
            type: 'doughnut',
            data: {
                labels: ['Single Print', 'Bulk Print'],
                datasets: [{
                    data: [<?php echo intval($overall['single_prints'] ?? 0); ?>, <?php echo intval($overall['bulk_prints'] ?? 0); ?>],
                    backgroundColor: ['#3498db', '#27ae60'],
                    borderWidth: 3,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 20, usePointStyle: true } },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                return ctx.label + ': ' + ctx.raw + ' (' + ((ctx.raw/total)*100).toFixed(1) + '%)';
                            }
                        }
                    }
                }
            }
        });
        
        // Rank Distribution Bar Chart
        new Chart(document.getElementById('rankBarChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($rank_labels); ?>,
                datasets: [{
                    label: 'Total Prints',
                    data: <?php echo json_encode($rank_totals); ?>,
                    backgroundColor: <?php echo json_encode($rank_colors); ?>,
                    borderWidth: 0,
                    borderRadius: 5
                }, {
                    label: 'Unique Personnel',
                    data: <?php echo json_encode($rank_uniques); ?>,
                    backgroundColor: 'rgba(0,0,0,0.08)',
                    borderWidth: 0,
                    borderRadius: 5
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', labels: { usePointStyle: true, padding: 15 } }
                },
                scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
        
        // Weekly Trend Chart
        new Chart(document.getElementById('weeklyTrendChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($weekly_labels); ?>,
                datasets: [{
                    label: 'Total Prints',
                    data: <?php echo json_encode($weekly_totals); ?>,
                    backgroundColor: 'rgba(52, 152, 219, 0.8)',
                    borderColor: '#3498db',
                    borderWidth: 1,
                    borderRadius: 5
                }, {
                    label: 'Unique Personnel',
                    data: <?php echo json_encode($weekly_uniques); ?>,
                    backgroundColor: 'rgba(39, 174, 96, 0.6)',
                    borderColor: '#27ae60',
                    borderWidth: 1,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', labels: { usePointStyle: true, padding: 15 } }
                },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
        
        // Hourly Distribution Chart
        new Chart(document.getElementById('hourlyChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($hourly_labels); ?>,
                datasets: [{
                    label: 'Prints',
                    data: <?php echo json_encode($hourly_totals); ?>,
                    backgroundColor: 'rgba(155, 89, 182, 0.8)',
                    borderColor: '#9b59b6',
                    borderWidth: 1,
                    borderRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
        
        // Day of Week Chart
        new Chart(document.getElementById('dowChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($dow_labels); ?>,
                datasets: [{
                    label: 'Prints',
                    data: <?php echo json_encode($dow_totals); ?>,
                    backgroundColor: [
                        'rgba(231, 76, 60, 0.8)', 'rgba(52, 152, 219, 0.8)',
                        'rgba(46, 204, 113, 0.8)', 'rgba(241, 196, 15, 0.8)',
                        'rgba(155, 89, 182, 0.8)', 'rgba(26, 188, 156, 0.8)',
                        'rgba(230, 126, 34, 0.8)'
                    ],
                    borderWidth: 1,
                    borderColor: '#fff',
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
        
        // Monthly Trend Chart
        new Chart(document.getElementById('monthlyChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($monthly_labels); ?>,
                datasets: [{
                    label: 'Total Prints',
                    data: <?php echo json_encode($monthly_totals); ?>,
                    borderColor: '#e74c3c',
                    backgroundColor: 'rgba(231, 76, 60, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#e74c3c'
                }, {
                    label: 'Unique Personnel',
                    data: <?php echo json_encode($monthly_uniques); ?>,
                    borderColor: '#9b59b6',
                    backgroundColor: 'rgba(155, 89, 182, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#9b59b6'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', labels: { usePointStyle: true, padding: 20 } }
                },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
        
        // Printed By Distribution Chart
        new Chart(document.getElementById('printedByChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($printed_by_labels); ?>,
                datasets: [{
                    label: 'Total Prints',
                    data: <?php echo json_encode($printed_by_totals); ?>,
                    backgroundColor: [
                        'rgba(52, 152, 219, 0.8)', 'rgba(46, 204, 113, 0.8)',
                        'rgba(231, 76, 60, 0.8)', 'rgba(241, 196, 15, 0.8)',
                        'rgba(155, 89, 182, 0.8)', 'rgba(26, 188, 156, 0.8)',
                        'rgba(230, 126, 34, 0.8)', 'rgba(52, 73, 94, 0.8)',
                        'rgba(149, 165, 166, 0.8)', 'rgba(192, 57, 43, 0.8)'
                    ],
                    borderWidth: 1,
                    borderColor: '#fff',
                    borderRadius: 5
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });

    </script>
    
    <?php 
    mysqli_close($conn);
    ?>
    
    <script>
        // Temporarily hide Chart.js v4 from custom.js to prevent Chart.defaults.global TypeError
        window.tempChart = window.Chart;
        delete window.Chart;
    </script>
    
    <?php include 'footer_tables.php'; ?>
    
    <script>
        // Restore Chart.js v4
        window.Chart = window.tempChart;
        delete window.tempChart;
    </script>