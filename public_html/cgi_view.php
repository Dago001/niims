<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index");
    die();
}

require_once('db/db.php');
require_once('functions/posting_helper.php');

// Robust case-insensitive sidebar inclusion with fallback for Linux production environments
$user_role_clean = strtolower(trim($_SESSION['role'] ?? ''));

if ($user_role_clean === 'superadmin' || $user_role_clean === 'super_admin' || $user_role_clean === 'super admin') {
    include 'sidebar-superadmin.php';
} elseif ($user_role_clean === 'admin') {
    include 'sidebar-admin.php';
} elseif ($user_role_clean === 'supervisor') {
    include 'sidebar-supervisor.php';
} elseif ($user_role_clean === 'dataentry' || $user_role_clean === 'data_entry' || $user_role_clean === 'data entry') {
    include 'sidebar-dataentry.php';
} elseif ($user_role_clean === 'report' || $user_role_clean === 'view') {
    include 'sidebar-view.php';
} elseif ($user_role_clean === 'cgi') {
    include 'sidebar-cgi.php';
} elseif (file_exists('sidebar-admin.php')) {
    include 'sidebar-admin.php';
} else {
    include 'sidebar.php';
}


include 'menu-footer-buttons.php';
include 'top-navigation.php';

// OPTIMIZATION: Add pagination with prepared statements
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$records_per_page = 50;
$offset = ($page - 1) * $records_per_page;

// SEARCH FUNCTIONALITY with prepared statements
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = '';
$search_params = [];
$param_types = '';

if (!empty($search_query)) {
    $search_condition = "WHERE (emp.serviceNo LIKE ? OR 
                               emp.surname LIKE ? OR 
                               emp.firstName LIKE ? OR 
                               emp.middleName LIKE ? OR 
                               emp.phone LIKE ? OR
                               emp2.currentRank LIKE ? OR
                               emp2.presentPosting LIKE ? OR
                               emp.bloodType LIKE ?)";
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

