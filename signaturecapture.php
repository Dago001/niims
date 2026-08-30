<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: index');
    exit;
}

require_once('db/db.php');

// Include appropriate sidebar based on role
if ($_SESSION['role'] == "DataEntry") {
    include 'sidebar-dataentry.php';
}
if ($_SESSION['role'] == "Admin") {
    include 'sidebar-admin.php';
}
if ($_SESSION['role'] == "SuperAdmin") {
    include 'sidebar-superadmin.php';
}
if ($_SESSION['role'] == "Supervisor") {
    include 'sidebar-supervisor.php';
}
if ($_SESSION['role'] == "Biometric") {
    include 'sidebar-biometric.php';
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
$sqluse = "SELECT DISTINCT emp.serviceNo, emp.surname, emp.firstName, emp.middleName, 
                  emp.bloodType, emp2.currentRank
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

// Define signature folder path
$signature_folder = 'signature'; // Folder where signature files are stored

// Get signature statistics by checking files in the signature folder
$total_with_signature = 0;
$total_without_signature = 0;

// Count signature files (assuming they are named as serviceNo.png or serviceNo.jpg)
if ($retrieve && $numb > 0) {
    mysqli_data_seek($retrieve, 0); // Reset result pointer
    while ($ret = mysqli_fetch_array($retrieve)) {
        $serviceNo = $ret['serviceNo'];
        
        // Check for signature files with common extensions
        $signature_file_png = $signature_folder . '/' . $serviceNo . '.png';
        $signature_file_jpg = $signature_folder . '/' . $serviceNo . '.jpg';
        $signature_file_jpeg = $signature_folder . '/' . $serviceNo . '.jpeg';
        $signature_file_svg = $signature_folder . '/' . $serviceNo . '.svg';
        $signature_file_gif = $signature_folder . '/' . $serviceNo . '.gif';
        
        if (file_exists($signature_file_png) || 
            file_exists($signature_file_jpg) || 
            file_exists($signature_file_jpeg) ||
            file_exists($signature_file_svg) ||
            file_exists($signature_file_gif)) {
            $total_with_signature++;
        } else {
            $total_without_signature++;
        }
    }
    mysqli_data_seek($retrieve, 0); // Reset pointer again for display
}
?> 

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signature Capture - Enrolled Personnel</title>
    
    <!-- SweetAlert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
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
            border-top: 4px solid #28a745;
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
            background: #28a745;
            color: white;
            border-color: #28a745;
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
            color: #28a745;
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
            border-color: #28a745;
            background: white;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
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
            background: linear-gradient(135deg, #28a745, #218838);
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
            background: linear-gradient(135deg, #218838, #1e7e34);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
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
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
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
            min-width: 900px;
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
            border-bottom: 2px solid #28a745;
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
        
        /* Signature preview styling */
        .signature-preview {
            width: 150px;
            height: 60px;
            border: 1px solid #ddd;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            overflow: hidden;
        }
        
        .signature-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        /* Edit button styling */
        .btn-edit {
            padding: 8px 15px;
            background: linear-gradient(135deg, #28a745, #218838);
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
        }
        
        .btn-edit:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }
        
        /* Signature status indicator */
        .signature-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: 500;
        }
        
        .signature-status.has-signature {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .signature-status.no-signature {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
        
        /* Statistics card */
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            padding: 20px;
            color: white;
            margin-bottom: 20px;
        }
        
        .stats-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stats-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .stat-item {
            text-align: center;
            flex: 1;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 13px;
            opacity: 0.9;
        }
        
        .stat-divider {
            width: 1px;
            height: 40px;
            background: rgba(255, 255, 255, 0.3);
        }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            table {
                min-width: 1000px;
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
            
            .stats-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .stat-divider {
                display: none;
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
                    <h3>Signature Capture - Enrolled Personnel</h3>
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
                
                <a href="?" class="clear-btn">
                    <i class="fas fa-times"></i> Clear Search
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
                                        <th>Service No.</th>
                                        <th>Surname</th>
                                        <th>First Name</th>
                                        <th>Middle Name</th>
                                        <th>Rank</th>
                                        <th>Signature Preview</th>
                                        <th>Status</th>
                                        <th>Actions</th>
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
                                            
                                            // Check for signature files in the signature folder
                                            $signature_file_png = $signature_folder . '/' . $rawServiceNo . '.png';
                                            $signature_file_jpg = $signature_folder . '/' . $rawServiceNo . '.jpg';
                                            $signature_file_jpeg = $signature_folder . '/' . $rawServiceNo . '.jpeg';
                                            $signature_file_svg = $signature_folder . '/' . $rawServiceNo . '.svg';
                                            $signature_file_gif = $signature_folder . '/' . $rawServiceNo . '.gif';
                                            
                                            $hasSignature = file_exists($signature_file_png) || 
                                                           file_exists($signature_file_jpg) || 
                                                           file_exists($signature_file_jpeg) ||
                                                           file_exists($signature_file_svg) ||
                                                           file_exists($signature_file_gif);
                                            
                                            // Get the actual signature file path if exists (WITH CACHE-BUSTING)
                                            $signature_path = '';
                                            if (file_exists($signature_file_png)) {
                                                $signature_path = $signature_file_png . '?v=' . filemtime($signature_file_png);
                                            } elseif (file_exists($signature_file_jpg)) {
                                                $signature_path = $signature_file_jpg . '?v=' . filemtime($signature_file_jpg);
                                            } elseif (file_exists($signature_file_jpeg)) {
                                                $signature_path = $signature_file_jpeg . '?v=' . filemtime($signature_file_jpeg);
                                            } elseif (file_exists($signature_file_svg)) {
                                                $signature_path = $signature_file_svg . '?v=' . filemtime($signature_file_svg);
                                            } elseif (file_exists($signature_file_gif)) {
                                                $signature_path = $signature_file_gif . '?v=' . filemtime($signature_file_gif);
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
                                        <tr data-service-no="<?php echo htmlspecialchars($rawServiceNo); ?>">
                                            <td><span class="service-no"><?php echo $serviceNo; ?></span></td>
                                            <td><span class="surname"><?php echo $surname; ?></span></td>
                                            <td><span class="first-name"><?php echo $firstName; ?></span></td>
                                            <td><span class="middle-name"><?php echo $middleName; ?></span></td>
                                            <td>
                                                <span class="rank-badge"><?php echo $rank; ?></span>
                                            </td>
                                            <td>
                                                <?php if ($hasSignature): ?>
                                                    <div class="signature-preview">
                                                        <img src="<?php echo htmlspecialchars($signature_path); ?>" 
                                                             alt="Signature of <?php echo $surname . ', ' . $firstName; ?>"
                                                             title="Signature of <?php echo $surname . ', ' . $firstName; ?>">
                                                    </div>
                                                <?php else: ?>
                                                    <div class="signature-preview" style="background: #f8f9fa; color: #6c757d; font-size: 12px;">
                                                        <i class="fas fa-signature" style="font-size: 20px; opacity: 0.3; margin-right: 5px;"></i>
                                                        No Signature
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="signature-status <?php echo $hasSignature ? 'has-signature' : 'no-signature'; ?>" 
                                                      data-service-no="<?php echo $rawServiceNo; ?>"
                                                      id="status-<?php echo $rawServiceNo; ?>">
                                                    <i class="fas fa-<?php echo $hasSignature ? 'check-circle' : 'times-circle'; ?>"></i>
                                                    <?php 
                                                    if ($hasSignature && $signature_path) {
                                                        // Get file modification time
                                                        $file_time = filemtime(strtok($signature_path, '?')); // Remove ?v= for filemtime
                                                        $date_captured = date('d/m/Y H:i', $file_time);
                                                        echo 'Captured: ' . $date_captured;
                                                    } else {
                                                        echo 'No Signature';
                                                    }
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn-edit" type="button" 
                                                        onclick="editSignature('<?php echo addslashes($rawServiceNo); ?>')"
                                                        title="<?php echo $hasSignature ? 'Update Signature for' : 'Capture Signature for'; ?> <?php echo addslashes($surname . ', ' . $firstName); ?>"
                                                        data-service-no="<?php echo $rawServiceNo; ?>"
                                                        id="btn-<?php echo $rawServiceNo; ?>">
                                                    <i class="fas fa-pencil-alt"></i> 
                                                    <?php echo $hasSignature ? 'Update Signature' : 'Capture Signature'; ?>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" style="text-align: center; padding: 40px;">
                                                <div style="color: #666; max-width: 400px; margin: 0 auto;">
                                                    <i class="fas fa-signature fa-3x" style="margin-bottom: 15px; opacity: 0.3;"></i>
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
                                                            Try a different search or <a href="?" style="color: #28a745; font-weight: 600;">view all records</a>
                                                        <?php else: ?>
                                                            There are no personnel records available for signature capture.
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
                                            style="padding: 8px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 5px;">
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
    
    <!-- SweetAlert Success Notification -->
    <?php if (isset($_SESSION['signature_success'])): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: '<?php echo $_SESSION['signature_success']; ?>',
            confirmButtonColor: '#28a745',
            confirmButtonText: 'OK',
            timer: 3000,
            timerProgressBar: true
        });
        // Clear the session variable after displaying
        <?php unset($_SESSION['signature_success']); ?>
    });
    </script>
    <?php endif; ?>

    <!-- SweetAlert Error Notification -->
    <?php if (isset($_SESSION['signature_error'])): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'error',
            title: 'Signature Capture Failed!',
            text: '<?php echo $_SESSION['signature_error']; ?>',
            confirmButtonColor: '#e74c3c',
            confirmButtonText: 'OK',
            timer: 5000,
            timerProgressBar: true
        });
        <?php unset($_SESSION['signature_error']); ?>
    });
    </script>
    <?php endif; ?>

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
        
        // ============ FIX 1: UPDATED EDIT SIGNATURE FUNCTION ============
        // Edit signature function
        function editSignature(serviceNo) {
            showLoading();
            
            // Store in sessionStorage to detect when we return
            sessionStorage.setItem('updatedSignature', serviceNo);
            sessionStorage.setItem('updatedSignatureTime', Date.now());
            
            // Redirect to signature capture page
            window.location.href = 'sign?id=' + encodeURIComponent(serviceNo);
        }
        // ============ END FIX 1 ============
        
        // Function to check signature status via AJAX
        function checkSignatureStatus(serviceNo) {
            const formData = new FormData();
            formData.append('serviceNo', serviceNo);
            formData.append('check_signature', 'true');
            
            fetch('check_signature.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const statusElement = document.getElementById('status-' + serviceNo);
                    const buttonElement = document.getElementById('btn-' + serviceNo);
                    const row = document.querySelector('[data-service-no="' + serviceNo + '"]');
                    const previewElement = row ? row.querySelector('.signature-preview') : null;
                    
                    if (statusElement) {
                        if (data.hasSignature) {
                            statusElement.className = 'signature-status has-signature';
                            statusElement.innerHTML = '<i class="fas fa-check-circle"></i> Captured: ' + data.timestamp;
                            
                            if (previewElement && data.signaturePath) {
                                // Add cache-busting timestamp
                                previewElement.innerHTML = '<img src="' + data.signaturePath + '?v=' + Date.now() + '" alt="Signature" title="Signature">';
                            }
                            
                            if (buttonElement) {
                                buttonElement.innerHTML = '<i class="fas fa-pencil-alt"></i> Update Signature';
                                buttonElement.title = 'Update Signature for ' + serviceNo;
                            }
                        } else {
                            statusElement.className = 'signature-status no-signature';
                            statusElement.innerHTML = '<i class="fas fa-times-circle"></i> No Signature';
                            
                            if (previewElement) {
                                previewElement.style.background = '#f8f9fa';
                                previewElement.style.color = '#6c757d';
                                previewElement.style.fontSize = '12px';
                                previewElement.innerHTML = '<i class="fas fa-signature" style="font-size: 20px; opacity: 0.3; margin-right: 5px;"></i>No Signature';
                            }
                            
                            if (buttonElement) {
                                buttonElement.innerHTML = '<i class="fas fa-pencil-alt"></i> Capture Signature';
                                buttonElement.title = 'Capture Signature for ' + serviceNo;
                            }
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error checking signature status:', error);
            });
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
            
            // ============ FIX 2: CHECK FOR UPDATED SIGNATURE ============
            // Check for updated signature when returning from capture page
            var updatedServiceNo = sessionStorage.getItem('updatedSignature');
            var updatedTime = sessionStorage.getItem('updatedSignatureTime');
            
            if (updatedServiceNo && updatedTime && (Date.now() - parseInt(updatedTime) < 300000)) {
                // Find the row with this service number
                var row = document.querySelector('[data-service-no="' + updatedServiceNo + '"]');
                if (row) {
                    // Update the signature preview
                    var previewElement = row.querySelector('.signature-preview');
                    if (previewElement) {
                        var img = previewElement.querySelector('img');
                        if (img) {
                            var baseSrc = img.src.split('?')[0];
                            img.src = baseSrc + '?v=' + Date.now();
                        } else {
                            // If there was no signature before, create a new image
                            var signaturePath = 'signature/' + updatedServiceNo + '.png';
                            previewElement.style.background = 'white';
                            previewElement.style.color = '';
                            previewElement.style.fontSize = '';
                            previewElement.innerHTML = '<img src="' + signaturePath + '?v=' + Date.now() + '" alt="Signature" title="Signature">';
                        }
                    }
                    
                    // Update status
                    var statusElement = document.getElementById('status-' + updatedServiceNo);
                    if (statusElement) {
                        statusElement.className = 'signature-status has-signature';
                        var now = new Date();
                        var dateStr = ('0' + now.getDate()).slice(-2) + '/' + ('0' + (now.getMonth() + 1)).slice(-2) + '/' + now.getFullYear() + ' ' + ('0' + now.getHours()).slice(-2) + ':' + ('0' + now.getMinutes()).slice(-2);
                        statusElement.innerHTML = '<i class="fas fa-check-circle"></i> Captured: ' + dateStr;
                    }
                    
                    // Update button
                    var buttonElement = document.getElementById('btn-' + updatedServiceNo);
                    if (buttonElement) {
                        buttonElement.innerHTML = '<i class="fas fa-pencil-alt"></i> Update Signature';
                        buttonElement.title = 'Update Signature for ' + updatedServiceNo;
                    }
                }
                
                // Clear after refresh
                setTimeout(function() {
                    sessionStorage.removeItem('updatedSignature');
                    sessionStorage.removeItem('updatedSignatureTime');
                }, 1000);
            }
            // ============ END FIX 2 ============
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
            // Alt+E to focus on first edit button
            if (e.altKey && e.key === 'e') {
                e.preventDefault();
                const firstEditBtn = document.querySelector('.btn-edit');
                if (firstEditBtn) {
                    firstEditBtn.focus();
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
                    // Skip cells that already have HTML content
                    if (cell.innerHTML.includes('<button') || cell.innerHTML.includes('<img') || 
                        cell.innerHTML.includes('<span class="highlight') || 
                        cell.innerHTML.includes('<span class="rank-badge') ||
                        cell.innerHTML.includes('<span class="signature-status') ||
                        cell.innerHTML.includes('<span class="surname') ||
                        cell.innerHTML.includes('<span class="first-name') ||
                        cell.innerHTML.includes('<span class="middle-name') ||
                        cell.innerHTML.includes('<span class="service-no') ||
                        cell.innerHTML.includes('class="signature-preview"')) {
                        return;
                    }
                    
                    const originalText = cell.textContent;
                    if (originalText && regex.test(originalText)) {
                        regex.lastIndex = 0;
                        cell.innerHTML = originalText.replace(regex, '<span class="highlight">$1</span>');
                    }
                });
            });
        }
        
        // Make edit buttons more accessible
        document.querySelectorAll('.btn-edit').forEach(btn => {
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
    if (isset($stats_result)) {
        mysqli_free_result($stats_result);
    }
    ?>
    
    <?php include 'footer_tables.php'; ?>
</body>
</html>