<?php 
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index");
    die();
}

require_once('db/db.php');

if ($_SESSION['role'] == "DataEntry") {
    include 'sidebar-dataentry.php';
}
if ($_SESSION['role'] == "Supervisor") {
    include 'sidebar-supervisor.php';
}
if ($_SESSION['role'] == "Report") {
    include 'sidebar-view.php';
}
if ($_SESSION['role'] == "Admin") {
    include 'sidebar-admin.php';
}

include 'menu-footer-buttons.php';
include 'top-navigation.php';
 
                            $id = $_GET['id'];
                            
                            $dateofRegistration = date("Y/m/d");
                            
                            $sqluse = "SELECT emp.serviceNo, emp.surname, emp.firstName, emp.maiden_name,emp.signature, 
                            emp.middleName, emp.bloodType,emp.gender, emp.state_of_origin, emp2.presentPosting,
                            emp.dob, nk.nok, nk.nok_phone, nk.nok_relationship, emp.lga, emp.town,
                            emp2.dofa, emp2.dopa, emp.marital_status, qf.qualification, qf.date_obtained,emp2.currentRank, fn.ippis_No
                            FROM tbl_emppersonal emp 
                            INNER JOIN tbl_employment emp2 ON emp.serviceNo = emp2.serviceNo
                            INNER JOIN tbl_nok nk ON emp2.serviceNo = nk.serviceNo
                            
                            INNER JOIN tbl_qualification qf ON emp.serviceNo = qf.serviceNo
                            INNER JOIN tbl_finance fn ON emp.serviceNo = fn.serviceNo
                            WHERE emp.serviceNo=$id";
                            $retrieve = mysqli_query($conn, $sqluse);
                            $numb = mysqli_num_rows($retrieve); 
                            while ($foundk = mysqli_fetch_array($retrieve)) {
                                $profile = $foundk['serviceNo'];
                                $presentposting = $foundk['presentPosting'];
                                $surname = $foundk['surname'];
                                $firstname = $foundk['firstName'];
                                $middlename = $foundk['middleName'];
                                $sign = $foundk['signature'];
                                $rank = $foundk['currentRank'];
                                $bloodType = $foundk['bloodType'];
                                $gender = $foundk['gender'];
                                $state = $foundk['state_of_origin'];
                                $nok = $foundk['nok'];
                                $nok_relationship = $foundk['nok_relationship'];
                                $nok_phone = $foundk['nok_phone'];
                                $lga = $foundk['lga'];
                                $dob = $foundk['dob'];
                                $maidenName = $foundk['maiden_name'];
                                $dofa = $foundk['dofa'];
                                $dopa = $foundk['dopa'];
                                $marital_status = $foundk['marital_status'];
                                $qualification = $foundk['qualification'];
                                $dateObtained = $foundk['date_obtained'];
                                $town = $foundk['town'];
                                $ippis = $foundk['ippis_No'];//"NIIMS-".$id.date("dmy");
                            }
                

?>
<style>
table {
  border-collapse: collapse;
}

table, th, td {
  border: 1px solid black;
  
}

