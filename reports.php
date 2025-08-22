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
    <title>Laporan - RT/RW Net</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-wifi me-2"></i>RT/RW Net
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">Dashboard</a>
                <a class="nav-link" href="customers.php">Pelanggan</a>
                <a class="nav-link" href="packages.php">Paket</a>
                <a class="nav-link" href="invoices.php">Invoice</a>
                <a class="nav-link" href="payments.php">Pembayaran</a>
                <a class="nav-link" href="monitoring.php">Monitoring</a>
                <a class="nav-link active" href="reports.php">Laporan</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-bar me-2"></i>Menu Laporan</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="?action=dashboard" class="list-group-item list-group-item-action <?= $action === 'dashboard' ? 'active' : '' ?>">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <a href="?action=customers" class="list-group-item list-group-item-action <?= $action === 'customers' ? 'active' : '' ?>">
                            <i class="fas fa-users me-2"></i>Laporan Pelanggan
                        </a>
                        <a href="?action=revenue" class="list-group-item list-group-item-action <?= $action === 'revenue' ? 'active' : '' ?>">
                            <i class="fas fa-money-bill-wave me-2"></i>Laporan Pendapatan
                        </a>
                        <a href="?action=bandwidth" class="list-group-item list-group-item-action <?= $action === 'bandwidth' ? 'active' : '' ?>">
                            <i class="fas fa-chart-line me-2"></i>Laporan Bandwidth
                        </a>
                        <a href="?action=top_users" class="list-group-item list-group-item-action <?= $action === 'top_users' ? 'active' : '' ?>">
                            <i class="fas fa-trophy me-2"></i>Top Users
                        </a>
                        <a href="?action=packages" class="list-group-item list-group-item-action <?= $action === 'packages' ? 'active' : '' ?>">
                            <i class="fas fa-box me-2"></i>Laporan Paket
                        </a>
                        <a href="?action=payments" class="list-group-item list-group-item-action <?= $action === 'payments' ? 'active' : '' ?>">
                            <i class="fas fa-credit-card me-2"></i>Laporan Pembayaran
                        </a>
                        <a href="?action=invoices" class="list-group-item list-group-item-action <?= $action === 'invoices' ? 'active' : '' ?>">
                            <i class="fas fa-file-invoice me-2"></i>Laporan Invoice
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-9">
                <?php if ($action === 'dashboard'): ?>
                    <!-- Dashboard Summary -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4><?= number_format($summary['total_customers']) ?></h4>
                                            <p class="mb-0">Total Pelanggan</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-users fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4><?= number_format($summary['active_customers']) ?></h4>
                                            <p class="mb-0">Pelanggan Aktif</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-user-check fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4><?= number_format($summary['online_customers']) ?></h4>
                                            <p class="mb-0">Sedang Online</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-wifi fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4>Rp <?= number_format($summary['monthly_revenue']) ?></h4>
                                            <p class="mb-0">Pendapatan Bulan Ini</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-money-bill-wave fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Invoice Pending & Overdue</h5>
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
                            <div class="card">
                                <div class="card-header">
                                    <h5>Quick Actions</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="?action=revenue" class="btn btn-primary">
                                            <i class="fas fa-chart-line me-2"></i>Lihat Laporan Pendapatan
                                        </a>
                                        <a href="?action=top_users" class="btn btn-info">
                                            <i class="fas fa-trophy me-2"></i>Lihat Top Users
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- Report Content -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
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
                                    <i class="fas fa-download me-1"></i>Export CSV
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>