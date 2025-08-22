<?php
/**
 * Halaman Manajemen Pelanggan - RT/RW Net
 * 
 * @author RT/RW Net System
 * @version 1.0
 */

require_once 'classes/Auth.php';
require_once 'classes/Customer.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('view_customers');

$current_admin = $auth->getCurrentAdmin();
$customer = new Customer();

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = '';
$error = '';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        if ($auth->hasPermission('add_customers')) {
            $result = $customer->create($_POST, $_FILES);
            if ($result['success']) {
                $message = $result['message'];
                $action = 'list';
            } else {
                $error = $result['message'];
            }
        } else {
            $error = 'Tidak memiliki permission untuk menambah pelanggan';
        }
    } elseif ($action === 'edit') {
        if ($auth->hasPermission('edit_customers')) {
            $id = isset($_POST['id']) ? $_POST['id'] : 0;
            $result = $customer->update($id, $_POST, $_FILES);
            if ($result['success']) {
                $message = $result['message'];
                $action = 'list';
            } else {
                $error = $result['message'];
            }
        } else {
            $error = 'Tidak memiliki permission untuk mengedit pelanggan';
        }
    }
}

// Handle delete
if ($action === 'delete' && isset($_GET['id'])) {
    if ($auth->hasPermission('edit_customers')) {
        $result = $customer->delete($_GET['id']);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
        $action = 'list';
    } else {
        $error = 'Tidak memiliki permission untuk menghapus pelanggan';
    }
}

