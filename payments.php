<?php
/**
 * Halaman Manajemen Pembayaran - RT/RW Net
 * 
 * @author RT/RW Net System
 * @version 1.0
 */

require_once 'classes/Auth.php';
require_once 'classes/Payment.php';
require_once 'classes/Invoice.php';
require_once 'config/database.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('view_payments');

$current_admin = $auth->getCurrentAdmin();
$payment = new Payment();
$invoice = new Invoice();
$db = new Database();

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = '';
$error = '';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        if ($auth->hasPermission('create_payments')) {
            // Handle file upload
            $proof_file = null;
            if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === UPLOAD_ERR_OK) {
                $upload_result = $payment->uploadProof($_FILES['proof_file']);
                if ($upload_result['success']) {
                    $proof_file = $upload_result['filename'];
                } else {
                    $error = $upload_result['message'];
                }
            }
            
            if (!$error) {
                $_POST['proof_file'] = $proof_file;
                $_POST['created_by'] = $current_admin['id'];
                $result = $payment->recordPayment($_POST);
                if ($result['success']) {
                    $message = $result['message'];
                    $action = 'list';
                } else {
                    $error = $result['message'];
                }
            }
        } else {
            $error = 'Tidak memiliki permission untuk membuat pembayaran';
        }
    } elseif ($action === 'update') {
        if ($auth->hasPermission('edit_payments')) {
            $id = $_POST['id'];
            
            // Handle file upload
            if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === UPLOAD_ERR_OK) {
                $upload_result = $payment->uploadProof($_FILES['proof_file']);
                if ($upload_result['success']) {
                    $_POST['proof_file'] = $upload_result['filename'];
                } else {
                    $error = $upload_result['message'];
                }
            }
            
            if (!$error) {
                $result = $payment->updatePayment($id, $_POST);
                if ($result['success']) {
                    $message = $result['message'];
                    $action = 'list';
                } else {
                    $error = $result['message'];
                }
            }
        } else {
            $error = 'Tidak memiliki permission untuk edit pembayaran';
        }
    }
}

