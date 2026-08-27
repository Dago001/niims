<?php
// Unused legacy duplicate of the enrolment save handler — nothing in the
// app links to this file. It had no login check and built its INSERT from
// unescaped POST data (SQL injection). Left in place (rather than deleted)
// but disabled, since save_record.php is the real, hardened endpoint.
http_response_code(404);
exit;
