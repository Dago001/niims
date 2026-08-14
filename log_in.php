<?php 
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
  
  require_once('db/db.php');

  if (isset($_POST['login']))
  {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Ensure no field is empty
    if (!empty($username) && !empty($password))
    {
      // Hash password
      //$password = md5($password);
      $sql ="SELECT * FROM tbl_users WHERE user_id ='$username' and password='$password'";
      $result = $conn -> query($sql);
      //$result = mysqli_query($connection,$sql);
      if ($row = $result -> FETCH_ASSOC())
      {
        //  $message = $row['email'];
          $_SESSION['username'] = $row['lastname'];
          $_SESSION['email'] = $row['email_address'];
          $_SESSION['role'] = $row['user_role'];
          if ($_SESSION['role'] == "Admin"){
            header('Location: index');
            exit();
          }else{
            header('Location: landing');
            exit();
          }
          
         
      }
      else {
        $errors[] = "Authentication failed.";
        echo trim($_POST['email']);
        echo $row['password'] ;
        echo $row['email'];
        header('Location: login');
        exit();

      }
    }
    else {
      $errors[] = "All fields are required.";
    }
  }
?>