// Handle other actions
if ($action === 'approve' && isset($_GET['id'])) {
    if ($auth->hasPermission('approve_payments')) {
        $result = $payment->updateStatus($_GET['id'], 'approved', $current_admin['id']);
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

if ($action === 'reject' && isset($_GET['id'])) {
    if ($auth->hasPermission('approve_payments')) {
        $reason = isset($_GET['reason']) ? $_GET['reason'] : 'Ditolak oleh admin';
        $result = $payment->updateStatus($_GET['id'], 'rejected', $current_admin['id'], $reason);
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

if ($action === 'refund' && isset($_GET['id'])) {
    if ($auth->hasPermission('refund_payments')) {
        $amount = isset($_GET['amount']) ? $_GET['amount'] : 0;
        $reason = isset($_GET['reason']) ? $_GET['reason'] : 'Refund oleh admin';
        $result = $payment->processRefund($_GET['id'], $amount, $reason, $current_admin['id']);
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
        'method' => isset($_GET['method']) ? $_GET['method'] : '',
        'date_from' => isset($_GET['date_from']) ? $_GET['date_from'] : '',
        'date_to' => isset($_GET['date_to']) ? $_GET['date_to'] : ''
    ];
    $payments_data = $payment->getAllPayments($page, 20, $filters);
    $stats = $payment->getStatistics();
    $payment_methods = $payment->getPaymentMethods();
} elseif ($action === 'create') {
    $pending_invoices = $db->query(
        "SELECT i.*, c.name as customer_name, c.phone 
         FROM invoices i 
         JOIN customers c ON i.customer_id = c.id 
         WHERE i.status = 'pending' 
         ORDER BY i.due_date ASC"
    )->fetchAll();
    $payment_methods = $payment->getPaymentMethods();
} elseif ($action === 'edit' && isset($_GET['id'])) {
    $payment_data = $payment->getPaymentById($_GET['id']);
    if (!$payment_data) {
        $error = 'Pembayaran tidak ditemukan';
        $action = 'list';
    } else {
        $payment_methods = $payment->getPaymentMethods();
    }
} elseif ($action === 'view' && isset($_GET['id'])) {
    $payment_data = $payment->getPaymentById($_GET['id']);
    if (!$payment_data) {
        $error = 'Pembayaran tidak ditemukan';
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
    <title>Manajemen Pembayaran - RT/RW Net</title>
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
        
        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-refunded {
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
        
        .proof-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .proof-modal .modal-body img {
            width: 100%;
            height: auto;
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
                    <a class="nav-link active" href="payments.php">
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
                <h4 class="mb-0">Manajemen Pembayaran</h4>
                <small class="text-muted">Kelola pembayaran dan konfirmasi transaksi</small>
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
                            <div class="stat-value"><?= $stats['total_payments'] ?></div>
                            <div class="stat-label">Total Pembayaran</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-value"><?= $stats['approved_payments'] ?></div>
                            <div class="stat-label">Disetujui</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-value"><?= $stats['pending_payments'] ?></div>
                            <div class="stat-label">Menunggu Konfirmasi</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-value">Rp <?= number_format($stats['total_amount'], 0, ',', '.') ?></div>
                            <div class="stat-label">Total Nilai</div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment List -->
                <div class="content-card">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Daftar Pembayaran</h5>
                        <div>
                            <?php if ($auth->hasPermission('create_payments')): ?>
                                <a href="?action=create" class="btn btn-primary">
                                    <i class="bi bi-plus-lg me-2"></i>Catat Pembayaran
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <!-- Search and Filter -->
                        <form method="GET" class="row g-3 mb-4">
                            <div class="col-md-3">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Cari customer atau referensi..." 
                                       value="<?= htmlspecialchars($filters['search']) ?>">
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="status">
                                    <option value="">Semua Status</option>
                                    <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="approved" <?= $filters['status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
                                    <option value="rejected" <?= $filters['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                    <option value="refunded" <?= $filters['status'] === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="method">
                                    <option value="">Semua Metode</option>
                                    <?php foreach ($payment_methods as $method): ?>
                                        <option value="<?= $method['code'] ?>" <?= $filters['method'] === $method['code'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($method['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="date" class="form-control" name="date_from" 
                                       value="<?= htmlspecialchars($filters['date_from']) ?>" placeholder="Dari Tanggal">
                            </div>
                            <div class="col-md-1">
                                <input type="date" class="form-control" name="date_to" 
                                       value="<?= htmlspecialchars($filters['date_to']) ?>" placeholder="Sampai Tanggal">
                            </div>
                            <div class="col-md-1">
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                            <div class="col-md-1">
                                <a href="?" class="btn btn-outline-secondary w-100">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </a>
                            </div>
                        </form>
                        
                        <!-- Payment Table -->
                        <?php if (!empty($payments_data['data'])): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Referensi</th>
                                            <th>Customer</th>
                                            <th>Invoice</th>
                                            <th>Tanggal</th>
                                            <th>Metode</th>
                                            <th>Jumlah</th>
                                            <th>Status</th>
                                            <th>Bukti</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payments_data['data'] as $pay): ?>
                                            <?php
                                            $status_class = 'status-pending';
                                            if ($pay['status'] === 'approved') $status_class = 'status-approved';
                                            elseif ($pay['status'] === 'rejected') $status_class = 'status-rejected';
                                            elseif ($pay['status'] === 'refunded') $status_class = 'status-refunded';
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($pay['reference_number']) ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?= htmlspecialchars(isset($pay['notes']) ? $pay['notes'] : '') ?></small>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?= htmlspecialchars($pay['customer_name']) ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?= htmlspecialchars($pay['phone']) ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($pay['invoice_number']): ?>
                                                        <a href="invoices.php?action=view&id=<?= $pay['invoice_id'] ?>" class="text-decoration-none">
                                                            <?= htmlspecialchars($pay['invoice_number']) ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= date('d/m/Y H:i', strtotime($pay['payment_date'])) ?></td>
                                                <td><?= htmlspecialchars($pay['payment_method']) ?></td>
                                                <td>Rp <?= number_format($pay['amount'], 0, ',', '.') ?></td>
                                                <td>
                                                    <span class="status-badge <?= $status_class ?>">
                                                        <?= ucfirst($pay['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($pay['proof_file']): ?>
                                                        <img src="uploads/payments/<?= htmlspecialchars($pay['proof_file']) ?>" 
                                                             class="proof-image" 
                                                             data-bs-toggle="modal" 
                                                             data-bs-target="#proofModal" 
                                                             data-proof="uploads/payments/<?= htmlspecialchars($pay['proof_file']) ?>" 
                                                             alt="Bukti Bayar">
                                                    <?php else: ?>
                                                        <span class="text-muted">Tidak ada</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="?action=view&id=<?= $pay['id'] ?>" class="btn btn-sm btn-outline-info" title="Lihat">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        
                                                        <?php if ($auth->hasPermission('edit_payments') && $pay['status'] === 'pending'): ?>
                                                            <a href="?action=edit&id=<?= $pay['id'] ?>" class="btn btn-sm btn-outline-warning" title="Edit">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($auth->hasPermission('approve_payments') && $pay['status'] === 'pending'): ?>
                                                            <a href="?action=approve&id=<?= $pay['id'] ?>" class="btn btn-sm btn-outline-success" title="Setujui"
                                                               onclick="return confirm('Yakin ingin menyetujui pembayaran ini?')">
                                                                <i class="bi bi-check-lg"></i>
                                                            </a>
                                                            
                                                            <a href="?action=reject&id=<?= $pay['id'] ?>" class="btn btn-sm btn-outline-danger" title="Tolak"
                                                               onclick="return confirm('Yakin ingin menolak pembayaran ini?')">
                                                                <i class="bi bi-x-lg"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($auth->hasPermission('refund_payments') && $pay['status'] === 'approved'): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-warning" title="Refund" 
                                                                    data-bs-toggle="modal" data-bs-target="#refundModal" 
                                                                    data-payment-id="<?= $pay['id'] ?>" 
                                                                    data-amount="<?= $pay['amount'] ?>">
                                                                <i class="bi bi-arrow-counterclockwise"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($payments_data['total_pages'] > 1): ?>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-center">
                                        <?php for ($i = 1; $i <= $payments_data['total_pages']; $i++): ?>
                                            <li class="page-item <?= $i == $payments_data['page'] ? 'active' : '' ?>">
                                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($filters['search']) ?>&status=<?= urlencode($filters['status']) ?>&method=<?= urlencode($filters['method']) ?>&date_from=<?= urlencode($filters['date_from']) ?>&date_to=<?= urlencode($filters['date_to']) ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-credit-card text-muted" style="font-size: 4rem;"></i>
                                <h5 class="text-muted mt-3">Belum ada pembayaran</h5>
                                <p class="text-muted">Catat pembayaran pertama dari customer</p>
                                <?php if ($auth->hasPermission('create_payments')): ?>
                                    <a href="?action=create" class="btn btn-primary">
                                        <i class="bi bi-plus-lg me-2"></i>Catat Pembayaran
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            <?php elseif ($action === 'create'): ?>
                <!-- Create Payment Form -->
                <div class="content-card">
                    <div class="card-header bg-transparent border-0">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-plus-lg me-2"></i>Catat Pembayaran Baru
                        </h5>
                    </div>
                    
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="action" value="create">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="invoice_id" name="invoice_id">
                                            <option value="">Pilih Invoice (Opsional)</option>
                                            <?php foreach ($pending_invoices as $inv): ?>
                                                <option value="<?= $inv['id'] ?>" data-amount="<?= $inv['total_amount'] ?>">
                                                    <?= htmlspecialchars($inv['invoice_number']) ?> - <?= htmlspecialchars($inv['customer_name']) ?> 
                                                    (Rp <?= number_format($inv['total_amount'], 0, ',', '.') ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label for="invoice_id">Invoice</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="datetime-local" class="form-control" id="payment_date" name="payment_date" 
                                               value="<?= date('Y-m-d\TH:i') ?>" required>
                                        <label for="payment_date">Tanggal Pembayaran *</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="number" class="form-control" id="amount" name="amount" 
                                               step="0.01" min="0" required>
                                        <label for="amount">Jumlah Pembayaran *</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="payment_method" name="payment_method" required>
                                            <option value="">Pilih Metode Pembayaran</option>
                                            <?php foreach ($payment_methods as $method): ?>
                                                <option value="<?= $method['code'] ?>">
                                                    <?= htmlspecialchars($method['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label for="payment_method">Metode Pembayaran *</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="reference_number" name="reference_number">
                                        <label for="reference_number">Nomor Referensi</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="proof_file" class="form-label">Bukti Pembayaran</label>
                                        <input type="file" class="form-control" id="proof_file" name="proof_file" 
                                               accept="image/*,.pdf">
                                        <div class="form-text">Upload gambar atau PDF (max 5MB)</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="notes" name="notes" 
                                          style="height: 100px"></textarea>
                                <label for="notes">Catatan</label>
                            </div>
                            
                            <div class="row">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="bi bi-check-lg me-2"></i>Simpan Pembayaran
                                    </button>
                                    <a href="?" class="btn btn-secondary">
                                        <i class="bi bi-x-lg me-2"></i>Batal
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
            <?php elseif ($action === 'view' && isset($payment_data)): ?>
                <!-- View Payment -->
                <div class="content-card">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-credit-card me-2"></i>Detail Pembayaran
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
                                <h6>Informasi Pembayaran</h6>
                                <table class="table table-borderless">
                                    <tr>
                                        <td width="40%">Referensi:</td>
                                        <td><strong><?= htmlspecialchars($payment_data['reference_number']) ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td>Tanggal:</td>
                                        <td><?= date('d/m/Y H:i', strtotime($payment_data['payment_date'])) ?></td>
                                    </tr>
                                    <tr>
                                        <td>Metode:</td>
                                        <td><?= htmlspecialchars($payment_data['payment_method']) ?></td>
                                    </tr>
                                    <tr>
                                        <td>Jumlah:</td>
                                        <td><strong>Rp <?= number_format($payment_data['amount'], 0, ',', '.') ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td>Status:</td>
                                        <td>
                                            <?php
                                            $status_class = 'status-pending';
                                            if ($payment_data['status'] === 'approved') $status_class = 'status-approved';
                                            elseif ($payment_data['status'] === 'rejected') $status_class = 'status-rejected';
                                            elseif ($payment_data['status'] === 'refunded') $status_class = 'status-refunded';
                                            ?>
                                            <span class="status-badge <?= $status_class ?>">
                                                <?= ucfirst($payment_data['status']) ?>
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
                                        <td><strong><?= htmlspecialchars($payment_data['customer_name']) ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td>Telepon:</td>
                                        <td><?= htmlspecialchars($payment_data['phone']) ?></td>
                                    </tr>
                                    <tr>
                                        <td>Email:</td>
                                        <td><?= htmlspecialchars(isset($payment_data['email']) ? $payment_data['email'] : '-') ?></td>
                                    </tr>
                                    <?php if ($payment_data['invoice_number']): ?>
                                        <tr>
                                            <td>Invoice:</td>
                                            <td>
                                                <a href="invoices.php?action=view&id=<?= $payment_data['invoice_id'] ?>" class="text-decoration-none">
                                                    <?= htmlspecialchars($payment_data['invoice_number']) ?>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                        
                        <?php if ($payment_data['notes']): ?>
                            <hr>
                            <h6>Catatan</h6>
                            <p><?= nl2br(htmlspecialchars($payment_data['notes'])) ?></p>
                        <?php endif; ?>
                        
                        <?php if ($payment_data['proof_file']): ?>
                            <hr>
                            <h6>Bukti Pembayaran</h6>
                            <img src="uploads/payments/<?= htmlspecialchars($payment_data['proof_file']) ?>" 
                                 class="img-fluid" style="max-width: 400px; border-radius: 8px;" 
                                 alt="Bukti Pembayaran">
                        <?php endif; ?>
                        
                        <?php if ($payment_data['processed_by']): ?>
                            <hr>
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Diproses Oleh</h6>
                                    <p><?= htmlspecialchars($payment_data['processed_by_name']) ?></p>
                                    <small class="text-muted"><?= date('d/m/Y H:i', strtotime($payment_data['processed_at'])) ?></small>
                                </div>
                                <?php if ($payment_data['rejection_reason']): ?>
                                    <div class="col-md-6">
                                        <h6>Alasan Penolakan</h6>
                                        <p><?= nl2br(htmlspecialchars($payment_data['rejection_reason'])) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Proof Modal -->
    <div class="modal fade proof-modal" id="proofModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Bukti Pembayaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="" alt="Bukti Pembayaran" id="proofImage">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Refund Modal -->
    <div class="modal fade" id="refundModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Proses Refund</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="refundForm" method="GET">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="refund">
                        <input type="hidden" name="id" id="refundPaymentId">
                        
                        <div class="form-floating mb-3">
                            <input type="number" class="form-control" id="refundAmount" name="amount" 
                                   step="0.01" min="0" required>
                            <label for="refundAmount">Jumlah Refund</label>
                        </div>
                        
                        <div class="form-floating mb-3">
                            <textarea class="form-control" id="refundReason" name="reason" 
                                      style="height: 100px" required></textarea>
                            <label for="refundReason">Alasan Refund</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-arrow-counterclockwise me-2"></i>Proses Refund
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-fill amount when invoice is selected
            const invoiceSelect = document.getElementById('invoice_id');
            const amountInput = document.getElementById('amount');
            
            if (invoiceSelect && amountInput) {
                invoiceSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const amount = selectedOption.getAttribute('data-amount');
                    if (amount) {
                        amountInput.value = amount;
                    }
                });
            }
            
            // Proof modal
            const proofModal = document.getElementById('proofModal');
            if (proofModal) {
                proofModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const proofSrc = button.getAttribute('data-proof');
                    const proofImage = document.getElementById('proofImage');
                    proofImage.src = proofSrc;
                });
            }
            
            // Refund modal
            const refundModal = document.getElementById('refundModal');
            if (refundModal) {
                refundModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const paymentId = button.getAttribute('data-payment-id');
                    const amount = button.getAttribute('data-amount');
                    
                    document.getElementById('refundPaymentId').value = paymentId;
                    document.getElementById('refundAmount').value = amount;
                    document.getElementById('refundAmount').max = amount;
                });
            }
        });
    </script>
</body>
</html>