<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Add cache prevention headers
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/json');

function getDashboardAnalytics($pdo) {
    $analytics = [
        'summary' => [
            'total_personnel' => 0,
            'total_users' => 0,
            'total_active_postings' => 0
        ],
        'charts' => [
            'formations' => [],
            'currentRank' => [],
            'gender' => [],
            'age' => []
        ],
        'recent_activities' => []
    ];

    try {
        // Get summary statistics
        $analytics['summary'] = $pdo->query("
            SELECT 
                (SELECT COUNT(*) FROM personnel) as total_personnel,
                (SELECT COUNT(*) FROM users) as total_users,
                (SELECT COUNT(*) FROM postings WHERE posting_status = 'Active') as total_active_postings
        ")->fetch(PDO::FETCH_ASSOC);

        // Get formation distribution
        $analytics['charts']['formations'] = $pdo->query("
            SELECT f.formation_type, COUNT(p.id) as count
            FROM formations f
            LEFT JOIN postings p ON p.formation_id = f.id AND p.posting_status = 'Active'
            GROUP BY f.formation_type
            ORDER BY count DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Get rank distribution
        $analytics['charts']['currentRanks'] = $pdo->query("
            SELECT currentRank, COUNT(*) as count 
            FROM tbl_employment 
            GROUP BY currentRank 
            ORDER BY count DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Get gender distribution
        $analytics['charts']['gender'] = $pdo->query("
            SELECT gender, COUNT(*) as count 
            FROM personnel 
            GROUP BY gender
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Get age distribution
        $analytics['charts']['age'] = $pdo->query("
            SELECT 
                CASE 
                    WHEN age < 25 THEN '18-24'
                    WHEN age BETWEEN 25 AND 34 THEN '25-34'
                    WHEN age BETWEEN 35 AND 44 THEN '35-44'
                    WHEN age BETWEEN 45 AND 54 THEN '45-54'
                    ELSE '55+'
                END as age_group,
                COUNT(*) as count
            FROM (
                SELECT TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) as age 
                FROM personnel
            ) age_calc
            GROUP BY age_group
            ORDER BY age_group
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Get recent activities (last 1 hour for real-time feel)
        $analytics['recent_activities'] = $pdo->query("
            SELECT al.*, u.username 
            FROM activity_logs al
            JOIN users u ON al.user_id = u.id
            WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ORDER BY al.created_at DESC
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Dashboard Analytics Error: " . $e->getMessage());
        throw new Exception("Error fetching dashboard analytics");
    }

    return $analytics;
}

try {
    $analyticsData = getDashboardAnalytics($pdo);
    echo json_encode([
        'success' => true,
        'data' => $analyticsData,
        'timestamp' => time()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>