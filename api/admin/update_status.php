<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
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

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed'
    ]);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['type']) || !isset($data['id']) || !isset($data['status'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Data tidak lengkap'
    ]);
    exit();
}

try {
    $db->beginTransaction();
    
    $type = $data['type'];
    $id = $data['id'];
    $status = $data['status'];
    $notes = $data['notes'] ?? '';
    
    if ($type === 'donation') {
        $query = "UPDATE donations SET payment_status = :status, notes = :notes 
                  WHERE id = :id OR donation_id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':status' => $status,
            ':notes' => $notes,
            ':id' => $id
        ]);
        
        $stmt = $db->prepare("SELECT * FROM donations WHERE id = :id OR donation_id = :id");
        $stmt->execute([':id' => $id]);
        $donation = $stmt->fetch();
        
        if ($donation) {
            Functions::createNotification($db, [
                'type' => 'donation',
                'title' => 'Status Donasi Diperbarui',
                'message' => "Status donasi {$donation['donation_id']} menjadi: $status",
                'donation_id' => $donation['id'],
                'admin_id' => $admin['id']
            ]);
        }
        
    } else if ($type === 'recipient') {
        $query = "UPDATE recipients SET status = :status, verification_note = :notes, 
                  verified_by = :verified_by, verified_at = NOW()
                  WHERE id = :id OR recipient_id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':status' => $status,
            ':notes' => $notes,
            ':verified_by' => $admin['id'],
            ':id' => $id
        ]);
        
        $stmt = $db->prepare("SELECT * FROM recipients WHERE id = :id OR recipient_id = :id");
        $stmt->execute([':id' => $id]);
        $recipient = $stmt->fetch();
        
        if ($recipient) {
            Functions::createNotification($db, [
                'type' => 'recipient',
                'title' => 'Status Permintaan Diperbarui',
                'message' => "Status permintaan {$recipient['recipient_id']} menjadi: $status",
                'recipient_id' => $recipient['id'],
                'admin_id' => $admin['id']
            ]);
        }
    }
    
    Functions::logActivity($db, [
        'user_type' => 'admin',
        'admin_id' => $admin['id'],
        'action' => 'update_status',
        'description' => "Update status $type ID: $id menjadi $status"
    ]);
    
    $db->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Status berhasil diupdate'
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    error_log("Update Status Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Gagal mengupdate status: ' . $e->getMessage()
    ]);
}
?>
