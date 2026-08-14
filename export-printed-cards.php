<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index");
    die();
}

require_once('db/db.php');

$format = isset($_GET['format']) ? $_GET['format'] : 'csv';

// Build filter conditions same as printed-cards.php
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$print_method = isset($_GET['print_method']) ? trim($_GET['print_method']) : '';
$rank_filter = isset($_GET['rank_filter']) ? trim($_GET['rank_filter']) : '';

$where_conditions = [];
$where_params = [];
$param_types = '';

if (!empty($search_query)) {
    $where_conditions[] = "(serviceNo LIKE ? OR 
                             surname LIKE ? OR 
                             firstName LIKE ? OR 
                             middleName LIKE ? OR 
                             currentRank LIKE ? OR 
                             printed_by LIKE ?)";
    $search_param = "%" . $search_query . "%";
    for ($i = 0; $i < 6; $i++) {
        $where_params[] = $search_param;
        $param_types .= 's';
    }
}

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

if (!empty($print_method)) {
    $where_conditions[] = "print_method = ?";
    $where_params[] = $print_method;
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

// Get records with deduplication
// Group by serviceNo, date, and method to avoid duplicates
$sql = "SELECT serviceNo, 
               surname, 
               firstName, 
               middleName, 
               currentRank, 
               printed_by, 
               printed_by_role, 
               DATE_FORMAT(MAX(print_date), '%Y-%m-%d %H:%i:%s') as print_date,
               print_method, 
               MAX(batch_id) as batch_id, 
               MAX(ip_address) as ip_address,
               COUNT(*) as print_count
        FROM tbl_printed_cards 
        $where_clause 
        GROUP BY serviceNo, DATE(print_date), print_method
        ORDER BY print_date DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($where_params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$where_params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($format === 'csv') {
    // CSV Export for Excel
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="printed_cards_' . date('Y-m-d_His') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel proper rendering
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Column Headers
    fputcsv($output, [
        'S/N', 
        'Service No.', 
        'Surname', 
        'First Name', 
        'Middle Name', 
        'Rank', 
        'Printed By', 
        'Role', 
        'Print Date', 
        'Method', 
        'Batch ID', 
        'IP Address',
        'Print Count'
    ]);
    
    $counter = 1;
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, [
            $counter++,
            $row['serviceNo'],
            $row['surname'],
            $row['firstName'],
            $row['middleName'],
            $row['currentRank'],
            $row['printed_by'],
            $row['printed_by_role'] ?: 'N/A',
            $row['print_date'],
            ucfirst($row['print_method']),
            $row['batch_id'] ?: 'N/A',
            $row['ip_address'] ?: 'N/A',
            $row['print_count']
        ]);
    }
    
    fclose($output);
    exit;
    
} elseif ($format === 'pdf') {
    // PDF export - redirect or implement later
    header('Location: printed-cards?error=pdf_not_implemented');
    exit;
    
} elseif ($format === 'print') {
    // Print-friendly HTML view
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Printed Cards Report</title>
        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body { 
                font-family: Arial, sans-serif; 
                margin: 20px; 
                color: #333;
            }
            
            .report-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 3px solid #27ae60;
            }
            
            .report-title h1 {
                color: #2c3e50;
                font-size: 24px;
                margin-bottom: 5px;
            }
            
            .report-title .subtitle {
                color: #666;
                font-size: 13px;
            }
            
            .report-meta {
                text-align: right;
                font-size: 12px;
                color: #666;
            }
            
            .report-meta div {
                margin-bottom: 3px;
            }
            
            .filter-info {
                background: #f8f9fa;
                padding: 10px 15px;
                border-radius: 6px;
                margin-bottom: 20px;
                font-size: 12px;
                color: #555;
                border-left: 4px solid #27ae60;
            }
            
            .filter-info strong {
                color: #2c3e50;
            }
            
            .action-buttons {
                margin-bottom: 20px;
                display: flex;
                gap: 10px;
            }
            
            .btn {
                padding: 10px 20px;
                border: none;
                border-radius: 6px;
                cursor: pointer;
                font-size: 13px;
                font-weight: 600;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                transition: all 0.3s;
            }
            
            .btn-print {
                background: #27ae60;
                color: white;
            }
            
            .btn-print:hover {
                background: #219a52;
            }
            
            .btn-close {
                background: #dc3545;
                color: white;
            }
            
            .btn-close:hover {
                background: #c82333;
            }
            
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-top: 15px;
                font-size: 12px;
            }
            
            thead th { 
                background: linear-gradient(135deg, #2c3e50, #34495e);
                color: white; 
                padding: 12px 10px; 
                text-align: left; 
                font-weight: 600;
                font-size: 11px;
                border-bottom: 2px solid #27ae60;
            }
            
            tbody td { 
                padding: 10px; 
                border-bottom: 1px solid #e0e0e0; 
            }
            
            tbody tr:nth-child(even) { 
                background: #f9f9f9; 
            }
            
            tbody tr:hover {
                background: #f0f7ff;
            }
            
            .service-no {
                font-family: 'Courier New', monospace;
                font-weight: bold;
                font-size: 11px;
            }
            
            .method-badge {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 4px;
                font-size: 10px;
                font-weight: 600;
                text-transform: uppercase;
            }
            
            .method-single {
                background: #e3f2fd;
                color: #1976d2;
            }
            
            .method-bulk {
                background: #e8f5e9;
                color: #2e7d32;
            }
            
            .report-footer { 
                margin-top: 30px; 
                padding-top: 15px;
                border-top: 1px solid #ddd;
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-size: 11px;
                color: #666; 
            }
            
            .total-records {
                font-weight: bold;
                color: #2c3e50;
                font-size: 13px;
            }
            
            @media print {
                @page {
                    margin: 1cm;
                    size: landscape;
                }
                
                body {
                    margin: 0;
                }
                
                .no-print { 
                    display: none !important; 
                }
                
                .report-header {
                    border-bottom-color: #000;
                }
                
                thead th {
                    background: #2c3e50 !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                
                tbody tr:nth-child(even) {
                    background: #f5f5f5 !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
            }
        </style>
    </head>
    <body>
        <div class="report-header">
            <div class="report-title">
                <h1><i class="fas fa-history"></i> Printed Cards Report</h1>
                <div class="subtitle">Deduplicated Print History</div>
            </div>
            <div class="report-meta">
                <div><i class="far fa-calendar-alt"></i> Generated: <?php echo date('d M Y H:i:s'); ?></div>
                <div><i class="far fa-user"></i> By: <?php echo htmlspecialchars($_SESSION['username']); ?></div>
            </div>
        </div>
        
        <div class="action-buttons no-print">
            <button onclick="window.print()" class="btn btn-print">
                <i class="fas fa-print"></i> Print Report
            </button>
            <button onclick="window.close()" class="btn btn-close">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
        
        <?php
        // Show active filters
        $active_filters = [];
        if (!empty($search_query)) $active_filters[] = "Search: <strong>" . htmlspecialchars($search_query) . "</strong>";
        if (!empty($date_from)) $active_filters[] = "From: <strong>" . htmlspecialchars($date_from) . "</strong>";
        if (!empty($date_to)) $active_filters[] = "To: <strong>" . htmlspecialchars($date_to) . "</strong>";
        if (!empty($print_method)) $active_filters[] = "Method: <strong>" . ucfirst($print_method) . "</strong>";
        if (!empty($rank_filter)) $active_filters[] = "Rank: <strong>" . htmlspecialchars($rank_filter) . "</strong>";
        
        if (!empty($active_filters)):
        ?>
            <div class="filter-info">
                <i class="fas fa-filter"></i> 
                Active Filters: <?php echo implode(' | ', $active_filters); ?>
            </div>
        <?php endif; ?>
        
        <table>
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Service No.</th>
                    <th>Name</th>
                    <th>Rank</th>
                    <th>Printed By</th>
                    <th>Role</th>
                    <th>Print Date</th>
                    <th>Method</th>
                    <th>Batch ID</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Reset result pointer
                mysqli_data_seek($result, 0);
                $counter = 1;
                $total = mysqli_num_rows($result);
                
                if ($total > 0):
                    while ($row = mysqli_fetch_assoc($result)): 
                        $fullName = trim($row['surname'] . ' ' . $row['firstName'] . ' ' . $row['middleName']);
                ?>
                <tr>
                    <td><?php echo $counter++; ?></td>
                    <td><span class="service-no"><?php echo htmlspecialchars($row['serviceNo']); ?></span></td>
                    <td><?php echo htmlspecialchars($fullName); ?></td>
                    <td><?php echo htmlspecialchars($row['currentRank']); ?></td>
                    <td><?php echo htmlspecialchars($row['printed_by']); ?></td>
                    <td><?php echo htmlspecialchars($row['printed_by_role'] ?: 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($row['print_date']); ?></td>
                    <td>
                        <span class="method-badge method-<?php echo $row['print_method']; ?>">
                            <?php echo ucfirst($row['print_method']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($row['batch_id'] ?: 'N/A'); ?></td>
                </tr>
                <?php 
                    endwhile; 
                else:
                ?>
                <tr>
                    <td colspan="9" style="text-align: center; padding: 30px; color: #999;">
                        <i class="fas fa-inbox" style="font-size: 40px; display: block; margin-bottom: 10px;"></i>
                        No records found matching your criteria
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div class="report-footer">
            <div>
                <i class="fas fa-check-circle" style="color: #27ae60;"></i> 
                Report includes deduplicated records (grouped by service number, date, and method)
            </div>
            <div class="total-records">
                <i class="fas fa-list"></i> Total Records: <?php echo $total; ?>
            </div>
        </div>
        
        <script>
            // Auto-print if requested via URL parameter
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('autoprint') === '1') {
                window.onload = function() {
                    setTimeout(() => window.print(), 500);
                };
            }
        </script>
    </body>
    </html>
    <?php
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>