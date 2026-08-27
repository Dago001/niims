<?php
session_start();
if (!isset($_SESSION['success_message']) || !isset($_SESSION['redirect_url'])) {
    header("Location: update_rank");
    exit();
}

$success_message = $_SESSION['success_message'];
$redirect_url = $_SESSION['redirect_url'];

// Clear the session variables
unset($_SESSION['success_message']);
unset($_SESSION['redirect_url']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Success</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            font-family: Arial, sans-serif;
            overflow: hidden;
        }
        .loading {
            font-size: 14px;
            color: #333;
            opacity: 0;
        }
        
        /* Make SweetAlert popup smaller */
        .swal2-popup {
            font-size: 14px !important;
            padding: 1.5rem !important;
            width: 350px !important;
            max-width: 90% !important;
        }
        
        .swal2-title {
            font-size: 18px !important;
            margin-bottom: 0.5rem !important;
        }
        
        .swal2-content {
            font-size: 14px !important;
        }
        
        .swal2-confirm {
            padding: 0.4rem 1.5rem !important;
            font-size: 14px !important;
        }
    </style>
</head>
<body>
    <div class="loading"></div>
    
    <script>
        // Configure SweetAlert to be smaller
        const swalWithBootstrapButtons = Swal.mixin({
            customClass: {
                confirmButton: 'btn btn-success',
                cancelButton: 'btn btn-danger'
            },
            buttonsStyling: false
        });
        
        // Show success popup
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: '<?php echo addslashes($success_message); ?>',
            confirmButtonText: 'OK',
            confirmButtonColor: '#0e7a2e',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showClass: {
                popup: 'swal2-show',
                backdrop: 'swal2-backdrop-show'
            },
            hideClass: {
                popup: 'swal2-hide',
                backdrop: 'swal2-backdrop-hide'
            },
            width: '350px',
            padding: '1.5rem'
        }).then((result) => {
            if (result.isConfirmed) {
                // Redirect to update_rank.php with search parameter
                window.location.href = '<?php echo $redirect_url; ?>';
            }
        });
    </script>
</body>
</html>