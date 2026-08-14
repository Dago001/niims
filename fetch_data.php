<?php
// Connect to the database
 require_once ('db/db.php');


// Fetch the data
if (isset($_POST['id'])) {
    $id = $_POST['id'];
    $query = "SELECT emp.serviceNo, emp.surname, emp.firstName, emp.phone, emp.middleName, emp.bloodType,emp2.currentRank,emp2.presentPosting
      FROM tbl_emppersonal emp INNER JOIN tbl_employment emp2 
      ON emp.serviceNo = emp2.serviceNo WHERE emp.serviceNo = '$id'";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    echo json_encode($row);
}

// Close the connection
mysqli_close($conn);
?>
