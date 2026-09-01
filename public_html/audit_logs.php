<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index");
    die();
}

// Only SuperAdmin and Admin can access Audit Logs
$allowed_roles = ['SuperAdmin', 'Admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: mainpage");
    exit();
}

require_once('db/db.php');

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

// ============================================================================
// PAGINATION & GLOBAL SEARCH SETUP (EXACTLY 30 RECORDS PER PAGE)
// ============================================================================
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$records_per_page = 30;
$offset = ($page - 1) * $records_per_page;

$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_sql = '';
$search_params = [];
$param_types = '';

if (!empty($search_query)) {
    // When searching, search the ENTIRE database across all history fields
    $where_sql = "WHERE (user_id LIKE ? OR username LIKE ? OR user_role LIKE ? OR action LIKE ? OR details LIKE ? OR ip_address LIKE ?)";
    $param_val = '%' . $search_query . '%';
    $search_params = array_fill(0, 6, $param_val);
    $param_types = 'ssssss';
}

// 1. Fetch Total Record Count for Pagination Calculation
$count_sql = "SELECT COUNT(*) as total FROM tbl_audit_logs $where_sql";
$count_stmt = mysqli_prepare($conn, $count_sql);

if (!empty($search_params)) {
    mysqli_stmt_bind_param($count_stmt, $param_types, ...$search_params);
}
mysqli_stmt_execute($count_stmt);
$count_res = mysqli_stmt_get_result($count_stmt);
$count_row = mysqli_fetch_assoc($count_res);
$total_records = isset($count_row['total']) ? intval($count_row['total']) : 0;
$total_pages = max(1, ceil($total_records / $records_per_page));
mysqli_stmt_close($count_stmt);

// Ensure current page does not exceed max pages
if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $records_per_page;
}

// 2. Main Paginated Query (LIMIT 30 OFFSET X)
$query = "SELECT * FROM tbl_audit_logs $where_sql ORDER BY id DESC LIMIT ? OFFSET ?";
$main_params = $search_params;
$main_params[] = $records_per_page;
$main_params[] = $offset;
$main_types = $param_types . 'ii';

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, $main_types, ...$main_params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>

<style>
.dataTables_filter {
    display: none; /* Hidden because custom global search box handles filtering */
}
.dataTables_paginate {
    display: none; /* Hidden because native PHP 30-record pagination is rendered below */
}
@media (max-width: 767px) {
    .dt-buttons {
        margin-bottom: 15px !important;
        float: none !important;
        text-align: center !important;
    }
    .dt-buttons .btn {
        margin-bottom: 5px !important;
    }
}
</style>

