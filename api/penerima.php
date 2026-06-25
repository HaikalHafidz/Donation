<?php
// api/penerima.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once '../includes/functions.php';

$db = (new Database())->connect();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'POST':
        // Create new recipient application
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
        $required = ['nama_lengkap', 'no_telepon', 'alamat', 'assistance_type', 'reason'];
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
        
        // Validate phone
        if (!Functions::validatePhone($data['no_telepon'])) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Format nomor telepon tidak valid'
            ]);
            exit();
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Get or create user
            $userId = null;
            $userData = [
                'nama_lengkap' => $data['nama_lengkap'],
                'email' => $data['email'] ?? '',
                'no_telepon' => $data['no_telepon'],
                'tipe_user' => 'penerima',
                'alamat' => $data['alamat']
            ];
            $userId = Functions::getOrCreateUser($db, $userData);
            
            // Generate recipient ID
            $recipientId = Functions::generateId('RCP');
            
            // Insert recipient
            $query = "INSERT INTO recipients 
                      (recipient_id, user_id, nama_lengkap, email, no_telepon,
                       nik, no_kk, tempat_lahir, tanggal_lahir, jenis_kelamin,
                       alamat, rt_rw, kelurahan, kecamatan, kota, provinsi, kode_pos,
                       pekerjaan, penghasilan, tanggungan,
                       assistance_type, amount_requested, food_items_requested, 
                       goods_items_requested, reason, ip_address) 
                      VALUES 
                      (:recipient_id, :user_id, :nama, :email, :phone,
                       :nik, :no_kk, :tempat, :tgl_lahir, :jk,
                       :alamat, :rt_rw, :kelurahan, :kecamatan, :kota, :provinsi, :pos,
                       :pekerjaan, :penghasilan, :tanggungan,
                       :assistance_type, :amount, :food_items, :goods_items,
                       :reason, :ip_address)";
            
            $stmt = $db->prepare($query);
            
            $foodItems = null;
            $goodsItems = null;
            $amount = 0;
            
            if ($data['assistance_type'] === 'makanan') {
                $foodItems = $data['food_items_requested'] ?? '';
            } else if ($data['assistance_type'] === 'barang') {
                $goodsItems = $data['goods_items_requested'] ?? '';
            } else if ($data['assistance_type'] === 'uang') {
                $amount = $data['amount_requested'] ?? 0;
            }
            
            $stmt->execute([
                ':recipient_id' => $recipientId,
                ':user_id' => $userId,
                ':nama' => Functions::sanitize($data['nama_lengkap']),
                ':email' => Functions::sanitize($data['email'] ?? ''),
                ':phone' => Functions::sanitize($data['no_telepon']),
                ':nik' => Functions::sanitize($data['nik'] ?? ''),
                ':no_kk' => Functions::sanitize($data['no_kk'] ?? ''),
                ':tempat' => Functions::sanitize($data['tempat_lahir'] ?? ''),
                ':tgl_lahir' => $data['tanggal_lahir'] ?? null,
                ':jk' => $data['jenis_kelamin'] ?? null,
                ':alamat' => Functions::sanitize($data['alamat']),
                ':rt_rw' => Functions::sanitize($data['rt_rw'] ?? ''),
                ':kelurahan' => Functions::sanitize($data['kelurahan'] ?? ''),
                ':kecamatan' => Functions::sanitize($data['kecamatan'] ?? ''),
                ':kota' => Functions::sanitize($data['kota'] ?? ''),
                ':provinsi' => Functions::sanitize($data['provinsi'] ?? ''),
                ':pos' => Functions::sanitize($data['kode_pos'] ?? ''),
                ':pekerjaan' => Functions::sanitize($data['pekerjaan'] ?? ''),
                ':penghasilan' => Functions::sanitize($data['penghasilan'] ?? ''),
                ':tanggungan' => $data['tanggungan'] ?? 0,
                ':assistance_type' => $data['assistance_type'],
                ':amount' => $amount,
                ':food_items' => $foodItems,
                ':goods_items' => $goodsItems,
                ':reason' => Functions::sanitize($data['reason']),
                ':ip_address' => Functions::getClientIP()
            ]);
            
            $insertId = $db->lastInsertId();
            
            // Create notification for admin
            Functions::createNotification($db, [
                'type' => 'recipient',
                'title' => 'Penerima Bantuan Baru',
                'message' => "Permintaan bantuan baru dari {$data['nama_lengkap']}",
                'recipient_id' => $insertId,
                'channel' => 'in_app'
            ]);
            
            // Log activity
            Functions::logActivity($db, [
                'user_type' => 'user',
                'user_id' => $userId,
                'action' => 'create_recipient',
                'description' => "Penerima bantuan baru dengan ID: $recipientId",
                'new_data' => $data
            ]);
            
            $db->commit();
            
            http_response_code(201);
            echo json_encode([
                'status' => 'success',
                'message' => 'Permintaan bantuan berhasil dikirim',
                'data' => [
                    'recipient_id' => $recipientId,
                    'id' => $insertId
                ]
            ]);
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Penerima Error: " . $e->getMessage());
            
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Gagal menyimpan data: ' . $e->getMessage()
            ]);
        }
        break;
        
    case 'GET':
        // Get recipient by ID
        if (isset($_GET['id'])) {
            $id = $_GET['id'];
            
            $query = "SELECT r.*, u.nama_lengkap as user_name 
                      FROM recipients r
                      LEFT JOIN users u ON r.user_id = u.id
                      WHERE r.recipient_id = :id OR r.id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute([':id' => $id]);
            
            $recipient = $stmt->fetch();
            
            if ($recipient) {
                echo json_encode([
                    'status' => 'success',
                    'data' => $recipient
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Data tidak ditemukan'
                ]);
            }
        }
        // Get all recipients with pagination
        else {
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $offset = ($page - 1) * $limit;
            
            $query = "SELECT r.*, u.nama_lengkap as user_name 
                      FROM recipients r
                      LEFT JOIN users u ON r.user_id = u.id
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
            
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'recipients' => $recipients,
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
        // Update recipient status
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'ID diperlukan'
            ]);
            exit();
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $_GET['id'];
        
        try {
            $db->beginTransaction();
            
            // Get current recipient
            $stmt = $db->prepare("SELECT * FROM recipients WHERE recipient_id = :id OR id = :id");
            $stmt->execute([':id' => $id]);
            $oldData = $stmt->fetch();
            
            if (!$oldData) {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Data tidak ditemukan'
                ]);
                exit();
            }
            
            // Build update query
            $updateFields = [];
            $params = [':id' => $id];
            
            $allowedFields = ['status', 'verification_note', 'verified_by'];
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateFields[] = "$field = :$field";
                    $params[":$field"] = $data[$field];
                }
            }
            
            if (isset($data['status']) && $data['status'] === 'diverifikasi') {
                $updateFields[] = "verified_at = NOW()";
            }
            
            if (empty($updateFields)) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Tidak ada field yang diupdate'
                ]);
                exit();
            }
            
            $query = "UPDATE recipients SET " . implode(', ', $updateFields) . " WHERE recipient_id = :id OR id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            
            // Get updated data
            $stmt = $db->prepare("SELECT * FROM recipients WHERE recipient_id = :id OR id = :id");
            $stmt->execute([':id' => $id]);
            $newData = $stmt->fetch();
            
            // Log activity
            Functions::logActivity($db, [
                'user_type' => 'admin',
                'action' => 'update_recipient',
                'description' => "Update penerima ID: $id",
                'old_data' => $oldData,
                'new_data' => $newData
            ]);
            
            // Create notification
            if (isset($data['status'])) {
                Functions::createNotification($db, [
                    'type' => 'recipient',
                    'title' => 'Status Permintaan Berubah',
                    'message' => "Status permintaan menjadi: {$data['status']}",
                    'recipient_id' => $oldData['id']
                ]);
            }
            
            $db->commit();
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Data berhasil diupdate',
                'data' => $newData
            ]);
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Update Recipient Error: " . $e->getMessage());
            
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Gagal mengupdate data: ' . $e->getMessage()
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