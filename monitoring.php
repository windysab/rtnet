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
    <title>Monitoring - RT/RW Net</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
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
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-router"></i> RT/RW Net
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link active" href="monitoring.php">Monitoring</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-activity"></i> Monitoring & Notifikasi</h2>
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
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card success">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3><?= isset($stats['stats']['online_customers']) ? $stats['stats']['online_customers'] : 0 ?></h3>
                            <p class="mb-0">Pelanggan Online</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-wifi" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <small><?= isset($stats['stats']['online_percentage']) ? $stats['stats']['online_percentage'] : 0 ?>% dari total pelanggan</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card warning">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3><?= isset($stats['stats']['offline_customers']) ? $stats['stats']['offline_customers'] : 0 ?></h3>
                            <p class="mb-0">Pelanggan Offline</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-wifi-off" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <small>Tidak terhubung saat ini</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card info">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3><?= isset($stats['stats']['total_bandwidth_today_mb']) ? $stats['stats']['total_bandwidth_today_mb'] : 0 ?> MB</h3>
                            <p class="mb-0">Bandwidth Hari Ini</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-speedometer2" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <small>Total pemakaian</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3><?= isset($stats['stats']['pending_reminders']) ? $stats['stats']['pending_reminders'] : 0 ?></h3>
                            <p class="mb-0">Pending Reminder</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-bell" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <small>Tagihan akan jatuh tempo</small>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Online Customers -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-people"></i> Pelanggan Online</h5>
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
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-graph-up"></i> Top Users (Bulan Ini)</h5>
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
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-bell"></i> Statistik Notifikasi</h5>
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
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="bi bi-clock-history"></i> Notifikasi Terbaru</h5>
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