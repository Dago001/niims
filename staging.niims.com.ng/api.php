<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key");

// Handle OPTIONS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once('db/db.php');

// Helper function to send JSON response
function sendResponse($status, $message, $data = null) {
    http_response_code($status);
    $response = [
        "success" => ($status >= 200 && $status < 300),
        "message" => $message
    ];
    if ($data !== null) {
        $response["data"] = $data;
    }
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit();
}

// 1. Initialize API Keys Table if not exists
try {
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'tbl_api_keys'");
    if (mysqli_num_rows($table_check) == 0) {
        $create_table_sql = "CREATE TABLE tbl_api_keys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_name VARCHAR(100) NOT NULL,
            api_key VARCHAR(64) UNIQUE NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        mysqli_query($conn, $create_table_sql);

        // No default key is seeded. An administrator must insert a freshly
        // generated key directly into tbl_api_keys for each real client.
    }

    // A fixed API key value was previously hardcoded in this file and committed
    // to source control, so it must be treated as permanently compromised.
    // Deactivate it wherever it may already exist, on every environment.
    $leaked_key = "niims_sec_token_86f5c88c7b80a1e359";
    $revoke_stmt = mysqli_prepare($conn, "UPDATE tbl_api_keys SET is_active = 0 WHERE api_key = ?");
    mysqli_stmt_bind_param($revoke_stmt, "s", $leaked_key);
    mysqli_stmt_execute($revoke_stmt);
    mysqli_stmt_close($revoke_stmt);

    // 1b. Initialize rate limit tracking table
    $rl_check = mysqli_query($conn, "SHOW TABLES LIKE 'tbl_api_rate_limit'");
    if (mysqli_num_rows($rl_check) == 0) {
        mysqli_query($conn, "CREATE TABLE tbl_api_rate_limit (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bucket_key VARCHAR(64) NOT NULL,
            window_start INT NOT NULL,
            request_count INT NOT NULL DEFAULT 0,
            UNIQUE KEY bucket_key (bucket_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }
} catch (Exception $e) {
    sendResponse(500, "Database table initialization failure: " . $e->getMessage());
}

// 2. Authenticate the Request
$api_key = "";

// Check X-API-Key Header
if (isset($_SERVER['HTTP_X_API_KEY'])) {
    $api_key = $_SERVER['HTTP_X_API_KEY'];
}
// Check Authorization Bearer Header
elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
    if (preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
        $api_key = $matches[1];
    }
}
// NOTE: no query-string (?api_key=) fallback — keys in URLs end up in access
// logs, browser history and Referer headers.

if (empty($api_key)) {
    sendResponse(401, "Authentication failed: Missing API Key in request headers.");
}

// 2b. Rate limit by API key (60 requests / minute)
$rate_limit_max = 60;
$rate_window_seconds = 60;
$window_start = intdiv(time(), $rate_window_seconds) * $rate_window_seconds;
$bucket_key = hash('sha256', $api_key) . ':' . $window_start;

mysqli_query($conn, "DELETE FROM tbl_api_rate_limit WHERE window_start < " . (int)($window_start - $rate_window_seconds * 5));

$rl_stmt = mysqli_prepare($conn, "INSERT INTO tbl_api_rate_limit (bucket_key, window_start, request_count) VALUES (?, ?, 1)
    ON DUPLICATE KEY UPDATE request_count = request_count + 1");
mysqli_stmt_bind_param($rl_stmt, "si", $bucket_key, $window_start);
mysqli_stmt_execute($rl_stmt);
mysqli_stmt_close($rl_stmt);

$rl_check_stmt = mysqli_prepare($conn, "SELECT request_count FROM tbl_api_rate_limit WHERE bucket_key = ?");
mysqli_stmt_bind_param($rl_check_stmt, "s", $bucket_key);
mysqli_stmt_execute($rl_check_stmt);
mysqli_stmt_bind_result($rl_check_stmt, $current_count);
mysqli_stmt_fetch($rl_check_stmt);
mysqli_stmt_close($rl_check_stmt);

if ($current_count > $rate_limit_max) {
    sendResponse(429, "Too Many Requests: rate limit of {$rate_limit_max} requests/minute exceeded.");
}

// Verify key in database
$stmt = mysqli_prepare($conn, "SELECT id, client_name FROM tbl_api_keys WHERE api_key = ? AND is_active = 1 LIMIT 1");
mysqli_stmt_bind_param($stmt, "s", $api_key);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if (mysqli_stmt_num_rows($stmt) == 0) {
    sendResponse(401, "Authentication failed: Invalid or inactive API Key.");
}

mysqli_stmt_bind_result($stmt, $client_id, $client_name);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// 3. Process Request based on HTTP Method
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'get_personnel') {
        if (empty($_GET['service_no'])) {
            sendResponse(400, "Bad Request: service_no is required.");
        }
        $service_no = intval($_GET['service_no']);

        // Fetch Personal Data
        $p_stmt = mysqli_prepare($conn, "SELECT * FROM tbl_emppersonal WHERE serviceNo = ? LIMIT 1");
        mysqli_stmt_bind_param($p_stmt, "i", $service_no);
        mysqli_stmt_execute($p_stmt);
        $p_res = mysqli_stmt_get_result($p_stmt);
        $personnel = mysqli_fetch_assoc($p_res);
        mysqli_stmt_close($p_stmt);

        if (!$personnel) {
            sendResponse(404, "Personnel with Service No '$service_no' not found.");
        }

        // Fetch Next of Kin
        $nok_stmt = mysqli_prepare($conn, "SELECT * FROM tbl_nok WHERE serviceNo = ? LIMIT 1");
        mysqli_stmt_bind_param($nok_stmt, "i", $service_no);
        mysqli_stmt_execute($nok_stmt);
        $nok_res = mysqli_stmt_get_result($nok_stmt);
        $nok = mysqli_fetch_assoc($nok_res) ?: new stdClass();
        mysqli_stmt_close($nok_stmt);

        // Fetch Qualification
        $qual_stmt = mysqli_prepare($conn, "SELECT * FROM tbl_qualification WHERE serviceNo = ? LIMIT 1");
        mysqli_stmt_bind_param($qual_stmt, "i", $service_no);
        mysqli_stmt_execute($qual_stmt);
        $qual_res = mysqli_stmt_get_result($qual_stmt);
        $qualification = mysqli_fetch_assoc($qual_res) ?: new stdClass();
        mysqli_stmt_close($qual_stmt);

        // Fetch Finance Details
        $fin_stmt = mysqli_prepare($conn, "SELECT * FROM tbl_finance WHERE serviceNo = ? LIMIT 1");
        mysqli_stmt_bind_param($fin_stmt, "i", $service_no);
        mysqli_stmt_execute($fin_stmt);
        $fin_res = mysqli_stmt_get_result($fin_stmt);
        $finance = mysqli_fetch_assoc($fin_res) ?: new stdClass();
        mysqli_stmt_close($fin_stmt);

        // Compile payload
        $payload = [
            "personal" => $personnel,
            "next_of_kin" => $nok,
            "qualification" => $qualification,
            "finance" => $finance
        ];

        sendResponse(200, "Personnel profile retrieved successfully.", $payload);
    } 
    elseif ($action === 'search_personnel') {
        $query = trim($_GET['query'] ?? '');
        if (strlen($query) < 2) {
            sendResponse(400, "Bad Request: search query must be at least 2 characters long.");
        }

        $search_term = "%$query%";
        $s_stmt = mysqli_prepare($conn, "SELECT serviceNo, surname, firstName, middleName, gender, rank, dob FROM tbl_emppersonal WHERE serviceNo LIKE ? OR surname LIKE ? OR firstName LIKE ? OR middleName LIKE ? LIMIT 50");
        mysqli_stmt_bind_param($s_stmt, "ssss", $search_term, $search_term, $search_term, $search_term);
        mysqli_stmt_execute($s_stmt);
        $s_res = mysqli_stmt_get_result($s_stmt);
        
        $results = [];
        while ($row = mysqli_fetch_assoc($s_res)) {
            $results[] = $row;
        }
        mysqli_stmt_close($s_stmt);

        sendResponse(200, "Found " . count($results) . " personnel matching query.", $results);
    } 
    elseif ($action === 'get_stats') {
        // Compute statistics dynamically
        $total_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM tbl_emppersonal");
        $total = mysqli_fetch_assoc($total_res)['cnt'];

        // Compute Retired and Active counts
        $retired_sql = "SELECT COUNT(*) as cnt FROM tbl_emppersonal WHERE 
                        (TIMESTAMPDIFF(YEAR, dob, CURDATE()) >= 60) OR 
                        (serviceNo IN (SELECT serviceNo FROM tbl_finance WHERE TIMESTAMPDIFF(YEAR, date_of_first_appointment, CURDATE()) >= 35))";
        $retired_res = mysqli_query($conn, $retired_sql);
        $retired = mysqli_fetch_assoc($retired_res)['cnt'];
        $active = $total - $retired;

        $stats = [
            "total_personnel" => intval($total),
            "active_personnel" => intval($active),
            "retired_personnel" => intval($retired),
            "last_synced" => date("Y-m-d H:i:s")
        ];

        sendResponse(200, "System statistics retrieved successfully.", $stats);
    } 
    else {
        sendResponse(400, "Bad Request: Invalid or missing GET action.");
    }
} 

