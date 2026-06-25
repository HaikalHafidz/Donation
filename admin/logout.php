<?php
require_once '../includes/functions.php';

if (isset($_SESSION['admin_id'])) {
    require_once '../config/database.php';
    $db = (new Database())->connect();
    
    Functions::logActivity($db, [
        'user_type' => 'admin',
        'admin_id' => $_SESSION['admin_id'],
        'action' => 'logout',
        'description' => "Admin logout"
    ]);
}

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

header('Location: login.php?logged_out=1');
exit();
?>
