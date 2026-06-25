<?php
// admin/dashboard.php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

$db = (new Database())->connect();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Get admin info
$stmt = $db->prepare("SELECT * FROM admins WHERE id = :id");
$stmt->execute([':id' => $_SESSION['admin_id']]);
$admin = $stmt->fetch();

if (!$admin) {
    session_destroy();
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - DonasiBersama</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="logo">
                <i class="fas fa-heart"></i>
                <span>DonasiBersama - Admin</span>
            </div>
            <ul class="nav-links">
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <li><a href="donations.php">Donasi</a></li>
                <li><a href="recipients.php">Penerima</a></li>
                <li><a href="users.php">Users</a></li>
                <li><a href="reports.php">Laporan</a></li>
                <li><a href="settings.php">Pengaturan</a></li>
                <li><a href="logout.php" class="admin-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>
    
    <div class="dashboard">
        <div class="dashboard-header">
            <div class="admin-info">
                <div class="admin-avatar">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div>
                    <h2>Selamat datang, <?php echo htmlspecialchars($admin['nama_lengkap']); ?>!</h2>
                    <p>Role: <?php echo ucfirst($admin['role']); ?> | Last Login: <?php echo $admin['last_login'] ? date('d M Y H:i', strtotime($admin['last_login'])) : 'Belum pernah'; ?></p>
                </div>
            </div>
            <div class="header-actions">
                <div class="notifications" id="notificationsBell">
                    <i class="fas fa-bell"></i>
                    <span class="badge" id="notificationCount">0</span>
                </div>
                <button onclick="logout()" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </button>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-hand-holding-heart"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Donasi</h3>
                    <div class="stat-number" id="totalDonations">0</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3>Penerima</h3>
                    <div class="stat-number" id="totalRecipients">0</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Donasi Uang</h3>
                    <div class="stat-number" id="totalAmount">Rp 0</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3>Menunggu Verifikasi</h3>
                    <div class="stat-number" id="pendingCount">0</div>
                </div>
            </div>
        </div>
        
        <!-- Charts -->
        <div class="charts-row">
            <div class="chart-card">
                <h3>Grafik Donasi 7 Hari Terakhir</h3>
                <canvas id="donationChart"></canvas>
            </div>
            <div class="chart-card">
                <h3>Donasi Berdasarkan Tipe</h3>
                <canvas id="typeChart"></canvas>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-btn active" onclick="showTab('donasi')">Data Donasi Terbaru</button>
            <button class="tab-btn" onclick="showTab('penerima')">Data Penerima Terbaru</button>
            <button class="tab-btn" onclick="showTab('aktivitas')">Aktivitas Terkini</button>
        </div>
        
        <!-- Tab Donasi -->
        <div id="tab-donasi" class="tab-content active">
            <div class="filter-bar">
                <input type="text" class="filter-input" id="searchDonation" placeholder="Cari donasi...">
                <select class="filter-select" id="filterDonationStatus">
                    <option value="">Semua Status</option>
                    <option value="pending">Pending</option>
                    <option value="success">Success</option>
                    <option value="failed">Failed</option>
                </select>
                <button class="btn-filter" onclick="loadDonations()">Filter</button>
            </div>
            <div class="table-container">
                <table id="donasiTable">
                    <thead>
                        <tr>
                            <th>ID Donasi</th>
                            <th>Donatur</th>
                            <th>Email</th>
                            <th>Tipe</th>
                            <th>Jumlah</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="donasiTableBody">
                        <tr>
                            <td colspan="8" style="text-align: center;">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="pagination" id="donationPagination"></div>
        </div>
        
        <!-- Tab Penerima -->
        <div id="tab-penerima" class="tab-content">
            <div class="filter-bar">
                <input type="text" class="filter-input" id="searchRecipient" placeholder="Cari penerima...">
                <select class="filter-select" id="filterRecipientStatus">
                    <option value="">Semua Status</option>
                    <option value="menunggu">Menunggu</option>
                    <option value="diverifikasi">Diverifikasi</option>
                    <option value="diproses">Diproses</option>
                    <option value="diterima">Diterima</option>
                    <option value="ditolak">Ditolak</option>
                </select>
                <button class="btn-filter" onclick="loadRecipients()">Filter</button>
            </div>
            <div class="table-container">
                <table id="penerimaTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama</th>
                            <th>No. Telepon</th>
                            <th>Tipe Bantuan</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="penerimaTableBody">
                        <tr>
                            <td colspan="7" style="text-align: center;">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="pagination" id="recipientPagination"></div>
        </div>
        
        <!-- Tab Aktivitas -->
        <div id="tab-aktivitas" class="tab-content">
            <div class="table-container">
                <table id="aktivitasTable">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Tipe</th>
                            <th>Referensi</th>
                            <th>Nama</th>
                            <th>Detail</th>
                        </tr>
                    </thead>
                    <tbody id="aktivitasTableBody">
                        <tr>
                            <td colspan="5" style="text-align: center;">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div id="editModal" class="edit-form">
        <h3 id="modalTitle">Update Status</h3>
        <form id="editForm">
            <input type="hidden" name="id" id="editId">
            <input type="hidden" name="type" id="editType">
            
            <div class="form-group">
                <label>Status:</label>
                <select name="status" id="editStatus" class="form-control">
                    <option value="pending">Pending</option>
                    <option value="success">Success</option>
                    <option value="failed">Failed</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Catatan:</label>
                <textarea name="notes" id="editNotes" rows="3" placeholder="Tambahkan catatan..."></textarea>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn-submit">Update</button>
                <button type="button" class="btn-submit" onclick="closeEditForm()" style="background: #a0aec0;">Batal</button>
            </div>
        </form>
    </div>
    
    <div id="overlay" class="overlay" onclick="closeEditForm()"></div>
    
    <!-- Notifications Panel -->
    <div id="notificationsPanel" class="notifications-panel">
        <div class="panel-header">
            <h3>Notifikasi</h3>
            <button onclick="markAllAsRead()">Tandai semua dibaca</button>
        </div>
        <div class="panel-body" id="notificationsList">
            <p style="text-align: center;">Loading...</p>
        </div>
    </div>
    
    <script src="../js/admin-dashboard.js"></script>
    <script>
        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            loadStatistics();
            loadDonations();
            loadRecipients();
            loadActivities();
            loadNotifications();
            initCharts();
            
            // Auto refresh every 30 seconds
            setInterval(() => {
                loadNotifications();
                loadStatistics();
            }, 30000);
        });
        
        // Toggle notifications panel
        document.getElementById('notificationsBell').addEventListener('click', function() {
            document.getElementById('notificationsPanel').classList.toggle('active');
        });
        
        // Close panel when clicking outside
        document.addEventListener('click', function(event) {
            const panel = document.getElementById('notificationsPanel');
            const bell = document.getElementById('notificationsBell');
            if (!panel.contains(event.target) && !bell.contains(event.target)) {
                panel.classList.remove('active');
            }
        });
    </script>
</body>
</html>