</style>
<!-- page content -->
<div class="right_col" role="main">
    <div class="">
        <div class="page-title">
            <div class="title_left">
                <h3>Personnel Record</h3>
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
    <style>
        .required {
            color: red;
        }
        .is-invalid {
        border: 1px solid red !important;
    }
    </style>
    <style>
                    table{
                        border: none;
                        text-align: right; 
                        
                    }
                    tr,td{
                        border: none;
                        
                           
                    }
                    
                .td-left{
                    text-align: left;
                }
                </style>
                <style>
                        h4 {
                            display: flex;
                            flex-direction: row;
                        }
                        
                        h4:before,
                        h4:after {
                            content: "";
                            flex: 1 1;
                            border-bottom: 2px solid #000;
                            margin: auto;
                        }
                        
                       /*  img {
                            height: 100px;
                            width: 100px;
                            border-radius: 50%;
                        } */
                    </style>
        <div class="clearfix"></div>

        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="x_panel">
                <div class="x_title">
                    
                </div>
                <div class="x_content">
                    <!-- Smart Wizard -->
                    <p></p>
                    
                     
                
                    <table cellpadding="10" style="margin-left: 70px;">
                        <tr>
                        <td colspan="2"></td>
                            <td colspan="2"></td>
                            <td colspan="2"></td>
                            <td colspan="2"></td>
                            <td colspan="2"></td>
                            <td colspan="2"></td>
                            <td colspan="2"></td>
                            <td colspan="2"></td>
                            <td colspan="8" align="center"></td>
                            <td colspan="2"></td>
                            <td colspan="2"></td><td colspan="2"></td><td colspan="2"></td>
                            <td colspan="2"></td>
                            <td colspan="2" align="center"><img src="upload/<?php echo $profile; ?>.png" style="width:85px;" ><br><br></td>
                        </tr>
                    </table>
                    <table cellpadding="10" style="margin-left: 70px; border: 1px solid;">  
                        <tr>
                            
                            <td><b>Service No:</b> </td>
                            <td class="td-left"><?php echo $profile; ?></td>
                            <td><b>IPPIS No:</b> </td>
                            <td colspan="1" class="td-left"><?php echo $ippis; ?></td>
                            
                        </tr>
                       
                        <tr>
                            <td><b>Surname:</b></td>
                            <td class="td-left"><?php echo $surname; ?></td>
                            <td><b>Gender:</b></td>
                            <td class="td-left"><?php echo $gender; ?></td>
                           
                        </tr>
                        <tr>
                            <td><b>First Name:</b> </td>
                            <td class="td-left"><?php echo $firstname; ?></td>
                       
                            <td><b>Date of Birth:</b> </td>
                            <td class="td-left"><?php echo $dob; ?></td>
                            
                        </tr>
                        <tr>
                            <td><b>Middle Name:</b> </td>
                            <td class="td-left"><?php echo $middlename; ?></td>
                            <td><b>Blood Group:</b> </td>
                            <td class="td-left"><?php echo $bloodType; ?></td>
                            
                        </tr>
                        <tr>
                            <td><b>State of Origin:</b></td>
                            <td class="td-left"><?php echo $state; ?></td>
                            <td><b>LGA:</b> </td>
                            <td class="td-left"><?php echo $lga; ?></td>
                            
                        </tr>
                        <tr>
                            <td><b>Town:</b> </td> 
                            <td class="td-left"><?php echo $town; ?></td>
                        </tr>
                        <tr><td colspan="5"><h4><b>Next of Kin</b></h4></td></tr>
                    
                        <tr>
                            <td><b>Next of Kin:</b> </td>
                            <td class="td-left"><?php echo $nok; ?></td>
                        </tr>
                        <tr>
                            <td><b>NOK Relationship:</b> </td>
                            <td class="td-left"><?php echo $nok_relationship; ?></td>
                        </tr>
                        <tr>
                            <td><b>NOK Phone:</b> </td>
                            <td class="td-left"><?php echo $nok_phone; ?></td>
                        </tr>
                        <tr><td colspan="5"><h4>Employment Information</h4></td></tr>
                        <tr>
                        
                            <td><b>Current Rank:</b> </td>
                            <td class="td-left"><?php echo $rank; ?></td>
                            <td><b>Current Formation:</b> </td>
                            <td class="td-left"><?php echo $presentposting ?></td>
                            
                        </tr>
                        <tr>
                            <td><b>Date of First Appointment:</b> </td>
                            <td class="td-left"><?php echo $dofa ; ?></td>
                            <td><b>Date of Present Appointment:</b> </td>
                            <td class="td-left"><?php echo $dopa; ?></td>
                        </tr>
                        <tr><td colspan="5"><h4>Educational Information</h4></td></tr>
                        <tr>
                        	<td><b>Present Qualification:</b></td><td class="td-left"><?php echo $qualification; ?></td>        
                            
                        </tr>
                        <tr>
                        	<td><b>Date Obtained:</b></td><td class="td-left"><?php echo $dateObtained; ?></td>        
                            
                        </tr>
                    	<tr>   
                            <td ></td><td></td>
                            <td> </td>
                            <td class="td-left"></td>
                            
                        </tr>
                      
                    </table>  
                        
                   
                    <!-- End SmartWizard Content -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- footer content -->
<?php include 'footer-enrol.php'; ?>


