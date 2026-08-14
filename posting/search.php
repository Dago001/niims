<?php
require_once 'includes/config.php';

$search_results = [];
$search_performed = false;

if (isset($_GET['service_number'])) {
    $service_number = sanitizeInput($_GET['service_number']);
    $search_performed = true;
    
    try {
        // Updated query - removed r.name as rank since rank is a direct column
        $stmt = $pdo->prepare("
            SELECT p.*
            FROM personnel p
            WHERE p.service_number = ?
        ");
        $stmt->execute([$service_number]);
        $personnel = $stmt->fetch();
        
        if ($personnel) {
            // Get postings data with formation details
            $stmt = $pdo->prepare("
                SELECT p.*, f.name as formation_name, f.location_type, z.name as zone_name,
                       p.posting_status, p.date_of_posting
                FROM postings p
                JOIN formations f ON p.formation_id = f.id
                JOIN zones z ON f.zone_id = z.id
                WHERE p.personnel_id = ?
                ORDER BY p.date_of_posting DESC
            ");
            $stmt->execute([$personnel['id']]);
            $postings = $stmt->fetchAll();
            
            $search_results = [
                'personnel' => $personnel,
                'postings' => $postings
            ];
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
} elseif (!isset($_GET['service_number']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Display all records with their most recent posting
    try {
        $stmt = $pdo->query("
            SELECT p.*, 
                   f.name as formation_name,
                   f.location_type,
                   pos.date_of_posting,
                   pos.posting_status
            FROM personnel p
            LEFT JOIN (
                SELECT personnel_id, formation_id, posting_status, date_of_posting
                FROM postings
                WHERE (personnel_id, date_of_posting) IN (
                    SELECT personnel_id, MAX(date_of_posting)
                    FROM postings
                    GROUP BY personnel_id
                )
            ) pos ON p.id = pos.personnel_id
            LEFT JOIN formations f ON pos.formation_id = f.id
            ORDER BY p.surname, p.firstname
        ");
        $all_personnel = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<?php include 'includes/header.php'; ?>
<style>
    body {
        background: linear-gradient(rgba(0, 128, 0, 0.7), rgba(0, 128, 0, 0.7)), url('assets/images/tech_building.jpg');
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        min-height: 100vh;
    }
    .content-container {
        background: rgba(255, 255, 255, 0.95);
        padding: 20px;
        border-radius: 10px;
        margin-top: 20px;
        margin-bottom: 20px;
        max-height: 85vh;
        overflow-y: auto;
    }
    .content-container::-webkit-scrollbar {
        width: 8px;
    }
    .content-container::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    .content-container::-webkit-scrollbar-thumb {
        background: #800000;s
        border-radius: 4px;
    }
    .content-container::-webkit-scrollbar-thumb:hover {
        background: #600000;
    }
    .table-responsive {
        max-height: 60vh;
        overflow-y: auto;
    }
    .card {
        margin-bottom: 20px;
    }
    .table thead th {
        position: sticky;
        top: 0;
        background: white;
        z-index: 1;
    }
    .card-header {
        background-color: #198754;
    }
    .card-header h4 {
        color: white;
        margin-bottom: 0;
    }
</style>
<div class="container content-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Personnel Records</h2>
        <div class="d-flex">
            <form class="form-inline mr-2" action="search" method="GET">
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="Search by Service Number" name="service_number" 
                           value="<?php echo isset($_GET['service_number']) ? htmlspecialchars($_GET['service_number']) : ''; ?>">
                    <div class="input-group-append">
                        <button class="btn btn-outline-secondary" type="submit">Search</button>
                    </div>
                </div>
            </form>
            <?php if ($search_performed && !empty($search_results)): ?>
            <form action="export" method="GET">
                <input type="hidden" name="service_number" value="<?php echo htmlspecialchars($_GET['service_number']); ?>">
                <button type="submit" class="btn btn-success">Export to Excel</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($search_performed && empty($search_results)): ?>
        <div class="alert alert-warning">No records found for the provided service number.</div>
    <?php endif; ?>
    
    <?php if (!empty($search_results)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h4>Personal Information</h4>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <th>Service Number</th>
                            <td><?php echo htmlspecialchars($search_results['personnel']['service_number']); ?></td>
                        </tr>
                        <tr>
                            <th>Surname</th>
                            <td><?php echo htmlspecialchars($search_results['personnel']['surname']); ?></td>
                        </tr>
                        <tr>
                            <th>Firstname</th>
                            <td><?php echo htmlspecialchars($search_results['personnel']['firstname']); ?></td>
                        </tr>
                        <tr>
                            <th>Middlename</th>
                            <td><?php echo htmlspecialchars($search_results['personnel']['middlename']); ?></td>
                        </tr>
                        <tr>
                            <th>Rank</th>
                            <td><?php echo htmlspecialchars($search_results['personnel']['rank']); ?></td>
                        </tr>
                        <tr>
                            <th>Date of Birth</th>
                            <td><?php echo htmlspecialchars($search_results['personnel']['date_of_birth']); ?></td>
                        </tr>
                        <tr>
                            <th>Gender</th>
                            <td><?php echo htmlspecialchars($search_results['personnel']['gender']); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h4>Postings</h4>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Formation</th>
                            <th>Zone</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Date of Posting</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($search_results['postings'] as $posting): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($posting['formation_name']); ?></td>
                                <td><?php echo htmlspecialchars($posting['zone_name']); ?></td>
                                <td><?php echo htmlspecialchars($posting['location_type']); ?></td>
                                <td><?php echo htmlspecialchars($posting['posting_status']); ?></td>
                                <td><?php echo htmlspecialchars($posting['date_of_posting']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php elseif (!$search_performed && isset($all_personnel)): ?>
    <h4 class="mb-3">All Personnel Records</h4>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Service Number</th>
                    <th>Surname</th>
                    <th>Firstname</th>
                    <th>Middlename</th>
                    <th>Rank</th>
                    <th>Current Formation</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Date of Posting</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_personnel as $person): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($person['service_number']); ?></td>
                        <td><?php echo htmlspecialchars($person['surname']); ?></td>
                        <td><?php echo htmlspecialchars($person['firstname']); ?></td>
                        <td><?php echo htmlspecialchars($person['middlename']); ?></td>
                        <td><?php echo htmlspecialchars($person['rank']); ?></td>
                        <td><?php echo htmlspecialchars($person['formation_name'] ?? 'No posting'); ?></td>
                        <td><?php echo htmlspecialchars($person['location_type'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($person['posting_status'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($person['date_of_posting'] ?? 'N/A'); ?></td>
                        <td>
                            <a href="search?service_number=<?php echo urlencode($person['service_number']); ?>" 
                               class="btn btn-sm" style="background-color: #198754; color: white;">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>
<!--<?php include 'includes/footer.php'; ?>-->