<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index");
    die();
}

require_once('db/db.php');
require_once('functions/posting_helper.php');

// Include print logging function
include_once 'includes/log_print.php';

// Include appropriate sidebar based on role
if ($_SESSION['role'] == "Admin") {
    include 'sidebar-admin.php';
}
if ($_SESSION['role'] == "SuperAdmin") {
    include 'sidebar-superadmin.php';
}

if ($_SESSION['role'] == "Supervisor") {
    include 'sidebar-supervisor.php';
}

if ($_SESSION['role'] == "CGI") {
    include 'sidebar-cgi.php';
}

include 'menu-footer-buttons.php';
include 'top-navigation.php';

// OPTIMIZATION: Add pagination with prepared statements
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$records_per_page = 50;
$offset = ($page - 1) * $records_per_page;

// Initialize filters
$filters = [
    'posting' => isset($_GET['posting']) ? trim($_GET['posting']) : '',
    'rank' => isset($_GET['rank']) ? trim($_GET['rank']) : '',
    'serviceNo' => isset($_GET['serviceNo']) ? trim($_GET['serviceNo']) : '',
    'confirmStatus' => isset($_GET['confirmStatus']) ? trim($_GET['confirmStatus']) : ''
];

// Get distinct postings for dropdown
$postings_query = "SELECT DISTINCT presentPosting FROM tbl_employment WHERE presentPosting IS NOT NULL AND presentPosting != '' ORDER BY presentPosting ASC";
$postings_result = mysqli_query($conn, $postings_query);
$postings = [];
while ($row = mysqli_fetch_assoc($postings_result)) {
    $postings[] = $row['presentPosting'];
}

// Get distinct ranks for dropdown
$ranks_query = "SELECT DISTINCT currentRank FROM tbl_employment WHERE currentRank IS NOT NULL AND currentRank != '' ORDER BY 
                CASE currentRank
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
                END ASC";
$ranks_result = mysqli_query($conn, $ranks_query);
$ranks = [];
while ($row = mysqli_fetch_assoc($ranks_result)) {
    $ranks[] = $row['currentRank'];
}

// Build WHERE conditions based on filters - FIXED CONFIRM STATUS LOGIC
$where_conditions = [];
$where_params = [];
$param_types = '';

if (!empty($filters['posting'])) {
    $where_conditions[] = "emp2.presentPosting = ?";
    $where_params[] = $filters['posting'];
    $param_types .= 's';
}

if (!empty($filters['rank'])) {
    $where_conditions[] = "emp2.currentRank = ?";
    $where_params[] = $filters['rank'];
    $param_types .= 's';
}

if (!empty($filters['serviceNo'])) {
    $where_conditions[] = "emp.serviceNo LIKE ?";
    $where_params[] = '%' . $filters['serviceNo'] . '%';
    $param_types .= 's';
}

// FIXED: Add confirm status filter - using prepared statement parameters
if (!empty($filters['confirmStatus'])) {
    if ($filters['confirmStatus'] === 'confirmed') {
        // For confirmed, we need to check for various forms of "yes" or "confirmed"
        $where_conditions[] = "(emp.confirmed_details LIKE ? OR emp.confirmed_details LIKE ? OR emp.confirmed_details LIKE ? OR emp.confirmed_details LIKE ?)";
        $where_params[] = '%yes%';
        $where_params[] = '%confirmed%';
        $where_params[] = '%Yes%';
        $where_params[] = '%Confirmed%';
        $param_types .= 'ssss';
    } elseif ($filters['confirmStatus'] === 'not_confirmed') {
        // For not confirmed, we need to check for NULL, empty, or not containing confirm keywords
        $where_conditions[] = "(emp.confirmed_details IS NULL OR emp.confirmed_details = '' OR (emp.confirmed_details NOT LIKE ? AND emp.confirmed_details NOT LIKE ? AND emp.confirmed_details NOT LIKE ? AND emp.confirmed_details NOT LIKE ?))";
        $where_params[] = '%yes%';
        $where_params[] = '%confirmed%';
        $where_params[] = '%Yes%';
        $where_params[] = '%Confirmed%';
        $param_types .= 'ssss';
    }
}

// Build WHERE clause
$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get total count for pagination
$count_sql = "SELECT COUNT(DISTINCT emp.serviceNo) as total 
              FROM tbl_emppersonal emp 
              INNER JOIN tbl_employment emp2 ON emp.serviceNo = emp2.serviceNo";
              
if (!empty($where_clause)) {
    $count_sql .= " " . $where_clause;
}

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

// OPTIMIZED QUERY: Using prepared statements for security - NOW INCLUDING confirmed_details
$sqluse = "SELECT DISTINCT emp.serviceNo, emp.surname, emp.firstName, emp.phone,
                  emp.middleName, emp.bloodType, emp.signature, emp.confirmed_details,
                  emp2.currentRank, emp2.presentPosting, emp2.directorate, emp2.unit, emp2.posting_other
           FROM tbl_emppersonal emp
           INNER JOIN tbl_employment emp2 ON emp.serviceNo = emp2.serviceNo";
           
if (!empty($where_clause)) {
    $sqluse .= " " . $where_clause;
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

// Add pagination parameters - FIXED: Only add if we have a where clause
if (!empty($where_clause)) {
    $where_params[] = $records_per_page;
    $where_params[] = $offset;
    $param_types .= 'ii'; // Add integer types for LIMIT and OFFSET
}

// Prepare and execute main query
$stmt = mysqli_prepare($conn, $sqluse);

if (!empty($where_clause)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$where_params);
} else {
    // If no where clause, we still need to bind pagination parameters
    mysqli_stmt_bind_param($stmt, 'ii', $records_per_page, $offset);
}

