<?php
/**
 * Halaman Manajemen Tagihan - RT/RW Net
 * 
 * @author RT/RW Net System
 * @version 1.0
 */

require_once 'classes/Auth.php';
require_once 'classes/Invoice.php';
require_once 'config/database.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('view_invoices');

$current_admin = $auth->getCurrentAdmin();
$invoice = new Invoice();
$db = new Database();

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = '';
$error = '';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        if ($auth->hasPermission('create_invoices')) {
            $result = $invoice->createInvoice($_POST);
            if ($result['success']) {
                $message = $result['message'];
                $action = 'list';
            } else {
                $error = $result['message'];
            }
        } else {
            $error = 'Tidak memiliki permission untuk membuat invoice';
        }
    } elseif ($action === 'update') {
        if ($auth->hasPermission('edit_invoices')) {
            $id = $_POST['id'];
            $result = $invoice->updateInvoice($id, $_POST);
            if ($result['success']) {
                $message = $result['message'];
                $action = 'list';
            } else {
                $error = $result['message'];
            }
        } else {
            $error = 'Tidak memiliki permission untuk edit invoice';
        }
    } elseif ($action === 'generate_monthly') {
        if ($auth->hasPermission('create_invoices')) {
            $month = isset($_POST['month']) ? $_POST['month'] : date('m');
        $year = isset($_POST['year']) ? $_POST['year'] : date('Y');
            $result = $invoice->generateMonthlyInvoices($month, $year);
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['message'];
            }
            $action = 'list';
        } else {
            $error = 'Tidak memiliki permission untuk generate invoice';
        }
    }
}

// Handle other actions
if ($action === 'mark_paid' && isset($_GET['id'])) {
    if ($auth->hasPermission('edit_invoices')) {
        $result = $invoice->markAsPaid($_GET['id']);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
        $action = 'list';
    } else {
        $error = 'Tidak memiliki permission';
    }
}

if ($action === 'cancel' && isset($_GET['id'])) {
    if ($auth->hasPermission('edit_invoices')) {
        $reason = isset($_GET['reason']) ? $_GET['reason'] : 'Dibatalkan oleh admin';
        $result = $invoice->cancelInvoice($_GET['id'], $reason);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
        $action = 'list';
    } else {
        $error = 'Tidak memiliki permission';
    }
}

if ($action === 'send_reminder' && isset($_GET['id'])) {
    if ($auth->hasPermission('edit_invoices')) {
        $result = $invoice->sendReminder($_GET['id']);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
        $action = 'list';
    } else {
        $error = 'Tidak memiliki permission';
    }
}

