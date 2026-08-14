<?php
// Initialize error variables
$service_number_error = "";
$email_error = "";
$name_error = "";

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // Validate Service Number
  if (empty($_POST["service_number"])) {
    $service_number_error = "Service Number is required";
  } elseif (!ctype_digit($_POST["service_number"])) {
    $service_number_error = "Service Number must be numeric";
  }

  // Validate Email
  if (empty($_POST["email"])) {
    $email_error = "Email is required";
  } elseif (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)) {
    $email_error = "Invalid email format";
  }

  // Validate Name
  if (empty($_POST["name"])) {
    $name_error = "Name is required";
  } elseif (preg_match('/\d/', $_POST["name"])) {
    $name_error = "Name must not contain numbers";
  }
}
?>
