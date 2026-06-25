<?php
// api/admin/get_recipients.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

$db = (new Database())->connect();

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
    
    $query = "SELECT r.*, 
              (SELECT COUNT(*) FROM notifications WHERE recipient_id = r.id AND is_read = FALSE) as unread_notif
              FROM recipients r
              WHERE 1=1";
    $countQuery = "SELECT COUNT(*) as total FROM recipients WHERE 1=1";
    $params = [];
    
    // Apply filters
    if (isset($_GET['status'])) {
        $query .= " AND r.status = :status";
        $countQuery .= " AND status = :status";
        $params[':status'] = $_GET['status'];
    }
    
    if (isset($_GET['type'])) {
        $query .= " AND r.assistance_type = :type";
        $countQuery .= " AND assistance_type = :type";
        $params[':type'] = $_GET['type'];
    }
    
    if (isset($_GET['search'])) {
        $query .= " AND (r.nama_lengkap LIKE :search OR r.nik LIKE :search OR r.recipient_id LIKE :search)";
        $countQuery .= " AND (nama_lengkap LIKE :search OR nik LIKE :search OR recipient_id LIKE :search)";
        $params[':search'] = '%' . $_GET['search'] . '%';
    }
    
    // Get total count
    $stmt = $db->prepare($countQuery);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    // Get paginated results
    $query .= " ORDER BY r.created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $recipients = $stmt->fetchAll();
    
    // Get statistics
    $statsQuery = "SELECT 
                   COUNT(*) as total_applications,
                   SUM(CASE WHEN status = 'menunggu' THEN 1 ELSE 0 END) as pending_count,
                   SUM(CASE WHEN status = 'diverifikasi' THEN 1 ELSE 0 END) as verified_count,
                   SUM(CASE WHEN status = 'diterima' THEN 1 ELSE 0 END) as accepted_count,
                   SUM(CASE WHEN status = 'ditolak' THEN 1 ELSE 0 END) as rejected_count
                   FROM recipients";
    
    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->execute();
    $statistics = $statsStmt->fetch();
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'recipients' => $recipients,
            'statistics' => $statistics,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Get Recipients Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Gagal mengambil data penerima'
    ]);
}
?>