// Get data for different actions
if ($action === 'list') {
    $page = isset($_GET['page']) ? $_GET['page'] : 1;
    $filters = [
        'search' => isset($_GET['search']) ? $_GET['search'] : '',
        'status' => isset($_GET['status']) ? $_GET['status'] : '',
        'date_from' => isset($_GET['date_from']) ? $_GET['date_from'] : '',
        'date_to' => isset($_GET['date_to']) ? $_GET['date_to'] : ''
    ];
    $invoices_data = $invoice->getAllInvoices($page, 20, $filters);
    $stats = $invoice->getStatistics();
} elseif ($action === 'create') {
    $customers = $db->select('SELECT * FROM customers WHERE status = "active"');
    $packages = $db->select('SELECT * FROM packages WHERE is_active = 1');
} elseif ($action === 'edit' && isset($_GET['id'])) {
    $invoice_data = $invoice->getInvoiceById($_GET['id']);
    if (!$invoice_data) {
        $error = 'Invoice tidak ditemukan';
        $action = 'list';
    }
} elseif ($action === 'view' && isset($_GET['id'])) {
    $invoice_data = $invoice->getInvoiceById($_GET['id']);
    if (!$invoice_data) {
        $error = 'Invoice tidak ditemukan';
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
    <title>Manajemen Tagihan - RT/RW Net</title>
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
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-paid {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-overdue {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-cancelled {
            background-color: #e2e3e5;
            color: #6c757d;
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        
        .action-buttons .btn {
            margin-right: 0.25rem;
            margin-bottom: 0.25rem;
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
                    <a class="nav-link active" href="invoices.php">
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
                <h4 class="mb-0">Manajemen Tagihan</h4>
                <small class="text-muted">Kelola tagihan dan billing pelanggan</small>
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
                            <div class="stat-value"><?= $stats['total_invoices'] ?></div>
                            <div class="stat-label">Total Tagihan</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-value"><?= $stats['paid_invoices'] ?></div>
                            <div class="stat-label">Sudah Dibayar</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-value"><?= $stats['pending_invoices'] ?></div>
                            <div class="stat-label">Belum Dibayar</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-value"><?= $stats['overdue_invoices'] ?></div>
                            <div class="stat-label">Terlambat</div>
                        </div>
                    </div>
                </div>
                
                <!-- Invoice List -->
                <div class="content-card">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Daftar Tagihan</h5>
                        <div>
                            <?php if ($auth->hasPermission('create_invoices')): ?>
                                <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#generateModal">
                                    <i class="bi bi-calendar-plus me-2"></i>Generate Bulanan
                                </button>
                                <a href="?action=create" class="btn btn-primary">
                                    <i class="bi bi-plus-lg me-2"></i>Buat Tagihan
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <!-- Search and Filter -->
                        <form method="GET" class="row g-3 mb-4">
                            <div class="col-md-3">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Cari customer atau nomor invoice..." 
                                       value="<?= htmlspecialchars($filters['search']) ?>">
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="status">
                                    <option value="">Semua Status</option>
                                    <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="paid" <?= $filters['status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                                    <option value="overdue" <?= $filters['status'] === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                                    <option value="cancelled" <?= $filters['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="date" class="form-control" name="date_from" 
                                       value="<?= htmlspecialchars($filters['date_from']) ?>" placeholder="Dari Tanggal">
                            </div>
                            <div class="col-md-2">
                                <input type="date" class="form-control" name="date_to" 
                                       value="<?= htmlspecialchars($filters['date_to']) ?>" placeholder="Sampai Tanggal">
                            </div>
                            <div class="col-md-1">
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                            <div class="col-md-2">
                                <a href="?" class="btn btn-outline-secondary w-100">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Reset
                                </a>
                            </div>
                        </form>
                        
                        <!-- Invoice Table -->
                        <?php if (!empty($invoices_data['data'])): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Invoice</th>
                                            <th>Customer</th>
                                            <th>Paket</th>
                                            <th>Tanggal</th>
                                            <th>Jatuh Tempo</th>
                                            <th>Jumlah</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invoices_data['data'] as $inv): ?>
                                            <?php
                                            $status_class = 'status-pending';
                                            if ($inv['status'] === 'paid') $status_class = 'status-paid';
                                            elseif ($inv['status'] === 'cancelled') $status_class = 'status-cancelled';
                                            elseif ($inv['status'] === 'pending' && $inv['due_date'] < date('Y-m-d')) $status_class = 'status-overdue';
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($inv['invoice_number']) ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?= htmlspecialchars($inv['description']) ?></small>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?= htmlspecialchars($inv['customer_name']) ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?= htmlspecialchars($inv['phone']) ?></small>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars(isset($inv['package_name']) ? $inv['package_name'] : '-') ?></td>
                                                <td><?= date('d/m/Y', strtotime($inv['invoice_date'])) ?></td>
                                                <td><?= date('d/m/Y', strtotime($inv['due_date'])) ?></td>
                                                <td>Rp <?= number_format($inv['total_amount'], 0, ',', '.') ?></td>
                                                <td>
                                                    <span class="status-badge <?= $status_class ?>">
                                                        <?= ucfirst($inv['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="?action=view&id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-info" title="Lihat">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        
                                                        <?php if ($auth->hasPermission('edit_invoices') && $inv['status'] !== 'paid'): ?>
                                                            <a href="?action=edit&id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-warning" title="Edit">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($auth->hasPermission('edit_invoices') && $inv['status'] === 'pending'): ?>
                                                            <a href="?action=mark_paid&id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-success" title="Tandai Lunas"
                                                               onclick="return confirm('Yakin ingin menandai invoice ini sebagai lunas?')">
                                                                <i class="bi bi-check-lg"></i>
                                                            </a>
                                                            
                                                            <a href="?action=send_reminder&id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-primary" title="Kirim Reminder">
                                                                <i class="bi bi-bell"></i>
                                                            </a>
                                                            
                                                            <a href="?action=cancel&id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-danger" title="Batalkan"
                                                               onclick="return confirm('Yakin ingin membatalkan invoice ini?')">
                                                                <i class="bi bi-x-lg"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($invoices_data['total_pages'] > 1): ?>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-center">
                                        <?php for ($i = 1; $i <= $invoices_data['total_pages']; $i++): ?>
                                            <li class="page-item <?= $i == $invoices_data['page'] ? 'active' : '' ?>">
                                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($filters['search']) ?>&status=<?= urlencode($filters['status']) ?>&date_from=<?= urlencode($filters['date_from']) ?>&date_to=<?= urlencode($filters['date_to']) ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-receipt text-muted" style="font-size: 4rem;"></i>
                                <h5 class="text-muted mt-3">Belum ada tagihan</h5>
                                <p class="text-muted">Buat tagihan pertama atau generate tagihan bulanan</p>
                                <?php if ($auth->hasPermission('create_invoices')): ?>
                                    <a href="?action=create" class="btn btn-primary">
                                        <i class="bi bi-plus-lg me-2"></i>Buat Tagihan
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            <?php elseif ($action === 'create'): ?>
                <!-- Create Invoice Form -->
                <div class="content-card">
                    <div class="card-header bg-transparent border-0">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-plus-lg me-2"></i>Buat Tagihan Baru
                        </h5>
                    </div>
                    
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="action" value="create">
                            
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
                                        <input type="date" class="form-control" id="due_date" name="due_date" 
                                               value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required>
                                        <label for="due_date">Jatuh Tempo *</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="number" class="form-control" id="amount" name="amount" 
                                               step="0.01" min="0" required>
                                        <label for="amount">Jumlah Tagihan *</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="number" class="form-control" id="tax_amount" name="tax_amount" 
                                               step="0.01" min="0" value="0">
                                        <label for="tax_amount">Pajak</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="description" name="description" 
                                          style="height: 100px" required></textarea>
                                <label for="description">Deskripsi *</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="notes" name="notes" 
                                          style="height: 80px"></textarea>
                                <label for="notes">Catatan</label>
                            </div>
                            
                            <div class="row">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="bi bi-check-lg me-2"></i>Simpan Tagihan
                                    </button>
                                    <a href="?" class="btn btn-secondary">
                                        <i class="bi bi-x-lg me-2"></i>Batal
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
            <?php elseif ($action === 'view' && isset($invoice_data)): ?>
                <!-- View Invoice -->
                <div class="content-card">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-receipt me-2"></i>Detail Tagihan
                        </h5>
                        <div>
                            <a href="?" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Kembali
                            </a>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Informasi Tagihan</h6>
                                <table class="table table-borderless">
                                    <tr>
                                        <td width="40%">Nomor Invoice:</td>
                                        <td><strong><?= htmlspecialchars($invoice_data['invoice_number']) ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td>Tanggal:</td>
                                        <td><?= date('d/m/Y', strtotime($invoice_data['invoice_date'])) ?></td>
                                    </tr>
                                    <tr>
                                        <td>Jatuh Tempo:</td>
                                        <td><?= date('d/m/Y', strtotime($invoice_data['due_date'])) ?></td>
                                    </tr>
                                    <tr>
                                        <td>Status:</td>
                                        <td>
                                            <?php
                                            $status_class = 'status-pending';
                                            if ($invoice_data['status'] === 'paid') $status_class = 'status-paid';
                                            elseif ($invoice_data['status'] === 'cancelled') $status_class = 'status-cancelled';
                                            elseif ($invoice_data['status'] === 'pending' && $invoice_data['due_date'] < date('Y-m-d')) $status_class = 'status-overdue';
                                            ?>
                                            <span class="status-badge <?= $status_class ?>">
                                                <?= ucfirst($invoice_data['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            
                            <div class="col-md-6">
                                <h6>Informasi Customer</h6>
                                <table class="table table-borderless">
                                    <tr>
                                        <td width="40%">Nama:</td>
                                        <td><strong><?= htmlspecialchars($invoice_data['customer_name']) ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td>Telepon:</td>
                                        <td><?= htmlspecialchars($invoice_data['phone']) ?></td>
                                    </tr>
                                    <tr>
                                        <td>Email:</td>
                                        <td><?= htmlspecialchars(isset($invoice_data['email']) ? $invoice_data['email'] : '-') ?></td>
                                    </tr>
                                    <tr>
                                        <td>Paket:</td>
                                        <td><?= htmlspecialchars(isset($invoice_data['package_name']) ? $invoice_data['package_name'] : '-') ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <h6>Deskripsi</h6>
                                <p><?= nl2br(htmlspecialchars($invoice_data['description'])) ?></p>
                                
                                <?php if ($invoice_data['notes']): ?>
                                    <h6>Catatan</h6>
                                    <p><?= nl2br(htmlspecialchars($invoice_data['notes'])) ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6>Rincian Pembayaran</h6>
                                        <table class="table table-borderless table-sm">
                                            <tr>
                                                <td>Subtotal:</td>
                                                <td class="text-end">Rp <?= number_format($invoice_data['amount'], 0, ',', '.') ?></td>
                                            </tr>
                                            <tr>
                                                <td>Pajak:</td>
                                                <td class="text-end">Rp <?= number_format($invoice_data['tax_amount'], 0, ',', '.') ?></td>
                                            </tr>
                                            <tr class="border-top">
                                                <td><strong>Total:</strong></td>
                                                <td class="text-end"><strong>Rp <?= number_format($invoice_data['total_amount'], 0, ',', '.') ?></strong></td>
                                            </tr>
                                            <?php if ($invoice_data['paid_amount'] > 0): ?>
                                                <tr>
                                                    <td>Dibayar:</td>
                                                    <td class="text-end text-success">Rp <?= number_format($invoice_data['paid_amount'], 0, ',', '.') ?></td>
                                                </tr>
                                                <tr>
                                                    <td>Sisa:</td>
                                                    <td class="text-end">Rp <?= number_format($invoice_data['total_amount'] - $invoice_data['paid_amount'], 0, ',', '.') ?></td>
                                                </tr>
                                            <?php endif; ?>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Generate Monthly Modal -->
    <div class="modal fade" id="generateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Generate Tagihan Bulanan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="generate_monthly">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="month" name="month" required>
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                            <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>" 
                                                    <?= $m == date('n') ? 'selected' : '' ?>>
                                                <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                    <label for="month">Bulan</label>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="year" name="year" required>
                                        <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                                            <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>>
                                                <?= $y ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                    <label for="year">Tahun</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Sistem akan generate tagihan untuk semua pelanggan aktif yang belum memiliki tagihan untuk bulan/tahun yang dipilih.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-calendar-plus me-2"></i>Generate Tagihan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto calculate total amount
        document.addEventListener('DOMContentLoaded', function() {
            const amountInput = document.getElementById('amount');
            const taxInput = document.getElementById('tax_amount');
            
            if (amountInput && taxInput) {
                function calculateTotal() {
                    const amount = parseFloat(amountInput.value) || 0;
                    const tax = parseFloat(taxInput.value) || 0;
                    const total = amount + tax;
                    
                    // You can add a total display field here if needed
                }
                
                amountInput.addEventListener('input', calculateTotal);
                taxInput.addEventListener('input', calculateTotal);
            }
        });
    </script>
</body>
</html>