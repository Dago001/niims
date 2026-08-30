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

// Include appropriate sidebar based on role
if ($_SESSION['role'] == "SuperAdmin") {
    include 'sidebar-superadmin.php';
} else {
    include 'sidebar-admin.php';
}

include 'menu-footer-buttons.php';
include 'top-navigation.php';

// Fetch audit logs
$query = "SELECT * FROM tbl_audit_logs ORDER BY id DESC LIMIT 2000";
$result = mysqli_query($conn, $query);
?>

<!-- page content -->
<div class="right_col" role="main">
    <div class="">
        <div class="page-title">
            <div class="title_left">
                <h3><i class="fa fa-history"></i> System Audit Logs</h3>
            </div>
        </div>

        <div class="clearfix"></div>

        <div class="row">
            <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="x_panel">
                    <div class="x_title">
                        <h2><i class="fa fa-shield"></i> Security & Activity Audit Trail</h2>
                        <div class="clearfix"></div>
                    </div>
                    <div class="x_content">
                        <p class="text-muted font-13 m-b-30">
                            Real-time record of authentication attempts, personnel enrolments, record modifications, and user management events.
                        </p>
                        
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
    $('#datatable-audit').DataTable({
        dom: 'Bfrtip',
        pageLength: 25,
        order: [[0, 'asc']],
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
});
</script>
