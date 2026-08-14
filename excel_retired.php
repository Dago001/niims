<?php 
    // Load the database configuration file 
    require_once('db/db.php');
     
    // Filter the excel data 
    function filterData(&$str){ 
        $str = preg_replace("/\t/", "\\t", $str); 
        $str = preg_replace("/\r?\n/", "\\n", $str); 
        if(strstr($str, '"')) $str = '"' . str_replace('"', '""', $str) . '"'; 
    } 
     
    // Excel file name for download 
    $fileName = "nis_retired_nominal_roll_" . date('Y-m-d') . ".xls"; 
     
    // Column names 
    $fields = array('SERVICE NO.', 'SURNAME', 'FIRST NAME', 'MIDDLE NAME', 'RANK', 'GENDER', 'STATE', 'LGA','TOWN', 'PHONE NO.','EMAIL', 'DOB', 'DOFA', 'DOPA', 'DOR','MARITAL STATUS', 'NEXT OF KIN', 'BLOOD TYPE','IPPIS NO','BANK','ACCOUNT NO','PFA','PENSION NO','NHF NO'); 
     
    // Display column names as first row 
    $excelData = implode("\t", array_values($fields)) . "\n"; 
     
    // Fetch retired records from database 
    $query = $conn->query("SELECT DISTINCT emp.serviceNo, emp.surname, emp.firstName, emp.maiden_name,emp.phone, emp.email,
            emp.middleName, emp.bloodType,emp.gender, emp.state_of_origin, emp2.presentPosting,
            emp.dob, nk.nok, nk.nok_phone, nk.nok_relationship, emp.lga, emp.town,
            emp2.dofa, emp2.dopa, emp.marital_status, qf.qualification, qf.date_obtained,emp2.currentRank,ff.ippis_no, ff.bank, ff.accountNo, ff.pensionNo, ff.pfa, ff.nhf_no
            FROM tbl_emppersonal emp JOIN tbl_employment emp2 ON emp.serviceNo = emp2.serviceNo
            JOIN tbl_nok nk ON emp.serviceNo = nk.serviceNo
            JOIN tbl_qualification qf ON emp.serviceNo = qf.serviceNo
            JOIN tbl_finance ff ON emp.serviceNo = ff.serviceNo
            WHERE emp2.empStatus != 'Deceased' AND (TIMESTAMPDIFF(YEAR, emp.dob, CURDATE()) >= 60 OR TIMESTAMPDIFF(YEAR, emp2.dofa, CURDATE()) >= 35 OR emp2.empStatus = 'Retired')"); 
    if($query->num_rows > 0){ 
        // Output each row of the data 
        while($row = $query->fetch_assoc()){ 
            $dob = new DateTime($row['dob']);
            $dofa = new DateTime($row['dofa']);
                
            $dob->add(new DateInterval('P60Y'));
            $dofa->add(new DateInterval('P35Y'));
                    
            if ($dob < $dofa) {
                $dor = $dob->format('Y-m-d');
            } else {
                $dor = $dofa->format('Y-m-d');
            }
            $lineData = array($row['serviceNo'], $row['surname'], $row['firstName'], $row['middleName'], $row['currentRank'], $row['gender'], $row['state_of_origin'], $row['lga'], $row['town'], $row['phone'], $row['email'], $row['dob'], $row['dofa'], $row['dopa'],$dor, $row['marital_status'], $row['nok'], $row['bloodType'],$row['ippis_no'],$row['bank'],$row['accountNo'],$row['pfa'],$row['pensionNo'],$row['nhf_no']); 
            array_walk($lineData, 'filterData'); 
            $excelData .= implode("\t", array_values($lineData)) . "\n"; 
        } 
    }else{ 
        $excelData .= 'No records found...'. "\n"; 
    } 
     
    // Headers for download 
    header("Content-Type: application/vnd.ms-excel"); 
    header("Content-Disposition: attachment; filename=\"$fileName\""); 
     
    // Render excel data 
    echo $excelData; 
     
    exit;
?>
