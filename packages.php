<?php
/**
 * Halaman Manajemen Paket Layanan - RT/RW Net
 * 
 * @author RT/RW Net System
 * @version 1.0
 */

require_once 'classes/Auth.php';
require_once 'classes/Package.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('view_packages');

$current_admin = $auth->getCurrentAdmin();
$package = new Package();

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = '';
$error = '';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        if ($auth->hasPermission('add_packages')) {
            $result = $package->create($_POST);
            if ($result['success']) {
                $message = $result['message'];
                $action = 'list';
            } else {
                $error = $result['message'];
            }
        } else {
            $error = 'Tidak memiliki permission untuk menambah paket';
        }
    } elseif ($action === 'edit') {
        if ($auth->hasPermission('edit_packages')) {
            $id = isset($_POST['id']) ? $_POST['id'] : 0;
            $result = $package->update($id, $_POST);
            if ($result['success']) {
                $message = $result['message'];
                $action = 'list';
            } else {
                $error = $result['message'];
            }
        } else {
            $error = 'Tidak memiliki permission untuk mengedit paket';
        }
    }
}

// Handle delete
if ($action === 'delete' && isset($_GET['id'])) {
    if ($auth->hasPermission('edit_packages')) {
        $result = $package->delete($_GET['id']);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
        $action = 'list';
    } else {
        $error = 'Tidak memiliki permission untuk menghapus paket';
    }
}

// Handle toggle status
if ($action === 'toggle' && isset($_GET['id'])) {
    if ($auth->hasPermission('edit_packages')) {
        $result = $package->toggleStatus($_GET['id']);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
        $action = 'list';
    } else {
        $error = 'Tidak memiliki permission untuk mengubah status paket';
    }
}

// Get data for different actions
if ($action === 'list') {
    $page = isset($_GET['page']) ? $_GET['page'] : 1;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
    $packages_data = $package->getAll($page, 20, $search, $status);
    $stats = $package->getStatistics();
} elseif ($action === 'edit' && isset($_GET['id'])) {
    $package_data = $package->getById($_GET['id']);
    if (!$package_data) {
        $error = 'Paket tidak ditemukan';
        $action = 'list';
    }
}

