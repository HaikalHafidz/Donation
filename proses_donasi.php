<?php
// proses_donasi.php
require_once 'config/database.php';
require_once 'includes/functions.php';

$db = (new Database())->connect();

$id = $_GET['id'] ?? '';

if (empty($id)) {
    header('Location: index.html');
    exit();
}

// Get donation data
$query = "SELECT * FROM donations WHERE donation_id = :id OR id = :id";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $id]);
$donation = $stmt->fetch();

if (!$donation) {
    header('Location: index.html');
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proses Donasi - DonasiBersama</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .process-page {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 20px;
        }
        
        .process-card {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .status-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            font-size: 2rem;
        }
        
        .status-icon.pending {
            background: #fef3c7;
            color: #d97706;
        }
        
        .status-icon.success {
            background: #def7ec;
            color: #0e9f6e;
        }
        
        .process-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .process-header h2 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .process-header p {
            color: #666;
        }
        
        .detail-box {
            background: #f8fafc;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            color: #64748b;
            font-weight: 500;
        }
        
        .detail-value {
            color: #1e293b;
            font-weight: 600;
        }
        
        .payment-steps {
            margin: 30px 0;
        }
        
        .step {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .step-number {
            width: 30px;
            height: 30px;
            background: #e2e8f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #475569;
        }
        
        .step.active .step-number {
            background: #667eea;
            color: white;
        }
        
        .step-content {
            flex: 1;
        }
        
        .step-title {
            font-weight: 600;
            color: #1e293b;
        }
        
        .step-desc {
            font-size: 0.9rem;
            color: #64748b;
        }
        
        .btn-primary {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            display: inline-block;
            padding: 10px 20px;
            background: #e2e8f0;
            color: #475569;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }
        
        .alert-success {
            background: #def7ec;
            color: #03543f;
            border-left: 4px solid #0e9f6e;
        }
    </style>
</head>
<body>
    <div class="process-page">
        <div class="process-card">
            <div class="status-icon <?php echo $donation['payment_status']; ?>">
                <?php if ($donation['payment_status'] === 'pending'): ?>
                    <i class="fas fa-clock"></i>
                <?php elseif ($donation['payment_status'] === 'success'): ?>
                    <i class="fas fa-check-circle"></i>
                <?php else: ?>
                    <i class="fas fa-exclamation-circle"></i>
                <?php endif; ?>
            </div>
            
            <div class="process-header">
                <h2>Proses Donasi</h2>
                <p>ID Donasi: <strong><?php echo $donation['donation_id']; ?></strong></p>
            </div>
            
            <div class="detail-box">
                <div class="detail-item">
                    <span class="detail-label">Nama Donatur</span>
                    <span class="detail-value"><?php echo htmlspecialchars($donation['donor_name']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Email</span>
                    <span class="detail-value"><?php echo htmlspecialchars($donation['donor_email']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Nominal</span>
                    <span class="detail-value">Rp <?php echo number_format($donation['amount'], 0, ',', '.'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Metode</span>
                    <span class="detail-value"><?php echo strtoupper($donation['payment_method']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Status</span>
                    <span class="detail-value status-<?php echo $donation['payment_status']; ?>">
                        <?php echo ucfirst($donation['payment_status']); ?>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Tanggal</span>
                    <span class="detail-value">
                        <?php echo date('d M Y H:i', strtotime($donation['created_at'])); ?>
                    </span>
                </div>
            </div>
            
            <?php if ($donation['payment_status'] === 'pending'): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle"></i>
                    Menunggu pembayaran. Silakan selesaikan pembayaran Anda.
                </div>
                
                <div class="payment-steps">
                    <h3>Cara Pembayaran:</h3>
                    
                    <div class="step active">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <div class="step-title">Transfer Bank</div>
                            <div class="step-desc">Transfer ke rekening BCA 1234567890 a.n. Yayasan DonasiBersama</div>
                        </div>
                    </div>
                    
                    <div class="step">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <div class="step-title">Konfirmasi Pembayaran</div>
                            <div class="step-desc">Kirim bukti transfer ke WhatsApp 081234567890</div>
                        </div>
                    </div>
                    
                    <div class="step">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <div class="step-title">Verifikasi</div>
                            <div class="step-desc">Tim kami akan memverifikasi pembayaran Anda</div>
                        </div>
                    </div>
                </div>
                
                <button class="btn-primary" onclick="confirmPayment()">
                    <i class="fas fa-check-circle"></i>
                    Saya Sudah Bayar
                </button>
                
            <?php elseif ($donation['payment_status'] === 'success'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Pembayaran berhasil! Terima kasih atas donasi Anda.
                </div>
                
                <button class="btn-primary" onclick="window.print()">
                    <i class="fas fa-print"></i>
                    Cetak Bukti Donasi
                </button>
            <?php endif; ?>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="index.html" class="btn-secondary">
                    <i class="fas fa-home"></i>
                    Kembali ke Beranda
                </a>
            </div>
        </div>
    </div>
    
    <script>
        function confirmPayment() {
            if (confirm('Apakah Anda sudah melakukan transfer?')) {
                alert('Terima kasih! Bukti transfer akan diverifikasi oleh tim kami.');
                window.location.href = 'sukses.php?type=donation&id=<?php echo $donation['donation_id']; ?>';
            }
        }

    </script>
</body>
</html>