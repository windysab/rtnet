<?php
/**
 * Halaman Monitoring - RT/RW Net
 * 
 * Menampilkan dashboard monitoring status online pelanggan,
 * pemakaian bandwidth, dan sistem notifikasi
 */

require_once 'classes/Auth.php';
require_once 'classes/Monitoring.php';
require_once 'classes/Notification.php';
require_once 'config/database.php';

// Cek autentikasi
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$monitoring = new Monitoring();
$notification = new Notification();
$db = new Database();

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : 'dashboard';
$message = '';
$error = '';

switch ($action) {
    case 'check_online':
        $result = $monitoring->checkOnlineStatus();
        if ($result['success']) {
            $message = "Status online berhasil diperbarui. Online: {$result['summary']['online']}, Offline: {$result['summary']['offline']}";
        } else {
            $error = $result['message'];
        }
        break;
        
    case 'collect_bandwidth':
        $result = $monitoring->collectBandwidthUsage();
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
        break;
        
    case 'send_reminders':
        $result = $notification->sendBatchReminders();
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
        break;
}

// Ambil data untuk dashboard
$stats = $monitoring->getMonitoringStats();
$notification_stats = $notification->getStats();
$top_users = $monitoring->getTopUsers(5);

// Ambil data online status
$online_customers = $db->query(
    "SELECT c.*, cp.service_type, cp.username 
     FROM customers c 
     JOIN customer_provisioning cp ON c.id = cp.customer_id 
     WHERE c.status = 'active' AND c.online_status = 'online' 
     ORDER BY c.last_seen DESC 
     LIMIT 10"
)->fetchAll();