<!-- page content -->
<div class="right_col" role="main" style="overflow-x: hidden;">
    <div class="">
        <div class="page-title">
            <div class="title_left">
                <h3>System Audit Logs</h3>
            </div>
        </div>

        <div class="clearfix"></div>

        <div class="row">
            <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="x_panel">
                    <div class="x_title">
                        <h2>Security & Activity Audit Trail <small>(Page <?php echo $page; ?> of <?php echo $total_pages; ?> - <?php echo number_format($total_records); ?> Total Entries)</small></h2>
                        <div class="clearfix"></div>
                    </div>
                    <div class="x_content">
                        <p class="text-muted font-13 m-b-30">
                            Real-time record of authentication attempts, personnel enrolments, record modifications, and user management events.
                        </p>
                        
                        <!-- Search Bar -->
                        <div class="row" style="margin-bottom: 15px;">
                            <div class="col-md-6 col-sm-8 col-xs-12">
                                <form method="GET" action="audit_logs.php" id="auditSearchForm">
                                    <label for="custom-audit-search" style="font-size: 13px; font-weight: 600; color: #333; margin-bottom: 5px;">
                                        <i class="fa fa-search"></i> Search Audit Logs (Global Database Search):
                                    </label>
                                    <div class="input-group">
                                        <input type="text" name="search" id="custom-audit-search" class="form-control" placeholder="Search by Service No, Username, Action, IP, or details..." value="<?php echo htmlspecialchars($search_query); ?>">
                                        <span class="input-group-btn">
                                            <button class="btn btn-primary" type="submit" id="btn-search-trigger"><i class="fa fa-search"></i> Search</button>
                                            <?php if (!empty($search_query)): ?>
                                                <a href="audit_logs.php" class="btn btn-default" title="Reset Search"><i class="fa fa-times"></i> Reset</a>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <?php if (!empty($search_query)): ?>
                        <div class="alert alert-info" style="padding: 10px 15px; margin-bottom: 15px;">
                            <i class="fa fa-info-circle"></i> Showing search results across entire database for: <strong>"<?php echo htmlspecialchars($search_query); ?>"</strong>
                            (<strong><?php echo number_format($total_records); ?></strong> matching records found)
                            <a href="audit_logs.php" class="btn btn-xs btn-default pull-right"><i class="fa fa-times"></i> Clear Search</a>
                        </div>
                        <?php endif; ?>

                        <div class="table-responsive" style="width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; margin-bottom: 15px;">
                            <table id="datatable-audit" class="table table-striped table-bordered dt-responsive nowrap" cellspacing="0" width="100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Timestamp</th>
                                        <th>User ID / Service No</th>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>Action</th>
                                        <th>Details</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $sn = $offset + 1;
                                    if ($result && mysqli_num_rows($result) > 0):
                                        while ($row = mysqli_fetch_assoc($result)):
                                            // Format action badge color
                                            $badge_class = 'label-default';
                                            $action = $row['action'];
                                            if (strpos($action, 'LOGIN_SUCCESS') !== false || strpos($action, 'SUCCESSFUL') !== false) {
                                                $badge_class = 'label-success';
                                            } elseif (strpos($action, 'FAILED') !== false || strpos($action, 'DENIED') !== false || strpos($action, 'DEACTIVATED') !== false) {
                                                $badge_class = 'label-danger';
                                            } elseif (strpos($action, 'ENROLMENT') !== false || strpos($action, 'CREATED') !== false) {
                                                $badge_class = 'label-primary';
                                            } elseif (strpos($action, 'UPDATE') !== false || strpos($action, 'ACTIVATED') !== false) {
                                                $badge_class = 'label-info';
                                            } elseif (strpos($action, 'LOGOUT') !== false) {
                                                $badge_class = 'label-warning';
                                            }
                                    ?>
                                        <tr>
                                            <td><?php echo $sn++; ?></td>
                                            <td><?php echo date('d-M-Y H:i:s', strtotime($row['created_at'])); ?></td>
                                            <td><strong><?php echo htmlspecialchars($row['user_id']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                                            <td><span class="label label-default"><?php echo htmlspecialchars($row['user_role']); ?></span></td>
                                            <td><span class="label <?php echo $badge_class; ?>"><?php echo htmlspecialchars($row['action']); ?></span></td>
                                            <td><?php echo htmlspecialchars($row['details']); ?></td>
                                            <td><code><?php echo htmlspecialchars($row['ip_address']); ?></code></td>
                                        </tr>
                                    <?php 
                                        endwhile;
                                    else:
                                    ?>
                                        <tr>
                                            <td colspan="8" class="text-center" style="padding: 30px; color: #777;">
                                                <i class="fa fa-info-circle fa-2x"></i><br>
                                                No audit log entries found<?php echo !empty($search_query) ? ' matching "' . htmlspecialchars($search_query) . '"' : ''; ?>.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Navigation -->
                        <div class="row" style="margin-top: 15px;">
                            <div class="col-md-6 col-sm-6 col-xs-12">
                                <p style="margin-top: 7px; color: #666; font-size: 13px;">
                                    Showing <strong><?php echo $total_records > 0 ? ($offset + 1) : 0; ?></strong> to <strong><?php echo min($offset + $records_per_page, $total_records); ?></strong> of <strong><?php echo number_format($total_records); ?></strong> entries
                                </p>
                            </div>
                            <div class="col-md-6 col-sm-6 col-xs-12 text-right">
                                <?php if ($total_pages > 1): ?>
                                <ul class="pagination pagination-sm" style="margin: 0;">
                                    <?php
                                    $base_url = 'audit_logs.php?' . (!empty($search_query) ? 'search=' . urlencode($search_query) . '&' : '') . 'page=';
                                    
                                    // First & Prev
                                    if ($page > 1) {
                                        echo '<li><a href="' . $base_url . '1">&laquo; First</a></li>';
                                        echo '<li><a href="' . $base_url . ($page - 1) . '">&lsaquo; Prev</a></li>';
                                    } else {
                                        echo '<li class="disabled"><span>&laquo; First</span></li>';
                                        echo '<li class="disabled"><span>&lsaquo; Prev</span></li>';
                                    }

                                    // Page numbers
                                    $start_p = max(1, $page - 2);
                                    $end_p = min($total_pages, $page + 2);
                                    for ($p = $start_p; $p <= $end_p; $p++) {
                                        $active = ($p == $page) ? 'class="active"' : '';
                                        echo '<li ' . $active . '><a href="' . $base_url . $p . '">' . $p . '</a></li>';
                                    }

                                    // Next & Last
                                    if ($page < $total_pages) {
                                        echo '<li><a href="' . $base_url . ($page + 1) . '">Next &rsaquo;</a></li>';
                                        echo '<li><a href="' . $base_url . $total_pages . '">Last &raquo;</a></li>';
                                    } else {
                                        echo '<li class="disabled"><span>Next &rsaquo;</span></li>';
                                        echo '<li class="disabled"><span>Last &raquo;</span></li>';
                                    }
                                    ?>
                                </ul>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- /page content -->

<?php include 'footer.php'; ?>

<!-- DataTables Core & Export Plugins -->
<link href="vendors/datatables.net-bs/css/dataTables.bootstrap.min.css" rel="stylesheet">
<link href="vendors/datatables.net-buttons-bs/css/buttons.bootstrap.min.css" rel="stylesheet">

<script src="vendors/datatables.net/js/jquery.dataTables.min.js"></script>
<script src="vendors/datatables.net-bs/js/dataTables.bootstrap.min.js"></script>
<script src="vendors/datatables.net-buttons/js/dataTables.buttons.min.js"></script>
<script src="vendors/datatables.net-buttons-bs/js/buttons.bootstrap.min.js"></script>
<script src="vendors/datatables.net-buttons/js/buttons.flash.min.js"></script>
<script src="vendors/datatables.net-buttons/js/buttons.html5.min.js"></script>
<script src="vendors/datatables.net-buttons/js/buttons.print.min.js"></script>
<script src="vendors/jszip/dist/jszip.min.js"></script>
<script src="vendors/pdfmake/build/pdfmake.min.js"></script>
<script src="vendors/pdfmake/build/vfs_fonts.js"></script>

<script>
$(document).ready(function() {
    if ($.fn.DataTable) {
        $('#datatable-audit').DataTable({
            dom: 'Bfrtip',
            paging: false,
            info: false,
            ordering: false,
            buttons: [
                {
                    extend: 'copy',
                    className: 'btn-sm btn-default'
                },
                {
                    extend: 'csv',
                    className: 'btn-sm btn-primary',
                    title: 'NIIMS_Audit_Logs_' + new Date().toISOString().slice(0,10)
                },
                {
                    extend: 'excel',
                    className: 'btn-sm btn-success',
                    title: 'NIIMS_Audit_Logs_' + new Date().toISOString().slice(0,10)
                },
                {
                    extend: 'pdfHtml5',
                    className: 'btn-sm btn-danger',
                    title: 'NIIMS Audit Trail Report',
                    orientation: 'landscape',
                    pageSize: 'A4'
                },
                {
                    extend: 'print',
                    className: 'btn-sm btn-info'
                }
            ]
        });
    }
});
</script>
