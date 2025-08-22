<?php
/**
 * Halaman Detail Pelanggan - RT/RW Net
 * 
 * @author RT/RW Net System
 * @version 1.0
 */

require_once 'classes/Auth.php';
require_once 'classes/Customer.php';
require_once 'config/database.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('view_customers');

$current_admin = $auth->getCurrentAdmin();
$customer = new Customer();
$db = new Database();

$customer_id = isset($_GET['id']) ? $_GET['id'] : 0;
$customer_data = $customer->getById($customer_id);

if (!$customer_data) {
    header('Location: customers.php?error=Pelanggan tidak ditemukan');
    exit;
}

// Get subscription history
$subscription_query = "SELECT s.*, p.name as package_name, p.price, p.bandwidth_up, p.bandwidth_down 
                      FROM subscriptions s 
                      LEFT JOIN packages p ON s.package_id = p.id 
                      WHERE s.customer_id = ? 
                      ORDER BY s.created_at DESC";
$subscriptions = $db->select($subscription_query, [$customer_id]);

// Get payment history
$payment_query = "SELECT py.*, i.invoice_number, i.amount as invoice_amount 
                  FROM payments py 
                  LEFT JOIN invoices i ON py.invoice_id = i.id 
                  WHERE i.customer_id = ? 
                  ORDER BY py.created_at DESC 
                  LIMIT 10";
$payments = $db->select($payment_query, [$customer_id]);

// Get activity logs
$activity_query = "SELECT * FROM customer_activity_logs 
                   WHERE customer_id = ? 
                   ORDER BY created_at DESC 
                   LIMIT 20";
$activities = $db->select($activity_query, [$customer_id]);

// Get bandwidth usage (last 30 days)
$usage_query = "SELECT * FROM bandwidth_usage 
                WHERE customer_id = ? 
                AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
                ORDER BY date DESC";
$bandwidth_usage = $db->select($usage_query, [$customer_id]);

// Calculate statistics
$total_paid = 0;
foreach ($payments as $payment) {
    if ($payment['status'] === 'paid') {
        $total_paid += $payment['amount'];
    }
}

