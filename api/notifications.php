<?php
// api/admin/get_notifications.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
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

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        try {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $unreadOnly = isset($_GET['unread']) && $_GET['unread'] === 'true';
            
            $query = "SELECT n.*, 
                      d.donor_name as donation_donor, d.donation_id,
                      r.nama_lengkap as recipient_name, r.recipient_id
                      FROM notifications n
                      LEFT JOIN donations d ON n.donation_id = d.id
                      LEFT JOIN recipients r ON n.recipient_id = r.id
                      WHERE 1=1";
            
            if ($unreadOnly) {
                $query .= " AND n.is_read = FALSE";
            }
            
            $query .= " ORDER BY n.created_at DESC LIMIT :limit";
            
            $stmt = $db->prepare($query);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $notifications = $stmt->fetchAll();
            
            // Get unread count
            $countStmt = $db->query("SELECT COUNT(*) as unread_count FROM notifications WHERE is_read = FALSE");
            $unreadCount = $countStmt->fetch()['unread_count'];
            
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'notifications' => $notifications,
                    'unread_count' => $unreadCount
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("Get Notifications Error: " . $e->getMessage());
            
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Gagal mengambil notifikasi'
            ]);
        }
        break;
        
    case 'PUT':
        // Mark notification as read
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'ID notifikasi diperlukan'
            ]);
            exit();
        }
        
        try {
            $id = $_GET['id'];
            
            if ($id === 'all') {
                // Mark all as read
                $query = "UPDATE notifications SET is_read = TRUE, read_at = NOW(), read_by = :admin_id 
                          WHERE is_read = FALSE";
                $stmt = $db->prepare($query);
                $stmt->execute([':admin_id' => $admin['id']]);
                
            } else {
                // Mark single as read
                $query = "UPDATE notifications SET is_read = TRUE, read_at = NOW(), read_by = :admin_id 
                          WHERE id = :id AND is_read = FALSE";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':admin_id' => $admin['id'],
                    ':id' => $id
                ]);
            }
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Notifikasi ditandai telah dibaca'
            ]);
            
        } catch (Exception $e) {
            error_log("Mark Notification Error: " . $e->getMessage());
            
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Gagal mengupdate notifikasi'
            ]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode([
            'status' => 'error',
            'message' => 'Method not allowed'
        ]);
        break;
}
?>