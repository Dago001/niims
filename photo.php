<?php
session_start();
  if (!isset($_SESSION['username'])) {
//       require_once('index.php');
    //   exit;
       header("Location: index");
        die();
  }
  require_once ('db/db.php');
if ($_SESSION['role'] == "DataEntry")
  {
    include 'sidebar-dataentry.php';
  }
  if ($_SESSION['role'] == "Supervisor")
  {
    include 'sidebar-supervisor.php';
  }
  if ($_SESSION['role'] == "Admin")
  {
    include 'sidebar-admin.php';
  }
  
   if ($_SESSION['role'] == "Biometric")
  {
    include 'sidebar-biometric.php';
  }
 

  include 'menu-footer-buttons.php';
  include 'top-navigation.php';

?>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/webcamjs/1.0.24/webcam.js"></script>
	 <div class="right_col" role="main">
          <div class="">
            <div class="page-title">
              <div class="title_left">
                <h3>NIIMS Photo Capture</h3>
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
			<!-- Content starts here -->
      <?php 
        //$id = $_GET['id']; 
      ?>
	  <style>
        #canvas {
            border: 1px solid black;
        }
    </style>
    
    <form id="image-form" action="image" method="post" enctype="multipart/form-data">
   
		<input type="file" name="file-upload" id="file-upload" accept="images/*" >
        <br><br>
        <canvas id="canvas" width="300" height="290"></canvas>
        <br><br>
        <button id="save-button">Save Image</button>
    </form>
		<script>
					// Get the file upload button and the canvas element
					const fileUpload = document.getElementById('file-upload');
					const canvas = document.getElementById('canvas');
					const ctx = canvas.getContext('2d');
          const saveButton = document.getElementById('save-button');



					// Add an event listener to the file upload button
					fileUpload.addEventListener('change', (e) => 
          {
            // Get the selected file
            const file = e.target.files[0];

            // Create a new FileReader object
            const reader = new FileReader();

            // Add an event listener to the FileReader object
              reader.addEventListener('load', () => 
              {
                // Create a new Image object
                const img = new Image();

                // Set the src attribute of the Image object to the result of the FileReader object
                img.src = reader.result;

                // Add an event listener to the Image object
                img.addEventListener('load', () => {
                // Draw the image on the canvas
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                });
              });

              // Read the selected file as a data URL
              reader.readAsDataURL(file);
					});

          //save button
          /*saveButton.addEventListener('click', (e) => 
          {
              console.log('Hello');
            const file = fileUpload.files[0];
            const formData = new FormData();
            formData.append('file', file);

            fetch('image.php', {
              method: 'POST',
              body: formData
            })
            .then(response => response.text())
            .then(message => console.log(message))
            .catch(error => console.error(error));
          });*/

				</script>

    

<?php include 'footer_tables.php'; ?>