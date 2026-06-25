<?php
function authenticateAdmin($db) {
    if (!isset($_SESSION['admin_id'])) {
        return false;
    }
    
    $query = "SELECT * FROM admins WHERE id = :id AND is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $_SESSION['admin_id']]);
    
    return $stmt->fetch();
}

function authenticateAdminToken($db) {
    $headers = getallheaders();
    
    if (!isset($headers['Authorization'])) {
        return false;
    }
    
    $token = str_replace('Bearer ', '', $headers['Authorization']);
    
    if (strlen($token) !== 64) {
        return false;
    }
    
    if (!isset($_SESSION['admin_id'])) {
        return false;
    }
    
    $query = "SELECT * FROM admins WHERE id = :id AND is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $_SESSION['admin_id']]);
    
    return $stmt->fetch();
}

function hasPermission($admin, $permission) {
    if ($admin['role'] === 'superadmin') {
        return true;
    }
    
    $permissions = json_decode($admin['permissions'] ?? '[]', true);
    return in_array($permission, $permissions);
}

function requireAdminLogin() {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit();
    }
}

function generateAdminToken($adminId) {
    return bin2hex(random_bytes(32));
}
?>
