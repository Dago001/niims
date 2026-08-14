<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("HTTP/1.1 401 Unauthorized");
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}
require_once('db/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['image']) || !isset($data['u_id'])) {
        $_SESSION['signature_error'] = 'Invalid data received';
        echo json_encode([
            'success' => false,
            'message' => 'Invalid data received'
        ]);
        exit;
    }
    
    $image = $data['image'];
    $serviceNo = $data['u_id'];
    $uid = $serviceNo . '.png';
    
    // Remove the data URL prefix (data:image/png;base64,)
    $image = str_replace('data:image/png;base64,', '', $image);
    $image = str_replace(' ', '+', $image);
    $imageData = base64_decode($image);

    if (!$imageData) {
        $_SESSION['signature_error'] = 'Invalid image data';
        echo json_encode([
            'success' => false,
            'message' => 'Invalid image data'
        ]);
        exit;
    }

    // Define the file path and name
    $filePath = 'signature/';
    $fileName = $uid;

    // Ensure the directory exists
    if (!is_dir($filePath)) {
        mkdir($filePath, 0777, true);
    }

    // Save the file
    if (file_put_contents($filePath . $fileName, $imageData)) {
        // Get user details for personalized message
        $userName = '';
        $sql = "SELECT surname, firstName, middleName 
                FROM tbl_emppersonal 
                WHERE serviceNo = ?";
                
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $serviceNo);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            $userName = $row['surname'] . ', ' . $row['firstName'];
            if (!empty($row['middleName'])) {
                $userName .= ' ' . $row['middleName'];
            }
        }
        mysqli_stmt_close($stmt);
        
        // Store success message in session for signaturecapture.php
        $message = 'Signature has been successfully saved';
        if ($userName) {
            $message .= ' for ' . $userName;
        }
        $_SESSION['signature_success'] = $message;
        
        // Return success response
        // echo json_encode([
        //     'success' => true,
        //     'message' => 'Signature saved successfully!'
        // ]);
    } else {
        $_SESSION['signature_error'] = 'Failed to save signature file';
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save signature file'
        ]);
    }
} else {
    $_SESSION['signature_error'] = 'Invalid request method';
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
}
?>