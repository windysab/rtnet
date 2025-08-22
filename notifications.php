<?php
/**
 * Halaman Notifikasi - RT/RW Net
 * 
 * Mengelola notifikasi sistem, reminder, dan komunikasi dengan pelanggan
 */

require_once 'classes/Auth.php';
require_once 'classes/Notification.php';
require_once 'config/database.php';

// Cek autentikasi
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$notification = new Notification();
$db = new Database();

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = '';
$error = '';

switch ($action) {
    case 'create':
        if ($_POST) {
            $result = $notification->create(
                $_POST['customer_id'],
                $_POST['type'],
                $_POST['category'],
                $_POST['title'],
                $_POST['message'],
                isset($_POST['data']) ? json_decode($_POST['data'], true) : null
            );
            
            if ($result['success']) {
                // Langsung kirim jika diminta
                if (isset($_POST['send_immediately'])) {
                    $send_result = $notification->send($result['notification_id']);
                    if ($send_result['success']) {
                        $message = 'Notifikasi berhasil dibuat dan dikirim';
                    } else {
                        $message = 'Notifikasi berhasil dibuat tapi gagal dikirim: ' . $send_result['message'];
                    }
                } else {
                    $message = 'Notifikasi berhasil dibuat';
                }
                header('Location: notifications.php?message=' . urlencode($message));
                exit;
            } else {
                $error = $result['message'];
            }
        }
        break;
        
    case 'send':
        $id = isset($_GET['id']) ? $_GET['id'] : 0;
        $result = $notification->send($id);
        if ($result['success']) {
            $message = 'Notifikasi berhasil dikirim';
        } else {
            $error = $result['message'];
        }
        header('Location: notifications.php?message=' . urlencode($message) . '&error=' . urlencode($error));
        exit;
        
    case 'delete':
        $id = isset($_GET['id']) ? $_GET['id'] : 0;
        $result = $notification->delete($id);
        if ($result['success']) {
            $message = 'Notifikasi berhasil dihapus';
        } else {
            $error = $result['message'];
        }
        header('Location: notifications.php?message=' . urlencode($message) . '&error=' . urlencode($error));
        exit;
        
    case 'batch_reminders':
        $result = $notification->sendBatchReminders();
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
        header('Location: notifications.php?message=' . urlencode($message) . '&error=' . urlencode($error));
        exit;
}

// Ambil pesan dari URL
if (isset($_GET['message'])) {
    $message = $_GET['message'];
}
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

// Filter dan pagination
$filters = [
    'customer_id' => isset($_GET['customer_id']) ? $_GET['customer_id'] : null,
    'type' => isset($_GET['type']) ? $_GET['type'] : null,
    'category' => isset($_GET['category']) ? $_GET['category'] : null,
    'status' => isset($_GET['status']) ? $_GET['status'] : null,
    'date_from' => isset($_GET['date_from']) ? $_GET['date_from'] : null,
    'date_to' => isset($_GET['date_to']) ? $_GET['date_to'] : null,
    'page' => isset($_GET['page']) ? $_GET['page'] : 1,
    'limit' => 20
];

// Hapus filter kosong
$filters = array_filter($filters, function($value) {
    return $value !== null && $value !== '';
});

// Ambil data notifikasi
$notifications_result = $notification->getAll($filters);
$notifications = $notifications_result['success'] ? $notifications_result['data'] : [];
$pagination = $notifications_result['success'] ? $notifications_result['pagination'] : null;

// Ambil data pelanggan untuk dropdown
$customers = $db->query("SELECT id, name FROM customers WHERE status = 'active' ORDER BY name")->fetchAll();