mysqli_stmt_execute($stmt);
$retrieve = mysqli_stmt_get_result($stmt);
$numb = $retrieve ? mysqli_num_rows($retrieve) : 0;

// Prepare selected records for JavaScript from ALL filtered records (not just current page)
// We need to create a separate query without LIMIT/OFFSET for counting
$all_selected_query = "SELECT DISTINCT emp.serviceNo, emp.surname, emp.firstName, 
                              emp2.currentRank, emp.confirmed_details
                       FROM tbl_emppersonal emp 
                       INNER JOIN tbl_employment emp2 ON emp.serviceNo = emp2.serviceNo";
                       
if (!empty($where_clause)) {
    $all_selected_query .= " " . $where_clause;
}

$all_selected_query .= " ORDER BY 
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
                       END ASC, emp.serviceNo ASC";

$all_selected_stmt = mysqli_prepare($conn, $all_selected_query);
if (!empty($where_clause)) {
    // Remove the LIMIT and OFFSET parameters for the all records query
    // We need to create fresh parameters without the pagination ones
    $all_selected_params = [];
    $all_selected_types = '';
    
    // Rebuild parameters for the confirm status filter
    if (!empty($filters['posting'])) {
        $all_selected_params[] = $filters['posting'];
        $all_selected_types .= 's';
    }
    
    if (!empty($filters['rank'])) {
        $all_selected_params[] = $filters['rank'];
        $all_selected_types .= 's';
    }
    
    if (!empty($filters['serviceNo'])) {
        $all_selected_params[] = '%' . $filters['serviceNo'] . '%';
        $all_selected_types .= 's';
    }
    
    // Handle confirm status parameters
    if (!empty($filters['confirmStatus'])) {
        if ($filters['confirmStatus'] === 'confirmed') {
            $all_selected_params[] = '%yes%';
            $all_selected_params[] = '%confirmed%';
            $all_selected_params[] = '%Yes%';
            $all_selected_params[] = '%Confirmed%';
            $all_selected_types .= 'ssss';
        } elseif ($filters['confirmStatus'] === 'not_confirmed') {
            $all_selected_params[] = '%yes%';
            $all_selected_params[] = '%confirmed%';
            $all_selected_params[] = '%Yes%';
            $all_selected_params[] = '%Confirmed%';
            $all_selected_types .= 'ssss';
        }
    }
    
    if (!empty($all_selected_params)) {
        mysqli_stmt_bind_param($all_selected_stmt, $all_selected_types, ...$all_selected_params);
    }
}
mysqli_stmt_execute($all_selected_stmt);
$all_retrieve = mysqli_stmt_get_result($all_selected_stmt);
$total_filtered_records = $all_retrieve ? mysqli_num_rows($all_retrieve) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk ID Card Printing</title>
    
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
        
        /* Native Gentelella Styling Tweaks & Uniform Font Scaling */
        .x_panel .x_title h2 {
            font-size: 16px !important;
            font-weight: 600 !important;
            margin: 0 !important;
        }
        .x_panel .x_title h2 small {
            font-size: 12px !important;
            font-weight: normal !important;
            color: #73879C !important;
        }
        .x_panel label {
            font-size: 12px !important;
            font-weight: 600 !important;
            color: #5A738E !important;
            margin-bottom: 5px !important;
        }
        .x_panel .form-control {
            font-size: 12px !important;
            height: 32px !important;
            padding: 4px 8px !important;
            border-radius: 3px !important;
            border: 1px solid #ccc !important;
        }
        .x_panel .btn {
            font-size: 12px !important;
            padding: 5px 12px !important;
            border-radius: 3px !important;
        }
        .filter-badge {
            display: inline-block;
            margin-right: 5px;
            margin-bottom: 5px;
        }
        
        /* Results Section */
        .results-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid #e0e0e0;
        }
        
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .results-title {
            color: #2c3e50;
            font-size: 20px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .results-title i {
            color: #27ae60;
        }
        
        .results-info {
            background: #f8f9fa;
            padding: 12px 25px;
            border-radius: 30px;
            font-size: 14px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .results-info strong {
            color: #27ae60;
            font-size: 16px;
        }
        
        /* Checkbox styling */
        .checkbox-container {
            display: flex;
            align-items: center;
            margin-bottom: 0;
        }
        
        .form-check-input {
            width: 20px;
            height: 20px;
            border: 2px solid #e0e0e0;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
        }
        
        .form-check-input:checked {
            background-color: #27ae60;
            border-color: #27ae60;
        }
        
        .form-check-input:focus {
            box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.15);
        }
        
        /* Table Styling - UPDATED COLUMN WIDTHS */
        .table-responsive {
            overflow-x: auto;
            border-radius: 10px;
            border: 1px solid #e0e0e0;
            margin-bottom: 20px;
            background: white;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1100px;
        }
        
        table thead {
            background: linear-gradient(135deg, #2c3e50, #34495e);
        }
        
        table thead th {
            color: white;
            padding: 15px 12px; /* Reduced padding */
            text-align: left;
            font-weight: 600;
            font-size: 13px; /* Smaller font */
            border-bottom: 2px solid #27ae60;
        }
        
        /* UPDATED COLUMN WIDTHS */
        table thead th:nth-child(1) { /* Checkbox */
            width: 50px;
        }
        
        table thead th:nth-child(2) { /* Photo */
            width: 70px;
        }
        
        table thead th:nth-child(3) { /* Service No. */
            width: 110px;
        }
        
        table thead th:nth-child(4) { /* Surname */
            width: 120px;
        }
        
        table thead th:nth-child(5) { /* First Name */
            width: 110px;
        }
        
        table thead th:nth-child(6) { /* Middle Name */
            width: 100px;
        }
        
        table thead th:nth-child(7) { /* Rank */
            width: 160px; /* Reduced */
        }
        
        table thead th:nth-child(8) { /* Current Posting */
            width: 180px; /* Reduced */
        }
        
        table thead th:nth-child(9) { /* Confirm */
            width: 100px;
        }
        
        table thead th:nth-child(10) { /* Blood Type */
            width: 100px;
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
            padding: 12px; /* Reduced padding */
            font-size: 12px; /* Smaller font */
            color: #333;
            vertical-align: middle;
        }
        
        /* Table improvements */
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
        
        /* Rank badge - more compact */
        .rank-badge {
            font-size: 11px; /* Smaller */
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 10px; /* Reduced padding */
            border-radius: 10px;
            display: inline-block;
            font-weight: 500;
            border: 1px solid #bbdefb;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 150px;
        }
        
        /* Posting badge - more compact */
        .posting-badge {
            font-size: 10px; /* Smaller */
            background: #fff3cd;
            color: #856404;
            padding: 3px 8px; /* Reduced padding */
            border-radius: 8px;
            display: inline-block;
            border: 1px solid #ffeaa7;
            max-width: 170px; /* Reduced */
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        /* Blood Type - moved to last */
        .blood-type {
            font-weight: bold;
            font-size: 12px; /* Smaller */
            padding: 4px 8px; /* Reduced padding */
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
        
        /* Confirm column styling - BEFORE Blood Type */
        .confirm-status {
            font-size: 11px; /* Smaller */
            padding: 5px 10px; /* Reduced padding */
            border-radius: 4px;
            display: inline-block;
            font-weight: 600;
            text-align: center;
            min-width: 90px;
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
        
        /* Service number styling */
        .service-no {
            font-weight: 600;
            color: #2c3e50;
            font-family: 'Courier New', monospace;
            font-size: 12px; /* Smaller */
        }
        
        /* Name styling - more compact */
        .surname {
            font-weight: 600;
            color: #2c3e50;
            font-size: 12px; /* Smaller */
        }
        
        .first-name {
            font-weight: 500;
            color: #2c3e50;
            font-size: 12px; /* Smaller */
        }
        
        .middle-name {
            font-weight: 400;
            color: #666;
            font-size: 12px; /* Smaller */
        }
        
        /* Selection Actions */
        .selection-actions {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            border: 1px solid #e0e0e0;
        }
        
        .selection-info {
            font-size: 14px; /* Slightly smaller */
            color: #2c3e50;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .selection-info i {
            color: #27ae60;
            font-size: 16px;
        }
        
        .selection-info span {
            color: #27ae60;
            font-weight: bold;
            font-size: 15px;
        }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        /* Print Progress Modal */
        .print-progress {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            z-index: 9999;
            min-width: 300px;
            max-width: 500px;
        }
        
        .progress-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .progress-header h5 {
            color: #2c3e50;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: center;
        }
        
        .progress-bar {
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            margin: 20px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #27ae60, #219a52);
            width: 0%;
            transition: width 0.3s ease;
            border-radius: 4px;
        }
        
        .progress-text {
            text-align: center;
            color: #666;
            font-size: 14px;
            margin-top: 15px;
            line-height: 1.5;
        }
        
        /* Notification */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 8px;
            color: white;
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            animation: slideIn 0.3s ease;
        }
        
        .notification.success {
            background: linear-gradient(135deg, #27ae60, #219a52);
        }
        
        .notification.error {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }
        
        .notification.warning {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #333;
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
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 60px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-state h4 {
            color: #666;
            margin-bottom: 10px;
            font-weight: 600;
            font-size: 18px;
        }
        
        .empty-state p {
            max-width: 500px;
            margin: 0 auto 20px;
            line-height: 1.6;
            font-size: 14px;
        }
        
        /* Active Filter Badges */
        .active-filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        
        .filter-badge {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid #c8e6c9;
        }
        
        .filter-badge.confirm-badge {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .filter-badge.not-confirm-badge {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .filter-badge i {
            font-size: 11px;
            cursor: pointer;
        }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            table {
                min-width: 1000px;
            }
        }
        
        @media (max-width: 992px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .selection-actions {
                flex-direction: column;
                text-align: center;
            }
            
            .action-buttons {
                width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            .filter-section,
            .results-section {
                padding: 20px;
            }
            
            .filter-header {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
                gap: 15px;
            }
            
            .results-header {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 576px) {
            .table-responsive {
                border-radius: 8px;
            }
            
            table {
                font-size: 11px;
            }
            
            table thead th,
            table tbody td {
                padding: 10px 8px;
            }
            
            .pagination {
                flex-direction: column;
                align-items: center;
            }
            
            .page-link {
                width: 100%;
                justify-content: center;
            }
            
            .confirm-status {
                font-size: 10px;
                padding: 4px 8px;
                min-width: 80px;
            }
            
            .rank-badge {
                font-size: 10px;
                padding: 3px 8px;
                max-width: 120px;
            }
            
            .posting-badge {
                font-size: 9px;
                padding: 2px 6px;
                max-width: 140px;
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
        <p>Processing...</p>
    </div>
    
    <!-- Print Progress Modal -->
    <div class="print-progress" id="printProgress">
        <div class="progress-header">
            <h5>
                <i class="fas fa-print"></i> Printing Cards
            </h5>
        </div>
        <div class="progress-bar">
            <div class="progress-fill" id="progressFill"></div>
        </div>
        <div class="progress-text" id="progressText">
            Preparing to print... (0/0)
        </div>
        <div style="text-align: center; margin-top: 20px;">
            <button class="btn btn-secondary btn-sm" onclick="cancelPrint()" style="padding: 8px 20px;">
                <i class="fas fa-times"></i> Cancel Printing
            </button>
        </div>
    </div>
    
    <div class="right_col" role="main">
        <div class="">
            <div class="page-title">
                <div class="title_left">
                    <h3>Bulk ID Card Printing</h3>
                </div>
            </div>
            <div class="clearfix"></div>
            
            <!-- Filter Section (Standard Gentelella Theme Styling) -->
            <div class="x_panel">
                <div class="x_title">
                    <h2><i class="fa fa-filter"></i> Filter Personnel <small>Total Records: <?php echo number_format($total_records); ?></small></h2>
                    <div class="clearfix"></div>
                </div>
                <div class="x_content">
                    <form method="GET" action="" id="filterForm">
                        <!-- Include page parameter in form -->
                        <input type="hidden" name="page" value="<?php echo $page; ?>" id="pageInput">
                        
                        <div class="row">
                            <!-- Current Posting Filter -->
                            <div class="col-md-3 col-sm-6 col-xs-12 form-group">
                                <label><i class="fa fa-map-marker"></i> Current Posting</label>
                                <select class="form-control" name="posting" id="postingFilter">
                                    <option value="">All Postings</option>
                                    <?php foreach ($postings as $posting): ?>
                                        <option value="<?php echo htmlspecialchars($posting); ?>" 
                                            <?php echo $filters['posting'] == $posting ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($posting); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Rank Filter -->
                            <div class="col-md-3 col-sm-6 col-xs-12 form-group">
                                <label><i class="fa fa-star"></i> Rank</label>
                                <select class="form-control" name="rank" id="rankFilter">
                                    <option value="">All Ranks</option>
                                    <?php foreach ($ranks as $rank): ?>
                                        <option value="<?php echo htmlspecialchars($rank); ?>" 
                                            <?php echo $filters['rank'] == $rank ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($rank); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Service Number Filter -->
                            <div class="col-md-3 col-sm-6 col-xs-12 form-group">
                                <label><i class="fa fa-id-card"></i> Service Number</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="serviceNo" 
                                       id="serviceNoFilter"
                                       placeholder="Enter service number..."
                                       value="<?php echo htmlspecialchars($filters['serviceNo']); ?>">
                            </div>
                            
                            <!-- Confirm Status Filter -->
                            <div class="col-md-3 col-sm-6 col-xs-12 form-group">
                                <label><i class="fa fa-check-circle"></i> Confirm Status</label>
                                <select class="form-control" name="confirmStatus" id="confirmStatusFilter">
                                    <option value="">All Status</option>
                                    <option value="confirmed" <?php echo $filters['confirmStatus'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed Only</option>
                                    <option value="not_confirmed" <?php echo $filters['confirmStatus'] == 'not_confirmed' ? 'selected' : ''; ?>>Not Confirmed Only</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Active Filters Display -->
                        <?php if (!empty($filters['posting']) || !empty($filters['rank']) || !empty($filters['serviceNo']) || !empty($filters['confirmStatus'])): ?>
                        <div class="row" style="margin-bottom: 10px;">
                            <div class="col-md-12">
                                <?php if (!empty($filters['posting'])): ?>
                                <span class="label label-success" style="font-size: 11px; padding: 4px 8px; margin-right: 4px;">
                                    Posting: <?php echo htmlspecialchars($filters['posting']); ?> <i class="fa fa-times" onclick="removeFilter('posting')" style="cursor:pointer;"></i>
                                </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($filters['rank'])): ?>
                                <span class="label label-success" style="font-size: 11px; padding: 4px 8px; margin-right: 4px;">
                                    Rank: <?php echo htmlspecialchars($filters['rank']); ?> <i class="fa fa-times" onclick="removeFilter('rank')" style="cursor:pointer;"></i>
                                </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($filters['serviceNo'])): ?>
                                <span class="label label-info" style="font-size: 11px; padding: 4px 8px; margin-right: 4px;">
                                    Service No: <?php echo htmlspecialchars($filters['serviceNo']); ?> <i class="fa fa-times" onclick="removeFilter('serviceNo')" style="cursor:pointer;"></i>
                                </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($filters['confirmStatus'])): ?>
                                <span class="label <?php echo $filters['confirmStatus'] == 'confirmed' ? 'label-success' : 'label-danger'; ?>" style="font-size: 11px; padding: 4px 8px; margin-right: 4px;">
                                    Status: <?php echo $filters['confirmStatus'] == 'confirmed' ? 'Confirmed Only' : 'Not Confirmed Only'; ?> <i class="fa fa-times" onclick="removeFilter('confirmStatus')" style="cursor:pointer;"></i>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-12 text-right">
                                <button type="submit" class="btn btn-success">
                                    <i class="fa fa-filter"></i> Apply Filters
                                </button>
                                <button type="button" class="btn btn-default" onclick="resetFilters()">
                                    <i class="fa fa-refresh"></i> Reset Filters
                                </button>
                                <a href="?" class="btn btn-warning">
                                    <i class="fa fa-times-circle"></i> Clear All
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Results Section (Standard Gentelella Theme Styling) -->
            <div class="x_panel">
                <div class="x_title">
                    <h2><i class="fa fa-list"></i> Filtered Results <small>Showing <?php echo $numb; ?> of <?php echo number_format($total_records); ?> records</small></h2>
                    <div class="clearfix"></div>
                </div>
                <div class="x_content">
                
                <?php if ($numb > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th width="50" class="text-center">
                                        <input type="checkbox" id="selectAll" class="form-check-input">
                                    </th>
                                    <th>Photo</th>
                                    <th>Service No.</th>
                                    <th>Surname</th>
                                    <th>First Name</th>
                                    <th>Middle Name</th>
                                    <th>Rank</th>
                                    <th>Current Posting</th>
                                    <th>Confirm</th> <!-- NEW ORDER: Before Blood Type -->
                                    <th>Blood Type</th> <!-- MOVED TO LAST -->
                                </tr>
                            </thead>
                            <tbody id="resultsTable">
                                <?php 
                                // Reset result pointer
                                mysqli_data_seek($retrieve, 0);
                                $index = 0;
                                while ($ret = mysqli_fetch_assoc($retrieve)): 
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
                                    $bloodType = htmlspecialchars($ret['bloodType']);
                                    $confirmedDetails = htmlspecialchars($ret['confirmed_details']);
                                    
                                    // ============ UPDATED PHOTO FETCHING SECTION ============
                                    // Check for photo in multiple formats
                                    $uploadDir = 'upload/';
                                    $defaultPhoto = $uploadDir . 'default.png';
                                    $photoFound = false;
                                    $photoSrc = $defaultPhoto;
                                    $photoAlt = htmlspecialchars($surname . ' ' . $firstName);
                                    
                                    // Array of possible file extensions to check
                                    $possibleExtensions = array('.png', '.jpg', '.jpeg', '.PNG', '.JPG', '.JPEG');
                                    
                                    // Check each extension
                                    foreach ($possibleExtensions as $ext) {
                                        $photoPath = $uploadDir . $rawServiceNo . $ext;
                                        if (file_exists($photoPath)) {
                                            $photoFound = true;
                                            $photoSrc = $photoPath;
                                            break; // Stop checking once we find a match
                                        }
                                    }
                                    
                                    // If no photo found and default doesn't exist, create SVG placeholder
                                    if (!$photoFound && !file_exists($defaultPhoto)) {
                                        $photoSrc = 'data:image/svg+xml;base64,' . base64_encode('<svg width="40" height="40" xmlns="http://www.w3.org/2000/svg"><rect width="100%" height="100%" fill="#f0f0f0"/><text x="50%" y="50%" font-family="Arial" font-size="12" fill="#999" text-anchor="middle" dy=".3em">No Photo</text></svg>');
                                    }
                                    // ============ END UPDATED SECTION ============
                                    
                                    // Determine blood type class
                                    $bloodClass = '';
                                    if (!empty($bloodType)) {
                                        $bloodClass = strpos($bloodType, '-') !== false ? 'blood-negative' : 'blood-positive';
                                    }
                                    
                                    // Determine confirm status and styling
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
                                    <td class="text-center">
                                        <input type="checkbox" 
                                               class="form-check-input record-checkbox" 
                                               data-service-no="<?php echo $rawServiceNo; ?>"
                                               data-name="<?php echo $surname . ' ' . $firstName; ?>"
                                               data-rank="<?php echo $rank; ?>"
                                               id="record_<?php echo $index; ?>">
                                    </td>
                                    <td>
                                        <div class="personnel-photo">
                                            <img src="<?php echo $photoSrc; ?>" 
                                                 alt="<?php echo $photoAlt; ?>"
                                                 onerror="this.onerror=null; this.src='<?php echo $defaultPhoto; ?>';">
                                        </div>
                                    </td>
                                    <td><span class="service-no"><?php echo $serviceNo; ?></span></td>
                                    <td><span class="surname"><?php echo $surname; ?></span></td>
                                    <td><span class="first-name"><?php echo $firstName; ?></span></td>
                                    <td><span class="middle-name"><?php echo $middleName; ?></span></td>
                                    <td>
                                        <span class="rank-badge" title="<?php echo $rank; ?>">
                                            <?php echo $rank; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($posting)): ?>
                                            <span class="posting-badge" title="<?php echo $posting; ?>">
                                                <?php echo $posting; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="confirm-status <?php echo $confirmClass; ?>" 
                                              title="<?php echo $confirmedDetails; ?>">
                                            <?php echo $confirmStatus; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($bloodType)): ?>
                                            <span class="blood-type <?php echo $bloodClass; ?>">
                                                <?php echo $bloodType; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php 
                                $index++;
                                endwhile; 
                                ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=1<?php echo !empty($filters['posting']) ? '&posting=' . urlencode($filters['posting']) : ''; ?><?php echo !empty($filters['rank']) ? '&rank=' . urlencode($filters['rank']) : ''; ?><?php echo !empty($filters['serviceNo']) ? '&serviceNo=' . urlencode($filters['serviceNo']) : ''; ?><?php echo !empty($filters['confirmStatus']) ? '&confirmStatus=' . urlencode($filters['confirmStatus']) : ''; ?>" 
                               class="page-link" title="First Page">
                                <i class="fas fa-angle-double-left"></i> First
                            </a>
                            <a href="?page=<?php echo $page - 1; ?><?php echo !empty($filters['posting']) ? '&posting=' . urlencode($filters['posting']) : ''; ?><?php echo !empty($filters['rank']) ? '&rank=' . urlencode($filters['rank']) : ''; ?><?php echo !empty($filters['serviceNo']) ? '&serviceNo=' . urlencode($filters['serviceNo']) : ''; ?><?php echo !empty($filters['confirmStatus']) ? '&confirmStatus=' . urlencode($filters['confirmStatus']) : ''; ?>" 
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
                            echo '<a href="?page=1' . 
                                (!empty($filters['posting']) ? '&posting=' . urlencode($filters['posting']) : '') . 
                                (!empty($filters['rank']) ? '&rank=' . urlencode($filters['rank']) : '') . 
                                (!empty($filters['serviceNo']) ? '&serviceNo=' . urlencode($filters['serviceNo']) : '') . 
                                (!empty($filters['confirmStatus']) ? '&confirmStatus=' . urlencode($filters['confirmStatus']) : '') . 
                                '" class="page-link" title="Page 1">1</a>';
                            if ($startPage > 2) {
                                echo '<span class="page-link disabled">...</span>';
                            }
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <a href="?page=<?php echo $i; ?><?php echo !empty($filters['posting']) ? '&posting=' . urlencode($filters['posting']) : ''; ?><?php echo !empty($filters['rank']) ? '&rank=' . urlencode($filters['rank']) : ''; ?><?php echo !empty($filters['serviceNo']) ? '&serviceNo=' . urlencode($filters['serviceNo']) : ''; ?><?php echo !empty($filters['confirmStatus']) ? '&confirmStatus=' . urlencode($filters['confirmStatus']) : ''; ?>" 
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
                            echo '<a href="?page=' . $total_pages . 
                                (!empty($filters['posting']) ? '&posting=' . urlencode($filters['posting']) : '') . 
                                (!empty($filters['rank']) ? '&rank=' . urlencode($filters['rank']) : '') . 
                                (!empty($filters['serviceNo']) ? '&serviceNo=' . urlencode($filters['serviceNo']) : '') . 
                                (!empty($filters['confirmStatus']) ? '&confirmStatus=' . urlencode($filters['confirmStatus']) : '') . 
                                '" class="page-link" title="Page ' . $total_pages . '">' . $total_pages . '</a>';
                        }
                        ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo !empty($filters['posting']) ? '&posting=' . urlencode($filters['posting']) : ''; ?><?php echo !empty($filters['rank']) ? '&rank=' . urlencode($filters['rank']) : ''; ?><?php echo !empty($filters['serviceNo']) ? '&serviceNo=' . urlencode($filters['serviceNo']) : ''; ?><?php echo !empty($filters['confirmStatus']) ? '&confirmStatus=' . urlencode($filters['confirmStatus']) : ''; ?>" 
                               class="page-link" title="Next Page">
                                Next <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="?page=<?php echo $total_pages; ?><?php echo !empty($filters['posting']) ? '&posting=' . urlencode($filters['posting']) : ''; ?><?php echo !empty($filters['rank']) ? '&rank=' . urlencode($filters['rank']) : ''; ?><?php echo !empty($filters['serviceNo']) ? '&serviceNo=' . urlencode($filters['serviceNo']) : ''; ?><?php echo !empty($filters['confirmStatus']) ? '&confirmStatus=' . urlencode($filters['confirmStatus']) : ''; ?>" 
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
                                <?php if (!empty($filters['posting'])): ?>
                                    <input type="hidden" name="posting" value="<?php echo htmlspecialchars($filters['posting']); ?>">
                                <?php endif; ?>
                                <?php if (!empty($filters['rank'])): ?>
                                    <input type="hidden" name="rank" value="<?php echo htmlspecialchars($filters['rank']); ?>">
                                <?php endif; ?>
                                <?php if (!empty($filters['serviceNo'])): ?>
                                    <input type="hidden" name="serviceNo" value="<?php echo htmlspecialchars($filters['serviceNo']); ?>">
                                <?php endif; ?>
                                <?php if (!empty($filters['confirmStatus'])): ?>
                                    <input type="hidden" name="confirmStatus" value="<?php echo htmlspecialchars($filters['confirmStatus']); ?>">
                                <?php endif; ?>
                                <button type="submit" 
                                        style="padding: 8px 20px; background: #27ae60; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 5px;">
                                    <i class="fas fa-arrow-right"></i> Go
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Selection Actions -->
                    <div class="selection-actions">
                        <div class="selection-info">
                            <i class="fas fa-check-circle"></i>
                            <span id="selectedCount">0</span> records selected
                            <?php if ($total_filtered_records > 0): ?>
                                (of <?php echo number_format($total_filtered_records); ?> total filtered records)
                            <?php endif; ?>
                        </div>
                        <div class="action-buttons">
                            <button type="button" class="btn btn-primary" onclick="printSelected()" id="printBtn">
                                <i class="fas fa-print"></i> Print Selected Cards
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="selectAllRecords()">
                                <i class="fas fa-check-square"></i> Select All on Page
                            </button>
                            <button type="button" class="btn btn-warning" onclick="deselectAllRecords()">
                                <i class="fas fa-times-circle"></i> Deselect All
                            </button>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users-slash"></i>
                        <h4>No Records Found</h4>
                        <p>No personnel match the current filter criteria. Try adjusting your filters or clear them to view all records.</p>
                        <a href="?" class="btn btn-primary" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fas fa-redo"></i> Clear Filters
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Global variables
        let selectedRecords = [];
        let isPrinting = false;
        let printQueue = [];
        let currentPrintIndex = 0;
        let allFilteredRecords = <?php echo json_encode($total_filtered_records); ?>;
        
        // DOM ready
        document.addEventListener('DOMContentLoaded', function() {
            updateSelectedCount();
            
            // Select All checkbox
            document.getElementById('selectAll').addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.record-checkbox');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                    updateRecordSelection(checkbox);
                });
                updateSelectedCount();
            });
            
            // Individual checkbox change
            document.querySelectorAll('.record-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateRecordSelection(this);
                    updateSelectedCount();
                });
            });
            
            // Load previously selected records from localStorage
            loadSavedSelections();
            
            // FIXED: Auto-submit on filter change with immediate submission
            document.getElementById('postingFilter').addEventListener('change', function() {
                showLoading();
                document.getElementById('pageInput').value = '1'; // Reset to page 1
                setTimeout(() => document.getElementById('filterForm').submit(), 50);
            });
            
            document.getElementById('rankFilter').addEventListener('change', function() {
                showLoading();
                document.getElementById('pageInput').value = '1'; // Reset to page 1
                setTimeout(() => document.getElementById('filterForm').submit(), 50);
            });
            
            document.getElementById('confirmStatusFilter').addEventListener('change', function() {
                showLoading();
                document.getElementById('pageInput').value = '1'; // Reset to page 1
                setTimeout(() => document.getElementById('filterForm').submit(), 50);
            });
            
            // Auto-submit service number filter on Enter key
            document.getElementById('serviceNoFilter').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    showLoading();
                    document.getElementById('pageInput').value = '1'; // Reset to page 1
                    setTimeout(() => document.getElementById('filterForm').submit(), 50);
                }
            });
            
            // Add loading to pagination links
            document.querySelectorAll('.page-link[href]').forEach(link => {
                link.addEventListener('click', function(e) {
                    if (!this.classList.contains('disabled')) {
                        showLoading();
                    }
                });
            });
            
            // Add loading to form submit
            document.getElementById('filterForm').addEventListener('submit', function() {
                showLoading();
            });
        });
        
        // Update record selection
        function updateRecordSelection(checkbox) {
            const serviceNo = checkbox.getAttribute('data-service-no');
            const name = checkbox.getAttribute('data-name');
            const rank = checkbox.getAttribute('data-rank');
            
            if (checkbox.checked) {
                // Add to selected records if not already present
                if (!selectedRecords.some(record => record.serviceNo === serviceNo)) {
                    selectedRecords.push({
                        serviceNo: serviceNo,
                        name: name,
                        rank: rank
                    });
                }
            } else {
                // Remove from selected records
                selectedRecords = selectedRecords.filter(record => record.serviceNo !== serviceNo);
            }
            
            // Update print button text
            updatePrintButton();
            
            // Save selections to localStorage
            saveSelections();
        }
        
        // Update selected count display
        function updateSelectedCount() {
            const count = selectedRecords.length;
            document.getElementById('selectedCount').textContent = count;
            
            // Update Select All checkbox state
            const totalCheckboxes = document.querySelectorAll('.record-checkbox').length;
            const checkedCheckboxes = document.querySelectorAll('.record-checkbox:checked').length;
            document.getElementById('selectAll').checked = totalCheckboxes > 0 && checkedCheckboxes === totalCheckboxes;
            document.getElementById('selectAll').indeterminate = totalCheckboxes > 0 && checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes;
        }
        
        // Update print button text
        function updatePrintButton() {
            const printBtn = document.getElementById('printBtn');
            const count = selectedRecords.length;
            
            if (count > 0) {
                printBtn.innerHTML = `<i class="fas fa-print"></i> Print ${count} Selected Card${count !== 1 ? 's' : ''}`;
                printBtn.disabled = false;
            } else {
                printBtn.innerHTML = `<i class="fas fa-print"></i> Print Selected Cards`;
                printBtn.disabled = false;
            }
        }
        
        // Select all records on current page
        function selectAllRecords() {
            const checkboxes = document.querySelectorAll('.record-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
                updateRecordSelection(checkbox);
            });
            updateSelectedCount();
            showNotification('All records on this page selected!', 'success');
        }
        
        // Deselect all records
        function deselectAllRecords() {
            const checkboxes = document.querySelectorAll('.record-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
                updateRecordSelection(checkbox);
            });
            updateSelectedCount();
            showNotification('All records deselected!', 'success');
        }
        
        // Remove specific filter
        function removeFilter(filterName) {
            document.getElementById(filterName + 'Filter').value = '';
            showLoading();
            document.getElementById('pageInput').value = '1'; // Reset to page 1
            setTimeout(() => document.getElementById('filterForm').submit(), 50);
        }
        
        // Reset filters
        function resetFilters() {
            document.getElementById('postingFilter').value = '';
            document.getElementById('rankFilter').value = '';
            document.getElementById('serviceNoFilter').value = '';
            document.getElementById('confirmStatusFilter').value = '';
            showLoading();
            document.getElementById('pageInput').value = '1'; // Reset to page 1
            setTimeout(() => document.getElementById('filterForm').submit(), 50);
        }
        
        // Save selections to localStorage
        function saveSelections() {
            const selections = selectedRecords.map(record => record.serviceNo);
            localStorage.setItem('bulkPrintSelections', JSON.stringify(selections));
        }
        
        // Load saved selections from localStorage
        function loadSavedSelections() {
            try {
                const savedSelections = JSON.parse(localStorage.getItem('bulkPrintSelections')) || [];
                const checkboxes = document.querySelectorAll('.record-checkbox');
                
                checkboxes.forEach(checkbox => {
                    const serviceNo = checkbox.getAttribute('data-service-no');
                    if (savedSelections.includes(serviceNo)) {
                        checkbox.checked = true;
                        updateRecordSelection(checkbox);
                    }
                });
                
                updateSelectedCount();
                updatePrintButton();
            } catch (e) {
                console.error('Error loading saved selections:', e);
            }
        }
        
        // Print selected cards
        // Modify the printSelected function to log bulk prints
