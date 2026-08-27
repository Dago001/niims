<?php
error_reporting(0);
ini_set('display_errors', 0);
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../index");
    die();
}

include("../db/db.php");
require "../vendors/autoload.php";
include "../vendors/phpqrcode/qrlib.php";

use Picqer\Barcode\BarcodeGeneratorPNG;

// file_put_contents('custom_barcode.png', $barcode);

$serial = "0108863";
$Bar = new Picqer\Barcode\BarcodeGeneratorHTML();
$code = $Bar->getBarcode($serial, $Bar::TYPE_CODE_128);
/* QRcode::png($url, "barcode.png", QR_ECLEVEL_L, 4, 2);

echo "<img src=\"barcode.png\" alt=\"Barcode\">"; */
?>

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
    <title>Card</title>
    
    <link rel="stylesheet" href="dist/css/style.css/">
    <link href="dist/css/imperator.css" rel="stylesheet">
</head>
<body>
<script type="text/javascript">
    window.print();
</script>


    <style>
        @media print {
                h4 {page-break-before: always;}
                
            }
    </style>
    <?php 
            $id = $_GET['id'];


            $sqluse = "SELECT emp.serviceNo, emp.surname, emp.firstName, 
            emp.signature, emp.middleName, emp.bloodType,emp2.currentRank
            FROM tbl_emppersonal emp INNER JOIN tbl_employment emp2 
            ON emp.serviceNo = emp2.serviceNo WHERE emp.serviceNo=$id";
            $retrieve = mysqli_query($conn, $sqluse);
            $numb = mysqli_num_rows($retrieve); 
            while ($foundk = mysqli_fetch_array($retrieve)) {
                $profile = $foundk['serviceNo'];
                $name = $foundk['currentRank'];
                $surname = $foundk['surname'];
                $firstname = $foundk['firstName'];
                $middlename = $foundk['middleName'];
                $sign = $foundk['signature'];
                $rank = $foundk['currentRank'];
                $bloodType = $foundk['bloodType'];
                //$passport = $foundk['passport'];
            }
            //$staffData = $profile . " - " . $surname . " " . $firstname . " -".$rank;
            $staffData = 'niims.com.ng/employee.php?id='.$profile ;
            $qrCodeData = json_encode($staffData);
            $qrCodeImage = 'staff_qr_code.png';

            QRcode::png($staffData, $qrCodeImage, 'L', 200, 2);

            
            $generator = new BarcodeGeneratorPNG();
            $barcode = $generator->getBarcode($profile, $generator::TYPE_CODE_128, 3, 50, [0, 0, 0]);

            
        ?>
       
    <div class="card" style="width: 500px; height: 290px; image-resolution: 300dpi; background-image: url('assets/images/landscape-front.png'); ">
    
        <div class="row g-0">
        
            <div class="col-md-12">
               
           
                <div class="card-body" >
                     <h2 style="text-align:center; margin-top: 2px; font-size: 24; font-family: ImperatorSmallCaps_Bold;">
                    <br>
                    </h2>
                    <table width="506">
                        <tr>
                            <td width="100" style="margin-left:10px;"><?php echo '<img src="' . $qrCodeImage . '" style="width: 75px;">'; ?></td>
                            <td width="256" style="text-align: center;"><img src="upload/<?php echo $profile ?>.png" style="display: relative; margin-left: auto; margin-top: 25px; margin-right: auto; margin-top: 0.5px; width: 170px; height:170px;" alt="..."></td>
                            <td width="150"></td>
                        </tr>
                        <tr>
                            <td width="100"></td>
                            <td width="256" style="text-align: center;"><p style="line-height:0.8;font-size: 18; font-family: ImperatorSmallCaps_Bold; "><b><?php if (isset($firstname)) { echo substr($firstname,0,1) . '' . substr($middlename,0,1) . ' ' . strtoupper($surname); } ?></b></p></td>
                            <td width="150"></td>
                        </tr>
                        <tr>
                            <td width="100"><br><b style="line-height : 0.01; font-size: 32; font-family: subway; text-align: right; position: relative; top: 0; margin-left : 65px;"> <?php if (isset($profile)) { echo $profile; } ?></b></td>
                            <td width="256"><p style="line-height : 0.5; font-size: 18; font-family: ImperatorSmallCaps; text-align: center;"><?php if (isset($rank)) { echo $rank; } ?></p></td>
                            <td width="150" style="text-align: right; padding-right: 25px;"> </td>
                        </tr>
                        <tr>
                            <td width="150"></td>
                            <td width="256" style="text-align: center;"><img src="signature/<?php echo $profile; ?>.png" alt="Signature" width="90px" height="35px" style="text-align: center;"> <!-- Signature image --></td>
                            <td width="150" style="text-align: right; padding-right: 25px;"><b style="font-size: 12; font-family: segoe UI; text-align: right; margin-bottom: 10px; margin-left : 15px;">&nbsp; &nbsp; BG: <?php echo $bloodType; ?></b> </td>
                        </tr>
                    </table>
                </div>
                
               <!--  <div class="card-body">                            
                             
                            <?php //echo '<img src="' . $qrCodeImage . '" style="margin-left : 400px; width: 50px;">';//echo $result->getString(); ?>
                            <b style="font-size: 12; font-family: segoe UI; text-align: right; margin-left : 430px;">&nbsp; &nbsp; BG: <?php echo $bloodType; ?></b> 
                            <b style="line-height : 0.3; font-size: 64; font-family: subway; text-align: right; margin-top:20px; margin-left : 30px;">
                                <?php //if (isset($profile)) { echo $profile; } ?>                                
                            </b> 
                </div> -->
            </div>
        </div>
    </div>
    <h4></h4>
    <div class="card border-success mb-3" style="width: 500px; height: 290px;  background-image: url('assets/images/landscape-back.png');">
            <!-- <div class="card border-success mb-3" style="width: 500px; height: 280px;  background-image: url('assets/images/portrait-back.jpg');"> 
         <div class="card-header bg-transparent border-success" style="text-align: center; color: red;">Valid till 
            <?php
                // Create a DateTime object for today's date
                //$date = new DateTime();

                // Add 4 years to the date
               // $date->add(new DateInterval('P3Y'));

                // Output the new date
               // echo $date->format('d-m-Y');
               
            ?>
        </div>-->
            <div class="card-body text-success">
                
                <p style="font-size: 16; text-align: center; padding-right: 20px; padding-left: 20px;"><br><br>
                    The bearer of this Identity Card is a Personnel of Nigeria Immigration Service and has the power and privileges ascribed to him or her by law.
                    <br>
                This Identity Card is an Official Document and relates only to the identity of the person described.
                If found please contact the nearest Immigration Office or Police Station.
                 <br>
                <img src="signature/authority.jpg" style="display: block; margin-left: auto; margin-right: auto; margin-top:20px; width: 70px;" alt="...">
                
                Comptroller General
                 <br>
                Immigration Service
                <center><?php if (isset($code)) { echo $code; } ?></center></p>
            </div>
        </div>    
    </div>
</body>
</html>
