<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: index');
    exit;
}

require_once ('db/db.php');

// Just get user profile info - much faster query
$profileSql = "SELECT emp.serviceNo, emp.surname, emp.firstName, emp2.currentRank 
               FROM tbl_emppersonal emp 
               INNER JOIN tbl_employment emp2 ON emp.serviceNo = emp2.serviceNo 
               LIMIT 1";
$profileResult = mysqli_query($conn, $profileSql);
$foundk = mysqli_fetch_assoc($profileResult);
$profile = $foundk['serviceNo'];
$surname = $foundk['surname'];
$firstname = $foundk['firstName'];
$rank = $foundk['currentRank'];

// Get total count only for display
$active_cond = "emp2.empStatus != 'Deceased' AND TIMESTAMPDIFF(YEAR, emp.dob, CURDATE()) < 60 AND TIMESTAMPDIFF(YEAR, emp2.dofa, CURDATE()) < 35 AND emp2.empStatus != 'Retired'";
$countSql = "SELECT COUNT(*) as total 
             FROM tbl_emppersonal emp 
             INNER JOIN tbl_employment emp2 ON emp.serviceNo = emp2.serviceNo
             WHERE $active_cond";
$countResult = mysqli_query($conn, $countSql);
$totalPersonnel = mysqli_fetch_assoc($countResult)['total'];

if ($_SESSION['role'] == "Admin") {
    include 'sidebar-admin.php';
}
if ($_SESSION['role'] == "Supervisor") {
    include 'sidebar-supervisor.php';
}
if ($_SESSION['role'] == "CGI") {
    include 'sidebar-cgi.php';
}
include 'menu-footer-buttons.php';
include 'top-navigation.php';
?>

<!-- CRITICAL FIX: Add this CSS to prevent DataTables from blocking sidebar -->
<style>
    /* Ensure DataTables doesn't create overlay blocking sidebar */
    .dataTables_wrapper {
        position: relative !important;
        z-index: 1 !important;
        pointer-events: auto !important;
    }
    
    .dataTables_wrapper .dataTables_processing {
        z-index: 100 !important;
        pointer-events: none !important;
    }
    
    /* Force sidebar to always be clickable */
    .nav_menu,
    .left_col,
    .sidebar,
    .nav.side-menu,
    .nav_menu .nav,
    .nav_menu .nav li,
    .nav_menu .nav li a {
        pointer-events: auto !important;
        z-index: 9999 !important;
        position: relative;
    }
    
    /* Fix for any modal/overlay from DataTables */
    .dt-button-collection,
    .dataTables_filter,
    .dataTables_length,
    .dataTables_info,
    .dataTables_paginate {
        z-index: 2 !important;
    }
    
    /* Ensure right content area stays in place */
    .right_col {
        position: relative !important;
        z-index: 0 !important;
    }
    
    /* Prevent DataTables from adding any blocking overlays */
    .dataTables_scrollBody {
        z-index: 1 !important;
    }
    
    /* Fix for mobile menu button */
    .nav_menu .nav-toggle {
        pointer-events: auto !important;
        z-index: 10000 !important;
        cursor: pointer !important;
    }
    
    /* Ensure dropdown menus work */
    .nav_menu .dropdown,
    .nav_menu .dropdown-menu {
        z-index: 10001 !important;
        pointer-events: auto !important;
    }
</style>

<div class="right_col" role="main">
    <div class="">
        <div class="page-title">
            <div class="title_left">
                <h3>Enrolled Personnel</h3>
            </div>
        </div>
        <div class="clearfix"></div>
        
        <div class="row">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Nominal Roll (Total: <?php echo $totalPersonnel; ?> personnel)</h5>
                    <div class="table-responsive">
                        <a href="excel" class="btn btn-success mb-3">
                            <i class="fa fa-download fs-3 mb-1 font-16">Export (Excel)</i>
                        </a>
                        
                        <table id="personnel_table" class="table table-striped table-bordered" style="width:100%;">
                            <thead>
                                <tr>
                                    <th>Service No.</th>
                                    <th>IPPIS No.</th>
                                    <th>Surname</th>
                                    <th>First Name</th>
                                    <th>Middle Name</th>
                                    <th>Rank</th>
                                    <th>Gender</th>
                                    <th>State of Origin</th>
                                    <th>LGA</th>
                                    <th>Town</th>
                                    <th>Date of Birth</th>
                                    <th>DOFA</th>
                                    <th>DOPA</th>
                                    <th>DOR</th>
                                    <th>Marital Status</th>
                                    <th>Bank</th>
                                    <th>Account No</th>
                                    <th>NOK</th>
                                    <th>Blood Type</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer_tables.php'; ?>

<!-- IMPORTANT: Only include DataTables CSS and JS if not already in your template -->
<!-- Use noConflict mode to prevent jQuery conflicts -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">

<script>
// Wait for document ready and use your template's jQuery
document.addEventListener('DOMContentLoaded', function() {
    // Check if jQuery exists from your template
    if (typeof jQuery === 'undefined') {
        // If jQuery doesn't exist, load it
        var script = document.createElement('script');
        script.src = 'https://code.jquery.com/jquery-3.7.0.js';
        script.onload = function() {
            loadDataTables();
        };
        document.head.appendChild(script);
    } else {
        // Use your template's jQuery
        loadDataTables();
    }
});

function loadDataTables() {
    // Load DataTables JS
    var dtScript = document.createElement('script');
    dtScript.src = 'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js';
    dtScript.onload = function() {
        // Load Buttons extension
        var btnScript = document.createElement('script');
        btnScript.src = 'https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js';
        btnScript.onload = function() {
            initializeDataTable();
        };
        document.head.appendChild(btnScript);
    };
    document.head.appendChild(dtScript);
}

function initializeDataTable() {
    // Use jQuery in noConflict mode to prevent conflicts
    var $j = jQuery.noConflict(true);
    
    $j(document).ready(function() {
        $j('#personnel_table').DataTable({
            "processing": true,
            "serverSide": true,
            "ajax": {
                "url": "fetch_personnel.php",
                "type": "POST",
                "error": function(xhr, error, thrown) {
                    console.log('DataTables error:', error);
                }
            },
            "pageLength": 25,
            "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
            "order": [[0, "asc"]],
            "scrollX": true,
            "columns": [
                { "data": 0 },
                { "data": 1 },
                { "data": 2 },
                { "data": 3 },
                { "data": 4 },
                { "data": 5 },
                { "data": 6 },
                { "data": 7 },
                { "data": 8 },
                { "data": 9 },
                { "data": 10 },
                { "data": 11 },
                { "data": 12 },
                { "data": 13 },
                { "data": 14 },
                { "data": 15 },
                { "data": 16 },
                { "data": 17 },
                { "data": 18 }
            ],
            "language": {
                "processing": "<div style='padding: 20px;'>Loading personnel data...</div>",
                "zeroRecords": "No matching records found",
                "info": "Showing _START_ to _END_ of _TOTAL_ personnel",
                "infoEmpty": "No personnel found",
                "infoFiltered": "(filtered from _MAX_ total personnel)"
            },
            "drawCallback": function() {
                // Ensure sidebar remains clickable after each table redraw
                document.querySelectorAll('.nav_menu, .left_col, .sidebar, .nav li a').forEach(function(el) {
                    el.style.pointerEvents = 'auto';
                    el.style.zIndex = '9999';
                });
            }
        });
    });
}

// Additional fix: Periodically ensure sidebar stays clickable
setInterval(function() {
    document.querySelectorAll('.nav_menu, .left_col, .sidebar, .nav.side-menu li a').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.zIndex = '9999';
    });
}, 500);
</script>