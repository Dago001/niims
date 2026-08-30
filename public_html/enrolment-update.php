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
}

if ($_SESSION['role'] == "Supervisor") {
    include 'sidebar-supervisor.php';
}

if ($_SESSION['role'] == "SuperAdmin") {
    include 'sidebar.php';
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
                               emp2.currentRank LIKE ?)";
    $search_param = "%" . $search_query . "%";
    $search_params = array_fill(0, 5, $search_param);
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
                  emp.middleName, emp.bloodType, emp2.currentRank
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
if (!empty($search_condition)) {
    $search_params[] = $records_per_page;
    $search_params[] = $offset;
    $param_types .= 'ii'; // Add integer types for LIMIT and OFFSET
}

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personnel Enrolment - Update Records</title>
    
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
            border-top: 4px solid #007bff;
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
            background: #007bff;
            color: white;
            border-color: #007bff;
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
        
        /* Stats bar */
        .stats-bar {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            border: 1px solid #eaeaea;
        }
        
        .stat-item {
            text-align: center;
            padding: 10px;
            background: white;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        .stat-value {
            font-size: 20px;
            font-weight: bold;
            color: #2c3e50;
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
            color: #007bff;
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
            border-color: #007bff;
            background: white;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
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
            background: linear-gradient(135deg, #219a52, #1e8749);
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
            background: linear-gradient(135deg, #0056b3, #004494);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.3);
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
        
        .search-hints {
            margin-top: 15px;
            font-size: 12px;
            color: #666;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            padding-top: 15px;
            border-top: 1px dashed #e0e0e0;
        }
        
        .search-hints i {
            color: #17a2b8;
            font-size: 14px;
        }
        
        .search-hints span {
            display: inline-block;
            background: #edf2f7;
            padding: 4px 10px;
            border-radius: 4px;
            margin: 0 3px;
            font-family: 'Courier New', monospace;
            font-size: 11px;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .search-hints span:hover {
            background: #e2e8f0;
            transform: translateY(-1px);
        }
        
        /* Search results info */
        .search-results-info {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .search-results-info p {
            margin: 0;
            color: #1565c0;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .search-results-info p i {
            font-size: 18px;
        }
        
        .search-results-info .clear-btn {
            padding: 8px 15px;
            font-size: 13px;
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
            min-width: 800px;
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
            border-bottom: 2px solid #007bff;
        }
        
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
        }
        
        /* Action buttons styling - UPDATED */
        .btn-action {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: 500;
            margin: 2px;
            min-width: 100px;
            justify-content: center;
        }
        
        .btn-personal {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
        }
        
        .btn-personal:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
        }
        
        .btn-employment {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }
        
        .btn-employment:hover {
            background: linear-gradient(135deg, #0056b3, #004494);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 123, 255, 0.3);
        }
        
        .btn-nok {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
        }
        
        .btn-nok:hover {
            background: linear-gradient(135deg, #138496, #117a8b);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(23, 162, 184, 0.3);
        }
        
        /* NEW BUTTON STYLES */
        .btn-qualification {
            background: linear-gradient(135deg, #9c27b0, #7b1fa2);
            color: white;
        }
        
        .btn-qualification:hover {
            background: linear-gradient(135deg, #7b1fa2, #6a1b9a);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(156, 39, 176, 0.3);
        }
        
        .btn-finance {
            background: linear-gradient(135deg, #ff9800, #f57c00);
            color: white;
        }
        
        .btn-finance:hover {
            background: linear-gradient(135deg, #f57c00, #ef6c00);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(255, 152, 0, 0.3);
        }
        
        /* NEW VIEW BUTTON STYLE */
        .btn-view {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
        }
        
        .btn-view:hover {
            background: linear-gradient(135deg, #5a6268, #495057);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(108, 117, 125, 0.3);
        }
        
        /* Action buttons container */
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 320px;
        }
        
        .action-row {
            display: flex;
            gap: 8px;
            justify-content: space-between;
        }
        
        /* Photo styling */
        .personnel-photo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e0e0e0;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .personnel-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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
            font-size: 12px;
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 10px;
            border-radius: 12px;
            display: inline-block;
            font-weight: 500;
            border: 1px solid #bbdefb;
        }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .action-buttons {
                min-width: 280px;
            }
            
            .btn-action {
                min-width: 85px;
                font-size: 11px;
                padding: 7px 10px;
            }
        }
        
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
            
            .stats-bar {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .action-buttons {
                min-width: 250px;
            }
            
            .action-row {
                flex-wrap: wrap;
            }
            
            .btn-action {
                min-width: 80px;
                font-size: 10px;
                padding: 6px 8px;
            }
        }
        
        @media (max-width: 768px) {
            .search-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-title {
                text-align: center;
                justify-content: center;
            }
            
            .search-results-info {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            
            .stats-bar {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                min-width: 200px;
            }
            
            .btn-action {
                min-width: 70px;
                font-size: 9px;
                padding: 5px 6px;
            }
            
            table {
                font-size: 12px;
            }
            
            table thead th,
            table tbody td {
                padding: 8px 10px;
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
            
            .action-buttons {
                min-width: 180px;
            }
            
            .action-row {
                flex-direction: column;
                gap: 5px;
            }
            
            .btn-action {
                width: 100%;
                min-width: auto;
                font-size: 11px;
                padding: 8px 10px;
            }
            
            table {
                min-width: 600px;
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
                    <h3>Personnel Enrolment - Update Records</h3>
                </div>
            </div>
            <div class="clearfix"></div>
            
            <!-- Professional Compact Search Section -->
            <div class="search-section">
                <div class="search-header">
                    
                    
                    <form method="GET" action="" class="search-form" id="searchForm">
                        <div class="search-input-container">
                            <input type="text" 
                                   class="search-input" 
                                   name="search" 
                                   id="searchInput"
                                   placeholder="Search by Service No, Name, or Rank..."
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
                <a href="?" class="clear-btn" style="padding: 8px 15px; font-size: 13px;">
                    <i class="fas fa-times"></i> Show All Records
                </a>
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
                            Showing <strong><?php echo $numb; ?></strong> of <strong><?php echo number_format($total_records); ?></strong> total personnel
                        </div>
                        <?php endif; ?>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Photo</th>
                                        <th>Service No.</th>
                                        <th>Surname</th>
                                        <th>First Name</th>
                                        <th>Middle Name</th>
                                        <th>Rank</th>
                                        <th>Update Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($retrieve && $numb > 0): ?>
                                        <?php 
                                        while ($ret = mysqli_fetch_array($retrieve)): 
                                            // Store original values before escaping
                                            $rawServiceNo = $ret['serviceNo'];
                                            $serviceNo = htmlspecialchars($rawServiceNo);
                                            $surname = htmlspecialchars($ret['surname']);
                                            $firstName = htmlspecialchars($ret['firstName']);
                                            $middleName = htmlspecialchars($ret['middleName']);
                                            $rank = htmlspecialchars($ret['currentRank']);
                                            
                                            // Check for profile photo - ADDED MULTIPLE FORMATS
                                            $photoPaths = [
                                                "upload/" . $rawServiceNo . ".png",
                                                "upload/" . $rawServiceNo . ".jpg",
                                                "upload/" . $rawServiceNo . ".jpeg",
                                                "upload/" . $rawServiceNo . ".PNG",
                                                "upload/" . $rawServiceNo . ".JPG",
                                                "upload/" . $rawServiceNo . ".JPEG",
                                                "upload/" . $rawServiceNo . ".gif",
                                                "upload/" . $rawServiceNo . ".GIF",
                                                "upload/" . $rawServiceNo . ".webp",
                                                "upload/" . $rawServiceNo . ".WEBP"
                                            ];
                                            
                                            $photoSrc = "upload/default.png";
                                            foreach ($photoPaths as $photoPath) {
                                                if (file_exists($photoPath)) {
                                                    $photoSrc = $photoPath;
                                                    break;
                                                }
                                            }
                                            
                                            // Highlight search terms if search query exists
                                            if (!empty($search_query)) {
                                                $searchPattern = '/(' . preg_quote($search_query, '/') . ')/i';
                                                $serviceNo = preg_replace($searchPattern, '<span class="highlight">$1</span>', $serviceNo);
                                                $surname = preg_replace($searchPattern, '<span class="highlight">$1</span>', $surname);
                                                $firstName = preg_replace($searchPattern, '<span class="highlight">$1</span>', $firstName);
                                                $middleName = preg_replace($searchPattern, '<span class="highlight">$1</span>', $middleName);
                                                $rank = preg_replace($searchPattern, '<span class="highlight">$1</span>', $rank);
                                            }
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="personnel-photo">
                                                    <img src="<?php echo $photoSrc; ?>" 
                                                         alt="<?php echo htmlspecialchars($surname . ' ' . $firstName); ?>"
                                                         onerror="this.onerror=null; this.src='upload/default.png';">
                                                </div>
                                            </td>
                                            <td><strong><?php echo $serviceNo; ?></strong></td>
                                            <td><?php echo $surname; ?></td>
                                            <td><?php echo $firstName; ?></td>
                                            <td><?php echo $middleName; ?></td>
                                            <td>
                                                <span class="rank-badge"><?php echo $rank; ?></span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <div class="action-row">
                                                        <button class="btn-action btn-personal" type="button" 
                                                                onclick="editPersonal('<?php echo addslashes($rawServiceNo); ?>')"
                                                                title="Update Personal Information for <?php echo addslashes($firstName . ' ' . $surname); ?>">
                                                            <i class="fas fa-user"></i> Personal
                                                        </button>
                                                        <button class="btn-action btn-employment" type="button" 
                                                                onclick="editEmployment('<?php echo addslashes($rawServiceNo); ?>')"
                                                                title="Update Employment Information for <?php echo addslashes($firstName . ' ' . $surname); ?>">
                                                            <i class="fas fa-edit"></i> Employment
                                                        </button>
                                                    </div>
                                                    <div class="action-row">
                                                        <button class="btn-action btn-nok" type="button" 
                                                                onclick="editNOK('<?php echo addslashes($rawServiceNo); ?>')"
                                                                title="Update Next of Kin Information for <?php echo addslashes($firstName . ' ' . $surname); ?>">
                                                            <i class="fas fa-users"></i> NOK
                                                        </button>
                                                        <button class="btn-action btn-qualification" type="button" 
                                                                onclick="editQualification('<?php echo addslashes($rawServiceNo); ?>')"
                                                                title="Update Qualification Information for <?php echo addslashes($firstName . ' ' . $surname); ?>">
                                                            <i class="fas fa-graduation-cap"></i> Qualification
                                                        </button>
                                                    </div>
                                                    <div class="action-row">
                                                        <button class="btn-action btn-finance" type="button" 
                                                                onclick="editFinance('<?php echo addslashes($rawServiceNo); ?>')"
                                                                title="Update Finance Information for <?php echo addslashes($firstName . ' ' . $surname); ?>">
                                                            <i class="fas fa-money-bill-wave"></i> Finance
                                                        </button>
                                                        <button class="btn-action btn-view" type="button" 
                                                                onclick="viewOfficer('<?php echo addslashes($rawServiceNo); ?>')"
                                                                title="View Complete Information for <?php echo addslashes($firstName . ' ' . $surname); ?>">
                                                            <i class="fas fa-eye"></i> View
                                                        </button>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" style="text-align: center; padding: 40px;">
                                                <div style="color: #666; max-width: 400px; margin: 0 auto;">
                                                    <i class="fas fa-users fa-3x" style="margin-bottom: 15px; opacity: 0.3;"></i>
                                                    <h4 style="margin: 0 0 10px 0;">
                                                        <?php if (!empty($search_query)): ?>
                                                            <i class="fas fa-search-minus"></i> No matching personnel found
                                                        <?php else: ?>
                                                            <i class="fas fa-database"></i> No personnel records found
                                                        <?php endif; ?>
                                                    </h4>
                                                    <p style="font-size: 14px; margin: 0;">
                                                        <?php if (!empty($search_query)): ?>
                                                            No personnel found for "<strong><?php echo htmlspecialchars($search_query); ?></strong>"<br>
                                                            Try a different search or <a href="?" style="color: #007bff; font-weight: 600;">view all records</a>
                                                        <?php else: ?>
                                                            There are no personnel records available for updates.
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
                            // Show page numbers
                            $startPage = max(1, $page - 2);
                            $endPage = min($total_pages, $page + 2);
                            
                            // Show first page if not in range
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
                            // Show last page if not in range
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
                                            style="padding: 8px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 5px;">
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
        
        // Set quick search value
        function setSearch(value) {
            document.getElementById('searchInput').value = value;
            document.getElementById('searchInput').focus();
        }
        
        // Edit functions
        function editPersonal(serviceNo) {
            showLoading();
            window.location.href = 'updatePersonal_info?id=' + encodeURIComponent(serviceNo);
        }
        
        function editEmployment(serviceNo) {
            showLoading();
            window.location.href = 'update_employment?id=' + encodeURIComponent(serviceNo);
        }
        
        function editNOK(serviceNo) {
            showLoading();
            window.location.href = 'update_nok?id=' + encodeURIComponent(serviceNo);
        }
        
        // NEW FUNCTIONS
        function editQualification(serviceNo) {
            showLoading();
            window.location.href = 'update_qualification?id=' + encodeURIComponent(serviceNo);
        }
        
        function editFinance(serviceNo) {
            showLoading();
            window.location.href = 'update_finance?id=' + encodeURIComponent(serviceNo);
        }
        
        // NEW VIEW FUNCTION
        function viewOfficer(serviceNo) {
            showLoading();
            window.location.href = 'view_employee?id=' + encodeURIComponent(serviceNo);
        }
        
        // Handle page load
        document.addEventListener('DOMContentLoaded', function() {
            // Hide loading after page loads
            setTimeout(function() {
                hideLoading();
            }, 500);
            
            // Add loading to pagination links
            document.querySelectorAll('.page-link[href]').forEach(link => {
                link.addEventListener('click', function(e) {
                    if (!this.classList.contains('disabled')) {
                        showLoading();
                    }
                });
            });
            
            // Add loading to form submit
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function() {
                    showLoading();
                });
            });
            
            // Focus search input on page load if it has a value
            <?php if (!empty($search_query)): ?>
                setTimeout(function() {
                    document.getElementById('searchInput').focus();
                    document.getElementById('searchInput').select();
                }, 100);
            <?php endif; ?>
            
            // Highlight search terms in table
            highlightSearchTerms();
        });
        
        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+F to focus search
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.getElementById('searchInput').focus().select();
            }
            // Escape to clear search if focused
            if (e.key === 'Escape' && document.activeElement === document.getElementById('searchInput')) {
                document.getElementById('searchInput').value = '';
                document.getElementById('searchInput').focus();
            }
            // Alt+P for personal, Alt+E for employment, Alt+N for NOK, Alt+Q for Qualification, Alt+F for Finance, Alt+V for View
            if (e.altKey) {
                switch(e.key.toLowerCase()) {
                    case 'p':
                        e.preventDefault();
                        const firstPersonalBtn = document.querySelector('.btn-personal');
                        if (firstPersonalBtn) {
                            firstPersonalBtn.focus();
                        }
                        break;
                    case 'e':
                        e.preventDefault();
                        const firstEmploymentBtn = document.querySelector('.btn-employment');
                        if (firstEmploymentBtn) {
                            firstEmploymentBtn.focus();
                        }
                        break;
                    case 'n':
                        e.preventDefault();
                        const firstNOKBtn = document.querySelector('.btn-nok');
                        if (firstNOKBtn) {
                            firstNOKBtn.focus();
                        }
                        break;
                    case 'q':
                        e.preventDefault();
                        const firstQualificationBtn = document.querySelector('.btn-qualification');
                        if (firstQualificationBtn) {
                            firstQualificationBtn.focus();
                        }
                        break;
                    case 'f':
                        e.preventDefault();
                        const firstFinanceBtn = document.querySelector('.btn-finance');
                        if (firstFinanceBtn) {
                            firstFinanceBtn.focus();
                        }
                        break;
                    case 'v':
                        e.preventDefault();
                        const firstViewBtn = document.querySelector('.btn-view');
                        if (firstViewBtn) {
                            firstViewBtn.focus();
                        }
                        break;
                }
            }
        });
        
        // Highlight search terms in table
        function highlightSearchTerms() {
            const searchQuery = "<?php echo addslashes($search_query); ?>";
            if (!searchQuery || searchQuery.length < 2) return;
            
            const tableRows = document.querySelectorAll('table tbody tr');
            const regex = new RegExp(`(${searchQuery.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
            
            tableRows.forEach(row => {
                const cells = row.querySelectorAll('td');
                cells.forEach(cell => {
                    // Skip cells that already have HTML content (like buttons, images, or status badges)
                    if (cell.innerHTML.includes('<button') || cell.innerHTML.includes('<img') || 
                        cell.innerHTML.includes('<span class="highlight') || 
                        cell.innerHTML.includes('<div class="personnel-photo') ||
                        cell.innerHTML.includes('<span class="rank-badge') ||
                        cell.innerHTML.includes('<div class="action-buttons')) {
                        return;
                    }
                    
                    const originalText = cell.textContent;
                    if (originalText && regex.test(originalText)) {
                        // Reset regex
                        regex.lastIndex = 0;
                        cell.innerHTML = originalText.replace(regex, '<span class="highlight">$1</span>');
                    }
                });
            });
        }
        
        // Make action buttons more accessible
        document.querySelectorAll('.btn-action').forEach(btn => {
            btn.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.click();
                }
            });
        });
    </script>
    
    <?php 
    // Clean up
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