// Get data for different actions
if ($action === 'list') {
    $page = isset($_GET['page']) ? $_GET['page'] : 1;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
    $customers_data = $customer->getAll($page, 20, $search, $status);
} elseif ($action === 'edit' && isset($_GET['id'])) {
    $customer_data = $customer->getById($_GET['id']);
    if (!$customer_data) {
        $error = 'Pelanggan tidak ditemukan';
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
    <title>Manajemen Pelanggan - RT/RW Net</title>
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
        
        .customer-photo {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        #map {
            height: 300px;
            border-radius: 8px;
        }
        
        .form-floating {
            margin-bottom: 1rem;
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
                <h4 class="mb-0">Manajemen Pelanggan</h4>
                <small class="text-muted">Kelola data pelanggan RT/RW Net</small>
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
                <!-- Customer List -->
                <div class="content-card">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Daftar Pelanggan</h5>
                        <?php if ($auth->hasPermission('add_customers')): ?>
                            <a href="?action=add" class="btn btn-primary">
                                <i class="bi bi-plus-lg me-2"></i>Tambah Pelanggan
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-body">
                        <!-- Search and Filter -->
                        <form method="GET" class="row g-3 mb-4">
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Cari nama, kode, atau telepon..." 
                                       value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="status">
                                    <option value="">Semua Status</option>
                                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Aktif</option>
                                    <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                    <option value="terminated" <?= $status === 'terminated' ? 'selected' : '' ?>>Terminated</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-search me-2"></i>Cari
                                </button>
                            </div>
                            <div class="col-md-3">
                                <a href="customers.php" class="btn btn-outline-secondary w-100">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Reset
                                </a>
                            </div>
                        </form>
                        
                        <!-- Table -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Kode</th>
                                        <th>Nama</th>
                                        <th>Kontak</th>
                                        <th>Paket</th>
                                        <th>Status</th>
                                        <th>Tgl Daftar</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($customers_data['data'])): ?>
                                        <?php foreach ($customers_data['data'] as $cust): ?>
                                            <tr>
                                                <td>
                                                    <span class="fw-semibold"><?= htmlspecialchars($cust['customer_code']) ?></span>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold"><?= htmlspecialchars($cust['full_name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($cust['address']) ?></small>
                                                </td>
                                                <td>
                                                    <div><?= htmlspecialchars($cust['phone']) ?></div>
                                                    <?php if ($cust['email']): ?>
                                                        <small class="text-muted"><?= htmlspecialchars($cust['email']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($cust['package_name']): ?>
                                                        <span class="badge bg-info"><?= htmlspecialchars($cust['package_name']) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">Belum ada</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $cust['status'] === 'active' ? 'success' : ($cust['status'] === 'suspended' ? 'warning' : 'danger') ?>">
                                                        <?= ucfirst($cust['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small><?= date('d/m/Y', strtotime($cust['created_at'])) ?></small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="customer-detail.php?id=<?= $cust['id'] ?>" class="btn btn-outline-info" title="Lihat">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <?php if ($auth->hasPermission('edit_customers')): ?>
                                                            <a href="?action=edit&id=<?= $cust['id'] ?>" class="btn btn-outline-primary" title="Edit">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <a href="?action=delete&id=<?= $cust['id'] ?>" 
                                                               class="btn btn-outline-danger" title="Hapus"
                                                               onclick="return confirm('Yakin ingin menghapus pelanggan ini?')">
                                                                <i class="bi bi-trash"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <div class="text-muted">
                                                    <i class="bi bi-people fs-1 d-block mb-2"></i>
                                                    Belum ada data pelanggan
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($customers_data['total_pages'] > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $customers_data['total_pages']; $i++): ?>
                                        <li class="page-item <?= $i == $customers_data['page'] ? 'active' : '' ?>">
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
                            <?= $action === 'add' ? 'Tambah' : 'Edit' ?> Pelanggan
                        </h5>
                    </div>
                    
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <?php if ($action === 'edit'): ?>
                                <input type="hidden" name="id" value="<?= $customer_data['id'] ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <!-- Data Pribadi -->
                                <div class="col-lg-6">
                                    <h6 class="mb-3">Data Pribadi</h6>
                                    
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="full_name" name="full_name" 
                                               placeholder="Nama Lengkap" required
                                               value="<?= htmlspecialchars(isset($customer_data['full_name']) ? $customer_data['full_name'] : '') ?>">
                                        <label for="full_name">Nama Lengkap *</label>
                                    </div>
                                    
                                    <div class="form-floating">
                                        <input type="email" class="form-control" id="email" name="email" 
                                               placeholder="Email"
                                               value="<?= htmlspecialchars(isset($customer_data['email']) ? $customer_data['email'] : '') ?>">
                                        <label for="email">Email</label>
                                    </div>
                                    
                                    <div class="form-floating">
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               placeholder="Nomor Telepon" required
                                               value="<?= htmlspecialchars(isset($customer_data['phone']) ? $customer_data['phone'] : '') ?>">
                                        <label for="phone">Nomor Telepon *</label>
                                    </div>
                                    
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="ktp_number" name="ktp_number" 
                                               placeholder="Nomor KTP"
                                               value="<?= htmlspecialchars(isset($customer_data['ktp_number']) ? $customer_data['ktp_number'] : '') ?>">
                                        <label for="ktp_number">Nomor KTP</label>
                                    </div>
                                </div>
                                
                                <!-- Alamat -->
                                <div class="col-lg-6">
                                    <h6 class="mb-3">Alamat</h6>
                                    
                                    <div class="form-floating">
                                        <textarea class="form-control" id="address" name="address" 
                                                  placeholder="Alamat Lengkap" required style="height: 100px"><?= htmlspecialchars(isset($customer_data['address']) ? $customer_data['address'] : '') ?></textarea>
                                        <label for="address">Alamat Lengkap *</label>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="text" class="form-control" id="rt_rw" name="rt_rw" 
                                                       placeholder="RT/RW"
                                                       value="<?= htmlspecialchars(isset($customer_data['rt_rw']) ? $customer_data['rt_rw'] : '') ?>">
                                                <label for="rt_rw">RT/RW</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="text" class="form-control" id="kode_pos" name="kode_pos" 
                                                       placeholder="Kode Pos"
                                                       value="<?= htmlspecialchars(isset($customer_data['kode_pos']) ? $customer_data['kode_pos'] : '') ?>">
                                                <label for="kode_pos">Kode Pos</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="text" class="form-control" id="kelurahan" name="kelurahan" 
                                                       placeholder="Kelurahan"
                                                       value="<?= htmlspecialchars(isset($customer_data['kelurahan']) ? $customer_data['kelurahan'] : '') ?>">
                                                <label for="kelurahan">Kelurahan</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="text" class="form-control" id="kecamatan" name="kecamatan" 
                                                       placeholder="Kecamatan"
                                                       value="<?= htmlspecialchars(isset($customer_data['kecamatan']) ? $customer_data['kecamatan'] : '') ?>">
                                                <label for="kecamatan">Kecamatan</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="kota" name="kota" 
                                               placeholder="Kota"
                                               value="<?= htmlspecialchars(isset($customer_data['kota']) ? $customer_data['kota'] : '') ?>">
                                        <label for="kota">Kota</label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Koordinat dan Foto -->
                            <div class="row mt-4">
                                <div class="col-lg-6">
                                    <h6 class="mb-3">Koordinat Lokasi</h6>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="number" class="form-control" id="latitude" name="latitude" 
                                                       placeholder="Latitude" step="any"
                                                       value="<?= htmlspecialchars(isset($customer_data['latitude']) ? $customer_data['latitude'] : '') ?>">
                                                <label for="latitude">Latitude</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="number" class="form-control" id="longitude" name="longitude" 
                                                       placeholder="Longitude" step="any"
                                                       value="<?= htmlspecialchars(isset($customer_data['longitude']) ? $customer_data['longitude'] : '') ?>">
                                                <label for="longitude">Longitude</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <button type="button" class="btn btn-outline-primary" onclick="getCurrentLocation()">
                                            <i class="bi bi-geo-alt me-2"></i>Ambil Lokasi Saat Ini
                                        </button>
                                    </div>
                                    
                                    <div id="map"></div>
                                </div>
                                
                                <div class="col-lg-6">
                                    <h6 class="mb-3">Upload Foto</h6>
                                    
                                    <div class="mb-3">
                                        <label for="ktp_photo" class="form-label">Foto KTP</label>
                                        <input type="file" class="form-control" id="ktp_photo" name="ktp_photo" 
                                               accept="image/jpeg,image/jpg,image/png">
                                        <div class="form-text">Format: JPG, JPEG, PNG. Maksimal 5MB.</div>
                                        <?php if ($action === 'edit' && $customer_data['ktp_photo']): ?>
                                            <div class="mt-2">
                                                <img src="uploads/customers/<?= htmlspecialchars($customer_data['ktp_photo']) ?>" 
                                                     class="customer-photo" alt="KTP">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="house_photo" class="form-label">Foto Rumah</label>
                                        <input type="file" class="form-control" id="house_photo" name="house_photo" 
                                               accept="image/jpeg,image/jpg,image/png">
                                        <div class="form-text">Format: JPG, JPEG, PNG. Maksimal 5MB.</div>
                                        <?php if ($action === 'edit' && $customer_data['house_photo']): ?>
                                            <div class="mt-2">
                                                <img src="uploads/customers/<?= htmlspecialchars($customer_data['house_photo']) ?>" 
                                                     class="customer-photo" alt="Rumah">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="form-floating">
                                        <select class="form-select" id="status" name="status">
                                            <option value="active" <?= (isset($customer_data['status']) ? $customer_data['status'] : 'active') === 'active' ? 'selected' : '' ?>>Aktif</option>
                                                     <option value="suspended" <?= (isset($customer_data['status']) ? $customer_data['status'] : '') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                                     <option value="terminated" <?= (isset($customer_data['status']) ? $customer_data['status'] : '') === 'terminated' ? 'selected' : '' ?>>Terminated</option>
                                        </select>
                                        <label for="status">Status</label>
                                    </div>
                                    
                                    <div class="form-floating">
                                        <textarea class="form-control" id="notes" name="notes" 
                                                  placeholder="Catatan" style="height: 100px"><?= htmlspecialchars(isset($customer_data['notes']) ? $customer_data['notes'] : '') ?></textarea>
                                        <label for="notes">Catatan</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="bi bi-check-lg me-2"></i>
                                        <?= $action === 'add' ? 'Simpan' : 'Update' ?>
                                    </button>
                                    <a href="customers.php" class="btn btn-secondary">
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
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize map
        let map, marker;
        
        function initMap() {
            const lat = parseFloat(document.getElementById('latitude').value) || -6.2088;
            const lng = parseFloat(document.getElementById('longitude').value) || 106.8456;
            
            map = L.map('map').setView([lat, lng], 13);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            marker = L.marker([lat, lng], {draggable: true}).addTo(map);
            
            marker.on('dragend', function(e) {
                const position = marker.getLatLng();
                document.getElementById('latitude').value = position.lat.toFixed(6);
                document.getElementById('longitude').value = position.lng.toFixed(6);
            });
            
            map.on('click', function(e) {
                marker.setLatLng(e.latlng);
                document.getElementById('latitude').value = e.latlng.lat.toFixed(6);
                document.getElementById('longitude').value = e.latlng.lng.toFixed(6);
            });
        }
        
        function getCurrentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    
                    document.getElementById('latitude').value = lat.toFixed(6);
                    document.getElementById('longitude').value = lng.toFixed(6);
                    
                    if (map) {
                        map.setView([lat, lng], 15);
                        marker.setLatLng([lat, lng]);
                    }
                }, function(error) {
                    alert('Error getting location: ' + error.message);
                });
            } else {
                alert('Geolocation is not supported by this browser.');
            }
        }
        
        // Update map when coordinates change
        document.getElementById('latitude').addEventListener('change', updateMapFromCoordinates);
        document.getElementById('longitude').addEventListener('change', updateMapFromCoordinates);
        
        function updateMapFromCoordinates() {
            const lat = parseFloat(document.getElementById('latitude').value);
            const lng = parseFloat(document.getElementById('longitude').value);
            
            if (!isNaN(lat) && !isNaN(lng) && map) {
                map.setView([lat, lng], 15);
                marker.setLatLng([lat, lng]);
            }
        }
        
        // Initialize map when page loads
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($action === 'add' || $action === 'edit'): ?>
                initMap();
            <?php endif; ?>
        });
    </script>
</body>
</html>