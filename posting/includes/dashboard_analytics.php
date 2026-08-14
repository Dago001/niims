<?php
// Add cache prevention headers at the TOP of the file
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/json');

function getDashboardAnalytics($pdo) {
    $analytics = [
        'timestamp' => time(), // Add timestamp for real-time tracking
        'summary' => [
            'total_personnel' => 0,
            'total_users' => 0,
            'total_active_postings' => 0
        ],
        'charts' => [
            'formations' => [],
            'ranks' => [],
            'gender' => [],
            'age' => []
        ],
        'recent_activities' => []
    ];

    try {
        // Get summary statistics using prepared statements
        $summaryStmt = $pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM personnel) as total_personnel,
                (SELECT COUNT(*) FROM users) as total_users,
                (SELECT COUNT(*) FROM postings WHERE posting_status = 'Active') as total_active_postings
        ");
        $summaryStmt->execute();
        $analytics['summary'] = $summaryStmt->fetch(PDO::FETCH_ASSOC);

        // Get formation distribution
        $formationStmt = $pdo->prepare("
            SELECT f.formation_type, COUNT(p.id) as count
            FROM formations f
            LEFT JOIN postings p ON p.formation_id = f.id AND p.posting_status = 'Active'
            GROUP BY f.formation_type
            ORDER BY count DESC
        ");
        $formationStmt->execute();
        $analytics['charts']['formations'] = $formationStmt->fetchAll(PDO::FETCH_ASSOC);

        // Get rank distribution
        $rankStmt = $pdo->prepare("
            SELECT rank, COUNT(*) as count 
            FROM personnel 
            GROUP BY rank 
            ORDER BY count DESC
        ");
        $rankStmt->execute();
        $analytics['charts']['ranks'] = $rankStmt->fetchAll(PDO::FETCH_ASSOC);

        // Get gender distribution
        $genderStmt = $pdo->prepare("
            SELECT gender, COUNT(*) as count 
            FROM personnel 
            GROUP BY gender
        ");
        $genderStmt->execute();
        $analytics['charts']['gender'] = $genderStmt->fetchAll(PDO::FETCH_ASSOC);

        // Get age distribution
        $ageStmt = $pdo->prepare("
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
        ");
        $ageStmt->execute();
        $analytics['charts']['age'] = $ageStmt->fetchAll(PDO::FETCH_ASSOC);

        // Get recent activities (last 1 hour for better real-time feel)
        $recentStmt = $pdo->prepare("
            SELECT al.*, u.username 
            FROM activity_logs al
            JOIN users u ON al.user_id = u.id
            WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ORDER BY al.created_at DESC
            LIMIT 10
        ");
        $recentStmt->execute();
        $analytics['recent_activities'] = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Dashboard Analytics Error: " . $e->getMessage());
        throw new Exception("Error fetching dashboard analytics");
    }

    return $analytics;
}

// Database connection (replace with your actual connection)
try {
    $pdo = new PDO("mysql:host=localhost;dbname=your_database", "username", "password");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get analytics data
    $analyticsData = getDashboardAnalytics($pdo);
    
    // Output as JSON
    echo json_encode($analyticsData);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>