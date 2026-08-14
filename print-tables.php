<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index");
    die();
}

require_once('db/db.php');

// Include appropriate sidebar based on role
if ($_SESSION['role'] == "SuperAdmin") {
    include 'sidebar-superadmin.php';
} elseif ($_SESSION['role'] == "Admin") {
    include 'sidebar-admin.php';
} elseif ($_SESSION['role'] == "Supervisor") {
    include 'sidebar-supervisor.php';
}

include 'menu-footer-buttons.php';
include 'top-navigation.php';

// Add pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$records_per_page = 50;
$offset = ($page - 1) * $records_per_page;

// Search functionality
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = '';
$search_params = [];
$param_types = '';

if (!empty($search_query)) {
    $search_condition = "WHERE (emp.serviceNo LIKE ? OR 
                               emp.surname LIKE ? OR 
                               emp.firstName LIKE ? OR 
                               emp.middleName LIKE ? OR 
                               emp2.currentRank LIKE ? OR 
                               emp.bloodType LIKE ? OR
                               emp.phone LIKE ? OR
                               emp.confirmed_details LIKE ?)";
    $search_param = "%" . $search_query . "%";
    $search_params = array_fill(0, 8, $search_param);
    $param_types = str_repeat('s', count($search_params));
}

// Get total count for pagination
$count_sql = "SELECT COUNT(DISTINCT emp.serviceNo) as total 
              FROM tbl_emppersonal emp 
              INNER JOIN tbl_employment emp2 ON emp.serviceNo = emp2.serviceNo";
              
if (!empty($search_condition)) {
    $count_sql .= " " . $search_condition;
}

$count_stmt = mysqli_prepare($conn, $count_sql);
if (!empty($search_params)) {
    mysqli_stmt_bind_param($count_stmt, $param_types, ...$search_params);
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

// Main query with pagination and search
$sqluse = "SELECT DISTINCT emp.serviceNo, emp.surname, emp.firstName, emp.signature, 
                  emp.middleName, emp.bloodType, emp.confirmed_details, emp2.currentRank, emp.phone
           FROM tbl_emppersonal emp 
           INNER JOIN tbl_employment emp2 ON emp.serviceNo = emp2.serviceNo";
           
if (!empty($search_condition)) {
    $sqluse .= " " . $search_condition;
}

$sqluse .= " ORDER BY 
           CASE emp2.currentRank
               WHEN 'Comptroller General' THEN 1
               WHEN 'Dep. Comptroller General' THEN 2
               WHEN 'Asst. Comptroller General' THEN 3
               WHEN 'Comptroller' THEN 4
               WHEN 'Deputy Comptroller' THEN 5
               WHEN 'Assistant Comptroller' THEN 6
               WHEN 'Chief Superintendent' THEN 7
               WHEN 'Superintendent' THEN 8
               WHEN 'Deputy Superintendent' THEN 9
               WHEN 'Asst. Superintendent 1' THEN 10
               WHEN 'Asst. Superintendent 2' THEN 11
               WHEN 'Inspector' THEN 12        
               WHEN 'Assistant Inspector' THEN 13
               WHEN 'Chief Immigration Asst.' THEN 14
               WHEN 'Senior Immigration Asst.' THEN 15
               WHEN 'Immigration Asst. 1' THEN 16
               WHEN 'Immigration Asst. 2' THEN 17
               WHEN 'Immigration Asst. 3' THEN 18
               ELSE 19
           END ASC, emp.serviceNo ASC
           LIMIT ? OFFSET ?";

// Add pagination parameters
$search_params[] = $records_per_page;
$search_params[] = $offset;
$param_types .= 'ii';

// Prepare and execute main query
$stmt = mysqli_prepare($conn, $sqluse);

if (!empty($search_condition)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$search_params);
} else {
    mysqli_stmt_bind_param($stmt, 'ii', $records_per_page, $offset);
}

mysqli_stmt_execute($stmt);
$retrieve = mysqli_stmt_get_result($stmt);
$numb = $retrieve ? mysqli_num_rows($retrieve) : 0;

