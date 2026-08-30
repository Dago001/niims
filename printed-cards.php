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

// Add pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$records_per_page = 50;
$offset = ($page - 1) * $records_per_page;

// Date range filter
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$print_method = isset($_GET['print_method']) ? trim($_GET['print_method']) : '';
$rank_filter = isset($_GET['rank_filter']) ? trim($_GET['rank_filter']) : '';

// Search functionality
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_conditions = [];
$where_params = [];
$param_types = '';

if (!empty($search_query)) {
    $where_conditions[] = "(pc.serviceNo LIKE ? OR 
                             pc.surname LIKE ? OR 
                             pc.firstName LIKE ? OR 
                             pc.middleName LIKE ? OR 
                             pc.currentRank LIKE ? OR 
                             pc.printed_by LIKE ?)";
    $search_param = "%" . $search_query . "%";
    for ($i = 0; $i < 6; $i++) {
        $where_params[] = $search_param;
        $param_types .= 's';
    }
}

// Date range filter
if (!empty($date_from)) {
    $where_conditions[] = "DATE(pc.print_date) >= ?";
    $where_params[] = $date_from;
    $param_types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(pc.print_date) <= ?";
    $where_params[] = $date_to;
    $param_types .= 's';
}

// Print method filter
if (!empty($print_method)) {
    $where_conditions[] = "pc.print_method = ?";
    $where_params[] = $print_method;
    $param_types .= 's';
}

// Rank filter
if (!empty($rank_filter)) {
    $where_conditions[] = "pc.currentRank = ?";
    $where_params[] = $rank_filter;
    $param_types .= 's';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM tbl_printed_cards pc $where_clause";

$count_stmt = mysqli_prepare($conn, $count_sql);
if (!empty($where_params)) {
    mysqli_stmt_bind_param($count_stmt, $param_types, ...$where_params);
}
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);

if ($count_result) {
    $count_row = mysqli_fetch_array($count_result);
    $total_records = $count_row['total'];
    $total_pages = ceil($total_records / $records_per_page);
} else {
    $total_records = 0;
    $total_pages = 1;
}
mysqli_stmt_close($count_stmt);

// Main query with pagination
$sqluse = "SELECT pc.*, 
           DATE_FORMAT(pc.print_date, '%d %b %Y %h:%i %p') as formatted_date
           FROM tbl_printed_cards pc 
           $where_clause
           ORDER BY pc.print_date DESC
           LIMIT ? OFFSET ?";

// Add pagination parameters
$all_params = $where_params;
$all_params[] = $records_per_page;
$all_params[] = $offset;
$all_types = $param_types . 'ii';

// Prepare and execute main query
$stmt = mysqli_prepare($conn, $sqluse);

if (!empty($where_params)) {
    mysqli_stmt_bind_param($stmt, $all_types, ...$all_params);
} else {
    mysqli_stmt_bind_param($stmt, 'ii', $records_per_page, $offset);
}

mysqli_stmt_execute($stmt);
$retrieve = mysqli_stmt_get_result($stmt);
$numb = $retrieve ? mysqli_num_rows($retrieve) : 0;

// Get summary statistics
$stats_sql = "SELECT 
              COUNT(*) as total_prints,
              COUNT(DISTINCT serviceNo) as unique_personnel,
              SUM(CASE WHEN print_method = 'single' THEN 1 ELSE 0 END) as single_prints,
              SUM(CASE WHEN print_method = 'bulk' THEN 1 ELSE 0 END) as bulk_prints,
              DATE(MAX(print_date)) as last_print_date
              FROM tbl_printed_cards";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Get today's prints count and unique personnel
$today_sql = "SELECT COUNT(*) as today_prints, COUNT(DISTINCT serviceNo) as today_unique 
              FROM tbl_printed_cards WHERE DATE(print_date) = CURDATE()";
$today_result = mysqli_query($conn, $today_sql);
$today_stats = mysqli_fetch_assoc($today_result);

// Get this week's prints count and unique personnel
$week_sql = "SELECT COUNT(*) as week_prints, COUNT(DISTINCT serviceNo) as week_unique 
             FROM tbl_printed_cards WHERE YEARWEEK(print_date, 1) = YEARWEEK(CURDATE(), 1)";