$csrf_token = $auth->generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Paket Layanan - RT/RW Net</title>
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
        
        .form-floating {
            margin-bottom: 1rem;
        }
        
        .package-card {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .package-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        }
        
        .package-card.featured {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
        }
        
        .bandwidth-display {
            font-size: 1.25rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .price-display {
            font-size: 1.5rem;
            font-weight: bold;
            color: #28a745;
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
                    <a class="nav-link active" href="packages.php">
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
                <h4 class="mb-0">Manajemen Paket Layanan</h4>
                <small class="text-muted">Kelola paket internet dan harga</small>
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
            
            <?php if ($action === 'list'): ?>
                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-value"><?= $stats['total_packages'] ?></div>
                            <div class="stat-label">Total Paket</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-value"><?= $stats['active_packages'] ?></div>
                            <div class="stat-label">Paket Aktif</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-value">Rp <?= number_format($stats['average_price'], 0, ',', '.') ?></div>
                            <div class="stat-label">Harga Rata-rata</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-value"><?= isset($stats['most_popular']['name']) ? $stats['most_popular']['name'] : '-' ?></div>
                            <div class="stat-label">Paket Terpopuler</div>
                        </div>
                    </div>
                </div>
                
                <!-- Package List -->
                <div class="content-card">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Daftar Paket Layanan</h5>
                        <?php if ($auth->hasPermission('add_packages')): ?>
                            <a href="?action=add" class="btn btn-primary">
                                <i class="bi bi-plus-lg me-2"></i>Tambah Paket
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-body">
                        <!-- Search and Filter -->
                        <form method="GET" class="row g-3 mb-4">
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Cari nama paket..." 
                                       value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="status">
                                    <option value="">Semua Status</option>
                                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Aktif</option>
                                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Tidak Aktif</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-search me-2"></i>Cari
                                </button>
                            </div>
                            <div class="col-md-3">
                                <a href="packages.php" class="btn btn-outline-secondary w-100">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Reset
                                </a>
                            </div>
                        </form>
                        
                        <!-- Package Cards -->
                        <div class="row">
                            <?php if (!empty($packages_data['data'])): ?>
                                <?php foreach ($packages_data['data'] as $pkg): ?>
                                    <div class="col-lg-4 col-md-6 mb-4">
                                        <div class="package-card <?= $pkg['active_subscriptions'] > 5 ? 'featured' : '' ?>">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h5 class="mb-1"><?= htmlspecialchars($pkg['name']) ?></h5>
                                                    <span class="badge bg-<?= $pkg['is_active'] ? 'success' : 'secondary' ?>">
                                                        <?= $pkg['is_active'] ? 'Aktif' : 'Tidak Aktif' ?>
                                                    </span>
                                                </div>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                            type="button" data-bs-toggle="dropdown">
                                                        <i class="bi bi-three-dots"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <?php if ($auth->hasPermission('edit_packages')): ?>
                                                            <li><a class="dropdown-item" href="?action=edit&id=<?= $pkg['id'] ?>">
                                                                <i class="bi bi-pencil me-2"></i>Edit
                                                            </a></li>
                                                            <li><a class="dropdown-item" href="?action=toggle&id=<?= $pkg['id'] ?>">
                                                                <i class="bi bi-toggle-<?= $pkg['is_active'] ? 'off' : 'on' ?> me-2"></i>
                                                                <?= $pkg['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                                                            </a></li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li><a class="dropdown-item text-danger" 
                                                                   href="?action=delete&id=<?= $pkg['id'] ?>"
                                                                   onclick="return confirm('Yakin ingin menghapus paket ini?')">
                                                                <i class="bi bi-trash me-2"></i>Hapus
                                                            </a></li>
                                                        <?php endif; ?>
                                                    </ul>
                                                </div>
                                            </div>
                                            
                                            <div class="bandwidth-display mb-2">
                                                <?= $pkg['bandwidth_up'] ?>/<?= $pkg['bandwidth_down'] ?> Mbps
                                            </div>
                                            
                                            <?php if ($pkg['description']): ?>
                                                <p class="text-muted small mb-3"><?= htmlspecialchars($pkg['description']) ?></p>
                                            <?php endif; ?>
                                            
                                            <div class="row text-center mb-3">
                                                <div class="col-6">
                                                    <div class="price-display">Rp <?= number_format($pkg['price'], 0, ',', '.') ?></div>
                                                    <small class="text-muted">per <?= $pkg['duration_days'] ?> hari</small>
                                                </div>
                                                <div class="col-6">
                                                    <div class="h5 mb-0"><?= $pkg['active_subscriptions'] ?></div>
                                                    <small class="text-muted">pelanggan aktif</small>
                                                </div>
                                            </div>
                                            
                                            <!-- Technical Details -->
                                            <div class="row small text-muted">
                                                <?php if ($pkg['burst_limit_up'] || $pkg['burst_limit_down']): ?>
                                                    <div class="col-12 mb-1">
                                                        <i class="bi bi-speedometer me-1"></i>
                                                        Burst: <?= $pkg['burst_limit_up'] ?>/<?= $pkg['burst_limit_down'] ?> Mbps
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($pkg['quota_mb']): ?>
                                                    <div class="col-12 mb-1">
                                                        <i class="bi bi-hdd me-1"></i>
                                                        Quota: <?= number_format($pkg['quota_mb'] / 1024, 1) ?> GB
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="col-12">
                                                    <i class="bi bi-star me-1"></i>
                                                    Priority: <?= $pkg['priority'] ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <div class="text-center py-5">
                                        <i class="bi bi-box text-muted" style="font-size: 4rem;"></i>
                                        <h5 class="text-muted mt-3">Belum ada paket layanan</h5>
                                        <p class="text-muted">Tambahkan paket layanan pertama Anda</p>
                                        <?php if ($auth->hasPermission('add_packages')): ?>
                                            <a href="?action=add" class="btn btn-primary">
                                                <i class="bi bi-plus-lg me-2"></i>Tambah Paket
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($packages_data['total_pages'] > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $packages_data['total_pages']; $i++): ?>
                                        <li class="page-item <?= $i == $packages_data['page'] ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
                
            <?php elseif ($action === 'add' || $action === 'edit'): ?>
                <!-- Add/Edit Form -->
                <div class="content-card">
                    <div class="card-header bg-transparent border-0">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-<?= $action === 'add' ? 'plus-lg' : 'pencil' ?> me-2"></i>
                            <?= $action === 'add' ? 'Tambah' : 'Edit' ?> Paket Layanan
                        </h5>
                    </div>
                    
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <?php if ($action === 'edit'): ?>
                                <input type="hidden" name="id" value="<?= $package_data['id'] ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <!-- Basic Information -->
                                <div class="col-lg-6">
                                    <h6 class="mb-3">Informasi Dasar</h6>
                                    
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="name" name="name" 
                                               placeholder="Nama Paket" required
                                               value="<?= htmlspecialchars(isset($package_data['name']) ? $package_data['name'] : '') ?>">
                                        <label for="name">Nama Paket *</label>
                                    </div>
                                    
                                    <div class="form-floating">
                                        <textarea class="form-control" id="description" name="description" 
                                                  placeholder="Deskripsi" style="height: 100px"><?= htmlspecialchars(isset($package_data['description']) ? $package_data['description'] : '') ?></textarea>
                                        <label for="description">Deskripsi</label>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="number" class="form-control" id="price" name="price" 
                                                       placeholder="Harga" required min="0" step="1000"
                                                       value="<?= isset($package_data['price']) ? $package_data['price'] : '' ?>">
                                                <label for="price">Harga (Rp) *</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="number" class="form-control" id="duration_days" name="duration_days" 
                                                       placeholder="Durasi" required min="1"
                                                       value="<?= isset($package_data['duration_days']) ? $package_data['duration_days'] : '30' ?>">
                                                <label for="duration_days">Durasi (Hari) *</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="number" class="form-control" id="priority" name="priority" 
                                                       placeholder="Priority" min="1" max="8"
                                                       value="<?= isset($package_data['priority']) ? $package_data['priority'] : '8' ?>">
                                                <label for="priority">Priority (1-8)</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <select class="form-select" id="is_active" name="is_active">
                                                    <option value="1" <?= (isset($package_data['is_active']) ? $package_data['is_active'] : 1) == 1 ? 'selected' : '' ?>>Aktif</option>
                                                     <option value="0" <?= (isset($package_data['is_active']) ? $package_data['is_active'] : 1) == 0 ? 'selected' : '' ?>>Tidak Aktif</option>
                                                </select>
                                                <label for="is_active">Status</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Bandwidth Configuration -->
                                <div class="col-lg-6">
                                    <h6 class="mb-3">Konfigurasi Bandwidth</h6>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="number" class="form-control" id="bandwidth_up" name="bandwidth_up" 
                                                       placeholder="Upload" required min="1"
                                                       value="<?= isset($package_data['bandwidth_up']) ? $package_data['bandwidth_up'] : '' ?>">
                                                <label for="bandwidth_up">Upload (Mbps) *</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="number" class="form-control" id="bandwidth_down" name="bandwidth_down" 
                                                       placeholder="Download" required min="1"
                                                       value="<?= isset($package_data['bandwidth_down']) ? $package_data['bandwidth_down'] : '' ?>">
                                                <label for="bandwidth_down">Download (Mbps) *</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="number" class="form-control" id="burst_limit_up" name="burst_limit_up" 
                                                       placeholder="Burst Upload" min="0"
                                                       value="<?= isset($package_data['burst_limit_up']) ? $package_data['burst_limit_up'] : '' ?>">
                                                <label for="burst_limit_up">Burst Upload (Mbps)</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="number" class="form-control" id="burst_limit_down" name="burst_limit_down" 
                                                       placeholder="Burst Download" min="0"
                                                       value="<?= isset($package_data['burst_limit_down']) ? $package_data['burst_limit_down'] : '' ?>">
                                                <label for="burst_limit_down">Burst Download (Mbps)</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="number" class="form-control" id="burst_threshold_up" name="burst_threshold_up" 
                                                       placeholder="Burst Threshold Up" min="0"
                                                       value="<?= isset($package_data['burst_threshold_up']) ? $package_data['burst_threshold_up'] : '' ?>">
                                                <label for="burst_threshold_up">Burst Threshold Up (Mbps)</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="number" class="form-control" id="burst_threshold_down" name="burst_threshold_down" 
                                                       placeholder="Burst Threshold Down" min="0"
                                                       value="<?= isset($package_data['burst_threshold_down']) ? $package_data['burst_threshold_down'] : '' ?>">
                                                <label for="burst_threshold_down">Burst Threshold Down (Mbps)</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-floating">
                                                <input type="number" class="form-control" id="burst_time" name="burst_time" 
                                                       placeholder="Burst Time" min="0"
                                                       value="<?= isset($package_data['burst_time']) ? $package_data['burst_time'] : '' ?>">
                                                <label for="burst_time">Burst Time (s)</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-floating">
                                                <input type="number" class="form-control" id="limit_at_up" name="limit_at_up" 
                                                       placeholder="Limit At Up" min="0"
                                                       value="<?= isset($package_data['limit_at_up']) ? $package_data['limit_at_up'] : '' ?>">
                                                <label for="limit_at_up">Limit At Up (Mbps)</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-floating">
                                                <input type="number" class="form-control" id="limit_at_down" name="limit_at_down" 
                                                       placeholder="Limit At Down" min="0"
                                                       value="<?= isset($package_data['limit_at_down']) ? $package_data['limit_at_down'] : '' ?>">
                                                <label for="limit_at_down">Limit At Down (Mbps)</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-floating">
                                        <input type="number" class="form-control" id="quota_mb" name="quota_mb" 
                                               placeholder="Quota" min="0"
                                               value="<?= isset($package_data['quota_mb']) ? $package_data['quota_mb'] : '' ?>">
                                        <label for="quota_mb">Quota (MB) - Kosongkan untuk unlimited</label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- MikroTik Configuration -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <h6 class="mb-3">Konfigurasi MikroTik</h6>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="pool_name" name="pool_name" 
                                               placeholder="Pool Name"
                                               value="<?= htmlspecialchars(isset($package_data['pool_name']) ? $package_data['pool_name'] : '') ?>">
                                        <label for="pool_name">Pool Name</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="profile_name" name="profile_name" 
                                               placeholder="Profile Name"
                                               value="<?= htmlspecialchars(isset($package_data['profile_name']) ? $package_data['profile_name'] : '') ?>">
                                        <label for="profile_name">Profile Name</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="bi bi-check-lg me-2"></i>
                                        <?= $action === 'add' ? 'Simpan' : 'Update' ?>
                                    </button>
                                    <a href="packages.php" class="btn btn-secondary">
                                        <i class="bi bi-x-lg me-2"></i>
                                        Batal
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-calculate burst values based on bandwidth
        document.getElementById('bandwidth_up').addEventListener('input', function() {
            const burstUp = document.getElementById('burst_limit_up');
            if (!burstUp.value) {
                burstUp.value = Math.ceil(this.value * 1.5);
            }
        });
        
        document.getElementById('bandwidth_down').addEventListener('input', function() {
            const burstDown = document.getElementById('burst_limit_down');
            if (!burstDown.value) {
                burstDown.value = Math.ceil(this.value * 1.5);
            }
        });
        
        // Auto-generate profile name based on package name
        document.getElementById('name').addEventListener('input', function() {
            const profileName = document.getElementById('profile_name');
            if (!profileName.value) {
                profileName.value = this.value.toLowerCase().replace(/[^a-z0-9]/g, '_');
            }
        });
    </script>
</body>
</html>