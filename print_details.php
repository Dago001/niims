<?php
session_start();
require_once('db/db.php');

if (!isset($_SESSION['username']) || !isset($_GET['id'])) {
    die('Unauthorized');
}

$printId = intval($_GET['id']);

$query = "SELECT * FROM tbl_printed_cards WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $printId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$print = mysqli_fetch_assoc($result);

if ($print) {
    ?>
    <div style="max-height: 400px; overflow-y: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Service Number:</strong></td>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($print['serviceNo']); ?></td>
            </tr>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Name:</strong></td>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($print['surname'] . ' ' . $print['firstName']); ?></td>
            </tr>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Rank:</strong></td>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($print['rank']); ?></td>
            </tr>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Print Date:</strong></td>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><?php echo date('d/m/Y H:i:s', strtotime($print['print_date'])); ?></td>
            </tr>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Printed By:</strong></td>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($print['printed_by']); ?></td>
            </tr>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Print Method:</strong></td>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;">
                    <span style="padding: 2px 8px; border-radius: 4px; background: <?php echo $print['print_method'] == 'bulk' ? '#d1ecf1' : '#fff3cd'; ?>; color: <?php echo $print['print_method'] == 'bulk' ? '#0c5460' : '#856404'; ?>;">
                        <?php echo htmlspecialchars(ucfirst($print['print_method'])); ?>
                    </span>
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Print Count:</strong></td>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;">
                    <span style="padding: 2px 8px; border-radius: 4px; background: #d4edda; color: #155724;">
                        <?php echo $print['print_count']; ?> time<?php echo $print['print_count'] > 1 ? 's' : ''; ?>
                    </span>
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Status:</strong></td>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;">
                    <span style="padding: 2px 8px; border-radius: 4px; background: <?php echo $print['status'] == 'success' ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $print['status'] == 'success' ? '#155724' : '#721c24'; ?>;">
                        <?php echo ucfirst($print['status']); ?>
                    </span>
                </td>
            </tr>
            <?php if (!empty($print['print_notes'])): ?>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Notes:</strong></td>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;">
                    <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px;">
                        <?php echo nl2br(htmlspecialchars($print['print_notes'])); ?>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        
        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 6px;">
            <h5 style="margin: 0 0 10px 0; color: #2c3e50;">Quick Actions</h5>
            <div style="display: flex; gap: 10px;">
                <button onclick="reprintCard('<?php echo $print['serviceNo']; ?>'); closeModal();" 
                        style="padding: 8px 15px; background: #27ae60; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    <i class="fas fa-print"></i> Reprint Card
                </button>
                <button onclick="viewPersonnel('<?php echo $print['serviceNo']; ?>')" 
                        style="padding: 8px 15px; background: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    <i class="fas fa-user"></i> View Personnel
                </button>
            </div>
        </div>
    </div>
    <?php
} else {
    echo '<p style="text-align: center; color: #666;">Print record not found.</p>';
}

mysqli_stmt_close($stmt);
?>