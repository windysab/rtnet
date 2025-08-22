<?php
/**
 * Halaman Manajemen MikroTik dan Provisioning - RT/RW Net
 * 
 * @author RT/RW Net System
 * @version 1.0
 */

require_once 'classes/Auth.php';
require_once 'classes/Provisioning.php';
require_once 'config/database.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('view_mikrotik');

$current_admin = $auth->getCurrentAdmin();
$provisioning = new Provisioning();
$db = new Database();

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : 'dashboard';
$message = '';
$error = '';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'test_connection') {
        $result = $provisioning->testConnection();
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    } elseif ($action === 'sync_customers') {
        if ($auth->hasPermission('edit_mikrotik')) {
            $result = $provisioning->syncAllCustomers();
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['message'];
            }
        } else {
            $error = 'Tidak memiliki permission untuk sync customers';
        }
    } elseif ($action === 'cleanup_inactive') {
        if ($auth->hasPermission('edit_mikrotik')) {
            $result = $provisioning->cleanupInactiveProvisioning();
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['message'];
            }
        } else {
            $error = 'Tidak memiliki permission untuk cleanup';
        }
    } elseif ($action === 'provision_customer') {
        if ($auth->hasPermission('edit_mikrotik')) {
            $customer_id = $_POST['customer_id'];
            $package_id = $_POST['package_id'];
            $service_type = $_POST['service_type'];
            
            $result = $provisioning->createCustomerAccount($customer_id, $package_id, $service_type);
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['message'];
            }
        } else {
            $error = 'Tidak memiliki permission untuk provisioning';
        }
    }
}

// Handle toggle/delete actions
if ($action === 'toggle_customer' && isset($_GET['id'])) {
    if ($auth->hasPermission('edit_mikrotik')) {
        $enable = isset($_GET['enable']) ? $_GET['enable'] : '1';
        $result = $provisioning->toggleCustomerAccount($_GET['id'], $enable == '1');
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
        $action = 'provisioning';
    } else {
        $error = 'Tidak memiliki permission';
    }
}

if ($action === 'delete_customer' && isset($_GET['id'])) {
    if ($auth->hasPermission('edit_mikrotik')) {
        $result = $provisioning->deleteCustomerAccount($_GET['id']);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
        $action = 'provisioning';
    } else {
        $error = 'Tidak memiliki permission';
    }
}

// Get data for different sections
if ($action === 'dashboard') {
    $stats = $provisioning->getStatistics();
    $mikrotik_config = $db->select('SELECT * FROM mikrotik_config WHERE is_active = 1 LIMIT 1');
} elseif ($action === 'provisioning') {
    $page = isset($_GET['page']) ? $_GET['page'] : 1;
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $service_type = isset($_GET['service_type']) ? $_GET['service_type'] : '';
    $provisioning_data = $provisioning->getAllProvisioning($page, 20, $search, $service_type);
} elseif ($action === 'add_customer') {
    // Get customers without provisioning
    $customers = $db->query(
        "SELECT c.* FROM customers c 
         LEFT JOIN customer_provisioning cp ON c.id = cp.customer_id AND cp.is_active = 1 
         WHERE c.status = 'active' AND cp.id IS NULL"
    );
    $packages = $db->select('SELECT * FROM packages WHERE is_active = 1');
}

