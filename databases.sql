CREATE DATABASE IF NOT EXISTS donasi_bersama;
USE donasi_bersama;

-- =====================================================
-- TABEL USERS
-- =====================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_lengkap VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    no_telepon VARCHAR(20),
    password VARCHAR(255),
    tipe_user ENUM('donatur', 'penerima', 'both') DEFAULT 'donatur',
    google_id VARCHAR(100),
    facebook_id VARCHAR(100),
    
    -- Verifikasi
    is_verified BOOLEAN DEFAULT FALSE,
    verifikasi_otp VARCHAR(6),
    otp_expired DATETIME,
    verified_at TIMESTAMP NULL,
    
    -- Alamat
    alamat TEXT,
    kota VARCHAR(50),
    provinsi VARCHAR(50),
    kode_pos VARCHAR(10),
    
    -- Tambahan untuk penerima
    nik VARCHAR(16),
    no_kk VARCHAR(20),
    tempat_lahir VARCHAR(50),
    tanggal_lahir DATE,
    jenis_kelamin ENUM('L', 'P'),
    pekerjaan VARCHAR(50),
    penghasilan VARCHAR(20),
    tanggungan INT DEFAULT 0,
    
    -- Metadata
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_tipe (tipe_user),
    INDEX idx_verified (is_verified)
);

-- =====================================================
-- TABEL DONASI
-- =====================================================
CREATE TABLE donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donation_id VARCHAR(50) UNIQUE NOT NULL,
    user_id INT,
    
    -- Data donatur
    donor_name VARCHAR(100) NOT NULL,
    donor_email VARCHAR(100) NOT NULL,
    donor_phone VARCHAR(20),
    
    -- Detail donasi
    donation_type ENUM('uang', 'makanan', 'barang') NOT NULL,
    amount DECIMAL(15,2) DEFAULT 0,
    food_items TEXT,
    goods_items TEXT,
    description TEXT,
    location TEXT,
    
    -- Pembayaran
    payment_method VARCHAR(50),
    payment_status ENUM('pending', 'processing', 'success', 'failed', 'expired') DEFAULT 'pending',
    payment_date TIMESTAMP NULL,
    transaction_id VARCHAR(100),
    
    -- Penyaluran
    distribution_status ENUM('menunggu', 'diproses', 'disalurkan', 'selesai') DEFAULT 'menunggu',
    distribution_date TIMESTAMP NULL,
    recipient_id INT,
    notes TEXT,
    
    -- Metadata
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_donation_id (donation_id),
    INDEX idx_status (payment_status, distribution_status),
    INDEX idx_email (donor_email),
    INDEX idx_date (created_at)
);

-- =====================================================
-- TABEL PENERIMA BANTUAN
-- =====================================================
CREATE TABLE recipients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_id VARCHAR(50) UNIQUE NOT NULL,
    user_id INT,
    
    -- Data pemohon
    nama_lengkap VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    no_telepon VARCHAR(20) NOT NULL,
    
    -- Identitas
    nik VARCHAR(16) UNIQUE,
    no_kk VARCHAR(20),
    tempat_lahir VARCHAR(50),
    tanggal_lahir DATE,
    jenis_kelamin ENUM('L', 'P'),
    
    -- Alamat
    alamat TEXT NOT NULL,
    rt_rw VARCHAR(10),
    kelurahan VARCHAR(50),
    kecamatan VARCHAR(50),
    kota VARCHAR(50),
    provinsi VARCHAR(50),
    kode_pos VARCHAR(10),
    
    -- Pekerjaan & Ekonomi
    pekerjaan VARCHAR(50),
    penghasilan VARCHAR(20),
    tanggungan INT DEFAULT 0,
    
    -- Detail bantuan
    assistance_type ENUM('uang', 'makanan', 'barang') NOT NULL,
    amount_requested DECIMAL(15,2) DEFAULT 0,
    food_items_requested TEXT,
    goods_items_requested TEXT,
    reason TEXT NOT NULL,
    
    -- Dokumen
    ktp_path VARCHAR(255),
    kk_path VARCHAR(255),
    support_docs TEXT,
    
    -- Status
    status ENUM('menunggu', 'diverifikasi', 'diproses', 'diterima', 'ditolak', 'selesai') DEFAULT 'menunggu',
    verification_note TEXT,
    verified_by INT,
    verified_at TIMESTAMP NULL,
    
    -- Metadata
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_recipient_id (recipient_id),
    INDEX idx_status (status),
    INDEX idx_nik (nik),
    INDEX idx_phone (no_telepon)
);

