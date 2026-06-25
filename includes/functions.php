<?php
class Functions {
    
    // Generate unique ID
    public static function generateId($prefix = 'DON') {
        return $prefix . '-' . date('Ymd') . '-' . 
               strtoupper(substr(uniqid(), -6)) . '-' . 
               rand(1000, 9999);
    }
    
    public static function generateNotifId() {
        return 'NOTIF-' . date('YmdHis') . '-' . rand(1000, 9999);
    }
    
    public static function logActivity($db, $data) {
        try {
            $logId = self::generateId('LOG');
            
            $query = "INSERT INTO activity_logs 
                      (log_id, user_type, user_id, admin_id, action, description, 
                       old_data, new_data, ip_address, user_agent) 
                      VALUES 
                      (:log_id, :user_type, :user_id, :admin_id, :action, :description,
                       :old_data, :new_data, :ip_address, :user_agent)";
            
            $stmt = $db->prepare($query);
            
            $stmt->execute([
                ':log_id' => $logId,
                ':user_type' => $data['user_type'] ?? 'system',
                ':user_id' => $data['user_id'] ?? null,
                ':admin_id' => $data['admin_id'] ?? null,
                ':action' => $data['action'],
                ':description' => $data['description'] ?? '',
                ':old_data' => isset($data['old_data']) ? json_encode($data['old_data']) : null,
                ':new_data' => isset($data['new_data']) ? json_encode($data['new_data']) : null,
                ':ip_address' => self::getClientIP(),
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            return $logId;
        } catch (Exception $e) {
            error_log("Log Activity Error: " . $e->getMessage());
            return false;
        }
    }
    
    public static function createNotification($db, $data) {
        try {
            $notifId = self::generateNotifId();
            
            $query = "INSERT INTO notifications 
                      (notification_id, type, title, message, donation_id, 
                       recipient_id, user_id, admin_id, channel) 
                      VALUES 
                      (:notif_id, :type, :title, :message, :donation_id,
                       :recipient_id, :user_id, :admin_id, :channel)";
            
            $stmt = $db->prepare($query);
            
            $stmt->execute([
                ':notif_id' => $notifId,
                ':type' => $data['type'],
                ':title' => $data['title'],
                ':message' => $data['message'],
                ':donation_id' => $data['donation_id'] ?? null,
                ':recipient_id' => $data['recipient_id'] ?? null,
                ':user_id' => $data['user_id'] ?? null,
                ':admin_id' => $data['admin_id'] ?? null,
                ':channel' => $data['channel'] ?? 'in_app'
            ]);
            
            return $notifId;
        } catch (Exception $e) {
            error_log("Create Notification Error: " . $e->getMessage());
            return false;
        }
    }
    
    // Validate email
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    // Validate phone number
    public static function validatePhone($phone) {
        return preg_match('/^[0-9]{10,15}$/', preg_replace('/[^0-9]/', '', $phone));
    }
    
    // Format currency
    public static function formatCurrency($amount) {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
    
    // Get client IP
    public static function getClientIP() {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if(isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;
    }
    
    // Send email notification (simplified)
    public static function sendEmail($to, $subject, $message) {
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= 'From: DonasiBersama <noreply@donasibersama.com>' . "\r\n";
        
        return mail($to, $subject, $message, $headers);
    }
    
    public static function sendWhatsApp($phone, $message) {
        $curl = curl_init();
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.fonnte.com/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array(
                'target' => $phone,
                'message' => $message,
            ),
            CURLOPT_HTTPHEADER => array(
                'Authorization: YOUR_TOKEN_HERE'
            ),
        ));
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        return $response;
    }
    
    public static function sanitize($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitize'], $input);
        }
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
    
    public static function generateOTP($length = 6) {
        return str_pad(rand(0, pow(10, $length)-1), $length, '0', STR_PAD_LEFT);
    }
    
    public static function userExists($db, $email, $phone = null) {
        $query = "SELECT id FROM users WHERE email = :email";
        $params = [':email' => $email];
        
        if ($phone) {
            $query = "SELECT id FROM users WHERE email = :email OR no_telepon = :phone";
            $params[':phone'] = $phone;
        }
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetch() ? true : false;
    }
    
    public static function getOrCreateUser($db, $data) {

        $stmt = $db->prepare("SELECT id FROM users WHERE email = :email OR no_telepon = :phone");
        $stmt->execute([
            ':email' => $data['email'] ?? '',
            ':phone' => $data['no_telepon'] ?? ''
        ]);
        $user = $stmt->fetch();
        
        if ($user) {
            return $user['id'];
        }
        
        $query = "INSERT INTO users (nama_lengkap, email, no_telepon, tipe_user, alamat) 
                  VALUES (:nama, :email, :phone, :tipe, :alamat)";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':nama' => $data['nama_lengkap'],
            ':email' => $data['email'] ?? '',
            ':phone' => $data['no_telepon'] ?? '',
            ':tipe' => $data['tipe_user'] ?? 'donatur',
            ':alamat' => $data['alamat'] ?? ''
        ]);
        
        return $db->lastInsertId();
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