$csrf_token = $auth->generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen MikroTik - RT/RW Net</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
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
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 1.5rem;
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
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-connected {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-disconnected {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .action-buttons .btn {
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
        }
        
        .nav-tabs .nav-link.active {
            background-color: var(--primary-color);
            color: white;
            border-radius: 8px;
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
                    <a class="nav-link active" href="mikrotik.php">
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
                <h4 class="mb-0">Manajemen MikroTik</h4>
                <small class="text-muted">Provisioning dan monitoring MikroTik RouterOS</small>
            </div>
            
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
        
        <!-- Content -->
        <div class="container-fluid px-4">
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Navigation Tabs -->
            <ul class="nav nav-tabs mb-4">
                <li class="nav-item">
                    <a class="nav-link <?= $action === 'dashboard' ? 'active' : '' ?>" href="?action=dashboard">
                        <i class="bi bi-speedometer2 me-2"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $action === 'provisioning' ? 'active' : '' ?>" href="?action=provisioning">
                        <i class="bi bi-gear me-2"></i>Provisioning
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $action === 'add_customer' ? 'active' : '' ?>" href="?action=add_customer">
                        <i class="bi bi-plus-lg me-2"></i>Tambah Customer
                    </a>
                </li>
            </ul>
            
            <?php if ($action === 'dashboard'): ?>
                <!-- Dashboard -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-value"><?= isset($stats['total_provisioned']) ? $stats['total_provisioned'] : 0 ?></div>
                            <div class="stat-label">Total Provisioned</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-value"><?= isset($stats['by_service_type']['hotspot']) ? $stats['by_service_type']['hotspot'] : 0 ?></div>
                            <div class="stat-label">Hotspot Users</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-value"><?= isset($stats['by_service_type']['pppoe']) ? $stats['by_service_type']['pppoe'] : 0 ?></div>
                            <div class="stat-label">PPPoE Users</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-value"><?= isset($stats['by_service_type']['simple_queue']) ? $stats['by_service_type']['simple_queue'] : 0 ?></div>
                            <div class="stat-label">Simple Queues</div>
                        </div>
                    </div>
                </div>
                
                <!-- MikroTik Status -->
                <div class="content-card mb-4">
                    <div class="card-header bg-transparent border-0">
                        <h5 class="card-title mb-0">Status MikroTik</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($mikrotik_config)): ?>
                            <?php $config = $mikrotik_config[0]; ?>
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6><?= htmlspecialchars($config['name']) ?></h6>
                                    <p class="text-muted mb-1">
                                        <i class="bi bi-globe me-2"></i><?= htmlspecialchars($config['ip_address']) ?>:<?= $config['api_port'] ?>
                                    </p>
                                    <p class="text-muted mb-0">
                                        <i class="bi bi-person me-2"></i><?= htmlspecialchars($config['username']) ?>
                                    </p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                        <button type="submit" name="action" value="test_connection" class="btn btn-outline-primary">
                                            <i class="bi bi-wifi me-2"></i>Test Koneksi
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
                                <h6 class="mt-3">Konfigurasi MikroTik Belum Ada</h6>
                                <p class="text-muted">Silakan tambahkan konfigurasi MikroTik di pengaturan</p>
                                <a href="settings.php" class="btn btn-primary">
                                    <i class="bi bi-gear me-2"></i>Pengaturan
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="content-card">
                    <div class="card-header bg-transparent border-0">
                        <h5 class="card-title mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="action-buttons">
                            <?php if ($auth->hasPermission('edit_mikrotik')): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                    <button type="submit" name="action" value="sync_customers" class="btn btn-success"
                                            onclick="return confirm('Yakin ingin sync semua customer ke MikroTik?')">
                                        <i class="bi bi-arrow-repeat me-2"></i>Sync All Customers
                                    </button>
                                </form>
                                
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                    <button type="submit" name="action" value="cleanup_inactive" class="btn btn-warning"
                                            onclick="return confirm('Yakin ingin cleanup provisioning yang tidak aktif?')">
                                        <i class="bi bi-trash me-2"></i>Cleanup Inactive
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <a href="?action=provisioning" class="btn btn-primary">
                                <i class="bi bi-list me-2"></i>Lihat Semua Provisioning
                            </a>
                            
                            <a href="?action=add_customer" class="btn btn-outline-primary">
                                <i class="bi bi-plus-lg me-2"></i>Tambah Customer
                            </a>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($action === 'provisioning'): ?>
                <!-- Provisioning List -->
                <div class="content-card">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Daftar Provisioning</h5>
                        <a href="?action=add_customer" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-2"></i>Tambah Customer
                        </a>
                    </div>
                    
                    <div class="card-body">
                        <!-- Search and Filter -->
                        <form method="GET" class="row g-3 mb-4">
                            <input type="hidden" name="action" value="provisioning">
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Cari nama customer atau username..." 
                                       value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="service_type">
                                    <option value="">Semua Service Type</option>
                                    <option value="hotspot" <?= $service_type === 'hotspot' ? 'selected' : '' ?>>Hotspot</option>
                                    <option value="pppoe" <?= $service_type === 'pppoe' ? 'selected' : '' ?>>PPPoE</option>
                                    <option value="simple_queue" <?= $service_type === 'simple_queue' ? 'selected' : '' ?>>Simple Queue</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-search me-2"></i>Cari
                                </button>
                            </div>
                            <div class="col-md-3">
                                <a href="?action=provisioning" class="btn btn-outline-secondary w-100">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Reset
                                </a>
                            </div>
                        </form>
                        
                        <!-- Provisioning Table -->
                        <?php if (!empty($provisioning_data['data'])): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Customer</th>
                                            <th>Username</th>
                                            <th>Service Type</th>
                                            <th>Package</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($provisioning_data['data'] as $prov): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?= htmlspecialchars($prov['customer_name']) ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?= htmlspecialchars($prov['phone']) ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <code><?= htmlspecialchars($prov['username']) ?></code>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $prov['service_type'] === 'hotspot' ? 'primary' : ($prov['service_type'] === 'pppoe' ? 'success' : 'info') ?>">
                                                        <?= ucfirst($prov['service_type']) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($prov['package_name']) ?></td>
                                                <td>
                                                    <small><?= date('d/m/Y H:i', strtotime($prov['created_at'])) ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($auth->hasPermission('edit_mikrotik')): ?>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="?action=toggle_customer&id=<?= $prov['customer_id'] ?>&enable=0" 
                                                               class="btn btn-outline-warning" title="Disable">
                                                                <i class="bi bi-pause"></i>
                                                            </a>
                                                            <a href="?action=toggle_customer&id=<?= $prov['customer_id'] ?>&enable=1" 
                                                               class="btn btn-outline-success" title="Enable">
                                                                <i class="bi bi-play"></i>
                                                            </a>
                                                            <a href="?action=delete_customer&id=<?= $prov['customer_id'] ?>" 
                                                               class="btn btn-outline-danger" title="Delete"
                                                               onclick="return confirm('Yakin ingin menghapus provisioning ini?')">
                                                                <i class="bi bi-trash"></i>
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($provisioning_data['total_pages'] > 1): ?>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-center">
                                        <?php for ($i = 1; $i <= $provisioning_data['total_pages']; $i++): ?>
                                            <li class="page-item <?= $i == $provisioning_data['page'] ? 'active' : '' ?>">
                                                <a class="page-link" href="?action=provisioning&page=<?= $i ?>&search=<?= urlencode($search) ?>&service_type=<?= urlencode($service_type) ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-router text-muted" style="font-size: 4rem;"></i>
                                <h5 class="text-muted mt-3">Belum ada provisioning</h5>
                                <p class="text-muted">Tambahkan customer pertama untuk provisioning</p>
                                <a href="?action=add_customer" class="btn btn-primary">
                                    <i class="bi bi-plus-lg me-2"></i>Tambah Customer
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            <?php elseif ($action === 'add_customer'): ?>
                <!-- Add Customer Form -->
                <div class="content-card">
                    <div class="card-header bg-transparent border-0">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-plus-lg me-2"></i>Tambah Customer ke MikroTik
                        </h5>
                    </div>
                    
                    <div class="card-body">
                        <?php if (!empty($customers) && !empty($packages)): ?>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <input type="hidden" name="action" value="provision_customer">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="customer_id" name="customer_id" required>
                                                <option value="">Pilih Customer</option>
                                                <?php foreach ($customers as $customer): ?>
                                                    <option value="<?= $customer['id'] ?>">
                                                        <?= htmlspecialchars($customer['name']) ?> - <?= htmlspecialchars($customer['phone']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <label for="customer_id">Customer *</label>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="package_id" name="package_id" required>
                                                <option value="">Pilih Paket</option>
                                                <?php foreach ($packages as $package): ?>
                                                    <option value="<?= $package['id'] ?>">
                                                        <?= htmlspecialchars($package['name']) ?> - Rp <?= number_format($package['price'], 0, ',', '.') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <label for="package_id">Paket *</label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="service_type" name="service_type" required>
                                                <option value="hotspot">Hotspot</option>
                                                <option value="pppoe">PPPoE</option>
                                                <option value="simple_queue">Simple Queue</option>
                                            </select>
                                            <label for="service_type">Service Type *</label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary me-2">
                                            <i class="bi bi-check-lg me-2"></i>Provision Customer
                                        </button>
                                        <a href="?action=provisioning" class="btn btn-secondary">
                                            <i class="bi bi-x-lg me-2"></i>Batal
                                        </a>
                                    </div>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-exclamation-triangle text-warning" style="font-size: 4rem;"></i>
                                <h5 class="text-muted mt-3">Tidak ada customer atau paket tersedia</h5>
                                <p class="text-muted">Pastikan sudah ada customer dan paket yang aktif</p>
                                <div>
                                    <a href="customers.php" class="btn btn-primary me-2">
                                        <i class="bi bi-people me-2"></i>Kelola Customer
                                    </a>
                                    <a href="packages.php" class="btn btn-outline-primary">
                                        <i class="bi bi-box me-2"></i>Kelola Paket
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto refresh status setiap 30 detik
        <?php if ($action === 'dashboard'): ?>
            setInterval(function() {
                // Refresh halaman untuk update status
                if (document.visibilityState === 'visible') {
                    location.reload();
                }
            }, 30000);
        <?php endif; ?>
    </script>
</body>
</html>