// Remove the LIMIT and OFFSET parameters from search_params array for highlighting
if (!empty($search_condition)) {
    array_pop($search_params);
    array_pop($search_params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Card Production - Enrolled Personnel</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Optimized Loading indicator */
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
            border: 4px solid rgba(243, 243, 243, 0.8);
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 0.6s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .loading-spinner p {
            margin: 0;
            font-size: 14px;
            color: #2c3e50;
            font-weight: 500;
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
            margin: 20px 0;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .page-link {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
            background: white;
            transition: all 0.3s;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .page-link:hover {
            background: #f5f5f5;
            transform: translateY(-2px);
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
        
        .page-info {
            text-align: center;
            margin: 15px 0;
            color: #666;
            font-size: 14px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        /* Professional compact search section */
        .search-section {
            background: #ffffff;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        
        .search-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .search-title {
            color: #2c3e50;
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .search-title i {
            color: #27ae60;
            font-size: 20px;
        }
        
        .search-form {
            display: flex;
            gap: 10px;
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .search-input-container {
            flex: 1;
            position: relative;
            min-width: 300px;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #27ae60;
            background: white;
            box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.1);
        }
        
        .search-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #95a5a6;
            font-size: 16px;
        }
        
        .search-btn {
            padding: 12px 25px;
            background: linear-gradient(135deg, #27ae60, #219a52);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .search-btn:hover {
            background: linear-gradient(135deg, #219a52, #1e8749);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }
        
        .clear-btn {
            padding: 12px 20px;
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .clear-btn:hover {
            background: linear-gradient(135deg, #7f8c8d, #6c7b7d);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(149, 165, 166, 0.3);
        }
        
        /* Search results info */
        .search-results-info {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #27ae60;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .search-results-info p {
            margin: 0;
            color: #2e7d32;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .search-results-info p i {
            font-size: 18px;
        }
        
        /* Table improvements */
        .table-responsive {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            margin-bottom: 20px;
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
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            border-bottom: 2px solid #27ae60;
        }
        
        table thead th:nth-child(1) { width: 110px; }
        table thead th:nth-child(2) { width: 120px; }
        table thead th:nth-child(3) { width: 120px; }
        table thead th:nth-child(4) { width: 100px; }
        table thead th:nth-child(5) { width: 160px; }
        table thead th:nth-child(6) { width: 120px; }
        table thead th:nth-child(7) { width: 100px; }
        table thead th:nth-child(8) { width: 100px; }
        table thead th:nth-child(9) { width: 160px; }
        
        table tbody tr {
            transition: all 0.2s;
            border-bottom: 1px solid #f0f0f0;
        }
        
        table tbody tr:hover {
            background-color: #f9f9f9;
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        table tbody td {
            padding: 12px 15px;
            font-size: 13px;
            color: #333;
            vertical-align: middle;
        }
        
        /* Actions container */
        .actions-container {
            display: flex;
            gap: 6px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        /* Print button styling */
        .btn-print {
            padding: 7px 12px;
            background: linear-gradient(135deg, #27ae60, #219a52);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-weight: 500;
            white-space: nowrap;
        }
        
        .btn-print:hover {
            background: linear-gradient(135deg, #219a52, #1e8749);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }
        
        /* View button styling */
        .btn-view {
            padding: 7px 12px;
            background: #2A3F54;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-weight: 500;
            text-decoration: none;
            white-space: nowrap;
        }
        
        .btn-view:hover {
            background: linear-gradient(135deg, #2980b9, #2471a3);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }
        
        /* Highlight search terms */
        .highlight {
            background-color: #fff9c4;
            font-weight: 600;
            padding: 2px 4px;
            border-radius: 3px;
            color: #333;
        }
        
        /* Rank badge */
        .rank-badge {
            font-size: 11px;
            background: #e3f2fd;
            color: #1976d2;
            padding: 3px 8px;
            border-radius: 10px;
            display: inline-block;
            font-weight: 500;
            border: 1px solid #bbdefb;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 140px;
        }
        
        /* Blood type styling */
        .blood-type {
            font-weight: bold;
            font-size: 13px;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        
        .blood-positive {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        
        .blood-negative {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        
        /* Confirm column styling */
        .confirm-status {
            font-size: 12px;
            padding: 5px 10px;
            border-radius: 4px;
            display: inline-block;
            font-weight: 600;
            text-align: center;
            min-width: 80px;
        }
        
        .confirmed-yes {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .confirmed-no {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .confirmed-partial {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        /* Service No styling */
        .service-no {
            font-weight: bold;
            font-size: 12px;
            font-family: 'Courier New', monospace;
            color: #2c3e50;
        }
        
        /* Print notification styles */
        .print-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #27ae60;
            color: white;
            padding: 15px 20px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 99999;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease-out;
            font-size: 14px;
        }
        
        .print-notification.error {
            background: #dc3545;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .search-form {
                flex-direction: column;
            }
            
            .search-input-container {
                min-width: 100%;
            }
            
            .search-btn, .clear-btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 768px) {
            .search-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-results-info {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            
            .actions-container {
                flex-direction: column;
                gap: 4px;
            }
            
            .btn-view, .btn-print {
                width: 100%;
                justify-content: center;
                font-size: 11px;
                padding: 6px 8px;
            }
        }
        
        @media (max-width: 576px) {
            .pagination {
                flex-direction: column;
                align-items: center;
            }
            
            .page-link {
                width: 100%;
                justify-content: center;
            }
            
            table {
                font-size: 12px;
            }
            
            table thead th,
            table tbody td {
                padding: 8px 10px;
            }
            
            .rank-badge {
                font-size: 10px;
                padding: 2px 6px;
                max-width: 120px;
            }
            
            .confirm-status {
                font-size: 11px;
                padding: 4px 8px;
                min-width: 70px;
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
        <p>Loading data...</p>
    </div>
    
    <div class="right_col" role="main">
        <div class="">
            <div class="page-title">
                <div class="title_left">
                    <h3>Card Production - Enrolled Personnel</h3>
                </div>
            </div>
            <div class="clearfix"></div>
            
            <!-- Search Section -->
            <div class="search-section">
                <div class="search-header">
                    <form method="GET" action="" class="search-form" id="searchForm">
                        <div class="search-input-container">
                            <input type="text" 
                                   class="search-input" 
                                   name="search" 
                                   id="searchInput"
                                   placeholder="Search by Service No, Name, Rank, Phone, Blood Type, or Confirm Status..."
                                   value="<?php echo htmlspecialchars($search_query); ?>"
                                   autocomplete="off"
                                   aria-label="Search personnel">
                            <i class="fas fa-search search-icon"></i>
                        </div>
                        
                        <button type="submit" class="search-btn" id="searchBtn">
                            <i class="fas fa-search"></i> Search
                        </button>
                        
                        <?php if (!empty($search_query)): ?>
                            <a href="?" class="clear-btn">
                                <i class="fas fa-times"></i> Clear All
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <!-- Search Results Info -->
            <?php if (!empty($search_query)): ?>
            <div class="search-results-info">
                <p>
                    <i class="fas fa-info-circle"></i>
                    Found <strong><?php echo number_format($total_records); ?></strong> record<?php echo $total_records != 1 ? 's' : ''; ?> for "<strong><?php echo htmlspecialchars($search_query); ?></strong>"
                </p>
            </div>
            <?php endif; ?>
            
            <!-- Start Page Content -->
            <div class="row">              
                <div class="card">
                    <div class="card-body">
                        
                        <!-- Page Info -->
                        <?php if ($total_pages > 1): ?>
                        <div class="page-info">
                            <i class="fas fa-chart-bar"></i> 
                            Page <strong><?php echo $page; ?></strong> of <strong><?php echo $total_pages; ?></strong> | 
                            Showing <strong><?php echo $numb; ?></strong> of <strong><?php echo number_format($total_records); ?></strong> total records
                        </div>
                        <?php endif; ?>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Service No.</th>
                                        <th>Surname</th>
                                        <th>First Name</th>
                                        <th>Middle Name</th>
                                        <th>Rank</th>
                                        <th>Phone</th>
                                        <th>Blood Type</th>
                                        <th>Confirm</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($retrieve && $numb > 0): ?>
                                        <?php 
                                        $counter = 1;
                                        while ($ret = mysqli_fetch_array($retrieve)): 
                                            $rawServiceNo = $ret['serviceNo'];
                                            $serviceNo = htmlspecialchars($rawServiceNo);
                                            $surname = htmlspecialchars($ret['surname']);
                                            $firstName = htmlspecialchars($ret['firstName']);
                                            $middleName = htmlspecialchars($ret['middleName']);
                                            $rank = htmlspecialchars($ret['currentRank']);
                                            $bloodType = htmlspecialchars($ret['bloodType']);
                                            $phone = htmlspecialchars($ret['phone']);
                                            $confirmedDetails = htmlspecialchars($ret['confirmed_details']);
                                            
                                            if (!empty($search_query)) {
                                                $searchPattern = '/(' . preg_quote($search_query, '/') . ')/i';
                                                $serviceNo = preg_replace($searchPattern, '<span class="highlight">$1</span>', $serviceNo);
                                                $surname = preg_replace($searchPattern, '<span class="highlight">$1</span>', $surname);
                                                $firstName = preg_replace($searchPattern, '<span class="highlight">$1</span>', $firstName);
                                                $middleName = preg_replace($searchPattern, '<span class="highlight">$1</span>', $middleName);
                                                $rank = preg_replace($searchPattern, '<span class="highlight">$1</span>', $rank);
                                                $bloodType = preg_replace($searchPattern, '<span class="highlight">$1</span>', $bloodType);
                                                $phone = preg_replace($searchPattern, '<span class="highlight">$1</span>', $phone);
                                                $confirmedDetails = preg_replace($searchPattern, '<span class="highlight">$1</span>', $confirmedDetails);
                                            }
                                            
                                            $bloodClass = '';
                                            if (!empty($bloodType)) {
                                                $bloodClass = strpos($bloodType, '-') !== false ? 'blood-negative' : 'blood-positive';
                                            }
                                            
                                            $confirmStatus = '';
                                            $confirmClass = '';
                                            
                                            if (!empty($confirmedDetails)) {
                                                $confirmLower = strtolower($confirmedDetails);
                                                if (strpos($confirmLower, 'yes') !== false || strpos($confirmLower, 'confirmed') !== false) {
                                                    $confirmStatus = 'Confirmed';
                                                    $confirmClass = 'confirmed-yes';
                                                } elseif (strpos($confirmLower, 'partial') !== false || strpos($confirmLower, 'pending') !== false) {
                                                    $confirmStatus = 'Partial';
                                                    $confirmClass = 'confirmed-partial';
                                                } else {
                                                    $confirmStatus = 'Not Confirmed';
                                                    $confirmClass = 'confirmed-no';
                                                }
                                            } else {
                                                $confirmStatus = 'Not Confirmed';
                                                $confirmClass = 'confirmed-no';
                                            }
                                        ?>
                                        <tr>
                                            <td>
                                                <span class="service-no"><?php echo $serviceNo; ?></span>
                                            </td>
                                            <td><?php echo $surname; ?></td>
                                            <td><?php echo $firstName; ?></td>
                                            <td><?php echo $middleName; ?></td>
                                            <td>
                                                <span class="rank-badge" title="<?php echo htmlspecialchars($ret['currentRank']); ?>">
                                                    <?php echo $rank; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $phone ?: 'N/A'; ?></td>
                                            <td>
                                                <?php if (!empty($bloodType)): ?>
                                                    <span class="blood-type <?php echo $bloodClass; ?>">
                                                        <?php echo $bloodType; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #95a5a6; font-size: 12px;">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="confirm-status <?php echo $confirmClass; ?>" 
                                                      title="<?php echo htmlspecialchars($ret['confirmed_details']); ?>">
                                                    <?php echo $confirmStatus; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="actions-container">
                                                    <!-- VIEW BUTTON - Passes view_only=1 so it won't count as printed -->
                                                    <a href="card?id=<?php echo urlencode($rawServiceNo); ?>&view_only=1" 
                                                       class="btn-view" 
                                                       target="_blank"
                                                       title="View ID Card for <?php echo addslashes($ret['firstName'] . ' ' . $ret['surname']); ?>">
                                                        <i class="fas fa-id-card"></i> View
                                                    </a>
                                                    <!-- PRINT BUTTON - This will count as printed -->
                                                    <button class="btn-print" type="button" 
                                                            onclick="printCardIframe('<?php echo addslashes($rawServiceNo); ?>')"
                                                            title="Print ID Card for <?php echo addslashes($ret['firstName'] . ' ' . $ret['surname']); ?>">
                                                        <i class="fas fa-print"></i> Print
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php 
                                        $counter++;
                                        endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" style="text-align: center; padding: 40px;">
                                                <div style="color: #666; max-width: 400px; margin: 0 auto;">
                                                    <i class="fas fa-users fa-3x" style="margin-bottom: 15px; opacity: 0.3;"></i>
                                                    <h4 style="margin: 0 0 10px 0;">
                                                        <?php if (!empty($search_query)): ?>
                                                            <i class="fas fa-search-minus"></i> No results found
                                                        <?php else: ?>
                                                            <i class="fas fa-database"></i> No records available
                                                        <?php endif; ?>
                                                    </h4>
                                                    <p style="font-size: 14px; margin: 0;">
                                                        <?php if (!empty($search_query)): ?>
                                                            No personnel found for "<strong><?php echo htmlspecialchars($search_query); ?></strong>"<br>
                                                            Try a different search or <a href="?" style="color: #27ae60; font-weight: 600;">view all records</a>
                                                        <?php else: ?>
                                                            There are no personnel records available for card production.
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=1<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                                   class="page-link" title="First Page">
                                    <i class="fas fa-angle-double-left"></i> First
                                </a>
                                <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                                   class="page-link" title="Previous Page">
                                    <i class="fas fa-angle-left"></i> Prev
                                </a>
                            <?php else: ?>
                                <span class="page-link disabled">
                                    <i class="fas fa-angle-double-left"></i> First
                                </span>
                                <span class="page-link disabled">
                                    <i class="fas fa-angle-left"></i> Prev
                                </span>
                            <?php endif; ?>
                            
                            <?php 
                            $startPage = max(1, $page - 2);
                            $endPage = min($total_pages, $page + 2);
                            
                            if ($startPage > 1) {
                                echo '<a href="?page=1' . (!empty($search_query) ? '&search=' . urlencode($search_query) : '') . '" 
                                      class="page-link" title="Page 1">1</a>';
                                if ($startPage > 2) {
                                    echo '<span class="page-link disabled">...</span>';
                                }
                            }
                            
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <a href="?page=<?php echo $i; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                                   class="page-link <?php echo $i == $page ? 'active' : ''; ?>"
                                   title="Page <?php echo $i; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php 
                            if ($endPage < $total_pages) {
                                if ($endPage < $total_pages - 1) {
                                    echo '<span class="page-link disabled">...</span>';
                                }
                                echo '<a href="?page=' . $total_pages . (!empty($search_query) ? '&search=' . urlencode($search_query) : '') . '" 
                                      class="page-link" title="Page ' . $total_pages . '">' . $total_pages . '</a>';
                            }
                            ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                                   class="page-link" title="Next Page">
                                    Next <i class="fas fa-angle-right"></i>
                                </a>
                                <a href="?page=<?php echo $total_pages; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                                   class="page-link" title="Last Page">
                                    Last <i class="fas fa-angle-double-right"></i>
                                </a>
                            <?php else: ?>
                                <span class="page-link disabled">
                                    Next <i class="fas fa-angle-right"></i>
                                </span>
                                <span class="page-link disabled">
                                    Last <i class="fas fa-angle-double-right"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Jump to page -->
                        <div style="text-align: center; margin-top: 15px;">
                            <form method="GET" action="" style="display: inline-block;">
                                <div style="display: flex; gap: 8px; align-items: center; justify-content: center; flex-wrap: wrap;">
                                    <span style="font-size: 14px; color: #666; font-weight: 500;">Go to page:</span>
                                    <input type="number" 
                                           name="page" 
                                           min="1" 
                                           max="<?php echo $total_pages; ?>" 
                                           value="<?php echo $page; ?>" 
                                           style="width: 70px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; text-align: center;"
                                           onchange="this.value = Math.max(1, Math.min(<?php echo $total_pages; ?>, parseInt(this.value) || 1))">
                                    <?php if (!empty($search_query)): ?>
                                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                                    <?php endif; ?>
                                    <button type="submit" 
                                            style="padding: 8px 20px; background: #27ae60; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 5px;">
                                        <i class="fas fa-arrow-right"></i> Go
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function showLoading() {
            document.getElementById('overlay').style.display = 'block';
            document.getElementById('loadingSpinner').style.display = 'block';
        }
        
        function hideLoading() {
            document.getElementById('overlay').style.display = 'none';
            document.getElementById('loadingSpinner').style.display = 'none';
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            hideLoading();
            
            const searchForm = document.getElementById('searchForm');
            if (searchForm) {
                searchForm.addEventListener('submit', function() {
                    showLoading();
                });
            }
            
            const paginationLinks = document.querySelectorAll('.page-link:not(.disabled)');
            paginationLinks.forEach(function(link) {
                link.addEventListener('click', function() {
                    showLoading();
                });
            });
        });
        
        window.addEventListener('load', function() {
            hideLoading();
        });
        
        setTimeout(function() {
            hideLoading();
        }, 5000);
        
        function showPrintSuccess(message = 'Print completed successfully!') {
            const existing = document.querySelector('.print-notification');
            if (existing) existing.remove();
            
            const notification = document.createElement('div');
            notification.className = 'print-notification';
            notification.innerHTML = `
                <i class="fas fa-check-circle" style="font-size: 18px;"></i>
                <span>${message}</span>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease-out forwards';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }
        
        function showPrintError(message = 'Print failed! Please try again.') {
            const existing = document.querySelector('.print-notification');
            if (existing) existing.remove();
            
            const notification = document.createElement('div');
            notification.className = 'print-notification error';
            notification.innerHTML = `
                <i class="fas fa-exclamation-circle" style="font-size: 18px;"></i>
                <span>${message}</span>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease-out forwards';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }
        
        function printCardIframe(serviceNo) {
            showLoading();
            
            const windowName = 'cardPrint_' + serviceNo + '_' + Date.now();
            
            const currentUrl = window.location.href;
            const baseUrl = currentUrl.substring(0, currentUrl.lastIndexOf('/') + 1);
            
            const printWindow = window.open(
                baseUrl + 'card.php?id=' + encodeURIComponent(serviceNo) + '&popup=1',
                windowName,
                'width=900,height=700,scrollbars=yes,resizable=yes,toolbar=no,location=no,status=no,menubar=no'
            );
            
            if (printWindow) {
                printWindow.focus();
                
                const checkWindowClosed = setInterval(() => {
                    try {
                        if (printWindow.closed) {
                            clearInterval(checkWindowClosed);
                            hideLoading();
                            showPrintSuccess('Print completed! Returning to list...');
                        }
                    } catch (e) {
                        clearInterval(checkWindowClosed);
                        hideLoading();
                        showPrintSuccess('Print completed!');
                    }
                }, 500);
                
                setTimeout(() => {
                    clearInterval(checkWindowClosed);
                    hideLoading();
                }, 5000);
                
            } else {
                hideLoading();
                showPrintError('Popup blocked! Please allow popups for this site.');
                
                setTimeout(() => {
                    if (confirm('Popup was blocked. Would you like to open the card in this window instead?')) {
                        window.location.href = baseUrl + 'card.php?id=' + encodeURIComponent(serviceNo) + '&return=1';
                    }
                }, 1000);
            }
        }
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