function printSelected() {
    if (selectedRecords.length === 0) {
        showNotification('Please select at least one record to print.', 'error');
        return;
    }
    
    if (selectedRecords.length > 50) {
        if (!confirm(`You are about to print ${selectedRecords.length} ID cards. This may take a while. Continue?`)) {
            return;
        }
    } else if (!confirm(`Print ${selectedRecords.length} ID card(s)?`)) {
        return;
    }
    
    // Generate batch ID for this bulk print
    const batchId = 'BATCH_' + new Date().toISOString().replace(/[-:]/g, '').slice(0,15) + '_' + Math.random().toString(36).substr(2, 9);
    
    // Show print progress modal
    showLoading();
    document.getElementById('printProgress').style.display = 'block';
    
    // Prepare print queue
    printQueue = [...selectedRecords];
    currentPrintIndex = 0;
    isPrinting = true;
    
    // Start printing process
    printNextCard();
}
        
        // Print next card in queue
        function printNextCard() {
            if (currentPrintIndex >= printQueue.length || !isPrinting) {
                // Printing completed or cancelled
                hideLoading();
                document.getElementById('printProgress').style.display = 'none';
                isPrinting = false;
                
                if (currentPrintIndex >= printQueue.length) {
                    showNotification(`Successfully printed ${printQueue.length} ID card(s)!`, 'success');
                    // Clear selections after successful print
                    deselectAllRecords();
                    localStorage.removeItem('bulkPrintSelections');
                } else {
                    showNotification(`Printing cancelled. Printed ${currentPrintIndex} of ${printQueue.length} cards.`, 'warning');
                }
                return;
            }
            
            const record = printQueue[currentPrintIndex];
            
            // Update progress
            updateProgress(currentPrintIndex + 1, printQueue.length, record.name);
            
            // Open print window
            const printWindow = window.open(
                `card.php?id=${encodeURIComponent(record.serviceNo)}&popup=1&bulk=1&batch_id=${encodeURIComponent(batchId)}`,
                `print_${record.serviceNo}_${Date.now()}`,
                'width=900,height=700,scrollbars=yes,resizable=yes,toolbar=no,location=no,status=no,menubar=no'
            );
            
            if (printWindow) {
                // Focus on the print window
                printWindow.focus();
                
                // Check if window is closed (user printed and closed)
                const checkWindowClosed = setInterval(() => {
                    try {
                        if (printWindow.closed) {
                            clearInterval(checkWindowClosed);
                            currentPrintIndex++;
                            
                            // Wait a bit before next print to prevent printer overload
                            setTimeout(printNextCard, 1000);
                        }
                    } catch (e) {
                        // Cross-origin error, window might be closed
                        clearInterval(checkWindowClosed);
                        currentPrintIndex++;
                        setTimeout(printNextCard, 1000);
                    }
                }, 500);
                
                // Fallback: Move to next after 8 seconds if window is still open
                setTimeout(() => {
                    clearInterval(checkWindowClosed);
                    try {
                        printWindow.close();
                    } catch (e) {
                        // Ignore close errors
                    }
                    currentPrintIndex++;
                    setTimeout(printNextCard, 500);
                }, 8000);
                
            } else {
                // Popup blocked
                showNotification('Popup blocked! Please allow popups for this site.', 'error');
                hideLoading();
                document.getElementById('printProgress').style.display = 'none';
                isPrinting = false;
            }
        }
        
        // Cancel printing
        function cancelPrint() {
            isPrinting = false;
            hideLoading();
            document.getElementById('printProgress').style.display = 'none';
            showNotification('Printing cancelled.', 'warning');
        }
        
        // Update progress display
        function updateProgress(current, total, currentName) {
            const percentage = (current / total) * 100;
            document.getElementById('progressFill').style.width = percentage + '%';
            document.getElementById('progressText').innerHTML = `
                <div><i class="fas fa-print"></i> Printing ${current} of ${total}</div>
                <div style="font-size: 12px; margin-top: 5px; color: #888;">
                    Current: ${currentName}
                </div>
            `;
        }
        
        // Show notification
        function showNotification(message, type = 'success') {
            // Remove existing notification
            const existing = document.querySelector('.notification');
            if (existing) existing.remove();
            
            // Create new notification
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'exclamation-triangle'}"></i>
                <span>${message}</span>
            `;
            
            document.body.appendChild(notification);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.style.opacity = '0';
                    notification.style.transform = 'translateX(100%)';
                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.parentNode.removeChild(notification);
                        }
                    }, 300);
                }
            }, 5000);
        }
        
        // Show loading
        function showLoading() {
            document.getElementById('overlay').style.display = 'block';
            document.getElementById('loadingSpinner').style.display = 'block';
        }
        
        // Hide loading
        function hideLoading() {
            document.getElementById('overlay').style.display = 'none';
            document.getElementById('loadingSpinner').style.display = 'none';
        }
        
        // Reset loading spinner on back/forward browser navigation
        window.addEventListener('pageshow', function(event) {
            hideLoading();
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+A to select all
            if (e.ctrlKey && e.key === 'a') {
                e.preventDefault();
                selectAllRecords();
            }
            // Ctrl+P to print selected
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                printSelected();
            }
            // Escape to cancel printing
            if (e.key === 'Escape' && isPrinting) {
                cancelPrint();
            }
        });
    </script>
    
    <?php 
    // Clean up
    if (isset($stmt)) {
        mysqli_stmt_close($stmt);
    }
    if (isset($all_selected_stmt)) {
        mysqli_stmt_close($all_selected_stmt);
    }
    if (isset($retrieve)) {
        mysqli_free_result($retrieve);
    }
    if (isset($all_retrieve)) {
        mysqli_free_result($all_retrieve);
    }
    if (isset($postings_result)) {
        mysqli_free_result($postings_result);
    }
    if (isset($ranks_result)) {
        mysqli_free_result($ranks_result);
    }
    ?>
    
    <?php include 'footer_tables.php'; ?>
</body>
</html>