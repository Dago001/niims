<?php
// The "Manage Users" list used to live on its own page here — it has been
// merged into the Manage Users tab of createuser.php. This redirect exists
// only so old bookmarks/links to edit-user keep working.
header("Location: createuser?tab=manage");
exit();
