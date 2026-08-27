<?php
 session_start();
  
  if (!isset($_SESSION['username'])) {
      require_once('index.php');
      exit;
  }
  require_once ('db/db.php');
  if ($_SESSION['role'] == "Admin") 
  {
    include 'sidebar-admin.php';
  }
  if ($_SESSION['role'] == "Supervisor")
  {
    include 'sidebar-supervisor.php';
  }
  if ($_SESSION['role'] == "DataEntry")
  {
    include 'sidebar-dataentry.php';
  }
  if ($_SESSION['role'] == "SuperAdmin")
  {
    include 'sidebar.php';
  }
     if ($_SESSION['role'] == "Biometric")
  {
    include 'sidebar-biometric.php';
  }
  include 'menu-footer-buttons.php';
  include 'top-navigation.php';

?>
      <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js" ></script>
      <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
      <script src="js/jquery.signature.js"></script>
      <link href="css/jquery.signature.css" rel="stylesheet">
      <style>
      .kbw-signature { width: 400px; height: 200px; }
      </style>
      <script src="dist/js/jquery.signature.min.js"></script>
      <script src="dist/js/jquery.signature.js"></script>

     <div class="right_col" role="main">
          <div class="">
            <div class="page-title">
              <div class="title_left">
                <h3>NIIMS Signature Capture</h3>
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
            <div class="clearfix"></div>


	<?php 
	$id = $_GET['id']; 
	?>

	<form method="post" action="signature_upload">
		<h4>NIS: <input type="text" name="user_id" value="<?php echo htmlspecialchars($id, ENT_QUOTES); ?>" readonly></h4>
		<div id="sig"></div>
		<p style="clear: both;">
			<!-- <button id="disable">Disable</button> --> 
			<button id="clear">Clear</button> 
			<!-- <button class="btn btn-success">To JSON</button>
			<button id="svg">To SVG</button> -->
			

			<textarea id="signature64" name="signed" style="display: none"></textarea>
		</p>
		<button class="btn btn-success" name="signature">Submit</button>
		</form>
<script type="text/javascript">
    var sig = $('#sig').signature({syncField: '#signature64', syncFormat: 'PNG'});
	var empid = <?php echo json_encode($id); ?>;
    $('#clear').click(function(e) {
        e.preventDefault();
        sig.signature('clear');
        $("#signature64").val('');
    });
</script>
<?php include 'footer_tables.php'; ?>
