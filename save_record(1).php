<?php
// Unused legacy duplicate of save_record.php — nothing in the app links to
// this file, and unlike the canonical save_record.php it had no login
// check. Left in place (rather than deleted) but disabled.
http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Not found.']);
exit;
