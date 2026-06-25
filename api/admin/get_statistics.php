<?php
// api/admin/get_statistics.php
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
    // Donation statistics
    $donationStats = $db->query("
        SELECT 
            COUNT(*) as total_donations,
            SUM(CASE WHEN payment_status = 'success' THEN 1 ELSE 0 END) as success_donations,
            SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_donations,
            SUM(CASE WHEN payment_status = 'failed' THEN 1 ELSE 0 END) as failed_donations,
            SUM(CASE WHEN payment_status = 'success' THEN amount ELSE 0 END) as total_amount,
            COUNT(DISTINCT donor_email) as unique_donors
        FROM donations
    ")->fetch();
    
    // Recipient statistics
    $recipientStats = $db->query("
        SELECT 
            COUNT(*) as total_applications,
            SUM(CASE WHEN status = 'menunggu' THEN 1 ELSE 0 END) as pending_applications,
            SUM(CASE WHEN status = 'diverifikasi' THEN 1 ELSE 0 END) as verified_applications,
            SUM(CASE WHEN status = 'diterima' THEN 1 ELSE 0 END) as accepted_applications,
            SUM(CASE WHEN status = 'ditolak' THEN 1 ELSE 0 END) as rejected_applications
        FROM recipients
    ")->fetch();
    
    // Monthly donation chart data
    $monthlyDonations = $db->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count,
            SUM(amount) as total
        FROM donations
        WHERE payment_status = 'success'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ")->fetchAll();
    
    // Donation by type
    $donationByType = $db->query("
        SELECT 
            donation_type,
            COUNT(*) as count,
            SUM(CASE WHEN payment_status = 'success' THEN amount ELSE 0 END) as total
        FROM donations
        WHERE payment_status = 'success'
        GROUP BY donation_type
    ")->fetchAll();
    
    // Recent activities
    $recentActivities = $db->query("
        (SELECT 
            'donation' as type,
            donation_id as ref_id,
            donor_name as name,
            amount,
            created_at
         FROM donations
         ORDER BY created_at DESC
         LIMIT 5)
        UNION ALL
        (SELECT 
            'recipient' as type,
            recipient_id as ref_id,
            nama_lengkap as name,
            amount_requested as amount,
            created_at
         FROM recipients
         ORDER BY created_at DESC
         LIMIT 5)
        ORDER BY created_at DESC
        LIMIT 10
    ")->fetchAll();
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'donations' => $donationStats,
            'recipients' => $recipientStats,
            'monthly_donations' => $monthlyDonations,
            'donation_by_type' => $donationByType,
            'recent_activities' => $recentActivities
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Get Statistics Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Gagal mengambil statistik'
    ]);
}
?>