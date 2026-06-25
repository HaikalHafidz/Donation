<?php
// api/donasi.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once '../includes/functions.php';

$db = (new Database())->connect();

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'POST':
        // Create new donation
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Data tidak valid'
            ]);
            exit();
        }
        
        // Validate required fields
        $required = ['donor_name', 'donor_email', 'donation_type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => "Field $field wajib diisi"
                ]);
                exit();
            }
        }
        
        // Validate email
        if (!Functions::validateEmail($data['donor_email'])) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Format email tidak valid'
            ]);
            exit();
        }
        
        // Validate amount for uang donation
        if ($data['donation_type'] === 'uang' && 
            (empty($data['amount']) || $data['amount'] < 1000)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Jumlah donasi minimal Rp 1.000'
            ]);
            exit();
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Get or create user
            $userId = null;
            if (!empty($data['donor_email'])) {
                $userData = [
                    'nama_lengkap' => $data['donor_name'],
                    'email' => $data['donor_email'],
                    'no_telepon' => $data['donor_phone'] ?? '',
                    'tipe_user' => 'donatur',
                    'alamat' => $data['location'] ?? ''
                ];
                $userId = Functions::getOrCreateUser($db, $userData);
            }
            
            // Generate donation ID
            $donationId = Functions::generateId('DON');
            
            // Insert donation
            $query = "INSERT INTO donations 
                      (donation_id, user_id, donor_name, donor_email, donor_phone, 
                       donation_type, amount, food_items, goods_items, description, location,
                       payment_method, ip_address, user_agent) 
                      VALUES 
                      (:donation_id, :user_id, :donor_name, :donor_email, :donor_phone,
                       :donation_type, :amount, :food_items, :goods_items, :description, :location,
                       :payment_method, :ip_address, :user_agent)";
            
            $stmt = $db->prepare($query);
            
            $foodItems = null;
            $goodsItems = null;
            
            if ($data['donation_type'] === 'makanan') {
                $foodItems = $data['food_items'] ?? '';
            } else if ($data['donation_type'] === 'barang') {
                $goodsItems = $data['goods_items'] ?? '';
            }
            
            $stmt->execute([
                ':donation_id' => $donationId,
                ':user_id' => $userId,
                ':donor_name' => Functions::sanitize($data['donor_name']),
                ':donor_email' => Functions::sanitize($data['donor_email']),
                ':donor_phone' => Functions::sanitize($data['donor_phone'] ?? ''),
                ':donation_type' => $data['donation_type'],
                ':amount' => $data['amount'] ?? 0,
                ':food_items' => $foodItems,
                ':goods_items' => $goodsItems,
                ':description' => Functions::sanitize($data['description'] ?? ''),
                ':location' => Functions::sanitize($data['location'] ?? ''),
                ':payment_method' => Functions::sanitize($data['payment_method'] ?? ''),
                ':ip_address' => Functions::getClientIP(),
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            $insertId = $db->lastInsertId();
            
            // Create notification for admin
            Functions::createNotification($db, [
                'type' => 'donation',
                'title' => 'Donasi Baru',
                'message' => "Donasi baru dari {$data['donor_name']} - {$data['donation_type']}",
                'donation_id' => $insertId,
                'channel' => 'in_app'
            ]);
            
            // Log activity
            Functions::logActivity($db, [
                'user_type' => 'user',
                'user_id' => $userId,
                'action' => 'create_donation',
                'description' => "Donasi baru dibuat dengan ID: $donationId",
                'new_data' => $data
            ]);
            
            $db->commit();
            
            http_response_code(201);
            echo json_encode([
                'status' => 'success',
                'message' => 'Donasi berhasil dibuat',
                'data' => [
                    'donation_id' => $donationId,
                    'id' => $insertId
                ]
            ]);
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Donasi Error: " . $e->getMessage());
            
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Gagal menyimpan donasi: ' . $e->getMessage()
            ]);
        }
        break;
        
    case 'GET':
        // Get donation by ID
        if (isset($_GET['id'])) {
            $id = $_GET['id'];
            
            $query = "SELECT d.*, u.nama_lengkap as user_name 
                      FROM donations d
                      LEFT JOIN users u ON d.user_id = u.id
                      WHERE d.donation_id = :id OR d.id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute([':id' => $id]);
            
            $donation = $stmt->fetch();
            
            if ($donation) {
                echo json_encode([
                    'status' => 'success',
                    'data' => $donation
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Donasi tidak ditemukan'
                ]);
            }
        } 
        // Get donations by email
        else if (isset($_GET['email'])) {
            $email = $_GET['email'];
            
            $query = "SELECT * FROM donations WHERE donor_email = :email ORDER BY created_at DESC LIMIT 10";
            $stmt = $db->prepare($query);
            $stmt->execute([':email' => $email]);
            
            $donations = $stmt->fetchAll();
            
            echo json_encode([
                'status' => 'success',
                'data' => $donations
            ]);
        }
        // Get all donations with pagination
        else {
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $offset = ($page - 1) * $limit;
            
            $query = "SELECT d.*, u.nama_lengkap as user_name 
                      FROM donations d
                      LEFT JOIN users u ON d.user_id = u.id
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
            
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'donations' => $donations,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'total_pages' => ceil($total / $limit)
                    ]
                ]
            ]);
        }
        break;
        
    case 'PUT':
        // Update donation status
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'ID donasi diperlukan'
            ]);
            exit();
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $_GET['id'];
        
        try {
            $db->beginTransaction();
            
            // Get current donation
            $stmt = $db->prepare("SELECT * FROM donations WHERE donation_id = :id OR id = :id");
            $stmt->execute([':id' => $id]);
            $oldData = $stmt->fetch();
            
            if (!$oldData) {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Donasi tidak ditemukan'
                ]);
                exit();
            }
            
            // Build update query
            $updateFields = [];
            $params = [':id' => $id];
            
            $allowedFields = ['payment_status', 'distribution_status', 'notes'];
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateFields[] = "$field = :$field";
                    $params[":$field"] = $data[$field];
                }
            }
            
            if (empty($updateFields)) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Tidak ada field yang diupdate'
                ]);
                exit();
            }
            
            $query = "UPDATE donations SET " . implode(', ', $updateFields) . " WHERE donation_id = :id OR id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            
            // Get updated data
            $stmt = $db->prepare("SELECT * FROM donations WHERE donation_id = :id OR id = :id");
            $stmt->execute([':id' => $id]);
            $newData = $stmt->fetch();
            
            // Log activity
            Functions::logActivity($db, [
                'user_type' => 'admin',
                'action' => 'update_donation',
                'description' => "Update donasi ID: $id",
                'old_data' => $oldData,
                'new_data' => $newData
            ]);
            
            // Create notification if status changed
            if (isset($data['payment_status']) && $data['payment_status'] !== $oldData['payment_status']) {
                Functions::createNotification($db, [
                    'type' => 'donation',
                    'title' => 'Status Donasi Berubah',
                    'message' => "Status donasi menjadi: {$data['payment_status']}",
                    'donation_id' => $oldData['id']
                ]);
            }
            
            $db->commit();
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Donasi berhasil diupdate',
                'data' => $newData
            ]);
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Update Donasi Error: " . $e->getMessage());
            
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Gagal mengupdate donasi: ' . $e->getMessage()
            ]);
        }
        break;
        
    case 'DELETE':
        // Delete donation (soft delete or hard delete)
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'ID donasi diperlukan'
            ]);
            exit();
        }
        
        $id = $_GET['id'];
        
        try {
            // Option 1: Soft delete - add deleted_at column
            // Option 2: Hard delete
            $query = "DELETE FROM donations WHERE donation_id = :id OR id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute([':id' => $id]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Donasi berhasil dihapus'
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Donasi tidak ditemukan'
                ]);
            }
            
        } catch (Exception $e) {
            error_log("Delete Donasi Error: " . $e->getMessage());
            
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Gagal menghapus donasi: ' . $e->getMessage()
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