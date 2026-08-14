<?php
session_start();
include("db/db.php");
require "vendor/autoload.php";
include 'vendor/phpqrcode/qrlib.php';



$serial = "09877";
$Bar = new Picqer\Barcode\BarcodeGeneratorHTML();
$code = $Bar->getBarcode($serial, $Bar::TYPE_CODE_128);


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


            $sqluse = "SELECT * FROM tbl_employee WHERE serviceNo=$id";
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
            }
            $staffData = $profile . " " . $surname . " " . $rank;
            $qrCodeData = json_encode($staffData);
            $qrCodeImage = 'staff_qr_code.png';

            QRcode::png($qrCodeData, $qrCodeImage, 'L', 200, 2);

            
        ?>
       
    <div class="card" style="width: 250px; height: 392px; text-align: center; background-image: url('assets/images/front.jpg'); ">
        <div class="row g-0">
            <div class="col-md-4">
                 
            </div>
            <div class="col-md-8">
                <img src="assets/images/logo.png" style="display: block; margin-left: auto; margin-right: auto; width: 50px;" alt="...">
                <p style="font-size : 15;" class="card-title">NIGERIA IMMIGRATION SERVICE</p>
                <div class="card-body">
                    
                    <img src=<?php echo "upload/".$profile ?> style="display: block; margin-left: auto; margin-right: auto; width: 100px; height:100px;" alt="...">
                </div>
                <div class="card-body">
                    
                    <p style="line-height:0.7;font-size: 20; font-family: ImperatorSmallCaps_Bold;"><b><?php if (isset($firstname)) { echo substr($firstname,0,1) . '' . substr($middlename,0,1) . ' ' . strtoupper($surname); } ?></b></p>
                    <p style="line-height : 0.5; font-family: ImperatorSmallCaps;"><?php if (isset($rank)) { echo $rank; } ?></p>
                    <img src=<?php echo "signature/".$sign ?> alt="Signature" width="60px" height="50px"> <!-- Signature image -->
                </div>
                <div class="card-body" style="text-align: right;">                   
                &nbsp;<?php echo '<img src="' . $qrCodeImage . '" style="width: 50px;">';//echo $result->getString(); ?>
                    &nbsp;<b style="font-size: 30; font-family: segoe UI; color: #BF9A33; text-align: centre; left: 148px;">NIS</b><b style="font-size: 24; font-family: subway; text-align: right;"><?php if (isset($profile)) { echo $profile; } ?></b> <b style="font-size: 12; font-family: segoe UI; text-align: right;">&nbsp; &nbsp; BG: <?php echo $bloodType; ?></b>
                </div>
            </div>
        </div>
    </div>
    <h4></h4>
    <div class="card border-success mb-3" style="width: 250px; height: 392px;  background-image: url('assets/images/back.jpg'">
        <div class="card-header bg-transparent border-success" style="text-align: center; color: red;">Valid till 
            <?php
                // Create a DateTime object for today's date
                $date = new DateTime();

                // Add 4 years to the date
                $date->add(new DateInterval('P3Y'));

                // Output the new date
                echo $date->format('d-m-Y');
               
            ?>
        </div>
            <div class="card-body text-success">
                
                <p style="font-size: 13;">The bearer of this Identity Card is a Personnel as defined by Section 2 of the Immigration Act, 2015 and has the power and privileges ascribed to him or her by law.<br><br>
                This Identity Card is an Official Document and relates only to the identity of the person described.<br><br>
                If found please contact nis.hrm@immigration.gov.ng or visit the nearest Immigration Office or Police Station.<br>
                
                <center><img src="signature/authority.jpg" style="display: block; margin-left: auto; margin-right: auto; width: 65px; height:60px;" alt="...">
                <!-- <hr style="border: 1px solid black; width: 80%; margin-top: 13% align: center"> -->
                Comptroller General <br>Immigration Service</center></p>
            </div>
            <center><div class="card-footer bg-transparent border-success"><?php if (isset($code)) { echo $code; } ?></div>
        </center>
        </div>    
    </div>
</body>
</html>
