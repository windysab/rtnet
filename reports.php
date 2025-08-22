<?php
require_once 'classes/Auth.php';
require_once 'classes/Report.php';
require_once 'config/database.php';

// Check authentication
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$report = new Report();
$action = isset($_GET['action']) ? $_GET['action'] : 'dashboard';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$export = isset($_GET['export']) ? $_GET['export'] : false;

// Handle export
if ($export) {
    switch ($action) {
        case 'customers':
            $data = $report->getCustomerStatusReport($start_date, $end_date);
            $report->exportToCSV($data, 'customer_report_' . date('Y-m-d') . '.csv');
            break;
        case 'revenue':
            $data = $report->getRevenueReport($start_date, $end_date);
            $report->exportToCSV($data, 'revenue_report_' . date('Y-m-d') . '.csv');
            break;
        case 'bandwidth':
            $data = $report->getBandwidthReport($start_date, $end_date, 100);
            $report->exportToCSV($data, 'bandwidth_report_' . date('Y-m-d') . '.csv');
            break;
        case 'top_users':
            $data = $report->getTopUsers($start_date, $end_date, 50);
            $report->exportToCSV($data, 'top_users_report_' . date('Y-m-d') . '.csv');
            break;
    }
}

// Get data based on action
$data = [];
$summary = $report->getDashboardSummary();

