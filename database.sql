-- Database Schema untuk Aplikasi RT/RW Net
-- Sistem Manajemen ISP Rumahan berbasis MikroTik + PHP

CREATE DATABASE IF NOT EXISTS rtnet_db;
USE rtnet_db;

-- Tabel Admin/User
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    role ENUM('super_admin', 'admin', 'operator') DEFAULT 'admin',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabel Konfigurasi MikroTik
CREATE TABLE mikrotik_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    host VARCHAR(15) NOT NULL, -- alias untuk ip_address
    ip_address VARCHAR(15) NOT NULL,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    port INT DEFAULT 8728, -- alias untuk api_port
    api_port INT DEFAULT 8728,
    is_active BOOLEAN DEFAULT TRUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabel Paket Layanan
CREATE TABLE packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    bandwidth_up VARCHAR(20) NOT NULL, -- contoh: 10M, 1G
    bandwidth_down VARCHAR(20) NOT NULL,
    burst_limit_up VARCHAR(20),
    burst_limit_down VARCHAR(20),
    burst_threshold_up VARCHAR(20),
    burst_threshold_down VARCHAR(20),
    burst_time VARCHAR(10),
    limit_at_up VARCHAR(20),
    limit_at_down VARCHAR(20),
    price DECIMAL(10,2) NOT NULL,
    duration_days INT DEFAULT 30, -- masa aktif dalam hari
    quota_mb BIGINT DEFAULT NULL, -- dalam MB, NULL = unlimited
    quota_limit BIGINT DEFAULT NULL, -- dalam bytes, NULL = unlimited (untuk kompatibilitas)
    priority INT DEFAULT 8, -- priority 1-8
    pool_name VARCHAR(100), -- nama pool IP untuk PPPoE
    profile_name VARCHAR(100), -- nama profile MikroTik
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabel Pelanggan
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL, -- alias untuk full_name
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    rt_rw VARCHAR(20),
    kelurahan VARCHAR(50),
    kecamatan VARCHAR(50),
    kota VARCHAR(50),
    kode_pos VARCHAR(10),
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    ktp_number VARCHAR(20),
    ktp_photo VARCHAR(255), -- path file foto KTP
    house_photo VARCHAR(255), -- path file foto rumah
    mac_address VARCHAR(17), -- MAC address untuk binding
    ip_address VARCHAR(15), -- IP address static
    installation_date DATE,
    status ENUM('active', 'suspended', 'terminated') DEFAULT 'active',
    notes TEXT,
    online_status ENUM('online','offline') DEFAULT 'offline',
    last_seen TIMESTAMP NULL DEFAULT NULL,
    telegram_id VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabel Langganan Pelanggan
CREATE TABLE subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    package_id INT NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL, -- username untuk hotspot/pppoe
    password VARCHAR(100) NOT NULL,
    mac_address VARCHAR(17), -- untuk binding MAC
    ip_address VARCHAR(15), -- IP static jika ada
    service_type ENUM('hotspot', 'pppoe') DEFAULT 'hotspot',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active', 'suspended', 'expired', 'terminated') DEFAULT 'active',
    mikrotik_profile VARCHAR(100), -- nama profile di MikroTik
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES packages(id)
);

-- Tabel Tagihan
CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    customer_id INT NOT NULL,
    subscription_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('unpaid', 'paid', 'overdue', 'cancelled') DEFAULT 'unpaid',
    payment_date DATETIME NULL,
    payment_method VARCHAR(50) NULL,
    payment_proof VARCHAR(255) NULL, -- path file bukti bayar
    reminder_sent TINYINT(1) DEFAULT 0, -- flag untuk reminder yang sudah dikirim
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id)
);

-- Tabel Pembayaran
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATETIME NOT NULL,
    payment_method ENUM('cash', 'transfer', 'qris', 'va') NOT NULL,
    reference_number VARCHAR(100),
    payment_proof VARCHAR(255),
    admin_id INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admins(id)
);

-- Tabel Provisioning Pelanggan
CREATE TABLE customer_provisioning (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    package_id INT NOT NULL,
    service_type ENUM('hotspot', 'pppoe', 'simple_queue') NOT NULL,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(100) NOT NULL,
    profile_name VARCHAR(100),
    mikrotik_id VARCHAR(50), -- ID dari MikroTik
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES packages(id),
    UNIQUE KEY unique_active_customer (customer_id, is_active)
);

-- Tabel Log Aktivitas Pelanggan
CREATE TABLE customer_activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    description TEXT,
    ip_address VARCHAR(15),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Tabel untuk monitoring bandwidth usage
