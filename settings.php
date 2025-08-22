<?php
require_once 'classes/Auth.php';
require_once 'config/database.php';

// Check authentication
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();
$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        foreach ($_POST as $key => $value) {
            if ($key !== 'submit') {
                $sql = "UPDATE system_settings SET setting_value = ? WHERE setting_key = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([$value, $key]);
            }
        }
        
        $db->commit();
        $message = 'Pengaturan berhasil disimpan!';
        $messageType = 'success';
    } catch (Exception $e) {
        $db->rollBack();
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get current settings
$settings = [];
$sql = "SELECT setting_key, setting_value, description FROM system_settings ORDER BY setting_key";
$stmt = $db->prepare($sql);
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Sistem - RT/RW Net</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
                <a class="nav-link" href="reports.php">Laporan</a>
                <a class="nav-link active" href="settings.php">Pengaturan</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-cog me-2"></i>Pengaturan Sistem</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                                <?= htmlspecialchars($message) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="row">
                                <!-- Company Information -->
                                <div class="col-md-6">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h6><i class="fas fa-building me-2"></i>Informasi Perusahaan</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">Nama Perusahaan</label>
                                                <input type="text" class="form-control" name="company_name" 
                                                       value="<?= htmlspecialchars(isset($settings['company_name']['setting_value']) ? $settings['company_name']['setting_value'] : '') ?>" required>
                                     <small class="text-muted"><?= htmlspecialchars(isset($settings['company_name']['description']) ? $settings['company_name']['description'] : '') ?></small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Alamat</label>
                                                <textarea class="form-control" name="company_address" rows="3"><?= htmlspecialchars(isset($settings['company_address']['setting_value']) ? $settings['company_address']['setting_value'] : '') ?></textarea>
                                     <small class="text-muted"><?= htmlspecialchars(isset($settings['company_address']['description']) ? $settings['company_address']['description'] : '') ?></small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Telepon</label>
                                                <input type="text" class="form-control" name="company_phone" 
                                                       value="<?= htmlspecialchars(isset($settings['company_phone']['setting_value']) ? $settings['company_phone']['setting_value'] : '') ?>">
                                     <small class="text-muted"><?= htmlspecialchars(isset($settings['company_phone']['description']) ? $settings['company_phone']['description'] : '') ?></small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Email</label>
                                                <input type="email" class="form-control" name="company_email" 
                                                       value="<?= htmlspecialchars(isset($settings['company_email']['setting_value']) ? $settings['company_email']['setting_value'] : '') ?>">
                                     <small class="text-muted"><?= htmlspecialchars(isset($settings['company_email']['description']) ? $settings['company_email']['description'] : '') ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- MikroTik Settings -->
                                <div class="col-md-6">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h6><i class="fas fa-router me-2"></i>Pengaturan MikroTik</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">Host/IP Address</label>
                                                <input type="text" class="form-control" name="mikrotik_host" 
                                                       value="<?= htmlspecialchars(isset($settings['mikrotik_host']['setting_value']) ? $settings['mikrotik_host']['setting_value'] : '') ?>" required>
                                                 <small class="text-muted"><?= htmlspecialchars(isset($settings['mikrotik_host']['description']) ? $settings['mikrotik_host']['description'] : '') ?></small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Port</label>
                                                <input type="number" class="form-control" name="mikrotik_port" 
                                                       value="<?= htmlspecialchars(isset($settings['mikrotik_port']['setting_value']) ? $settings['mikrotik_port']['setting_value'] : '') ?>" required>
                                                 <small class="text-muted"><?= htmlspecialchars(isset($settings['mikrotik_port']['description']) ? $settings['mikrotik_port']['description'] : '') ?></small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Username</label>
                                                <input type="text" class="form-control" name="mikrotik_username" 
                                                       value="<?= htmlspecialchars(isset($settings['mikrotik_username']['setting_value']) ? $settings['mikrotik_username']['setting_value'] : '') ?>" required>
                                                 <small class="text-muted"><?= htmlspecialchars(isset($settings['mikrotik_username']['description']) ? $settings['mikrotik_username']['description'] : '') ?></small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Password</label>
                                                <input type="password" class="form-control" name="mikrotik_password" 
                                                       value="<?= htmlspecialchars(isset($settings['mikrotik_password']['setting_value']) ? $settings['mikrotik_password']['setting_value'] : '') ?>" required>
                                                 <small class="text-muted"><?= htmlspecialchars(isset($settings['mikrotik_password']['description']) ? $settings['mikrotik_password']['description'] : '') ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Billing Settings -->
                                <div class="col-md-6">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h6><i class="fas fa-file-invoice me-2"></i>Pengaturan Billing</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">Prefix Invoice</label>
                                                <input type="text" class="form-control" name="invoice_prefix" 
                                                       value="<?= htmlspecialchars(isset($settings['invoice_prefix']['setting_value']) ? $settings['invoice_prefix']['setting_value'] : '') ?>" required>
                                                 <small class="text-muted"><?= htmlspecialchars(isset($settings['invoice_prefix']['description']) ? $settings['invoice_prefix']['description'] : '') ?></small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Jatuh Tempo (Hari)</label>
                                                <input type="number" class="form-control" name="payment_due_days" 
                                                       value="<?= htmlspecialchars(isset($settings['payment_due_days']['setting_value']) ? $settings['payment_due_days']['setting_value'] : '') ?>" min="1" required>
                                                 <small class="text-muted"><?= htmlspecialchars(isset($settings['payment_due_days']['description']) ? $settings['payment_due_days']['description'] : '') ?></small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Mata Uang</label>
                                                <select class="form-control" name="currency">
                                                    <option value="IDR" <?= (isset($settings['currency']['setting_value']) ? $settings['currency']['setting_value'] : '') === 'IDR' ? 'selected' : '' ?>>IDR - Rupiah</option>
                                                     <option value="USD" <?= (isset($settings['currency']['setting_value']) ? $settings['currency']['setting_value'] : '') === 'USD' ? 'selected' : '' ?>>USD - Dollar</option>
                                                </select>
                                                <small class="text-muted"><?= htmlspecialchars(isset($settings['currency']['description']) ? $settings['currency']['description'] : '') ?></small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Zona Waktu</label>
                                                <select class="form-control" name="timezone">
                                                    <option value="Asia/Jakarta" <?= (isset($settings['timezone']['setting_value']) ? $settings['timezone']['setting_value'] : '') === 'Asia/Jakarta' ? 'selected' : '' ?>>Asia/Jakarta (WIB)</option>
                                <option value="Asia/Makassar" <?= (isset($settings['timezone']['setting_value']) ? $settings['timezone']['setting_value'] : '') === 'Asia/Makassar' ? 'selected' : '' ?>>Asia/Makassar (WITA)</option>
                                <option value="Asia/Jayapura" <?= (isset($settings['timezone']['setting_value']) ? $settings['timezone']['setting_value'] : '') === 'Asia/Jayapura' ? 'selected' : '' ?>>Asia/Jayapura (WIT)</option>
                                                </select>
                                                <small class="text-muted"><?= htmlspecialchars(isset($settings['timezone']['description']) ? $settings['timezone']['description'] : '') ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Email Settings -->
                                <div class="col-md-6">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h6><i class="fas fa-envelope me-2"></i>Pengaturan Email</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">SMTP Host</label>
                                                <input type="text" class="form-control" name="email_smtp_host" 
                                                       value="<?= htmlspecialchars(isset($settings['email_smtp_host']['setting_value']) ? $settings['email_smtp_host']['setting_value'] : '') ?>">
                                <small class="text-muted"><?= htmlspecialchars(isset($settings['email_smtp_host']['description']) ? $settings['email_smtp_host']['description'] : '') ?></small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">SMTP Port</label>
                                                <input type="number" class="form-control" name="email_smtp_port" 
                                                       value="<?= htmlspecialchars(isset($settings['email_smtp_port']['setting_value']) ? $settings['email_smtp_port']['setting_value'] : '') ?>">
                                <small class="text-muted"><?= htmlspecialchars(isset($settings['email_smtp_port']['description']) ? $settings['email_smtp_port']['description'] : '') ?></small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">SMTP Username</label>
                                                <input type="text" class="form-control" name="email_smtp_username" 
                                                       value="<?= htmlspecialchars(isset($settings['email_smtp_username']['setting_value']) ? $settings['email_smtp_username']['setting_value'] : '') ?>">
                                <small class="text-muted"><?= htmlspecialchars(isset($settings['email_smtp_username']['description']) ? $settings['email_smtp_username']['description'] : '') ?></small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">SMTP Password</label>
                                                <input type="password" class="form-control" name="email_smtp_password" 
                                                       value="<?= htmlspecialchars(isset($settings['email_smtp_password']['setting_value']) ? $settings['email_smtp_password']['setting_value'] : '') ?>">
                                <small class="text-muted"><?= htmlspecialchars(isset($settings['email_smtp_password']['description']) ? $settings['email_smtp_password']['description'] : '') ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- WhatsApp Settings -->
                                <div class="col-md-6">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h6><i class="fab fa-whatsapp me-2"></i>Pengaturan WhatsApp</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">API URL</label>
                                                <input type="url" class="form-control" name="whatsapp_api_url" 
                                                       value="<?= htmlspecialchars(isset($settings['whatsapp_api_url']['setting_value']) ? $settings['whatsapp_api_url']['setting_value'] : '') ?>">
                                <small class="text-muted"><?= htmlspecialchars(isset($settings['whatsapp_api_url']['description']) ? $settings['whatsapp_api_url']['description'] : '') ?></small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">API Token</label>
                                                <input type="text" class="form-control" name="whatsapp_api_token" 
                                                       value="<?= htmlspecialchars(isset($settings['whatsapp_api_token']['setting_value']) ? $settings['whatsapp_api_token']['setting_value'] : '') ?>">
                                <small class="text-muted"><?= htmlspecialchars(isset($settings['whatsapp_api_token']['description']) ? $settings['whatsapp_api_token']['description'] : '') ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Telegram Settings -->
                                <div class="col-md-6">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h6><i class="fab fa-telegram me-2"></i>Pengaturan Telegram</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">Bot Token</label>
                                                <input type="text" class="form-control" name="telegram_bot_token" 
                                                       value="<?= htmlspecialchars(isset($settings['telegram_bot_token']['setting_value']) ? $settings['telegram_bot_token']['setting_value'] : '') ?>">
                                <small class="text-muted"><?= htmlspecialchars(isset($settings['telegram_bot_token']['description']) ? $settings['telegram_bot_token']['description'] : '') ?></small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Chat ID</label>
                                                <input type="text" class="form-control" name="telegram_chat_id" 
                                                       value="<?= htmlspecialchars(isset($settings['telegram_chat_id']['setting_value']) ? $settings['telegram_chat_id']['setting_value'] : '') ?>">
                                <small class="text-muted"><?= htmlspecialchars(isset($settings['telegram_chat_id']['description']) ? $settings['telegram_chat_id']['description'] : '') ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Monitoring Settings -->
                                <div class="col-md-6">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h6><i class="fas fa-chart-line me-2"></i>Pengaturan Monitoring</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">Interval Monitoring (detik)</label>
                                                <input type="number" class="form-control" name="monitoring_interval" 
                                                       value="<?= htmlspecialchars(isset($settings['monitoring_interval']['setting_value']) ? $settings['monitoring_interval']['setting_value'] : '') ?>" min="60">
                                <small class="text-muted"><?= htmlspecialchars(isset($settings['monitoring_interval']['description']) ? $settings['monitoring_interval']['description'] : '') ?></small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Hari Reminder (pisahkan dengan koma)</label>
                                                <input type="text" class="form-control" name="notification_reminder_days" 
                                                       value="<?= htmlspecialchars(isset($settings['notification_reminder_days']['setting_value']) ? $settings['notification_reminder_days']['setting_value'] : '') ?>">
                                <small class="text-muted"><?= htmlspecialchars(isset($settings['notification_reminder_days']['description']) ? $settings['notification_reminder_days']['description'] : '') ?></small>
                                            </div>
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="bandwidth_collection_enabled" 
                                                           value="1" <?= (isset($settings['bandwidth_collection_enabled']['setting_value']) ? $settings['bandwidth_collection_enabled']['setting_value'] : '') === '1' ? 'checked' : '' ?>>
                                                    <label class="form-check-label">
                                                        Aktifkan Pengumpulan Data Bandwidth
                                                    </label>
                                                </div>
                                                <small class="text-muted"><?= htmlspecialchars(isset($settings['bandwidth_collection_enabled']['description']) ? $settings['bandwidth_collection_enabled']['description'] : '') ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="text-end">
                                <button type="submit" name="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Simpan Pengaturan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Test Connection Section -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-plug me-2"></i>Test Koneksi</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <button type="button" class="btn btn-outline-primary w-100 mb-2" onclick="testMikroTikConnection()">
                                    <i class="fas fa-router me-2"></i>Test Koneksi MikroTik
                                </button>
                            </div>
                            <div class="col-md-4">
                                <button type="button" class="btn btn-outline-success w-100 mb-2" onclick="testEmailConnection()">
                                    <i class="fas fa-envelope me-2"></i>Test Koneksi Email
                                </button>
                            </div>
                            <div class="col-md-4">
                                <button type="button" class="btn btn-outline-info w-100 mb-2" onclick="testWhatsAppConnection()">
                                    <i class="fab fa-whatsapp me-2"></i>Test WhatsApp API
                                </button>
                            </div>
                        </div>
                        <div id="testResult" class="mt-3"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function testMikroTikConnection() {
            showTestResult('Testing MikroTik connection...', 'info');
            
            fetch('mikrotik.php?action=test_connection')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showTestResult('MikroTik connection successful!', 'success');
                    } else {
                        showTestResult('MikroTik connection failed: ' + data.message, 'danger');
                    }
                })
                .catch(error => {
                    showTestResult('Error testing MikroTik connection: ' + error.message, 'danger');
                });
        }
        
        function testEmailConnection() {
            showTestResult('Testing email connection...', 'info');
            // Implement email test
            setTimeout(() => {
                showTestResult('Email test not implemented yet', 'warning');
            }, 1000);
        }
        
        function testWhatsAppConnection() {
            showTestResult('Testing WhatsApp API...', 'info');
            // Implement WhatsApp test
            setTimeout(() => {
                showTestResult('WhatsApp API test not implemented yet', 'warning');
            }, 1000);
        }
        
        function showTestResult(message, type) {
            const resultDiv = document.getElementById('testResult');
            resultDiv.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
        }
    </script>
</body>
</html>