$current_subscription = null;
if (!empty($subscriptions)) {
    foreach ($subscriptions as $sub) {
        if ($sub['status'] === 'active') {
            $current_subscription = $sub;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pelanggan - <?= htmlspecialchars($customer_data['full_name']) ?> - RT/RW Net</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
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
        }
        
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-menu .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1.5rem;
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
        
        .content-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 2rem;
        }
        
        .customer-photo {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 12px;
            border: 3px solid #e9ecef;
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        #map {
            height: 300px;
            border-radius: 8px;
        }
        
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 0.75rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -1.75rem;
            top: 0.5rem;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--primary-color);
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
                    <a class="nav-link active" href="customers.php">
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
                    <a class="nav-link" href="monitoring.php">
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
                <h4 class="mb-0">Detail Pelanggan</h4>
                <small class="text-muted"><?= htmlspecialchars($customer_data['full_name']) ?> - <?= htmlspecialchars($customer_data['customer_code']) ?></small>
            </div>
            
            <div class="d-flex gap-2">
                <?php if ($auth->hasPermission('edit_customers')): ?>
                    <a href="customers.php?action=edit&id=<?= $customer_data['id'] ?>" class="btn btn-primary">
                        <i class="bi bi-pencil me-2"></i>Edit
                    </a>
                <?php endif; ?>
                <a href="customers.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Kembali
                </a>
                
                <div class="dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-2"></i>
                        <?= htmlspecialchars($current_admin['username']) ?>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Profil</a></li>
                        <li><a class="dropdown-item" href="change-password.php"><i class="bi bi-key me-2"></i>Ubah Password</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Content -->
        <div class="container-fluid px-4">
            <!-- Customer Info -->
            <div class="content-card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="mb-3">Informasi Pribadi</h5>
                                    <table class="table table-borderless">
                                        <tr>
                                            <td width="40%"><strong>Kode Pelanggan:</strong></td>
                                            <td><?= htmlspecialchars($customer_data['customer_code']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Nama Lengkap:</strong></td>
                                            <td><?= htmlspecialchars($customer_data['full_name']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Email:</strong></td>
                                            <td><?= htmlspecialchars($customer_data['email'] ?: '-') ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Telepon:</strong></td>
                                            <td><?= htmlspecialchars($customer_data['phone']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Nomor KTP:</strong></td>
                                            <td><?= htmlspecialchars($customer_data['ktp_number'] ?: '-') ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Status:</strong></td>
                                            <td>
                                                <span class="badge bg-<?= $customer_data['status'] === 'active' ? 'success' : ($customer_data['status'] === 'suspended' ? 'warning' : 'danger') ?>">
                                                    <?= ucfirst($customer_data['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Tgl Daftar:</strong></td>
                                            <td><?= date('d/m/Y H:i', strtotime($customer_data['created_at'])) ?></td>
                                        </tr>
                                    </table>
                                </div>
                                
                                <div class="col-md-6">
                                    <h5 class="mb-3">Alamat</h5>
                                    <table class="table table-borderless">
                                        <tr>
                                            <td width="40%"><strong>Alamat:</strong></td>
                                            <td><?= htmlspecialchars($customer_data['address']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>RT/RW:</strong></td>
                                            <td><?= htmlspecialchars($customer_data['rt_rw'] ?: '-') ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Kelurahan:</strong></td>
                                            <td><?= htmlspecialchars($customer_data['kelurahan'] ?: '-') ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Kecamatan:</strong></td>
                                            <td><?= htmlspecialchars($customer_data['kecamatan'] ?: '-') ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Kota:</strong></td>
                                            <td><?= htmlspecialchars($customer_data['kota'] ?: '-') ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Kode Pos:</strong></td>
                                            <td><?= htmlspecialchars($customer_data['kode_pos'] ?: '-') ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Koordinat:</strong></td>
                                            <td>
                                                <?php if ($customer_data['latitude'] && $customer_data['longitude']): ?>
                                                    <?= $customer_data['latitude'] ?>, <?= $customer_data['longitude'] ?>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            
                            <?php if ($customer_data['notes']): ?>
                                <div class="mt-3">
                                    <h6>Catatan:</h6>
                                    <p class="text-muted"><?= nl2br(htmlspecialchars($customer_data['notes'])) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-lg-4">
                            <h5 class="mb-3">Foto</h5>
                            <div class="row">
                                <?php if ($customer_data['ktp_photo']): ?>
                                    <div class="col-6 mb-3">
                                        <div class="text-center">
                                            <img src="uploads/customers/<?= htmlspecialchars($customer_data['ktp_photo']) ?>" 
                                                 class="customer-photo" alt="KTP">
                                            <div class="mt-2"><small class="text-muted">Foto KTP</small></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($customer_data['house_photo']): ?>
                                    <div class="col-6 mb-3">
                                        <div class="text-center">
                                            <img src="uploads/customers/<?= htmlspecialchars($customer_data['house_photo']) ?>" 
                                                 class="customer-photo" alt="Rumah">
                                            <div class="mt-2"><small class="text-muted">Foto Rumah</small></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-value"><?= count($subscriptions) ?></div>
                        <div class="stat-label">Total Langganan</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-value">Rp <?= number_format($total_paid, 0, ',', '.') ?></div>
                        <div class="stat-label">Total Pembayaran</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-value"><?= count($payments) ?></div>
                        <div class="stat-label">Transaksi</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-value"><?= count($activities) ?></div>
                        <div class="stat-label">Aktivitas</div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Current Subscription -->
                <div class="col-lg-6">
                    <div class="content-card">
                        <div class="card-header bg-transparent border-0">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-box me-2"></i>
                                Langganan Aktif
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($current_subscription): ?>
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h6 class="mb-1"><?= htmlspecialchars($current_subscription['package_name']) ?></h6>
                                        <p class="text-muted mb-1">
                                            <?= $current_subscription['bandwidth_up'] ?>/<?= $current_subscription['bandwidth_down'] ?> Mbps
                                        </p>
                                        <small class="text-muted">
                                            Mulai: <?= date('d/m/Y', strtotime($current_subscription['start_date'])) ?><br>
                                            Berakhir: <?= date('d/m/Y', strtotime($current_subscription['end_date'])) ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <div class="h5 mb-1">Rp <?= number_format($current_subscription['price'], 0, ',', '.') ?></div>
                                        <span class="badge bg-success">Aktif</span>
                                    </div>
                                </div>
                                
                                <?php
                                $days_left = ceil((strtotime($current_subscription['end_date']) - time()) / (60 * 60 * 24));
                                $progress = max(0, min(100, (30 - $days_left) / 30 * 100));
                                ?>
                                
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between">
                                        <small>Sisa waktu</small>
                                        <small><?= $days_left ?> hari</small>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-<?= $days_left > 7 ? 'success' : ($days_left > 3 ? 'warning' : 'danger') ?>" 
                                             style="width: <?= $progress ?>%"></div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-box text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-2">Belum ada langganan aktif</p>
                                    <a href="subscriptions.php?customer_id=<?= $customer_data['id'] ?>" class="btn btn-primary btn-sm">
                                        <i class="bi bi-plus me-2"></i>Tambah Langganan
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Map -->
                <div class="col-lg-6">
                    <div class="content-card">
                        <div class="card-header bg-transparent border-0">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-geo-alt me-2"></i>
                                Lokasi
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($customer_data['latitude'] && $customer_data['longitude']): ?>
                                <div id="map"></div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-geo-alt text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-2">Koordinat belum diset</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tabs -->
            <div class="content-card mt-4">
                <div class="card-header bg-transparent border-0">
                    <ul class="nav nav-tabs card-header-tabs" id="customerTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="subscriptions-tab" data-bs-toggle="tab" 
                                    data-bs-target="#subscriptions" type="button" role="tab">
                                <i class="bi bi-box me-2"></i>Riwayat Langganan
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="payments-tab" data-bs-toggle="tab" 
                                    data-bs-target="#payments" type="button" role="tab">
                                <i class="bi bi-credit-card me-2"></i>Riwayat Pembayaran
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="activities-tab" data-bs-toggle="tab" 
                                    data-bs-target="#activities" type="button" role="tab">
                                <i class="bi bi-activity me-2"></i>Log Aktivitas
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="bandwidth-tab" data-bs-toggle="tab" 
                                    data-bs-target="#bandwidth" type="button" role="tab">
                                <i class="bi bi-graph-up me-2"></i>Penggunaan Bandwidth
                            </button>
                        </li>
                    </ul>
                </div>
                
                <div class="card-body">
                    <div class="tab-content" id="customerTabsContent">
                        <!-- Subscriptions Tab -->
                        <div class="tab-pane fade show active" id="subscriptions" role="tabpanel">
                            <?php if (!empty($subscriptions)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Paket</th>
                                                <th>Bandwidth</th>
                                                <th>Harga</th>
                                                <th>Periode</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($subscriptions as $sub): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($sub['package_name']) ?></td>
                                                    <td><?= $sub['bandwidth_up'] ?>/<?= $sub['bandwidth_down'] ?> Mbps</td>
                                                    <td>Rp <?= number_format($sub['price'], 0, ',', '.') ?></td>
                                                    <td>
                                                        <?= date('d/m/Y', strtotime($sub['start_date'])) ?> - 
                                                        <?= date('d/m/Y', strtotime($sub['end_date'])) ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?= $sub['status'] === 'active' ? 'success' : ($sub['status'] === 'expired' ? 'danger' : 'warning') ?>">
                                                            <?= ucfirst($sub['status']) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-box text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-2">Belum ada riwayat langganan</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Payments Tab -->
                        <div class="tab-pane fade" id="payments" role="tabpanel">
                            <?php if (!empty($payments)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Invoice</th>
                                                <th>Jumlah</th>
                                                <th>Metode</th>
                                                <th>Tanggal</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($payments as $payment): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($payment['invoice_number']) ?></td>
                                                    <td>Rp <?= number_format($payment['amount'], 0, ',', '.') ?></td>
                                                    <td><?= htmlspecialchars($payment['payment_method']) ?></td>
                                                    <td><?= date('d/m/Y H:i', strtotime($payment['created_at'])) ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= $payment['status'] === 'paid' ? 'success' : ($payment['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                                            <?= ucfirst($payment['status']) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-credit-card text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-2">Belum ada riwayat pembayaran</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Activities Tab -->
                        <div class="tab-pane fade" id="activities" role="tabpanel">
                            <?php if (!empty($activities)): ?>
                                <div class="timeline">
                                    <?php foreach ($activities as $activity): ?>
                                        <div class="timeline-item">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1"><?= htmlspecialchars($activity['activity_type']) ?></h6>
                                                    <p class="mb-1"><?= htmlspecialchars($activity['description']) ?></p>
                                                    <small class="text-muted"><?= date('d/m/Y H:i', strtotime($activity['created_at'])) ?></small>
                                                </div>
                                                <span class="badge bg-primary"><?= htmlspecialchars($activity['activity_type']) ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-activity text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-2">Belum ada log aktivitas</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Bandwidth Tab -->
                        <div class="tab-pane fade" id="bandwidth" role="tabpanel">
                            <?php if (!empty($bandwidth_usage)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>Upload (MB)</th>
                                                <th>Download (MB)</th>
                                                <th>Total (MB)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($bandwidth_usage as $usage): ?>
                                                <tr>
                                                    <td><?= date('d/m/Y', strtotime($usage['date'])) ?></td>
                                                    <td><?= number_format($usage['upload_mb'], 2) ?></td>
                                                    <td><?= number_format($usage['download_mb'], 2) ?></td>
                                                    <td><?= number_format($usage['upload_mb'] + $usage['download_mb'], 2) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-graph-up text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-2">Belum ada data penggunaan bandwidth</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize map if coordinates exist
        <?php if ($customer_data['latitude'] && $customer_data['longitude']): ?>
            const lat = <?= $customer_data['latitude'] ?>;
            const lng = <?= $customer_data['longitude'] ?>;
            
            const map = L.map('map').setView([lat, lng], 15);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            L.marker([lat, lng]).addTo(map)
                .bindPopup('<?= htmlspecialchars($customer_data['full_name']) ?><br><?= htmlspecialchars($customer_data['address']) ?>')
                .openPopup();
        <?php endif; ?>
    </script>
</body>
</html>