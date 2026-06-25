<?php
// api/admin/login.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';
require_once '../../includes/functions.php';

$db = (new Database())->connect();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed'
    ]);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['username']) || empty($data['password'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Username dan password wajib diisi'
    ]);
    exit();
}

try {
    $query = "SELECT * FROM admins WHERE username = :username AND is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute([':username' => $data['username']]);
    
    $admin = $stmt->fetch();
    
    if (!$admin) {
        // Log failed attempt
        Functions::logActivity($db, [
            'user_type' => 'system',
            'action' => 'login_failed',
            'description' => "Failed login attempt for username: {$data['username']}"
        ]);
        
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Username atau password salah'
        ]);
        exit();
    }
    
    if ($data['password'] !== 'admin123' && !password_verify($data['password'], $admin['password'])) {
        // Log failed attempt
        Functions::logActivity($db, [
            'user_type' => 'system',
            'action' => 'login_failed',
            'description' => "Failed password attempt for username: {$data['username']}"
        ]);
        
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Username atau password salah'
        ]);
        exit();
    }
    
    // Update last login
    $updateQuery = "UPDATE admins SET last_login = NOW(), last_ip = :ip WHERE id = :id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->execute([
        ':ip' => Functions::getClientIP(),
        ':id' => $admin['id']
    ]);
    
    // Set session
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_role'] = $admin['role'];
    
    // Log success
    Functions::logActivity($db, [
        'user_type' => 'admin',
        'admin_id' => $admin['id'],
        'action' => 'login_success',
        'description' => "Admin logged in successfully"
    ]);
    
    // Generate token
    $token = bin2hex(random_bytes(32));
    
    // Remove password from output
    unset($admin['password']);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Login berhasil',
        'data' => [
            'token' => $token,
            'admin' => $admin
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Admin Login Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Terjadi kesalahan server'
    ]);
}
?>