$week_result = mysqli_query($conn, $week_sql);
$week_stats = mysqli_fetch_assoc($week_result);

// Get all distinct ranks for filter dropdown
$ranks_sql = "SELECT DISTINCT currentRank FROM tbl_printed_cards ORDER BY currentRank ASC";
$ranks_result = mysqli_query($conn, $ranks_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Printed Cards History</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Date Range Picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    
    <style>
        /* Loading indicator */
        .loading-spinner {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255, 255, 255, 0.95);
            padding: 20px 30px;
            border-radius: 10px;
            box-shadow: 0 5px 30px rgba(0,0,0,0.15);
            z-index: 9999;
            text-align: center;
            border: 1px solid #e0e0e0;
        }
        
        .spinner {
            border: 3px solid rgba(243, 243, 243, 0.8);
            border-top: 3px solid #3498db;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            animation: spin 0.6s linear infinite;
            margin: 0 auto 8px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(2px);
            z-index: 9998;
        }
        
        /* Pagination styles */
        .pagination {
            display: flex;
            justify-content: center;
            margin: 15px 0;
            gap: 4px;
            flex-wrap: wrap;
        }
        
        .page-link {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
            background: white;
            transition: all 0.3s;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .page-link:hover {
            background: #f5f5f5;
            transform: translateY(-1px);
        }
        
        .page-link.active {
            background: #27ae60;
            color: white;
            border-color: #27ae60;
            font-weight: bold;
        }
        
        .page-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        /* Stats Cards */
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
        .stat-icon.unique { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
        
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
        
        /* Filter Section */
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
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #c82333, #bd2130);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 11px;
        }
        
        /* Table Styles */
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
            min-width: 1000px;
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
        
        table thead th:nth-child(1) { width: 35px; }
        table thead th:nth-child(2) { width: 100px; }
        table thead th:nth-child(3) { width: 110px; }
        table thead th:nth-child(4) { width: 120px; }
        table thead th:nth-child(5) { width: 120px; }
        table thead th:nth-child(6) { width: 140px; }
        table thead th:nth-child(7) { width: 80px; }
        table thead th:nth-child(8) { width: 80px; }
        
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
        
        .service-no {
            font-weight: bold;
            font-size: 10px;
            font-family: 'Courier New', monospace;
            color: #2c3e50;
        }
        
        .rank-badge {
            font-size: 10px;
            background: #e3f2fd;
            color: #1976d2;
            padding: 3px 8px;
            border-radius: 8px;
            display: inline-block;
            font-weight: 500;
            border: 1px solid #bbdefb;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 110px;
        }
        
        .method-badge {
            font-size: 10px;
            padding: 3px 6px;
            border-radius: 4px;
            display: inline-block;
            font-weight: 600;
            text-align: center;
        }
        
        .method-single {
            background: #e3f2fd;
            color: #1976d2;
            border: 1px solid #bbdefb;
        }
        
        .method-bulk {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        
        .print-date {
            font-size: 10px;
            color: #2c3e50;
        }
        
        .print-date i {
            color: #27ae60;
            margin-right: 4px;
            font-size: 10px;
        }
        
        .action-btn {
            padding: 4px 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 10px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
            background: #f8f9fa;
            color: #2c3e50;
            text-decoration: none;
        }
        
        .action-btn:hover {
            background: #27ae60;
            color: white;
            transform: translateY(-1px);
        }
        
        .action-btn i {
            font-size: 11px;
        }
        
        .highlight {
            background-color: #fff9c4;
            font-weight: 600;
            padding: 1px 3px;
            border-radius: 2px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 50px;
            color: #ddd;
            margin-bottom: 15px;
        }
        
        .empty-state h4 {
            color: #666;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 16px;
        }
        
        .empty-state p {
            max-width: 400px;
            margin: 0 auto 15px;
            line-height: 1.5;
            font-size: 13px;
        }
        
        .export-dropdown {
            position: relative;
            display: inline-block;
        }
        
        .export-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: white;
            min-width: 180px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            border-radius: 8px;
            z-index: 100;
            border: 1px solid #e0e0e0;
        }
        
        .export-content a {
            color: #333;
            padding: 10px 14px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            transition: background 0.2s;
        }
        
        .export-content a:hover {
            background-color: #f8f9fa;
        }
        
        .export-content a:first-child {
            border-radius: 8px 8px 0 0;
        }
        
        .export-content a:last-child {
            border-radius: 0 0 8px 8px;
        }
        
        .export-dropdown:hover .export-content {
            display: block;
        }
        
        .page-info {
            text-align: center;
            padding: 10px;
            font-size: 12px;
            color: #666;
        }
        
        .filter-stats {
            font-size: 12px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filter-grid {
                grid-template-columns: 1fr 1fr;
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
            
            .pagination {
                flex-direction: column;
                align-items: center;
            }
            
            .page-link {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
    
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>
<body>
    <div class="overlay" id="overlay"></div>
    
    <div class="loading-spinner" id="loadingSpinner">
        <div class="spinner"></div>
        <p style="font-size: 13px; margin: 0;">Loading data...</p>
    </div>
    
    <div class="right_col" role="main">
        <div class="">
            <div class="page-title">
                <div class="title_left">
                    <h3 style="font-size: 20px;"><i class="fas fa-history"></i> Printed Cards History</h3>
                </div>
                
            </div>
            <div class="clearfix"></div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon total">
                        <i class="fas fa-print"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total_prints']); ?></div>
                    <div class="stat-label">Total Cards Printed</div>
                    <div class="stat-sub">Since inception</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon today">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($today_stats['today_prints']); ?></div>
                    <div class="stat-label">Today's Prints</div>
                    <div class="stat-sub"><?php echo date('d M Y'); ?> | <?php echo number_format($today_stats['today_unique']); ?> unique</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon week">
                        <i class="fas fa-calendar-week"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($week_stats['week_prints']); ?></div>
                    <div class="stat-label">This Week</div>
                    <div class="stat-sub">Last 7 days | <?php echo number_format($week_stats['week_unique']); ?> unique</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon unique">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['unique_personnel']); ?></div>
                    <div class="stat-label">Unique Personnel</div>
                    <div class="stat-sub">Distinct individuals (all time)</div>
                </div>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-header">
                    <h4 class="filter-title">
                        <i class="fas fa-filter"></i> Filter Print History
                    </h4>
                    <div class="filter-stats">
                        <i class="fas fa-database"></i>
                        Total Records: <?php echo number_format($total_records); ?>
                    </div>
                </div>
                
                <form method="GET" action="" id="filterForm">
                    <input type="hidden" name="page" value="<?php echo $page; ?>" id="pageInput">
                    
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label class="filter-label">
                                <i class="fas fa-search"></i> Search
                            </label>
                            <input type="text" 
                                   class="filter-input" 
                                   name="search" 
                                   placeholder="Service No, Name, Rank, Printed By..."
                                   value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">
                                <i class="fas fa-calendar-alt"></i> Date From
                            </label>
                            <input type="text" 
                                   class="filter-input datepicker" 
                                   name="date_from" 
                                   placeholder="YYYY-MM-DD"
                                   value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">
                                <i class="fas fa-calendar-alt"></i> Date To
                            </label>
                            <input type="text" 
                                   class="filter-input datepicker" 
                                   name="date_to" 
                                   placeholder="YYYY-MM-DD"
                                   value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">
                                <i class="fas fa-print"></i> Print Method
                            </label>
                            <select class="filter-select" name="print_method">
                                <option value="">All Methods</option>
                                <option value="single" <?php echo $print_method == 'single' ? 'selected' : ''; ?>>Single Print</option>
                                <option value="bulk" <?php echo $print_method == 'bulk' ? 'selected' : ''; ?>>Bulk Print</option>
                            </select>
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
                        <a href="?" class="btn btn-warning">
                            <i class="fas fa-redo"></i> Reset Filters
                        </a>
                        <button type="button" class="btn btn-secondary" onclick="clearFilters()">
                            <i class="fas fa-times"></i> Clear All
                        </button>
                        <div class="export-dropdown">
                        <button class="btn btn-primary">
                            <i class="fas fa-download"></i> Export <i class="fas fa-caret-down"></i>
                        </button>
                        <div class="export-content">
                            <a href="#" onclick="exportData('csv')">
                                <i class="fas fa-file-excel" style="color: #27ae60;"></i> Export as Excel (CSV)
                            </a>
                            <a href="#" onclick="exportData('print')">
                                <i class="fas fa-print" style="color: #3498db;"></i> Print Report
                            </a>
                        </div>
                    </div>
                    </div>
                </form>
            </div>
            
            <!-- Results Section -->
            <div class="results-section">
                <?php if ($numb > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>S/N</th>
                                    <th>Service No.</th>
                                    <th>Name</th>
                                    <th>Rank</th>
                                    <th>Printed By</th>
                                    <th>Print Date</th>
                                    <th>Method</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $counter = ($page - 1) * $records_per_page + 1;
                                while ($row = mysqli_fetch_assoc($retrieve)):
                                    $serviceNo = htmlspecialchars($row['serviceNo']);
                                    $surname = htmlspecialchars($row['surname']);
                                    $firstName = htmlspecialchars($row['firstName']);
                                    $middleName = htmlspecialchars($row['middleName']);
                                    $currentRank = htmlspecialchars($row['currentRank']);
                                    $printedBy = htmlspecialchars($row['printed_by']);
                                    
                                    if (!empty($search_query)) {
                                        $searchPattern = '/(' . preg_quote($search_query, '/') . ')/i';
                                        $serviceNo = preg_replace($searchPattern, '<span class="highlight">$1</span>', $serviceNo);
                                        $surname = preg_replace($searchPattern, '<span class="highlight">$1</span>', $surname);
                                        $firstName = preg_replace($searchPattern, '<span class="highlight">$1</span>', $firstName);
                                        $middleName = preg_replace($searchPattern, '<span class="highlight">$1</span>', $middleName);
                                        $currentRank = preg_replace($searchPattern, '<span class="highlight">$1</span>', $currentRank);
                                        $printedBy = preg_replace($searchPattern, '<span class="highlight">$1</span>', $printedBy);
                                    }
                                    
                                    $fullName = trim($surname . ' ' . $firstName . ' ' . $middleName);
                                    if (strlen($fullName) > 25) {
                                        $fullName = substr($fullName, 0, 22) . '...';
                                    }
                                ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><span class="service-no"><?php echo $serviceNo; ?></span></td>
                                    <td><?php echo $fullName; ?></td>
                                    <td><span class="rank-badge"><?php echo $currentRank; ?></span></td>
                                    <td><?php echo $printedBy; ?></td>
                                    <td>
                                        <span class="print-date">
                                            <i class="far fa-clock"></i>
                                            <?php echo htmlspecialchars($row['formatted_date']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="method-badge method-<?php echo $row['print_method'] === 'bulk' ? 'bulk' : 'single'; ?>">
                                            <?php echo htmlspecialchars(ucfirst($row['print_method'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="card?id=<?php echo urlencode($row['serviceNo']); ?>" 
                                           class="action-btn" 
                                           target="_blank"
                                           title="View Card">
                                            <i class="fas fa-id-card"></i>
                                        </a>
                                        <button class="action-btn" 
                                                onclick="printAgain('<?php echo $row['serviceNo']; ?>')"
                                                title="Print Again">
                                            <i class="fas fa-print"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php 
                        $query_params = [];
                        if (!empty($search_query)) $query_params['search'] = $search_query;
                        if (!empty($date_from)) $query_params['date_from'] = $date_from;
                        if (!empty($date_to)) $query_params['date_to'] = $date_to;
                        if (!empty($print_method)) $query_params['print_method'] = $print_method;
                        if (!empty($rank_filter)) $query_params['rank_filter'] = $rank_filter;
                        
                        $query_string = http_build_query($query_params);
                        $query_prefix = $query_string ? '&' . $query_string : '';
                        ?>
                        
                        <?php if ($page > 1): ?>
                            <a href="?page=1<?php echo $query_prefix; ?>" class="page-link">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="?page=<?php echo $page - 1 . $query_prefix; ?>" class="page-link">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        
                        if ($start > 1) {
                            echo '<span class="page-link disabled">...</span>';
                        }
                        
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <a href="?page=<?php echo $i . $query_prefix; ?>" 
                               class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($end < $total_pages): ?>
                            <span class="page-link disabled">...</span>
                        <?php endif; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1 . $query_prefix; ?>" class="page-link">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="?page=<?php echo $total_pages . $query_prefix; ?>" class="page-link">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="page-info">
                        <i class="fas fa-chart-bar"></i>
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?> | 
                        Showing <?php echo $numb; ?> of <?php echo number_format($total_records); ?> records
                    </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <h4>No Print Records Found</h4>
                        <p>No card printing history matches your search criteria. Try adjusting your filters or print some cards first.</p>
                        <a href="print-tables" class="btn btn-primary">
                            <i class="fas fa-print"></i> Go to Card Production
                        </a>
                    </div>
                <?php endif; ?>
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
        
        function showLoading() {
            document.getElementById('overlay').style.display = 'block';
            document.getElementById('loadingSpinner').style.display = 'block';
        }
        
        function hideLoading() {
            document.getElementById('overlay').style.display = 'none';
            document.getElementById('loadingSpinner').style.display = 'none';
        }
        
        function clearFilters() {
            document.querySelectorAll('.filter-input, .filter-select').forEach(input => {
                if (input.type !== 'hidden') {
                    input.value = '';
                }
            });
            document.getElementById('pageInput').value = '1';
            document.getElementById('filterForm').submit();
        }
        
        function printAgain(serviceNo) {
            showLoading();
            
            const printWindow = window.open(
                'card.php?id=' + encodeURIComponent(serviceNo) + '&popup=1',
                'print_' + serviceNo + '_' + Date.now(),
                'width=900,height=700,scrollbars=yes,resizable=yes'
            );
            
            if (printWindow) {
                printWindow.focus();
                
                const checkClosed = setInterval(() => {
                    try {
                        if (printWindow.closed) {
                            clearInterval(checkClosed);
                            hideLoading();
                            showNotification('Print completed!', 'success');
                        }
                    } catch (e) {
                        clearInterval(checkClosed);
                        hideLoading();
                    }
                }, 500);
                
                setTimeout(() => {
                    clearInterval(checkClosed);
                    hideLoading();
                }, 10000);
                
            } else {
                hideLoading();
                showNotification('Popup blocked! Please allow popups.', 'error');
            }
        }
        
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
            `;
            
            Object.assign(notification.style, {
                position: 'fixed',
                top: '20px',
                right: '20px',
                padding: '15px 25px',
                borderRadius: '8px',
                color: 'white',
                background: type === 'success' ? 'linear-gradient(135deg, #27ae60, #219a52)' : 'linear-gradient(135deg, #dc3545, #c82333)',
                zIndex: '9999',
                display: 'flex',
                alignItems: 'center',
                gap: '12px',
                boxShadow: '0 4px 15px rgba(0,0,0,0.1)',
                animation: 'slideIn 0.3s ease'
            });
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease forwards';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
        
        function exportData(format) {
            showLoading();
            
            let url = 'export-printed-cards.php?format=' + format;
            
            const search = document.querySelector('input[name="search"]').value;
            const date_from = document.querySelector('input[name="date_from"]').value;
            const date_to = document.querySelector('input[name="date_to"]').value;
            const print_method = document.querySelector('select[name="print_method"]').value;
            const rank_filter = document.querySelector('select[name="rank_filter"]').value;
            
            if (search) url += '&search=' + encodeURIComponent(search);
            if (date_from) url += '&date_from=' + encodeURIComponent(date_from);
            if (date_to) url += '&date_to=' + encodeURIComponent(date_to);
            if (print_method) url += '&print_method=' + encodeURIComponent(print_method);
            if (rank_filter) url += '&rank_filter=' + encodeURIComponent(rank_filter);
            
            window.location.href = url;
            
            setTimeout(hideLoading, 2000);
        }
        
        document.addEventListener('DOMContentLoaded', hideLoading);
        
        document.getElementById('filterForm').addEventListener('submit', showLoading);
        
        document.querySelectorAll('.page-link').forEach(link => {
            link.addEventListener('click', function(e) {
                if (!this.classList.contains('disabled')) {
                    showLoading();
                }
            });
        });
        
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
    </script>
    
    <?php 
    if (isset($stmt)) {
        mysqli_stmt_close($stmt);
    }
    if (isset($retrieve)) {
        mysqli_free_result($retrieve);
    }
    ?>
    
    <?php include 'footer_tables.php'; ?>
</body>
</html>