<?php
    if (isset($_POST["signature"])){
            $serviceNo = $_POST['user_id'];
    }
    $folderPath = "signature/";
    $image_parts = explode(";base64,", $_POST['signed']); 
    $image_type_aux = explode("image/", $image_parts[0]);
    $image_type = $image_type_aux[1];
    $image_base64 = base64_decode($image_parts[1]);
    $file = $folderPath . $serviceNo . '.'.$image_type;
    file_put_contents($file, $image_base64);
    echo "Signature Uploaded Successfully.";
?>