<?php
require_once 'includes/auth.php';
require_once 'includes/permissions.php';

if (!isLoggedIn()) {
    header('Location: login');
    exit();
}

// Get posting ID
if (isset($_GET['posting_id'])) {
    $posting_id = $_GET['posting_id'];
    // Query to get posting details
    $stmt = $pdo->prepare("SELECT * FROM postings WHERE id = ?");
    $stmt->execute([$posting_id]);
    $posting = $stmt->fetch();

    if ($posting) {
        // Display posting details and allow editing
        ?>
        <form action="edit_posting" method="post">
            <input type="hidden" name="posting_id" value="<?php echo $posting_id; ?>">
            <label>Posting Date:</label>
            <input type="date" name="date_of_posting" value="<?php echo $posting['date_of_posting']; ?>">
            <br>
            <label>Posting Type:</label>
            <select name="posting_type">
                <option value="Previous">Previous Posting</option>
                <option value="Current">Current Posting</option>
            </select>
            <br>
            <label>Location:</label>
            <input type="text" name="location" value="<?php echo $posting['location']; ?>">
            <br>
            <button type="submit">Save Changes</button>
        </form>
        <?php
    } else {
        echo "Posting not found.";
    }
} else {
    echo "Invalid posting ID.";
}
?>