// Ambil statistik
$stats = $notification->getStats();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi - RT/RW Net</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-router"></i> RT/RW Net
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="monitoring.php">Monitoring</a>
                <a class="nav-link active" href="notifications.php">Notifikasi</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if ($action == 'create'): ?>
            <!-- Form Create Notification -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-plus-circle"></i> Buat Notifikasi Baru</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                            <?php endif; ?>
                            
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Pelanggan</label>
                                            <select name="customer_id" class="form-select" required>
                                                <option value="">Pilih Pelanggan</option>
                                                <?php foreach ($customers as $customer): ?>
                                                    <option value="<?= $customer['id'] ?>">
                                                        <?= htmlspecialchars($customer['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Type</label>
                                            <select name="type" class="form-select" required>
                                                <option value="email">Email</option>
                                                <option value="whatsapp">WhatsApp</option>
                                                <option value="telegram">Telegram</option>
                                                <option value="system">System</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Kategori</label>
                                            <select name="category" class="form-select" required>
                                                <option value="reminder">Reminder</option>
                                                <option value="payment">Payment</option>
                                                <option value="info">Info</option>
                                                <option value="system">System</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Judul</label>
                                    <input type="text" name="title" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Pesan</label>
                                    <textarea name="message" class="form-control" rows="5" required></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Data Tambahan (JSON)</label>
                                    <textarea name="data" class="form-control" rows="3" placeholder='{"key": "value"}'></textarea>
                                    <small class="text-muted">Optional: Data tambahan dalam format JSON</small>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="send_immediately" class="form-check-input" id="send_immediately">
                                        <label class="form-check-label" for="send_immediately">
                                            Kirim langsung setelah dibuat
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check"></i> Buat Notifikasi
                                    </button>
                                    <a href="notifications.php" class="btn btn-secondary">
                                        <i class="bi bi-arrow-left"></i> Kembali
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h6>Template Pesan</h6>
                        </div>
                        <div class="card-body">
                            <h6>Reminder Tagihan:</h6>
                            <small class="text-muted">
                                Tagihan Anda dengan nomor [INVOICE_NUMBER] sebesar Rp [AMOUNT] akan jatuh tempo pada [DUE_DATE]. Mohon segera lakukan pembayaran.
                            </small>
                            
                            <h6 class="mt-3">Konfirmasi Pembayaran:</h6>
                            <small class="text-muted">
                                Pembayaran Anda untuk tagihan [INVOICE_NUMBER] sebesar Rp [AMOUNT] telah kami terima. Terima kasih.
                            </small>
                            
                            <h6 class="mt-3">Info Layanan:</h6>
                            <small class="text-muted">
                                Informasi penting mengenai layanan internet Anda. [MESSAGE]
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <!-- List Notifications -->
            <div class="row">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="bi bi-bell"></i> Notifikasi</h2>
                        <div class="btn-group">
                            <a href="?action=create" class="btn btn-primary">
                                <i class="bi bi-plus"></i> Buat Notifikasi
                            </a>
                            <a href="?action=batch_reminders" class="btn btn-warning" 
                               onclick="return confirm('Kirim reminder untuk semua tagihan yang akan jatuh tempo?')">
                                <i class="bi bi-bell"></i> Kirim Batch Reminder
                            </a>
                        </div>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?= htmlspecialchars($message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Statistics -->
            <?php if ($stats['success']): ?>
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-primary"><?= $stats['stats']['total'] ?></h3>
                                <p class="mb-0">Total Notifikasi</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-success"><?= isset($stats['stats']['by_status']['sent']) ? $stats['stats']['by_status']['sent'] : 0 ?></h3>
                                <p class="mb-0">Terkirim</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-warning"><?= isset($stats['stats']['by_status']['pending']) ? $stats['stats']['by_status']['pending'] : 0 ?></h3>
                                <p class="mb-0">Pending</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-info"><?= $stats['stats']['today'] ?></h3>
                                <p class="mb-0">Hari Ini</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <select name="customer_id" class="form-select">
                                <option value="">Semua Pelanggan</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?= $customer['id'] ?>" 
                                            <?= (isset($filters['customer_id']) ? $filters['customer_id'] : '') == $customer['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($customer['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="type" class="form-select">
                                <option value="">Semua Type</option>
                                <option value="email" <?= (isset($filters['type']) ? $filters['type'] : '') == 'email' ? 'selected' : '' ?>>Email</option>
                                <option value="whatsapp" <?= (isset($filters['type']) ? $filters['type'] : '') == 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option>
                                <option value="telegram" <?= (isset($filters['type']) ? $filters['type'] : '') == 'telegram' ? 'selected' : '' ?>>Telegram</option>
                                <option value="system" <?= (isset($filters['type']) ? $filters['type'] : '') == 'system' ? 'selected' : '' ?>>System</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-select">
                                <option value="">Semua Status</option>
                                <option value="pending" <?= (isset($filters['status']) ? $filters['status'] : '') == 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="sent" <?= (isset($filters['status']) ? $filters['status'] : '') == 'sent' ? 'selected' : '' ?>>Sent</option>
                                <option value="failed" <?= (isset($filters['status']) ? $filters['status'] : '') == 'failed' ? 'selected' : '' ?>>Failed</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="date_from" class="form-control" 
                                   value="<?= isset($filters['date_from']) ? $filters['date_from'] : '' ?>" placeholder="Dari Tanggal">
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="date_to" class="form-control" 
                                   value="<?= isset($filters['date_to']) ? $filters['date_to'] : '' ?>" placeholder="Sampai Tanggal">
                        </div>
                        <div class="col-md-1">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Notifications Table -->
            <div class="card">
                <div class="card-body">
                    <?php if (empty($notifications)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-bell-slash" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="text-muted mt-2">Tidak ada notifikasi ditemukan</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Pelanggan</th>
                                        <th>Type</th>
                                        <th>Kategori</th>
                                        <th>Judul</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($notifications as $notif): ?>
                                        <tr>
                                            <td>
                                                <small><?= date('d/m/Y H:i', strtotime($notif['created_at'])) ?></small>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars(isset($notif['customer_name']) ? $notif['customer_name'] : 'System') ?>
                                            </td>
                                            <td>
                                                <?php
                                                $type_badges = [
                                                    'email' => 'bg-primary',
                                                    'whatsapp' => 'bg-success',
                                                    'telegram' => 'bg-info',
                                                    'system' => 'bg-secondary'
                                                ];
                                                $badge_class = isset($type_badges[$notif['type']]) ? $type_badges[$notif['type']] : 'bg-secondary';
                                                ?>
                                                <span class="badge <?= $badge_class ?>">
                                                    <?= ucfirst($notif['type']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-outline-secondary">
                                                    <?= ucfirst($notif['category']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($notif['title']) ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars(substr($notif['message'], 0, 50)) ?>...
                                                </small>
                                            </td>
                                            <td>
                                                <?php
                                                $status_badges = [
                                                    'pending' => 'bg-warning',
                                                    'sent' => 'bg-success',
                                                    'failed' => 'bg-danger'
                                                ];
                                                $status_badge = isset($status_badges[$notif['status']]) ? $status_badges[$notif['status']] : 'bg-secondary';
                                                ?>
                                                <span class="badge <?= $status_badge ?>">
                                                    <?= ucfirst($notif['status']) ?>
                                                </span>
                                                <?php if ($notif['sent_at']): ?>
                                                    <br><small class="text-muted">
                                                        <?= date('d/m H:i', strtotime($notif['sent_at'])) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <?php if ($notif['status'] == 'pending'): ?>
                                                        <a href="?action=send&id=<?= $notif['id'] ?>" 
                                                           class="btn btn-outline-primary" 
                                                           onclick="return confirm('Kirim notifikasi ini?')">
                                                            <i class="bi bi-send"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-outline-info" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#viewModal<?= $notif['id'] ?>">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <a href="?action=delete&id=<?= $notif['id'] ?>" 
                                                       class="btn btn-outline-danger" 
                                                       onclick="return confirm('Hapus notifikasi ini?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        
                                        <!-- View Modal -->
                                        <div class="modal fade" id="viewModal<?= $notif['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Detail Notifikasi</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <strong>Pelanggan:</strong><br>
                                                                <?= htmlspecialchars(isset($notif['customer_name']) ? $notif['customer_name'] : 'System') ?>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <strong>Type:</strong><br>
                                                                <span class="badge <?= $badge_class ?>">
                                                                    <?= ucfirst($notif['type']) ?>
                                                                </span>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <strong>Status:</strong><br>
                                                                <span class="badge <?= $status_badge ?>">
                                                                    <?= ucfirst($notif['status']) ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        
                                                        <hr>
                                                        
                                                        <div class="mb-3">
                                                            <strong>Judul:</strong><br>
                                                            <?= htmlspecialchars($notif['title']) ?>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <strong>Pesan:</strong><br>
                                                            <div class="bg-light p-3 rounded">
                                                                <?= nl2br(htmlspecialchars($notif['message'])) ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <?php if ($notif['data']): ?>
                                                            <div class="mb-3">
                                                                <strong>Data Tambahan:</strong><br>
                                                                <pre class="bg-light p-2 rounded"><code><?= htmlspecialchars($notif['data']) ?></code></pre>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <strong>Dibuat:</strong><br>
                                                                <?= date('d/m/Y H:i:s', strtotime($notif['created_at'])) ?>
                                                            </div>
                                                            <?php if ($notif['sent_at']): ?>
                                                                <div class="col-md-6">
                                                                    <strong>Dikirim:</strong><br>
                                                                    <?= date('d/m/Y H:i:s', strtotime($notif['sent_at'])) ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($pagination && $pagination['pages'] > 1): ?>
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $pagination['pages']; $i++): ?>
                                        <li class="page-item <?= $i == $pagination['page'] ? 'active' : '' ?>">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $i])) ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>