// OPTIMIZED QUERY: Using prepared statements for security
$sqluse = "SELECT DISTINCT emp.serviceNo, emp.surname, emp.firstName, emp.phone,
                  emp.middleName, emp.bloodType, emp2.currentRank, emp2.presentPosting,
                  emp2.directorate, emp2.unit, emp2.posting_other
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
    <title>Personnel Enrolment - CGI View</title>
    
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
        
        /* SEARCH SECTION STYLES */
        .search-section {
            background: white;
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
            color: #3498db;
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
        
        /* Table improvements - maintaining original column structure */
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
        
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-view {
            padding: 8px 15px;
            background: linear-gradient(135deg, #27ae60, #219a52);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
            min-width: 90px;
        }
        
        .btn-view:hover {
            background: linear-gradient(135deg, #219a52, #1e8749);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
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
        
        /* Posting badge */
        .posting-badge {
            font-size: 11px;
            background: #fff3cd;
            color: #856404;
            padding: 3px 8px;
            border-radius: 10px;
            display: inline-block;
            border: 1px solid #ffeaa7;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        /* Phone number styling */
        .phone-number {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #2c3e50;
            font-weight: 500;
        }
        
        /* Name styling */
        .surname {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .first-name {
            font-weight: 500;
            color: #2c3e50;
        }
        
        .middle-name {
            font-weight: 400;
            color: #666;
        }
        
        /* Service number styling */
        .service-no {
            font-weight: 600;
            color: #2c3e50;
            font-family: 'Courier New', monospace;
        }
        
        /* Highlight search terms */
        .highlight {
            background-color: #fff9c4;
            font-weight: 600;
            padding: 2px 4px;
            border-radius: 3px;
            color: #333;
        }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            table {
                min-width: 1100px;
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
            
            .action-buttons {
                flex-direction: column;
                gap: 5px;
            }
            
            .btn-view {
                width: 100%;
                justify-content: center;
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
                    <h3>Personnel Records</h3>
                </div>
            </div>
            <div class="clearfix"></div>
            
            <!-- SEARCH SECTION -->
            <div class="search-section">
                <div class="search-header">
                    
                    
                    <form method="GET" action="cgi_view" class="search-form" id="searchForm">
                        <div class="search-input-container">
                            <input type="text" 
                                   class="search-input" 
                                   name="search" 
                                   id="searchInput"
                                   placeholder="Search by Service No, Name, Phone, Rank, Posting, or Blood Type..."
                                   value="<?php echo htmlspecialchars($search_query); ?>"
                                   autocomplete="off"
                                   aria-label="Search personnel records">
                            <i class="fas fa-search search-icon"></i>
                        </div>
                        
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i> Search
                        </button>
                        
                        <?php if (!empty($search_query)): ?>
                            <a href="cgi_view" class="clear-btn">
                                <i class="fas fa-times"></i> Clear Search
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
                    Search results for: <strong>"<?php echo htmlspecialchars($search_query); ?>"</strong>
                    (<?php echo number_format($total_records); ?> records found)
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
                            Showing <?php echo number_format($numb); ?> of <?php echo number_format($total_records); ?> total records
                            (Page <?php echo $page; ?> of <?php echo $total_pages; ?>)
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
                                        <th>Current Posting</th>
                                        <th>Phone No.</th>                          
                                        <th>Blood Type</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($retrieve && $numb > 0): ?>
                                        <?php while ($ret = mysqli_fetch_array($retrieve)): 
                                            // Store original values before escaping
                                            $rawServiceNo = $ret['serviceNo'];
                                            $serviceNo = htmlspecialchars($rawServiceNo);
                                            $surname = htmlspecialchars($ret['surname']);
                                            $firstName = htmlspecialchars($ret['firstName']);
                                            $middleName = htmlspecialchars($ret['middleName']);
                                            $rank = htmlspecialchars($ret['currentRank']);
                                            $posting = htmlspecialchars(formatPresentPosting(
                                                $ret['presentPosting'],
                                                $ret['directorate'] ?? '',
                                                $ret['unit'] ?? '',
                                                $ret['posting_other'] ?? ''
                                            ));
                                            $phone = htmlspecialchars($ret['phone']);
                                            $bloodType = htmlspecialchars($ret['bloodType']);
                                            
                                            // ============ UPDATED PHOTO FETCHING SECTION ============
                                            // Check for photos in multiple formats
                                            $defaultPhoto = "upload/default.png";
                                            $photoSrc = $defaultPhoto; // Default value
                                            
                                            // Array of possible file extensions to check
                                            $possibleExtensions = array('.png', '.jpg', '.jpeg', '.PNG', '.JPG', '.JPEG');
                                            
                                            // Check each extension
                                            foreach ($possibleExtensions as $ext) {
                                                $photoPath = "upload/" . $rawServiceNo . $ext;
                                                if (file_exists($photoPath)) {
                                                    $photoSrc = $photoPath;
                                                    break; // Stop checking once we find a match
                                                }
                                            }
                                            // ============ END UPDATED SECTION ============
                                            
                                            // Highlight search terms in the displayed data
                                            if (!empty($search_query)) {
                                                $searchPattern = '/(' . preg_quote($search_query, '/') . ')/i';
                                                $serviceNo = preg_replace($searchPattern, '<span class="highlight">$1</span>', $serviceNo);
                                                $surname = preg_replace($searchPattern, '<span class="highlight">$1</span>', $surname);
                                                $firstName = preg_replace($searchPattern, '<span class="highlight">$1</span>', $firstName);
                                                $middleName = preg_replace($searchPattern, '<span class="highlight">$1</span>', $middleName);
                                                $rank = preg_replace($searchPattern, '<span class="highlight">$1</span>', $rank);
                                                $posting = preg_replace($searchPattern, '<span class="highlight">$1</span>', $posting);
                                                $phone = preg_replace($searchPattern, '<span class="highlight">$1</span>', $phone);
                                                $bloodType = preg_replace($searchPattern, '<span class="highlight">$1</span>', $bloodType);
                                            }
                                            
                                            // Determine blood type class
                                            $bloodClass = '';
                                            if (!empty($bloodType)) {
                                                $bloodClass = strpos($bloodType, '-') !== false ? 'blood-negative' : 'blood-positive';
                                            }
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="personnel-photo">
                                                    <img src="<?php echo $photoSrc; ?>" 
                                                         alt="<?php echo htmlspecialchars($surname . ' ' . $firstName); ?>"
                                                         onerror="this.onerror=null; this.src='<?php echo $defaultPhoto; ?>';">
                                                </div>
                                            </td>
                                            <td><span class="service-no"><?php echo $serviceNo; ?></span></td>
                                            <td><span class="surname"><?php echo $surname; ?></span></td>
                                            <td><span class="first-name"><?php echo $firstName; ?></span></td>
                                            <td><span class="middle-name"><?php echo $middleName; ?></span></td>
                                            <td>
                                                <span class="rank-badge"><?php echo $rank; ?></span>
                                            </td>
                                            <td>
                                                <?php if (!empty($posting)): ?>
                                                    <span class="posting-badge" title="<?php echo $posting; ?>">
                                                        <?php echo $posting; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #95a5a6; font-size: 12px;">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($phone)): ?>
                                                    <span class="phone-number"><?php echo $phone; ?></span>
                                                <?php else: ?>
                                                    <span style="color: #95a5a6; font-size: 12px;">N/A</span>
                                                <?php endif; ?>
                                            </td>
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
                                                <div class="action-buttons">
                                                    <button class="btn-view" type="button" 
                                                            onclick="viewEmployee('<?php echo addslashes($rawServiceNo); ?>')"
                                                            title="View detailed information for <?php echo addslashes($firstName . ' ' . $surname); ?>">
                                                        <i class="fas fa-eye"></i> View Details
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="10" style="text-align: center; padding: 40px;">
                                                <div style="color: #666; max-width: 400px; margin: 0 auto;">
                                                    <i class="fas fa-users fa-3x" style="margin-bottom: 15px; opacity: 0.3;"></i>
                                                    <h4 style="margin: 0 0 10px 0;">
                                                        <?php if (!empty($search_query)): ?>
                                                            <i class="fas fa-search-minus"></i> No results found
                                                        <?php else: ?>
                                                            <i class="fas fa-database"></i> No personnel records found
                                                        <?php endif; ?>
                                                    </h4>
                                                    <p style="font-size: 14px; margin: 0;">
                                                        <?php if (!empty($search_query)): ?>
                                                            No personnel found for "<strong><?php echo htmlspecialchars($search_query); ?></strong>"<br>
                                                            Try a different search term or <a href="cgi_view" style="color: #27ae60; font-weight: 600;">view all records</a>
                                                        <?php else: ?>
                                                            There are no records to display.
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
                                <a href="cgi_view?page=1<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                                   class="page-link" title="First Page">
                                    <i class="fas fa-angle-double-left"></i> First
                                </a>
                                <a href="cgi_view?page=<?php echo $page - 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
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
                                echo '<a href="cgi_view?page=1' . (!empty($search_query) ? '&search=' . urlencode($search_query) : '') . '" 
                                      class="page-link" title="Page 1">1</a>';
                                if ($startPage > 2) {
                                    echo '<span class="page-link disabled">...</span>';
                                }
                            }
                            
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <a href="cgi_view?page=<?php echo $i; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
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
                                echo '<a href="cgi_view?page=' . $total_pages . (!empty($search_query) ? '&search=' . urlencode($search_query) : '') . '" 
                                      class="page-link" title="Page ' . $total_pages . '">' . $total_pages . '</a>';
                            }
                            ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="cgi_view?page=<?php echo $page + 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                                   class="page-link" title="Next Page">
                                    Next <i class="fas fa-angle-right"></i>
                                </a>
                                <a href="cgi_view?page=<?php echo $total_pages; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
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
                            <form method="GET" action="cgi_view" style="display: inline-block;">
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
        
        // View employee function
        function viewEmployee(serviceNo) {
            showLoading();
            
            // Redirect to view employee page
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
            // Left arrow for previous page
            if (e.key === 'ArrowLeft' && <?php echo $page; ?> > 1) {
                e.preventDefault();
                window.location.href = 'cgi_view?page=<?php echo $page - 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>';
            }
            // Right arrow for next page
            if (e.key === 'ArrowRight' && <?php echo $page; ?> < <?php echo $total_pages; ?>) {
                e.preventDefault();
                window.location.href = 'cgi_view?page=<?php echo $page + 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>';
            }
            // Alt+V to focus on first view button
            if (e.altKey && e.key === 'v') {
                e.preventDefault();
                const firstViewBtn = document.querySelector('.btn-view');
                if (firstViewBtn) {
                    firstViewBtn.focus();
                }
            }
        });
        
        // Auto-submit search when Enter is pressed
        document.getElementById('searchInput').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('searchForm').submit();
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
                    // Skip cells that already have HTML content (like buttons, images, or badges)
                    if (cell.innerHTML.includes('<button') || cell.innerHTML.includes('<img') || 
                        cell.innerHTML.includes('<span class="highlight') || 
                        cell.innerHTML.includes('<div class="personnel-photo') ||
                        cell.innerHTML.includes('<span class="rank-badge') ||
                        cell.innerHTML.includes('<span class="posting-badge') ||
                        cell.innerHTML.includes('<span class="blood-type') ||
                        cell.innerHTML.includes('<span class="phone-number') ||
                        cell.innerHTML.includes('<span class="surname') ||
                        cell.innerHTML.includes('<span class="first-name') ||
                        cell.innerHTML.includes('<span class="middle-name')) {
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
        
        // Make view buttons more accessible
        document.querySelectorAll('.btn-view').forEach(btn => {
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