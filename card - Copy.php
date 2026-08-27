<?php
// Unused legacy duplicate of card.php — nothing in the app links to this
// file, and it predates the SQL-injection fix applied to card.php. Left in
// place (rather than deleted) but disabled, since it served no function
// other than being a second, unpatched attack surface for the same page.
http_response_code(404);
exit;