elseif ($method === 'POST') {
    // Read raw JSON request body
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(400, "Bad Request: Invalid JSON request payload.");
    }

    $action = $data['action'] ?? '';

    if ($action === 'create_personnel') {
        // Validate required fields for tbl_emppersonal
        $required = ['serviceNo', 'surname', 'firstName', 'middleName', 'dob', 'marital_status', 'gender', 'state_of_origin', 'town', 'lga', 'bloodType', 'genotype', 'email', 'user_id', 'date_of_entry'];
        $missing = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            sendResponse(400, "Bad Request: Missing required payload fields: " . implode(', ', $missing));
        }

        $serviceNo = intval($data['serviceNo']);

        // Check if service number already exists
        $dup_check = mysqli_prepare($conn, "SELECT serviceNo FROM tbl_emppersonal WHERE serviceNo = ? LIMIT 1");
        mysqli_stmt_bind_param($dup_check, "i", $serviceNo);
        mysqli_stmt_execute($dup_check);
        mysqli_stmt_store_result($dup_check);
        if (mysqli_stmt_num_rows($dup_check) > 0) {
            mysqli_stmt_close($dup_check);
            sendResponse(409, "Conflict: Personnel with Service No '$serviceNo' already exists.");
        }
        mysqli_stmt_close($dup_check);

        // Insert into tbl_emppersonal
        $insert_sql = "INSERT INTO tbl_emppersonal (
            serviceNo, surname, firstName, middleName, dob, marital_status, maiden_name, gender, 
            state_of_origin, town, lga, bloodType, genotype, email, phone, user_id, date_of_entry
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $ins_stmt = mysqli_prepare($conn, $insert_sql);
        
        $surname = trim($data['surname']);
        $firstName = trim($data['firstName']);
        $middleName = trim($data['middleName']);
        $dob = trim($data['dob']);
        $marital_status = trim($data['marital_status']);
        $maiden_name = trim($data['maiden_name'] ?? '');
        $gender = trim($data['gender']);
        $state_of_origin = trim($data['state_of_origin']);
        $town = trim($data['town']);
        $lga = trim($data['lga']);
        $bloodType = trim($data['bloodType']);
        $genotype = trim($data['genotype']);
        $email = trim($data['email']);
        $phone = trim($data['phone'] ?? '');
        $user_id = trim($data['user_id']);
        $date_of_entry = trim($data['date_of_entry']);

        mysqli_stmt_bind_param(
            $ins_stmt, 
            "issssssssssssssss", 
            $serviceNo, $surname, $firstName, $middleName, $dob, $marital_status, $maiden_name, $gender,
            $state_of_origin, $town, $lga, $bloodType, $genotype, $email, $phone, $user_id, $date_of_entry
        );

        if (mysqli_stmt_execute($ins_stmt)) {
            mysqli_stmt_close($ins_stmt);
            sendResponse(201, "Personnel record created successfully in real-time.", ["serviceNo" => $serviceNo]);
        } else {
            $err = mysqli_stmt_error($ins_stmt);
            mysqli_stmt_close($ins_stmt);
            sendResponse(500, "Server Error: Failed to insert personnel record: " . $err);
        }
    } 
    elseif ($action === 'update_personnel') {
        if (empty($data['serviceNo'])) {
            sendResponse(400, "Bad Request: serviceNo is required for update operations.");
        }
        $serviceNo = intval($data['serviceNo']);

        // Check if record exists
        $exist_check = mysqli_prepare($conn, "SELECT serviceNo FROM tbl_emppersonal WHERE serviceNo = ? LIMIT 1");
        mysqli_stmt_bind_param($exist_check, "i", $serviceNo);
        mysqli_stmt_execute($exist_check);
        mysqli_stmt_store_result($exist_check);
        if (mysqli_stmt_num_rows($exist_check) == 0) {
            mysqli_stmt_close($exist_check);
            sendResponse(404, "Not Found: Personnel record with Service No '$serviceNo' does not exist.");
        }
        mysqli_stmt_close($exist_check);

        // Build dynamic UPDATE query based on passed parameters
        $allowed_fields = ['surname', 'firstName', 'middleName', 'dob', 'marital_status', 'maiden_name', 'gender', 'state_of_origin', 'town', 'lga', 'bloodType', 'genotype', 'email', 'phone'];
        $updates = [];
        $params = [];
        $types = "";

        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = trim($data[$field]);
                $types .= "s";
            }
        }

        if (empty($updates)) {
            sendResponse(400, "Bad Request: No updatable fields provided in request body.");
        }

        $update_sql = "UPDATE tbl_emppersonal SET " . implode(', ', $updates) . " WHERE serviceNo = ?";
        $params[] = $serviceNo;
        $types .= "i";

        $up_stmt = mysqli_prepare($conn, $update_sql);
        
        // Dynamically bind params
        mysqli_stmt_bind_param($up_stmt, $types, ...$params);

        if (mysqli_stmt_execute($up_stmt)) {
            mysqli_stmt_close($up_stmt);
            sendResponse(200, "Personnel record updated successfully in real-time.", ["serviceNo" => $serviceNo]);
        } else {
            $err = mysqli_stmt_error($up_stmt);
            mysqli_stmt_close($up_stmt);
            sendResponse(500, "Server Error: Failed to update personnel record: " . $err);
        }
    } 
    else {
        sendResponse(400, "Bad Request: Invalid or missing POST action.");
    }
} 

else {
    sendResponse(405, "Method Not Allowed: Only GET and POST requests are supported.");
}
?>