CREATE TABLE bandwidth_monitoring (
  id int(11) NOT NULL AUTO_INCREMENT,
  customer_id int(11) NOT NULL,
  date date NOT NULL,
  upload_bytes bigint(20) DEFAULT 0,
  download_bytes bigint(20) DEFAULT 0,
  total_bytes bigint(20) DEFAULT 0,
  session_time int(11) DEFAULT 0,
  created_at timestamp DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY unique_customer_date (customer_id, date),
  KEY idx_bandwidth_date (date),
  KEY idx_bandwidth_customer (customer_id),
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Tabel untuk log notifikasi
CREATE TABLE notification_logs (
  id int(11) NOT NULL AUTO_INCREMENT,
  customer_id int(11) DEFAULT NULL,
  type enum('email','whatsapp','telegram','system') NOT NULL,
  title varchar(255) NOT NULL,
  message text NOT NULL,
  recipient varchar(255) NOT NULL,
  status enum('pending','sent','failed','delivered','read') DEFAULT 'pending',
  response_data text,
  sent_at timestamp NULL DEFAULT NULL,
  created_at timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notification_customer (customer_id),
  KEY idx_notification_type (type),
  KEY idx_notification_status (status),
  KEY idx_notification_date (created_at),
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
);

-- Tabel Log Aktivitas Pelanggan (untuk kompatibilitas)
CREATE TABLE customer_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    subscription_id INT,
    activity_type ENUM('login', 'logout', 'suspend', 'activate', 'terminate', 'payment') NOT NULL,
    description TEXT,
    ip_address VARCHAR(15),
    mac_address VARCHAR(17),
    bytes_in BIGINT DEFAULT 0,
    bytes_out BIGINT DEFAULT 0,
    session_time INT DEFAULT 0, -- dalam detik
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id)
);

-- Tabel Monitoring Bandwidth
CREATE TABLE bandwidth_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    subscription_id INT NOT NULL,
    date DATE NOT NULL,
    bytes_in BIGINT DEFAULT 0,
    bytes_out BIGINT DEFAULT 0,
    total_bytes BIGINT GENERATED ALWAYS AS (bytes_in + bytes_out) STORED,
    session_count INT DEFAULT 0,
    online_time INT DEFAULT 0, -- dalam detik
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id),
    UNIQUE KEY unique_daily_usage (customer_id, subscription_id, date)
);

-- Tabel Notifikasi
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    type ENUM('payment_reminder', 'payment_overdue', 'service_suspended', 'service_activated', 'quota_warning') NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    channel ENUM('email', 'whatsapp', 'telegram', 'sms') NOT NULL,
    recipient VARCHAR(100) NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    sent_at DATETIME NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Tabel Pengaturan Sistem
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert data default
INSERT INTO admins (username, password, full_name, email, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@rtnet.local', 'super_admin');
-- Password default: password

INSERT INTO system_settings (setting_key, setting_value, description) VALUES 
('company_name', 'RT/RW Net', 'Nama perusahaan'),
('company_address', 'Jl. Contoh No. 123', 'Alamat perusahaan'),
('company_phone', '021-12345678', 'Telepon perusahaan'),
('company_email', 'info@rtnet.local', 'Email perusahaan'),
('invoice_prefix', 'INV', 'Prefix nomor invoice'),
('customer_prefix', 'CUST', 'Prefix kode pelanggan'),
('default_package_duration', '30', 'Durasi paket default (hari)'),
('payment_due_days', '7', 'Jatuh tempo pembayaran (hari)'),
('notification_whatsapp_enabled', '0', 'Aktifkan notifikasi WhatsApp'),
('notification_email_enabled', '1', 'Aktifkan notifikasi Email'),
('whatsapp_api_url', '', 'URL API WhatsApp'),
('whatsapp_api_token', '', 'Token API WhatsApp'),
('telegram_bot_token', '', 'Token Bot Telegram'),
('telegram_chat_id', '', 'Chat ID Telegram'),
('email_smtp_host', 'smtp.gmail.com', 'SMTP Host Email'),
('email_smtp_port', '587', 'SMTP Port Email'),
('email_smtp_username', '', 'Username SMTP Email'),
('email_smtp_password', '', 'Password SMTP Email'),
('notification_reminder_days', '3,1', 'Hari reminder sebelum jatuh tempo'),
('monitoring_interval', '300', 'Interval monitoring (detik)'),
('bandwidth_collection_enabled', '1', 'Aktifkan pengumpulan data bandwidth');

-- Index untuk optimasi query
CREATE INDEX idx_customers_status ON customers(status);
CREATE INDEX idx_customers_online_status ON customers(online_status);
CREATE INDEX idx_subscriptions_status ON subscriptions(status);
CREATE INDEX idx_subscriptions_dates ON subscriptions(start_date, end_date);
CREATE INDEX idx_invoices_status ON invoices(status);
CREATE INDEX idx_invoices_due_date ON invoices(due_date);
CREATE INDEX idx_bandwidth_usage_date ON bandwidth_usage(date);
CREATE INDEX idx_customer_logs_date ON customer_logs(created_at);
CREATE INDEX idx_notifications_status ON notifications(status);