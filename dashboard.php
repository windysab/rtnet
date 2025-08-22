<?php
/**
 * Dashboard Admin - RT/RW Net
 * 
 * @author RT/RW Net System
 * @version 1.0
 */

require_once 'classes/Auth.php';
require_once 'config/database.php';

$auth = new Auth();
$auth->requireLogin();

$current_admin = $auth->getCurrentAdmin();
$db = new Database();

// Get statistics
try {
    // Total customers
    $stmt = $db->query("SELECT COUNT(*) as total FROM customers WHERE status = 'active'");
    $total_customers = $stmt->fetch()['total'];
    
    // Total revenue this month
    $stmt = $db->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE MONTH(payment_date) = MONTH(CURRENT_DATE()) AND YEAR(payment_date) = YEAR(CURRENT_DATE())");
    $monthly_revenue = $stmt->fetch()['total'];
    
    // Pending invoices
    $stmt = $db->query("SELECT COUNT(*) as total FROM invoices WHERE status = 'unpaid' AND due_date >= CURRENT_DATE()");
    $pending_invoices = $stmt->fetch()['total'];
    
    // Overdue invoices
    $stmt = $db->query("SELECT COUNT(*) as total FROM invoices WHERE status = 'unpaid' AND due_date < CURRENT_DATE()");
    $overdue_invoices = $stmt->fetch()['total'];
    
    // Recent customers
    $stmt = $db->query("SELECT c.*, p.name as package_name FROM customers c LEFT JOIN subscriptions s ON c.id = s.customer_id LEFT JOIN packages p ON s.package_id = p.id ORDER BY c.created_at DESC LIMIT 5");
    $recent_customers = $stmt->fetchAll();
    
    // Recent payments
    $stmt = $db->query("SELECT p.*, c.full_name as customer_name, i.invoice_number FROM payments p JOIN invoices i ON p.invoice_id = i.id JOIN customers c ON i.customer_id = c.id ORDER BY p.payment_date DESC LIMIT 5");
    $recent_payments = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error_message = "Error loading dashboard data: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - RT/RW Net Management</title>
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
                    <a class="nav-link active" href="dashboard.php">
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
                <h4 class="mb-0">Dashboard</h4>
                <small class="text-muted">Selamat datang, <?= htmlspecialchars($current_admin['full_name']) ?></small>
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
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stats-card">
                        <div class="d-flex align-items-center">
                            <div class="stats-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <i class="bi bi-people"></i>
                            </div>
                            <div class="ms-3">
                                <div class="text-muted small">Total Pelanggan</div>
                                <div class="h4 mb-0"><?= number_format(isset($total_customers) ? $total_customers : 0) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stats-card">
                        <div class="d-flex align-items-center">
                            <div class="stats-icon" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                                <i class="bi bi-currency-dollar"></i>
                            </div>
                            <div class="ms-3">
                                <div class="text-muted small">Pendapatan Bulan Ini</div>
                                <div class="h4 mb-0">Rp <?= number_format(isset($monthly_revenue) ? $monthly_revenue : 0, 0, ',', '.') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stats-card">
                        <div class="d-flex align-items-center">
                            <div class="stats-icon" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
                                <i class="bi bi-clock"></i>
                            </div>
                            <div class="ms-3">
                                <div class="text-muted small">Tagihan Pending</div>
                                <div class="h4 mb-0"><?= number_format(isset($pending_invoices) ? $pending_invoices : 0) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stats-card">
                        <div class="d-flex align-items-center">
                            <div class="stats-icon" style="background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);">
                                <i class="bi bi-exclamation-triangle"></i>
                            </div>
                            <div class="ms-3">
                                <div class="text-muted small">Tagihan Overdue</div>
                                <div class="h4 mb-0"><?= number_format(isset($overdue_invoices) ? $overdue_invoices : 0) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Data -->
            <div class="row">
                <!-- Recent Customers -->
                <div class="col-lg-6 mb-4">
                    <div class="content-card">
                        <div class="card-header bg-transparent border-0 pb-0">
                            <h5 class="card-title mb-0">Pelanggan Terbaru</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recent_customers)): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Nama</th>
                                                <th>Paket</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_customers as $customer): ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-semibold"><?= htmlspecialchars($customer['full_name']) ?></div>
                                                        <small class="text-muted"><?= htmlspecialchars($customer['phone']) ?></small>
                                                    </td>
                                                    <td><?= htmlspecialchars(isset($customer['package_name']) ? $customer['package_name'] : 'Belum ada') ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= $customer['status'] === 'active' ? 'success' : 'warning' ?>">
                                                            <?= ucfirst($customer['status']) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center py-3">Belum ada data pelanggan</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Payments -->
                <div class="col-lg-6 mb-4">
                    <div class="content-card">
                        <div class="card-header bg-transparent border-0 pb-0">
                            <h5 class="card-title mb-0">Pembayaran Terbaru</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recent_payments)): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Pelanggan</th>
                                                <th>Jumlah</th>
                                                <th>Tanggal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_payments as $payment): ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-semibold"><?= htmlspecialchars($payment['customer_name']) ?></div>
                                                        <small class="text-muted"><?= htmlspecialchars($payment['invoice_number']) ?></small>
                                                    </td>
                                                    <td class="fw-semibold text-success">Rp <?= number_format($payment['amount'], 0, ',', '.') ?></td>
                                                    <td>
                                                        <small><?= date('d/m/Y H:i', strtotime($payment['payment_date'])) ?></small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center py-3">Belum ada data pembayaran</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto refresh dashboard every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);
        
        // Session timeout warning
        let sessionTimeout = <?= $auth->getSessionTimeoutRemaining() ?>;
        
        if (sessionTimeout > 0) {
            setTimeout(function() {
                if (confirm('Sesi Anda akan berakhir dalam 5 menit. Klik OK untuk memperpanjang sesi.')) {
                    location.reload();
                }
            }, (sessionTimeout - 300) * 1000); // 5 minutes before timeout
        }
    </script>
</body>
</html>