switch ($action) {
    case 'customers':
        $data = $report->getCustomerStatusReport($start_date, $end_date);
        break;
    case 'revenue':
        $data = $report->getRevenueReport($start_date, $end_date);
        break;
    case 'bandwidth':
        $data = $report->getBandwidthReport($start_date, $end_date);
        break;
    case 'top_users':
        $data = $report->getTopUsers($start_date, $end_date);
        break;
    case 'packages':
        $data = $report->getPackageReport();
        break;
    case 'payments':
        $data = $report->getPaymentReport($start_date, $end_date);
        break;
    case 'invoices':
        $data = $report->getInvoiceReport($start_date, $end_date);
        break;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - RT/RW Net Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/dashboard.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <a class="nav-link active" href="reports.php">
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
                <h4 class="mb-0">Laporan</h4>
                <small class="text-muted">Kelola dan lihat laporan sistem</small>
            </div>
            
            <div class="dropdown">
                <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-2"></i>
                    Admin
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
                <div class="col-md-3">
                    <div class="content-card">
                        <div class="card-header bg-transparent border-0 pb-0">
                            <h5 class="card-title mb-0"><i class="bi bi-list-ul me-2"></i>Menu Laporan</h5>
                        </div>
                        <div class="list-group list-group-flush">
                            <a href="?action=dashboard" class="list-group-item list-group-item-action <?= $action === 'dashboard' ? 'active' : '' ?>">
                                <i class="bi bi-speedometer2 me-2"></i>Dashboard
                            </a>
                            <a href="?action=customers" class="list-group-item list-group-item-action <?= $action === 'customers' ? 'active' : '' ?>">
                                <i class="bi bi-people me-2"></i>Laporan Pelanggan
                            </a>
                            <a href="?action=revenue" class="list-group-item list-group-item-action <?= $action === 'revenue' ? 'active' : '' ?>">
                                <i class="bi bi-cash-stack me-2"></i>Laporan Pendapatan
                            </a>
                            <a href="?action=bandwidth" class="list-group-item list-group-item-action <?= $action === 'bandwidth' ? 'active' : '' ?>">
                                <i class="bi bi-graph-up me-2"></i>Laporan Bandwidth
                            </a>
                            <a href="?action=top_users" class="list-group-item list-group-item-action <?= $action === 'top_users' ? 'active' : '' ?>">
                                <i class="bi bi-trophy me-2"></i>Top Users
                            </a>
                            <a href="?action=packages" class="list-group-item list-group-item-action <?= $action === 'packages' ? 'active' : '' ?>">
                                <i class="bi bi-box me-2"></i>Laporan Paket
                            </a>
                            <a href="?action=payments" class="list-group-item list-group-item-action <?= $action === 'payments' ? 'active' : '' ?>">
                                <i class="bi bi-credit-card me-2"></i>Laporan Pembayaran
                            </a>
                            <a href="?action=invoices" class="list-group-item list-group-item-action <?= $action === 'invoices' ? 'active' : '' ?>">
                                <i class="bi bi-file-earmark-text me-2"></i>Laporan Invoice
                            </a>
                        </div>
                    </div>
                </div>
            
            <div class="col-md-9">
                <?php if ($action === 'dashboard'): ?>
                    <!-- Dashboard Summary -->
                    <div class="row mb-4">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="stats-card">
                                <div class="d-flex align-items-center">
                                    <div class="stats-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                        <i class="bi bi-people"></i>
                                    </div>
                                    <div class="ms-3">
                                        <div class="text-muted small">Total Pelanggan</div>
                                        <div class="h4 mb-0"><?= number_format($summary['total_customers']) ?></div>
                                        <small class="text-muted">Semua pelanggan</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="stats-card">
                                <div class="d-flex align-items-center">
                                    <div class="stats-icon" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                                        <i class="bi bi-person-check"></i>
                                    </div>
                                    <div class="ms-3">
                                        <div class="text-muted small">Pelanggan Aktif</div>
                                        <div class="h4 mb-0"><?= number_format($summary['active_customers']) ?></div>
                                        <small class="text-muted">Status aktif</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="stats-card">
                                <div class="d-flex align-items-center">
                                    <div class="stats-icon" style="background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);">
                                        <i class="bi bi-wifi"></i>
                                    </div>
                                    <div class="ms-3">
                                        <div class="text-muted small">Sedang Online</div>
                                        <div class="h4 mb-0"><?= number_format($summary['online_customers']) ?></div>
                                        <small class="text-muted">Terhubung sekarang</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="stats-card">
                                <div class="d-flex align-items-center">
                                    <div class="stats-icon" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
                                        <i class="bi bi-cash-stack"></i>
                                    </div>
                                    <div class="ms-3">
                                        <div class="text-muted small">Pendapatan Bulan Ini</div>
                                        <div class="h4 mb-0">Rp <?= number_format($summary['monthly_revenue']) ?></div>
                                        <small class="text-muted">Total revenue</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="content-card">
                                <div class="card-header d-flex align-items-center">
                                    <i class="bi bi-exclamation-triangle text-warning me-2"></i>
                                    <h5 class="mb-0">Invoice Pending & Overdue</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center">
                                        <div class="col-6">
                                            <h3 class="text-warning"><?= number_format($summary['pending_invoices']) ?></h3>
                                            <p>Pending</p>
                                        </div>
                                        <div class="col-6">
                                            <h3 class="text-danger"><?= number_format($summary['overdue_invoices']) ?></h3>
                                            <p>Overdue</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="content-card">
                                <div class="card-header d-flex align-items-center">
                                    <i class="bi bi-lightning text-primary me-2"></i>
                                    <h5 class="mb-0">Quick Actions</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="?action=revenue" class="btn btn-primary">
                                            <i class="bi bi-graph-up me-2"></i>Lihat Laporan Pendapatan
                                        </a>
                                        <a href="?action=top_users" class="btn btn-info">
                                            <i class="bi bi-trophy me-2"></i>Lihat Top Users
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- Report Content -->
                    <div class="content-card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-file-earmark-text text-primary me-2"></i>
                                <h5 class="mb-0">
                                    <?php
                                    $titles = [
                                        'customers' => 'Laporan Pelanggan',
                                        'revenue' => 'Laporan Pendapatan',
                                        'bandwidth' => 'Laporan Bandwidth',
                                        'top_users' => 'Top Users',
                                        'packages' => 'Laporan Paket',
                                        'payments' => 'Laporan Pembayaran',
                                        'invoices' => 'Laporan Invoice'
                                    ];
                                    echo isset($titles[$action]) ? $titles[$action] : 'Laporan';
                                    ?>
                                </h5>
                            </div>
                            <div>
                                <?php if (in_array($action, ['customers', 'revenue', 'bandwidth', 'top_users'])): ?>
                                    <form method="GET" class="d-inline-flex align-items-center me-2">
                                        <input type="hidden" name="action" value="<?= $action ?>">
                                        <input type="date" name="start_date" value="<?= $start_date ?>" class="form-control form-control-sm me-1">
                                        <input type="date" name="end_date" value="<?= $end_date ?>" class="form-control form-control-sm me-1">
                                        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                                    </form>
                                <?php endif; ?>
                                <a href="?action=<?= $action ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&export=1" class="btn btn-sm btn-success">
                                    <i class="bi bi-download me-1"></i>Export CSV
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($data)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Tidak ada data untuk ditampilkan</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <?php if ($action === 'customers'): ?>
                                                    <th>Status</th>
                                                    <th>Total</th>
                                                    <th>Online</th>
                                                    <th>Offline</th>
                                                <?php elseif ($action === 'revenue'): ?>
                                                    <th>Periode</th>
                                                    <th>Total Pembayaran</th>
                                                    <th>Total Pendapatan</th>
                                                    <th>Rata-rata</th>
                                                    <th>Pelanggan Unik</th>
                                                <?php elseif ($action === 'bandwidth' || $action === 'top_users'): ?>
                                                    <th>Nama</th>
                                                    <th>Username</th>
                                                    <th>Paket</th>
                                                    <th>Upload</th>
                                                    <th>Download</th>
                                                    <th>Total Usage</th>
                                                    <th>Session Time</th>
                                                <?php elseif ($action === 'packages'): ?>
                                                    <th>Nama Paket</th>
                                                    <th>Harga</th>
                                                    <th>Bandwidth</th>
                                                    <th>Total Pelanggan</th>
                                                    <th>Aktif</th>
                                                    <th>Suspended</th>
                                                    <th>Pendapatan Bulanan</th>
                                                <?php elseif ($action === 'payments'): ?>
                                                    <th>Metode Pembayaran</th>
                                                    <th>Total Pembayaran</th>
                                                    <th>Total Amount</th>
                                                    <th>Approved</th>
                                                    <th>Pending</th>
                                                    <th>Rejected</th>
                                                <?php elseif ($action === 'invoices'): ?>
                                                    <th>Status</th>
                                                    <th>Total Invoice</th>
                                                    <th>Total Amount</th>
                                                    <th>Rata-rata</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($data as $row): ?>
                                                <tr>
                                                    <?php if ($action === 'customers'): ?>
                                                        <td><span class="badge bg-<?= $row['status'] === 'active' ? 'success' : ($row['status'] === 'suspended' ? 'warning' : 'danger') ?>"><?= ucfirst($row['status']) ?></span></td>
                                                        <td><?= number_format($row['total']) ?></td>
                                                        <td><?= number_format($row['online_count']) ?></td>
                                                        <td><?= number_format($row['offline_count']) ?></td>
                                                    <?php elseif ($action === 'revenue'): ?>
                                                        <td><?= $row['period'] ?></td>
                                                        <td><?= number_format($row['total_payments']) ?></td>
                                                        <td>Rp <?= number_format($row['total_revenue']) ?></td>
                                                        <td>Rp <?= number_format($row['avg_payment']) ?></td>
                                                        <td><?= number_format($row['unique_customers']) ?></td>
                                                    <?php elseif ($action === 'bandwidth' || $action === 'top_users'): ?>
                                                        <td><?= htmlspecialchars($row['full_name']) ?></td>
                                                        <td><?= htmlspecialchars($row['username']) ?></td>
                                                        <td><?= htmlspecialchars($row['package_name']) ?></td>
                                                        <td><?= $report->formatBytes($row['total_upload']) ?></td>
                                                        <td><?= $report->formatBytes($row['total_download']) ?></td>
                                                        <td><?= $report->formatBytes($row['total_usage']) ?></td>
                                                        <td><?= $report->formatSessionTime($row['total_session_time']) ?></td>
                                                    <?php elseif ($action === 'packages'): ?>
                                                        <td><?= htmlspecialchars($row['name']) ?></td>
                                                        <td>Rp <?= number_format($row['price']) ?></td>
                                                        <td><?= $row['bandwidth_up'] ?>/<?= $row['bandwidth_down'] ?> Mbps</td>
                                                        <td><?= number_format($row['total_customers']) ?></td>
                                                        <td><?= number_format($row['active_customers']) ?></td>
                                                        <td><?= number_format($row['suspended_customers']) ?></td>
                                                        <td>Rp <?= number_format($row['monthly_revenue']) ?></td>
                                                    <?php elseif ($action === 'payments'): ?>
                                                        <td><?= ucfirst($row['payment_method']) ?></td>
                                                        <td><?= number_format($row['total_payments']) ?></td>
                                                        <td>Rp <?= number_format($row['total_amount']) ?></td>
                                                        <td><?= number_format($row['approved_count']) ?></td>
                                                        <td><?= number_format($row['pending_count']) ?></td>
                                                        <td><?= number_format($row['rejected_count']) ?></td>
                                                    <?php elseif ($action === 'invoices'): ?>
                                                        <td><span class="badge bg-<?= $row['status'] === 'paid' ? 'success' : ($row['status'] === 'pending' ? 'warning' : 'danger') ?>"><?= ucfirst($row['status']) ?></span></td>
                                                        <td><?= number_format($row['total_invoices']) ?></td>
                                                        <td>Rp <?= number_format($row['total_amount']) ?></td>
                                                        <td>Rp <?= number_format($row['avg_amount']) ?></td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto refresh reports every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>