-- =====================================================
-- TABEL ADMIN
-- =====================================================
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    no_telepon VARCHAR(20),
    
    role ENUM('superadmin', 'admin', 'verifikator') DEFAULT 'admin',
    permissions TEXT, -- JSON array of permissions
    
    last_login TIMESTAMP NULL,
    last_ip VARCHAR(45),
    is_active BOOLEAN DEFAULT TRUE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_username (username),
    INDEX idx_role (role)
);

-- =====================================================
-- TABEL NOTIFIKASI
-- =====================================================
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    notification_id VARCHAR(50) UNIQUE NOT NULL,
    
    type ENUM('donation', 'recipient', 'system', 'alert') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    
    -- Relasi
    donation_id INT,
    recipient_id INT,
    user_id INT,
    admin_id INT,
    
    -- Untuk notifikasi
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    read_by INT,
    
    -- Channel
    channel ENUM('email', 'whatsapp', 'telegram', 'in_app') DEFAULT 'in_app',
    sent_status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (donation_id) REFERENCES donations(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES recipients(id) ON DELETE CASCADE,
    INDEX idx_read (is_read),
    INDEX idx_type (type)
);

-- =====================================================
-- TABEL LOG AKTIVITAS
-- =====================================================
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_id VARCHAR(50) UNIQUE NOT NULL,
    
    user_type ENUM('user', 'admin', 'system') NOT NULL,
    user_id INT,
    admin_id INT,
    
    action VARCHAR(100) NOT NULL,
    description TEXT,
    old_data JSON,
    new_data JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user (user_type, user_id),
    INDEX idx_action (action),
    INDEX idx_date (created_at)
);

-- =====================================================
-- INSERT DATA DEFAULT
-- =====================================================

-- Insert admin default (password: Admin123! -> hash: $2y$10$YourHashedPasswordHere)
-- Gunakan password_hash('Admin123!', PASSWORD_DEFAULT) untuk production
INSERT INTO admins (username, password, email, nama_lengkap, role) VALUES 
('superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin@donasibersama.com', 'Super Admin', 'superadmin'),
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@donasibersama.com', 'Admin Utama', 'admin');

-- Insert sample users
INSERT INTO users (nama_lengkap, email, no_telepon, tipe_user, is_verified) VALUES
('Budi Santoso', 'budi@example.com', '081234567890', 'donatur', TRUE),
('Siti Aminah', 'siti@example.com', '081234567891', 'penerima', TRUE),
('Ahmad Hidayat', 'ahmad@example.com', '081234567892', 'both', TRUE);

-- Insert sample donations
INSERT INTO donations (donation_id, donor_name, donor_email, donor_phone, donation_type, amount, payment_method, payment_status, distribution_status) VALUES
('DON-20240101-001', 'Budi Santoso', 'budi@example.com', '081234567890', 'uang', 500000, 'BCA', 'success', 'disalurkan'),
('DON-20240102-002', 'PT Maju Jaya', 'cs@majujaya.com', '0211234567', 'makanan', 0, NULL, 'success', 'diproses'),
('DON-20240103-003', 'Yayasan Peduli', 'info@yayasanpeduli.org', '081234567893', 'barang', 0, NULL, 'success', 'menunggu');

-- Insert sample recipients
INSERT INTO recipients (recipient_id, nama_lengkap, email, no_telepon, nik, alamat, assistance_type, amount_requested, reason, status) VALUES
('RCP-20240101-001', 'Siti Aminah', 'siti@example.com', '081234567891', '1234567890123456', 'Jl. Melati No. 1, Jakarta', 'uang', 1000000, 'Membutuhkan biaya pengobatan', 'diverifikasi'),
('RCP-20240102-002', 'Ahmad Hidayat', 'ahmad@example.com', '081234567892', '1234567890123457', 'Jl. Mawar No. 2, Jakarta', 'makanan', 0, 'Keluarga kurang mampu', 'menunggu');