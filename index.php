<?php
require_once 'classes/Auth.php';
require_once 'classes/Report.php';
require_once 'classes/Customer.php';
require_once 'classes/Invoice.php';
require_once 'classes/Payment.php';
require_once 'classes/Monitoring.php';
require_once 'config/database.php';

// Check authentication
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$report = new Report();
$customer = new Customer();
$invoice = new Invoice();
$payment = new Payment();
$monitoring = new Monitoring();

// Get dashboard data
$summary = $report->getDashboardSummary();
$recentCustomers = $customer->getAll(1, 5)['data'];
$overdueInvoices = $invoice->getOverdueInvoices(5);
$recentPayments = $payment->getAllPayments(1, 5)['data'];
// Get online customers from database directly since getOnlineCustomers method doesn't exist
$onlineCustomersQuery = $customer->getAll(1, 5, '', 'active');
$onlineCustomers = array_filter($onlineCustomersQuery['data'], function($customer) {
    return isset($customer['online_status']) && $customer['online_status'] === 'online';
});
$topUsers = $report->getTopUsers(date('Y-m-01'), date('Y-m-t'), 5);

// Get monthly revenue chart data
$revenueData = $report->getRevenueReport(date('Y-01-01'), date('Y-12-31'), 'month');
$revenueLabels = [];
$revenueValues = [];
foreach ($revenueData as $data) {
    $revenueLabels[] = date('M Y', strtotime($data['period'] . '-01'));
    $revenueValues[] = $data['total_revenue'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - RT/RW Net</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .card-stats {
            transition: transform 0.2s;
        }
        .card-stats:hover {
            transform: translateY(-5px);
        }
        .quick-action {
            transition: all 0.2s;
        }
        .quick-action:hover {
            transform: scale(1.05);
        }
        .status-online {
            color: #28a745;
        }
        .status-offline {
            color: #6c757d;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-wifi me-2"></i>RT/RW Net
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <a class="nav-link active" href="index.php">Dashboard</a>
                    <a class="nav-link" href="customers.php">Pelanggan</a>
                    <a class="nav-link" href="packages.php">Paket</a>
                    <a class="nav-link" href="invoices.php">Invoice</a>
                    <a class="nav-link" href="payments.php">Pembayaran</a>
                    <a class="nav-link" href="mikrotik.php">MikroTik</a>
                    <a class="nav-link" href="monitoring.php">Monitoring</a>
                    <a class="nav-link" href="reports.php">Laporan</a>
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> Admin
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Pengaturan</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Welcome Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-primary border-0 shadow-sm">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-tachometer-alt fa-2x me-3"></i>
                        <div>
                            <h4 class="alert-heading mb-1">Selamat Datang di Dashboard RT/RW Net</h4>
                            <p class="mb-0">Kelola jaringan internet RT/RW Anda dengan mudah dan efisien</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card card-stats bg-primary text-white shadow">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3 class="mb-1"><?= number_format($summary['total_customers']) ?></h3>
                                <p class="mb-0">Total Pelanggan</p>
                                <small class="opacity-75">
                                    <i class="fas fa-user-check me-1"></i><?= number_format($summary['active_customers']) ?> Aktif
                                </small>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-users fa-3x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card card-stats bg-success text-white shadow">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3 class="mb-1"><?= number_format($summary['online_customers']) ?></h3>
                                <p class="mb-0">Sedang Online</p>
                                <small class="opacity-75">
                                    <i class="fas fa-wifi me-1"></i><?= $summary['active_customers'] > 0 ? round(($summary['online_customers']/$summary['active_customers'])*100, 1) : 0 ?>% dari aktif
                                </small>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-wifi fa-3x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card card-stats bg-warning text-white shadow">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3 class="mb-1">Rp <?= number_format($summary['monthly_revenue']/1000000, 1) ?>M</h3>
                                <p class="mb-0">Pendapatan Bulan Ini</p>
                                <small class="opacity-75">
                                    <i class="fas fa-calendar me-1"></i><?= date('F Y') ?>
                                </small>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-money-bill-wave fa-3x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card card-stats bg-danger text-white shadow">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3 class="mb-1"><?= number_format($summary['overdue_invoices']) ?></h3>
                                <p class="mb-0">Invoice Overdue</p>
                                <small class="opacity-75">
                                    <i class="fas fa-exclamation-triangle me-1"></i><?= number_format($summary['pending_invoices']) ?> Pending
                                </small>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-file-invoice fa-3x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2 col-6 mb-3">
                                <a href="customers.php?action=add" class="btn btn-outline-primary w-100 quick-action">
                                    <i class="fas fa-user-plus fa-2x mb-2"></i><br>
                                    <small>Tambah Pelanggan</small>
                                </a>
                            </div>
                            <div class="col-md-2 col-6 mb-3">
                                <a href="invoices.php?action=generate" class="btn btn-outline-success w-100 quick-action">
                                    <i class="fas fa-file-invoice fa-2x mb-2"></i><br>
                                    <small>Generate Invoice</small>
                                </a>
                            </div>
                            <div class="col-md-2 col-6 mb-3">
                                <a href="payments.php" class="btn btn-outline-info w-100 quick-action">
                                    <i class="fas fa-credit-card fa-2x mb-2"></i><br>
                                    <small>Cek Pembayaran</small>
                                </a>
                            </div>
                            <div class="col-md-2 col-6 mb-3">
                                <a href="mikrotik.php" class="btn btn-outline-warning w-100 quick-action">
                                    <i class="fas fa-router fa-2x mb-2"></i><br>
                                    <small>MikroTik</small>
                                </a>
                            </div>
                            <div class="col-md-2 col-6 mb-3">
                                <a href="monitoring.php" class="btn btn-outline-secondary w-100 quick-action">
                                    <i class="fas fa-chart-line fa-2x mb-2"></i><br>
                                    <small>Monitoring</small>
                                </a>
                            </div>
                            <div class="col-md-2 col-6 mb-3">
                                <a href="reports.php" class="btn btn-outline-dark w-100 quick-action">
                                    <i class="fas fa-chart-bar fa-2x mb-2"></i><br>
                                    <small>Laporan</small>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Revenue Chart -->
            <div class="col-xl-8 mb-4">
                <div class="card shadow">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Grafik Pendapatan Tahunan</h5>
                        <small class="text-muted"><?= date('Y') ?></small>
                    </div>
                    <div class="card-body">
                        <canvas id="revenueChart" height="100"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Online Customers -->
            <div class="col-xl-4 mb-4">
                <div class="card shadow">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-wifi me-2"></i>Pelanggan Online</h5>
                        <span class="badge bg-success"><?= count($onlineCustomers) ?></span>
                    </div>
                    <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                        <?php if (empty($onlineCustomers)): ?>
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-wifi fa-2x mb-2"></i>
                                <p class="mb-0">Tidak ada pelanggan online</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($onlineCustomers as $customer): ?>
                                <div class="d-flex align-items-center mb-2 p-2 bg-light rounded">
                                    <div class="me-2">
                                        <i class="fas fa-circle status-online"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold"><?= htmlspecialchars($customer['full_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($customer['username']) ?></small>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-success">Online</small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <div class="text-center mt-3">
                            <a href="monitoring.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Customers -->
            <div class="col-xl-6 mb-4">
                <div class="card shadow">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-users me-2"></i>Pelanggan Terbaru</h5>
                        <a href="customers.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentCustomers)): ?>
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-users fa-2x mb-2"></i>
                                <p class="mb-0">Belum ada pelanggan</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Nama</th>
                                            <th>Paket</th>
                                            <th>Status</th>
                                            <th>Tanggal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentCustomers as $customer): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold"><?= htmlspecialchars($customer['full_name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($customer['phone']) ?></small>
                                                </td>
                                                <td><?= htmlspecialchars(isset($customer['package_name']) ? $customer['package_name'] : '-') ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $customer['status'] === 'active' ? 'success' : ($customer['status'] === 'suspended' ? 'warning' : 'danger') ?>">
                                                        <?= ucfirst($customer['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('d/m/Y', strtotime($customer['created_at'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Overdue Invoices -->
            <div class="col-xl-6 mb-4">
                <div class="card shadow">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2 text-danger"></i>Invoice Overdue</h5>
                        <a href="invoices.php?filter=overdue" class="btn btn-sm btn-outline-danger">Lihat Semua</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($overdueInvoices)): ?>
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-check-circle fa-2x mb-2 text-success"></i>
                                <p class="mb-0">Tidak ada invoice overdue</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Invoice</th>
                                            <th>Pelanggan</th>
                                            <th>Amount</th>
                                            <th>Overdue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($overdueInvoices as $invoice): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold"><?= htmlspecialchars($invoice['invoice_number']) ?></div>
                                                </td>
                                                <td>
                                                    <div><?= htmlspecialchars($invoice['customer_name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($invoice['customer_phone']) ?></small>
                                                </td>
                                                <td>Rp <?= number_format($invoice['amount']) ?></td>
                                                <td>
                                                    <span class="badge bg-danger">
                                                        <?= abs((strtotime($invoice['due_date']) - time()) / (60*60*24)) ?> hari
                                                    </span>
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
        </div>

        <!-- Top Users -->
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card shadow">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Top Users Bulan Ini</h5>
                        <a href="reports.php?action=top_users" class="btn btn-sm btn-outline-primary">Lihat Detail</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($topUsers)): ?>
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-chart-bar fa-2x mb-2"></i>
                                <p class="mb-0">Belum ada data penggunaan</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Rank</th>
                                            <th>Nama</th>
                                            <th>Paket</th>
                                            <th>Total Usage</th>
                                            <th>Session Time</th>
                                            <th>Active Days</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topUsers as $index => $user): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-<?= $index === 0 ? 'warning' : ($index === 1 ? 'secondary' : ($index === 2 ? 'dark' : 'light text-dark')) ?>">
                                                        #<?= $index + 1 ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="fw-bold"><?= htmlspecialchars($user['full_name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($user['username']) ?></small>
                                                </td>
                                                <td><?= htmlspecialchars($user['package_name']) ?></td>
                                                <td><?= $report->formatBytes($user['total_usage']) ?></td>
                                                <td><?= $report->formatSessionTime($user['total_session_time']) ?></td>
                                                <td><?= $user['active_days'] ?> hari</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Revenue Chart
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($revenueLabels) ?>,
                datasets: [{
                    label: 'Pendapatan (Rp)',
                    data: <?= json_encode($revenueValues) ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Pendapatan: Rp ' + context.parsed.y.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });

        // Auto refresh every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>