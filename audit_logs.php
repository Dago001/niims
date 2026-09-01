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

// Fetch audit logs with global search support
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
if (!empty($search_query)) {
    // Query entire audit table when a specific search term is supplied
    $query = "SELECT * FROM tbl_audit_logs 
              WHERE user_id LIKE ? OR username LIKE ? OR user_role LIKE ? OR action LIKE ? OR details LIKE ? OR ip_address LIKE ? 
              ORDER BY id DESC";
    $stmt = mysqli_prepare($conn, $query);
    $param = '%' . $search_query . '%';
    mysqli_stmt_bind_param($stmt, 'ssssss', $param, $param, $param, $param, $param, $param);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    // Default fetch up to 5000 records so DataTables breaks into 30 entries per page
    $query = "SELECT * FROM tbl_audit_logs ORDER BY id DESC LIMIT 5000";
    $result = mysqli_query($conn, $query);
}

?>

<style>
.dataTables_filter {
    text-align: right;
    margin-bottom: 15px;
}
.dataTables_filter label {
    font-weight: 500;
    margin-bottom: 0;
    width: 100%;
}
.dataTables_filter input {
    width: 250px !important;
    max-width: 100%;
    display: inline-block;
    padding: 6px 12px;
    font-size: 13px;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-shadow: inset 0 1px 1px rgba(0,0,0,.075);
    transition: border-color ease-in-out .15s,box-shadow ease-in-out .15s;
}
.dataTables_filter input:focus {
    border-color: #1abb9c;
    outline: 0;
    box-shadow: inset 0 1px 1px rgba(0,0,0,.075),0 0 8px rgba(26,187,156,.6);
}
.dataTables_paginate {
    text-align: right;
    margin-top: 10px;
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
    .dataTables_filter {
        text-align: center !important;
        margin-top: 10px;
    }
    .dataTables_filter input {
        width: 100% !important;
    }
    .dataTables_paginate {
        text-align: center !important;
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
                        <h2>Security & Activity Audit Trail</h2>
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
                                    $sn = 1;
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
                                    endif; 
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- /page content -->

<?php include 'footer.php'; ?>

<!-- DataTables Buttons CSS & JS for Exporting -->
<link href="vendors/datatables.net-buttons-bs/css/buttons.bootstrap.min.css" rel="stylesheet">
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
    var table = $('#datatable-audit').DataTable({
        dom: 'Blfrtip',
        pageLength: 30,
        lengthMenu: [[10, 25, 30, 50, 100, 250, -1], [10, 25, 30, 50, 100, 250, "All"]],
        order: [[0, 'asc']],
        language: {
            search: "Filter: ",
            searchPlaceholder: "Type to filter...",
            lengthMenu: "Show _MENU_ entries per page",
            info: "Showing _START_ to _END_ of _TOTAL_ audit entries",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        },
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

    // Wire up custom search bar
    $('#custom-audit-search').on('keyup change clear', function() {
        table.search(this.value).draw();
    });
    $('#btn-search-trigger').on('click', function() {
        table.search($('#custom-audit-search').val()).draw();
    });
});
</script>
