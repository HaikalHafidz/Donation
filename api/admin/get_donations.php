<?php
// api/admin/get_donations.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

$db = (new Database())->connect();

// Verify admin token
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$admin = authenticateAdmin($db);
if (!$admin) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized'
    ]);
    exit();
}

try {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;
    
    $query = "SELECT d.*, 
              (SELECT COUNT(*) FROM notifications WHERE donation_id = d.id AND is_read = FALSE) as unread_notif
              FROM donations d
              WHERE 1=1";
    $countQuery = "SELECT COUNT(*) as total FROM donations WHERE 1=1";
    $params = [];
    
    // Apply filters
    if (isset($_GET['status'])) {
        $query .= " AND d.payment_status = :status";
        $countQuery .= " AND payment_status = :status";
        $params[':status'] = $_GET['status'];
    }
    
    if (isset($_GET['type'])) {
        $query .= " AND d.donation_type = :type";
        $countQuery .= " AND donation_type = :type";
        $params[':type'] = $_GET['type'];
    }
    
    if (isset($_GET['search'])) {
        $query .= " AND (d.donor_name LIKE :search OR d.donor_email LIKE :search OR d.donation_id LIKE :search)";
        $countQuery .= " AND (donor_name LIKE :search OR donor_email LIKE :search OR donation_id LIKE :search)";
        $params[':search'] = '%' . $_GET['search'] . '%';
    }
    
    if (isset($_GET['start_date'])) {
        $query .= " AND DATE(d.created_at) >= :start_date";
        $countQuery .= " AND DATE(created_at) >= :start_date";
        $params[':start_date'] = $_GET['start_date'];
    }
    
    if (isset($_GET['end_date'])) {
        $query .= " AND DATE(d.created_at) <= :end_date";
        $countQuery .= " AND DATE(created_at) <= :end_date";
        $params[':end_date'] = $_GET['end_date'];
    }
    
    // Get total count
    $stmt = $db->prepare($countQuery);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    // Get paginated results
    $query .= " ORDER BY d.created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $donations = $stmt->fetchAll();
    
    // Get statistics
    $statsQuery = "SELECT 
                   COUNT(*) as total_donations,
                   SUM(CASE WHEN payment_status = 'success' THEN 1 ELSE 0 END) as success_count,
                   SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                   SUM(CASE WHEN payment_status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                   SUM(CASE WHEN payment_status = 'success' THEN amount ELSE 0 END) as total_amount,
                   COUNT(DISTINCT donor_email) as unique_donors
                   FROM donations";
    
    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->execute();
    $statistics = $statsStmt->fetch();
    
    // Get daily stats for chart
    $dailyQuery = "SELECT 
                   DATE(created_at) as date,
                   COUNT(*) as count,
                   SUM(amount) as total
                   FROM donations
                   WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                   GROUP BY DATE(created_at)
                   ORDER BY date DESC";
    
    $dailyStmt = $db->prepare($dailyQuery);
    $dailyStmt->execute();
    $dailyStats = $dailyStmt->fetchAll();
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'donations' => $donations,
            'statistics' => $statistics,
            'daily_stats' => $dailyStats,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Get Donations Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Gagal mengambil data donasi'
    ]);
}
?>