// Ambil notifikasi terbaru
$recent_notifications = $notification->getAll(['limit' => 5]);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring - RT/RW Net Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/dashboard.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --sidebar-width: 250px;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-menu {
            padding: 1rem 0;
        }
        
        .sidebar-menu .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1.5rem;
            border-radius: 0;
            transition: all 0.3s ease;
        }
        
        .sidebar-menu .nav-link:hover,
        .sidebar-menu .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }
        
        .top-navbar {
            background: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 1rem 2rem;
            margin-bottom: 2rem;
        }
        
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: none;
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .content-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: none;
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        
        .badge {
            font-size: 0.75rem;
            padding: 0.5rem 0.75rem;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stat-card.success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .stat-card.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .stat-card.info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .online-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .online {
            background-color: #28a745;
        }
        .offline {
            background-color: #dc3545;
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h4 class="mb-0">
                <i class="bi bi-wifi me-2"></i>
                RT/RW Net
            </h4>
            <small class="text-light opacity-75">Management System</small>
        </div>
        
        <nav class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="bi bi-speedometer2 me-2"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="customers.php">
                        <i class="bi bi-people me-2"></i>
                        Pelanggan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="packages.php">
                        <i class="bi bi-box me-2"></i>
                        Paket Layanan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="invoices.php">
                        <i class="bi bi-receipt me-2"></i>
                        Tagihan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="payments.php">
                        <i class="bi bi-credit-card me-2"></i>
                        Pembayaran
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="monitoring.php">
                        <i class="bi bi-activity me-2"></i>
                        Monitoring
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php">
                        <i class="bi bi-graph-up me-2"></i>
                        Laporan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="mikrotik.php">
                        <i class="bi bi-router me-2"></i>
                        MikroTik
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="settings.php">
                        <i class="bi bi-gear me-2"></i>
                        Pengaturan
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">Monitoring</h4>
                <small class="text-muted">Monitoring & Notifikasi Sistem</small>
            </div>
            
            <div class="dropdown">
                <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-2"></i>
                    <?= htmlspecialchars($auth->getCurrentAdmin()['username']) ?>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Profil</a></li>
                    <li><a class="dropdown-item" href="change-password.php"><i class="bi bi-key me-2"></i>Ubah Password</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
        
        <!-- Content -->
        <div class="container-fluid px-4">
            <div class="row">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h5 class="mb-0">Monitoring & Notifikasi</h5>
                            <small class="text-muted">Status online pelanggan dan sistem notifikasi</small>
                        </div>
                        <div class="btn-group">
                            <a href="?action=check_online" class="btn btn-primary">
                                <i class="bi bi-arrow-clockwise"></i> Cek Status Online
                            </a>
                            <a href="?action=collect_bandwidth" class="btn btn-info">
                                <i class="bi bi-download"></i> Collect Bandwidth
                            </a>
                            <a href="?action=send_reminders" class="btn btn-warning">
                                <i class="bi bi-bell"></i> Kirim Reminder
                            </a>
                        </div>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?= htmlspecialchars($message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stats-card">
                        <div class="d-flex align-items-center">
                            <div class="stats-icon" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                                <i class="bi bi-wifi"></i>
                            </div>
                            <div class="ms-3">
                                <div class="text-muted small">Pelanggan Online</div>
                                <div class="h4 mb-0"><?= isset($stats['stats']['online_customers']) ? number_format($stats['stats']['online_customers']) : 0 ?></div>
                                <small class="text-success"><?= isset($stats['stats']['online_percentage']) ? $stats['stats']['online_percentage'] : 0 ?>% dari total</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stats-card">
                        <div class="d-flex align-items-center">
                            <div class="stats-icon" style="background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);">
                                <i class="bi bi-wifi-off"></i>
                            </div>
                            <div class="ms-3">
                                <div class="text-muted small">Pelanggan Offline</div>
                                <div class="h4 mb-0"><?= isset($stats['stats']['offline_customers']) ? number_format($stats['stats']['offline_customers']) : 0 ?></div>
                                <small class="text-muted">Tidak terhubung saat ini</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stats-card">
                        <div class="d-flex align-items-center">
                            <div class="stats-icon" style="background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);">
                                <i class="bi bi-speedometer2"></i>
                            </div>
                            <div class="ms-3">
                                <div class="text-muted small">Bandwidth Hari Ini</div>
                                <div class="h4 mb-0"><?= isset($stats['stats']['total_bandwidth_today_mb']) ? number_format($stats['stats']['total_bandwidth_today_mb']) : 0 ?> MB</div>
                                <small class="text-muted">Total pemakaian</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stats-card">
                        <div class="d-flex align-items-center">
                            <div class="stats-icon" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
                                <i class="bi bi-bell"></i>
                            </div>
                            <div class="ms-3">
                                <div class="text-muted small">Pending Reminder</div>
                                <div class="h4 mb-0"><?= isset($stats['stats']['pending_reminders']) ? number_format($stats['stats']['pending_reminders']) : 0 ?></div>
                                <small class="text-muted">Tagihan akan jatuh tempo</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Online Customers -->
                <div class="col-md-6">
                    <div class="content-card">
                        <div class="card-header bg-transparent border-0 pb-0">
                            <h5 class="card-title mb-0"><i class="bi bi-people me-2"></i>Pelanggan Online</h5>
                        </div>
                        <div class="card-body">
                        <?php if (empty($online_customers)): ?>
                            <p class="text-muted">Tidak ada pelanggan yang online saat ini.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Status</th>
                                            <th>Nama</th>
                                            <th>Username</th>
                                            <th>Service</th>
                                            <th>Last Seen</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($online_customers as $customer): ?>
                                            <tr>
                                                <td>
                                                    <span class="online-indicator online"></span>
                                                </td>
                                                <td><?= htmlspecialchars($customer['name']) ?></td>
                                                <td><?= htmlspecialchars($customer['username']) ?></td>
                                                <td>
                                                    <span class="badge bg-primary">
                                                        <?= strtoupper($customer['service_type']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($customer['last_seen']): ?>
                                                        <small><?= date('H:i', strtotime($customer['last_seen'])) ?></small>
                                                    <?php else: ?>
                                                        <small class="text-muted">-</small>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Top Users -->
            <div class="col-md-6">
                <div class="content-card">
                    <div class="card-header bg-transparent border-0 pb-0">
                        <h5 class="card-title mb-0"><i class="bi bi-graph-up me-2"></i>Top Users (Bulan Ini)</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($top_users['success'] && !empty($top_users['data'])): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Nama</th>
                                            <th>Paket</th>
                                            <th>Usage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_users['data'] as $index => $user): ?>
                                            <tr>
                                                <td><?= $index + 1 ?></td>
                                                <td><?= htmlspecialchars($user['name']) ?></td>
                                                <td>
                                                    <small><?= htmlspecialchars(isset($user['package_name']) ? $user['package_name'] : '-') ?></small>
                                                </td>
                                                <td>
                                                    <strong><?= number_format($user['total_mb'], 1) ?> MB</strong>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">Belum ada data pemakaian bandwidth.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Notification Statistics -->
            <div class="col-md-6">
                <div class="content-card">
                    <div class="card-header bg-transparent border-0 pb-0">
                        <h5 class="card-title mb-0"><i class="bi bi-bell me-2"></i>Statistik Notifikasi</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($notification_stats['success']): ?>
                            <div class="row">
                                <div class="col-6">
                                    <h4><?= $notification_stats['stats']['total'] ?></h4>
                                    <p class="text-muted mb-3">Total Notifikasi</p>
                                </div>
                                <div class="col-6">
                                    <h4><?= $notification_stats['stats']['today'] ?></h4>
                                    <p class="text-muted mb-3">Hari Ini</p>
                                </div>
                            </div>
                            
                            <?php if (isset($notification_stats['stats']['by_status'])): ?>
                                <h6>Berdasarkan Status:</h6>
                                <?php foreach ($notification_stats['stats']['by_status'] as $status => $count): ?>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-capitalize"><?= $status ?></span>
                                        <span class="badge bg-secondary"><?= $count ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <?php if (isset($notification_stats['stats']['by_type'])): ?>
                                <h6 class="mt-3">Berdasarkan Type:</h6>
                                <?php foreach ($notification_stats['stats']['by_type'] as $type => $count): ?>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-capitalize"><?= $type ?></span>
                                        <span class="badge bg-info"><?= $count ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="text-muted">Error loading notification statistics.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Notifications -->
            <div class="col-md-6">
                <div class="content-card">
                    <div class="card-header bg-transparent border-0 pb-0 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><i class="bi bi-clock-history me-2"></i>Notifikasi Terbaru</h5>
                        <a href="notifications.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                    </div>
                    <div class="card-body">
                        <?php if ($recent_notifications['success'] && !empty($recent_notifications['data'])): ?>
                            <?php foreach ($recent_notifications['data'] as $notif): ?>
                                <div class="d-flex align-items-start mb-3">
                                    <div class="me-3">
                                        <?php
                                        $icon_class = 'bi-bell';
                                        $badge_class = 'bg-secondary';
                                        
                                        switch ($notif['type']) {
                                            case 'email':
                                                $icon_class = 'bi-envelope';
                                                $badge_class = 'bg-primary';
                                                break;
                                            case 'whatsapp':
                                                $icon_class = 'bi-whatsapp';
                                                $badge_class = 'bg-success';
                                                break;
                                            case 'telegram':
                                                $icon_class = 'bi-telegram';
                                                $badge_class = 'bg-info';
                                                break;
                                        }
                                        
                                        if ($notif['status'] == 'sent') {
                                            $badge_class = 'bg-success';
                                        } elseif ($notif['status'] == 'failed') {
                                            $badge_class = 'bg-danger';
                                        }
                                        ?>
                                        <span class="badge <?= $badge_class ?>">
                                            <i class="<?= $icon_class ?>"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?= htmlspecialchars($notif['title']) ?></h6>
                                        <p class="mb-1 text-muted small">
                                            <?= htmlspecialchars(isset($notif['customer_name']) ? $notif['customer_name'] : 'System') ?>
                                        </p>
                                        <small class="text-muted">
                                            <?= date('d/m/Y H:i', strtotime($notif['created_at'])) ?>
                                        </small>
                                    </div>
                                    <div>
                                        <span class="badge <?= $notif['status'] == 'sent' ? 'bg-success' : ($notif['status'] == 'failed' ? 'bg-danger' : 'bg-warning') ?>">
                                            <?= ucfirst($notif['status']) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">Belum ada notifikasi.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto refresh setiap 30 detik
        setTimeout(function() {
            if (window.location.search === '' || window.location.search === '?action=dashboard') {
                window.location.reload();
            }
        }, 30000);
        
        // Update timestamp setiap detik
        setInterval(function() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('id-ID');
            document.title = `Monitoring (${timeString}) - RT/RW Net`;
        }, 1000);
    </script>
</body>
</html>