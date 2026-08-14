<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("HTTP/1.1 401 Unauthorized");
    exit("Unauthorized");
}
 $id = $_POST['user_id'];
$filename =  $id . '.png';
$filepath = 'upload/';
if(!is_dir($filepath))
	mkdir($filepath);
if(isset($_FILES['webcam'])){	
	move_uploaded_file($_FILES['webcam']['tmp_name'], $filepath.$filename);

	echo $filepath.$filename;
}
?>
