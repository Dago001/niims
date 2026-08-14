<?php
error_reporting(E_ALL);
require_once('db/db.php');

if (isset($_POST["regEmployee"])){
		
		$NISNo= ($_POST['serviceNo']);
        $currentRank= ($_POST['currentRank']);
        $firstName= ($_POST['fname']);
        $middleName= ($_POST['mname']);
        $surname= ($_POST['lname']);
        $gender= ($_POST['gender']);
        $bloodType= ($_POST['bloodType']);
        $presentPosting= ($_POST['presentPosting']);
        $email= ($_POST['email']);
        $dob= ($_POST['dob']);
        $town= ($_POST['town']);
        $maritalStatus= ($_POST['marital_status']);
        $maidenName= ($_POST['maiden_name']);
        $state= ($_POST['state_of_origin']);
        $lga= ($_POST['lga']);
        $phone= ($_POST['phone']);
        $genotype= ($_POST['genotype']);
        $nok= ($_POST['nok']);
        $nok_relationship= ($_POST['nok_relationship']);
        $nok_phone= ($_POST['nok_phone']);
        $qualification= ($_POST['qualification']);
        $institution= ($_POST['institution']);
        $dateObtained= ($_POST['date_obtained']);
        $certificateNo= ($_POST['certificate_number']);
        $dopa= ($_POST['dopa']);
        $dofa= ($_POST['dofa']);
        $date_of_entry = date("Y/m/d");

        
	                 
                /*   // Get image name
                $image = $_FILES["photo"]['name'];
                $sign = $_FILES["signature"]["name"];
                
                // image file directory
                $target = "upload/".basename($image);
                $target2 = "signature/".basename($sign);

                //echo $target;
                $temp = explode(".", $_FILES["photo"]["name"]);
                $temp2 = explode(".", $_FILES["signature"]["name"]);
               

                $extension = pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION);
                $extension2 = pathinfo($_FILES["signature"]["name"], PATHINFO_EXTENSION);

                $newfilename = $NISNo.".".$extension;
                $newfilename2 = $NISNo.".".$extension2; */
				

		$insertion="INSERT INTO tbl_employee
                                    VALUES(
									'$NISNo',
                                    '$surname',                                    
                                    '$firstName', 
                                    '$middleName',
                                    '$dob',
                                    '$maritalStatus',
                                    '$maidenName',                                    
                                    '$gender',
                                    '$state',
                                    '$town','$lga', 
                                    '$bloodType',
                                    '$genotype',
                                    '$nok','$nok_relationship','$nok_phone',
                                    '$qualification','$institution',
                                    '$certificateNo','$dateObtained',
                                    '$dofa','$dopa',
                                    '$presentPosting',
									'$email', 
                                    '$phone',
                                    '','', 
                                    '$currentRank',
                                    '$date_of_entry'
                                    ) ";
						    
                        

                                   /*  if (/* move_uploaded_file($_FILES["photo"]["tmp_name"], "upload/".$newfilename) && move_uploaded_file($_FILES["signature"]["tmp_name"], "signature/".$newfilename2) ) { */
                                        $insert = mysqli_query($conn, $insertion, $resultmode = MYSQLI_STORE_RESULT) or die(mysqli_error($conn)."There was an error querying the database");
                                     if ($insert){   
                                        require_once('success.php');    

                                    }else{
                                        $msg = "Failed to upload image and candidate information. 1. Please check file size and format 2. Click the back browser arrow to complete the cleared fields then retry submitting the form.";
                                    echo $msg;
                                    }
                                    
                                    
                                    
}


?>