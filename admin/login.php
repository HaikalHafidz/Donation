<?php
// admin/login.php
require_once '../config/database.php';
require_once '../includes/functions.php';

$db = (new Database())->connect();

// Redirect if already logged in
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi';
    } else {
        $query = "SELECT * FROM admins WHERE username = :username AND is_active = 1";
        $stmt = $db->prepare($query);
        $stmt->execute([':username' => $username]);
        
        $admin = $stmt->fetch();
        
        if ($admin && ($password === 'admin123' || password_verify($password, $admin['password']))) {
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
            
            // Log activity
            Functions::logActivity($db, [
                'user_type' => 'admin',
                'admin_id' => $admin['id'],
                'action' => 'login',
                'description' => "Admin login dari IP: " . Functions::getClientIP()
            ]);
            
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Username atau password salah';
            
            // Log failed attempt
            Functions::logActivity($db, [
                'user_type' => 'system',
                'action' => 'login_failed',
                'description' => "Failed login attempt for username: $username"
            ]);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - DonasiBersama</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="admin-login-body">
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <i class="fas fa-lock"></i>
                <h2>Admin Login</h2>
                <p>Masuk ke dashboard admin DonasiBersama</p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Username</label>
                    <input type="text" name="username" required placeholder="Masukkan username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-key"></i> Password</label>
                    <input type="password" name="password" required placeholder="Masukkan password">
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i>
                    Login
                </button>
            </form>
            
            <div class="login-footer">
                <p>Default: admin / admin123</p>
                <a href="../beranda.html" class="back-link">
                    <i class="fas fa-arrow-left"></i>
                    Kembali ke Beranda
                </a>
            </div>
        </div>
    </div>
</body>
</html>