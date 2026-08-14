<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login');
    exit;
}
require_once('db/db.php');

// Include appropriate sidebar based on role
if ($_SESSION['role'] == "Admin") {
    include 'sidebar-admin.php';
}
if ($_SESSION['role'] == "Supervisor") {
    include 'sidebar-supervisor.php';
}
if ($_SESSION['role'] == "DataEntry") {
    include 'sidebar-dataentry.php';
}
if ($_SESSION['role'] == "SuperAdmin") {
    include 'sidebar.php';
}
if ($_SESSION['role'] == "Biometric") {
    include 'sidebar-biometric.php';
}

include 'menu-footer-buttons.php';
include 'top-navigation.php';
?>
   
<!-- <script src="https://cdn.jsdelivr.net/npm/signature_pad@2.3.2/dist/signature_pad.min.js"></script> -->
<script src="dist/js/signature_pad.min.js"></script>
<style>
    #signature-pad {
        border: 1px solid #000;
        width: 400px;
        height: 200px;
    }
</style>
<div class="right_col" role="main">
    <div class="">
        <div class="page-title">
            <div class="title_left">
                <h3>Signature Capture</h3>
            </div>

            <div class="title_right">
                <div class="col-md-5 col-sm-5 col-xs-12 form-group pull-right top_search">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Search for...">
                        <span class="input-group-btn">
                            <button class="btn btn-default" type="button">Go!</button>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="clearfix">
            <h3>Sign Below</h3>
            <canvas id="signature-pad" style="margin-left: 100px;margin-top: 50px;"></canvas><br/>
            <div style="margin-left: 100px;">
                <button id="clear">Clear</button>
                <button id="save">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
    var canvas = document.getElementById('signature-pad');
    var signaturePad = new SignaturePad(canvas);

    document.getElementById('clear').addEventListener('click', function () {
        signaturePad.clear();
    });

    document.getElementById('save').addEventListener('click', function () {
        if (signaturePad.isEmpty()) {
            alert("Please provide a signature first.");
        } else {
            var dataURL = signaturePad.toDataURL();
            var u_id = <?php echo json_encode($_GET['id']); ?>;
            
            fetch('save_signature.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ 
                    image: dataURL,
                    u_id: u_id
                })
            })
            .then(response => response.json())
            .then(data => {
                // Redirect immediately to signaturecapture.php without any message
                window.location.href = 'signaturecapture';
            })
            .catch(error => {
                console.error('Error:', error);
                // Redirect even on error
                window.location.href = 'signaturecapture';
            });
        }
    });
</script>

<?php include 'footer_tables.php'; ?>