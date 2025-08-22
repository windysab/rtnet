<?php
/**
 * Class Notification - RT/RW Net
 * 
 * Menangani sistem notifikasi untuk reminder jatuh tempo,
 * status pembayaran, dan notifikasi sistem lainnya
 * 
 * @author RT/RW Net System
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';

class Notification {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Buat notifikasi baru
     */
    public function create($customer_id, $type, $category, $title, $message, $data = null) {
        try {
            $notification_id = $this->db->insert('notifications', [
                'customer_id' => $customer_id,
                'type' => $type, // email, whatsapp, telegram, system
                'category' => $category, // reminder, payment, system, info
                'title' => $title,
                'message' => $message,
                'data' => $data ? json_encode($data) : null,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return [
                'success' => true,
                'notification_id' => $notification_id,
                'message' => 'Notification created successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error creating notification: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Kirim notifikasi
     */
    public function send($notification_id) {
        try {
            $notification = $this->getById($notification_id);
            if (!$notification['success']) {
                return $notification;
            }
            
            $notif = $notification['data'];
            $result = false;
            
            switch ($notif['type']) {
                case 'email':
                    $result = $this->sendEmail($notif);
                    break;
                case 'whatsapp':
                    $result = $this->sendWhatsApp($notif);
                    break;
                case 'telegram':
                    $result = $this->sendTelegram($notif);
                    break;
                case 'system':
                    $result = true; // System notifications are just stored
                    break;
            }
            
            // Update status
            $status = $result ? 'sent' : 'failed';
            $this->db->update('notifications', [
                'status' => $status,
                'sent_at' => $result ? date('Y-m-d H:i:s') : null,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$notification_id]);
            
            return [
                'success' => $result,
                'message' => $result ? 'Notification sent successfully' : 'Failed to send notification'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error sending notification: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Kirim email
     */
    private function sendEmail($notification) {
        try {
            // Ambil data customer
            $customer = $this->db->selectOne('customers', 
                ['name', 'email'], 
                'id = ?', 
                [$notification['customer_id']]
            );
            
            if (!$customer || !$customer['email']) {
                return false;
            }
            
            // Ambil konfigurasi email dari system_settings
            $email_config = $this->getEmailConfig();
            if (!$email_config) {
                return false;
            }
            
            // Setup email headers
            $to = $customer['email'];
            $subject = $notification['title'];
            $message = $this->formatEmailMessage($notification['message'], $customer['name']);
            
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: {$email_config['from_name']} <{$email_config['from_email']}>" . "\r\n";
            $headers .= "Reply-To: {$email_config['from_email']}" . "\r\n";
            
            // Kirim email
            $result = mail($to, $subject, $message, $headers);
            
            return $result;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Kirim WhatsApp
     */
    private function sendWhatsApp($notification) {
        try {
            // Ambil data customer
            $customer = $this->db->selectOne('customers', 
                ['name', 'phone'], 
                'id = ?', 
                [$notification['customer_id']]
            );
            
            if (!$customer || !$customer['phone']) {
                return false;
            }
            
            // Ambil konfigurasi WhatsApp
            $wa_config = $this->getWhatsAppConfig();
            if (!$wa_config) {
                return false;
            }
            
            // Format nomor telepon
            $phone = $this->formatPhoneNumber($customer['phone']);
            $message = $this->formatWhatsAppMessage($notification['message'], $customer['name']);
            
            // Kirim via WhatsApp API (contoh menggunakan service pihak ketiga)
            $curl = curl_init();
            
            curl_setopt_array($curl, [
                CURLOPT_URL => $wa_config['api_url'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode([
                    'phone' => $phone,
                    'message' => $message
                ]),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $wa_config['api_key']
                ],
            ]);
            
            $response = curl_exec($curl);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            
            return $http_code == 200;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Kirim Telegram
     */
    private function sendTelegram($notification) {
        try {
            // Ambil data customer
            $customer = $this->db->selectOne('customers', 
                ['name', 'telegram_id'], 
                'id = ?', 
                [$notification['customer_id']]
            );
            
            if (!$customer || !$customer['telegram_id']) {
                return false;
            }
            
            // Ambil konfigurasi Telegram
            $tg_config = $this->getTelegramConfig();
            if (!$tg_config) {
                return false;
            }
            
            $message = $this->formatTelegramMessage($notification['message'], $customer['name']);
            
            // Kirim via Telegram Bot API
            $curl = curl_init();
            
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://api.telegram.org/bot{$tg_config['bot_token']}/sendMessage",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode([
                    'chat_id' => $customer['telegram_id'],
                    'text' => $message,
                    'parse_mode' => 'HTML'
                ]),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json'
                ],
            ]);
            
            $response = curl_exec($curl);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            
            return $http_code == 200;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Format nomor telepon
     */
    private function formatPhoneNumber($phone) {
        // Hapus karakter non-digit
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Jika dimulai dengan 0, ganti dengan 62
        if (substr($phone, 0, 1) == '0') {
            $phone = '62' . substr($phone, 1);
        }
        
        // Jika tidak dimulai dengan 62, tambahkan 62
        if (substr($phone, 0, 2) != '62') {
            $phone = '62' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Format pesan email
     */
    private function formatEmailMessage($message, $customer_name) {
        $html = "<!DOCTYPE html>";
        $html .= "<html><head><meta charset='UTF-8'></head><body>";
        $html .= "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>";
        $html .= "<h2 style='color: #333;'>RT/RW Net</h2>";
        $html .= "<p>Yth. <strong>$customer_name</strong>,</p>";
        $html .= "<div style='background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0;'>";
        $html .= nl2br(htmlspecialchars($message));
        $html .= "</div>";
        $html .= "<p>Terima kasih,<br><strong>Tim RT/RW Net</strong></p>";
        $html .= "<hr style='border: 1px solid #eee; margin: 20px 0;'>";
        $html .= "<p style='font-size: 12px; color: #666;'>";
        $html .= "Email ini dikirim secara otomatis oleh sistem RT/RW Net. ";
        $html .= "Mohon tidak membalas email ini.";
        $html .= "</p>";
        $html .= "</div></body></html>";
        
        return $html;
    }
    
    /**
     * Format pesan WhatsApp
     */
    private function formatWhatsAppMessage($message, $customer_name) {
        $formatted = "*RT/RW Net*\n\n";
        $formatted .= "Halo *$customer_name*,\n\n";
        $formatted .= $message . "\n\n";
        $formatted .= "Terima kasih,\n";
        $formatted .= "*Tim RT/RW Net*";
        
        return $formatted;
    }
    
    /**
     * Format pesan Telegram
     */
    private function formatTelegramMessage($message, $customer_name) {
        $formatted = "<b>RT/RW Net</b>\n\n";
        $formatted .= "Halo <b>$customer_name</b>,\n\n";
        $formatted .= htmlspecialchars($message) . "\n\n";
        $formatted .= "Terima kasih,\n";
        $formatted .= "<b>Tim RT/RW Net</b>";
        
        return $formatted;
    }
    
    /**
     * Ambil konfigurasi email
     */
    private function getEmailConfig() {
        $config = $this->db->query(
            "SELECT setting_key, setting_value FROM system_settings 
             WHERE setting_key IN ('email_from_name', 'email_from_address', 'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password')"
        )->fetchAll();
        
        $email_config = [];
        foreach ($config as $setting) {
            $email_config[$setting['setting_key']] = $setting['setting_value'];
        }
        
        if (empty($email_config['email_from_address'])) {
            return null;
        }
        
        return [
            'from_name' => isset($email_config['email_from_name']) ? $email_config['email_from_name'] : 'RT/RW Net',
            'from_email' => $email_config['email_from_address'],
            'smtp_host' => isset($email_config['smtp_host']) ? $email_config['smtp_host'] : null,
            'smtp_port' => isset($email_config['smtp_port']) ? $email_config['smtp_port'] : 587,
            'smtp_username' => isset($email_config['smtp_username']) ? $email_config['smtp_username'] : null,
            'smtp_password' => isset($email_config['smtp_password']) ? $email_config['smtp_password'] : null
        ];
    }
    
    /**
     * Ambil konfigurasi WhatsApp
     */
    private function getWhatsAppConfig() {
        $config = $this->db->query(
            "SELECT setting_key, setting_value FROM system_settings 
             WHERE setting_key IN ('whatsapp_api_url', 'whatsapp_api_key')"
        )->fetchAll();
        
        $wa_config = [];
        foreach ($config as $setting) {
            $wa_config[$setting['setting_key']] = $setting['setting_value'];
        }
        
        if (empty($wa_config['whatsapp_api_url']) || empty($wa_config['whatsapp_api_key'])) {
            return null;
        }
        
        return [
            'api_url' => $wa_config['whatsapp_api_url'],
            'api_key' => $wa_config['whatsapp_api_key']
        ];
    }
    
    /**
     * Ambil konfigurasi Telegram
     */
    private function getTelegramConfig() {
        $config = $this->db->selectOne('system_settings', 
            ['setting_value'], 
            'setting_key = ?', 
            ['telegram_bot_token']
        );
        
        if (!$config || empty($config['setting_value'])) {
            return null;
        }
        
        return [
            'bot_token' => $config['setting_value']
        ];
    }
    
    /**
     * Ambil notifikasi berdasarkan ID
     */
    public function getById($id) {
        try {
            $notification = $this->db->selectOne('notifications', 
                ['*'], 
                'id = ?', 
                [$id]
            );
            
            if (!$notification) {
                return [
                    'success' => false,
                    'message' => 'Notification not found'
                ];
            }
            
            return [
                'success' => true,
                'data' => $notification
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error getting notification: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Ambil semua notifikasi dengan filter
     */
    public function getAll($filters = []) {
        try {
            $where_conditions = [];
            $params = [];
            
            if (isset($filters['customer_id'])) {
                $where_conditions[] = 'n.customer_id = ?';
                $params[] = $filters['customer_id'];
            }
            
            if (isset($filters['type'])) {
                $where_conditions[] = 'n.type = ?';
                $params[] = $filters['type'];
            }
            
            if (isset($filters['category'])) {
                $where_conditions[] = 'n.category = ?';
                $params[] = $filters['category'];
            }
            
            if (isset($filters['status'])) {
                $where_conditions[] = 'n.status = ?';
                $params[] = $filters['status'];
            }
            
            if (isset($filters['date_from'])) {
                $where_conditions[] = 'DATE(n.created_at) >= ?';
                $params[] = $filters['date_from'];
            }
            
            if (isset($filters['date_to'])) {
                $where_conditions[] = 'DATE(n.created_at) <= ?';
                $params[] = $filters['date_to'];
            }
            
            $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
            
            // Pagination
            $page = isset($filters['page']) ? max(1, intval($filters['page'])) : 1;
            $limit = isset($filters['limit']) ? max(1, intval($filters['limit'])) : 20;
            $offset = ($page - 1) * $limit;
            
            // Count total
            $total_query = "SELECT COUNT(*) as total FROM notifications n 
                           LEFT JOIN customers c ON n.customer_id = c.id 
                           $where_clause";
            $total_result = $this->db->query($total_query, $params)->fetch();
            $total = $total_result['total'];
            
            // Get data
            $data_query = "SELECT n.*, c.name as customer_name, c.phone, c.email 
                          FROM notifications n 
                          LEFT JOIN customers c ON n.customer_id = c.id 
                          $where_clause 
                          ORDER BY n.created_at DESC 
                          LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
            $notifications = $this->db->query($data_query, $params)->fetchAll();
            
            return [
                'success' => true,
                'data' => $notifications,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error getting notifications: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Hapus notifikasi
     */
    public function delete($id) {
        try {
            $result = $this->db->delete('notifications', 'id = ?', [$id]);
            
            return [
                'success' => $result,
                'message' => $result ? 'Notification deleted successfully' : 'Failed to delete notification'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error deleting notification: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Kirim notifikasi batch (untuk reminder jatuh tempo)
     */
    public function sendBatchReminders() {
        try {
            // Ambil invoice yang akan jatuh tempo dalam 3 hari
            $reminder_date = date('Y-m-d', strtotime('+3 days'));
            
            $invoices = $this->db->query(
                "SELECT i.*, c.name as customer_name, c.phone, c.email 
                 FROM invoices i 
                 JOIN customers c ON i.customer_id = c.id 
                 WHERE i.status = 'pending' 
                 AND i.due_date <= ? 
                 AND i.reminder_sent = 0",
                [$reminder_date]
            )->fetchAll();
            
            $sent_count = 0;
            $failed_count = 0;
            
            foreach ($invoices as $invoice) {
                $title = "Reminder Tagihan - {$invoice['invoice_number']}";
                $message = "Tagihan Anda dengan nomor {$invoice['invoice_number']} ";
                $message .= "sebesar Rp " . number_format($invoice['total_amount'], 0, ',', '.') . " ";
                $message .= "akan jatuh tempo pada " . date('d/m/Y', strtotime($invoice['due_date'])) . ". ";
                $message .= "Mohon segera lakukan pembayaran untuk menghindari pemutusan layanan.";
                
                $notification_data = [
                    'invoice_id' => $invoice['id'],
                    'invoice_number' => $invoice['invoice_number'],
                    'amount' => $invoice['total_amount'],
                    'due_date' => $invoice['due_date']
                ];
                
                // Kirim via WhatsApp jika ada nomor HP
                if ($invoice['phone']) {
                    $wa_result = $this->create($invoice['customer_id'], 'whatsapp', 'reminder', $title, $message, $notification_data);
                    if ($wa_result['success']) {
                        $send_result = $this->send($wa_result['notification_id']);
                        if ($send_result['success']) $sent_count++; else $failed_count++;
                    }
                }
                
                // Kirim via Email jika ada email
                if ($invoice['email']) {
                    $email_result = $this->create($invoice['customer_id'], 'email', 'reminder', $title, $message, $notification_data);
                    if ($email_result['success']) {
                        $send_result = $this->send($email_result['notification_id']);
                        if ($send_result['success']) $sent_count++; else $failed_count++;
                    }
                }
                
                // Update status reminder di invoice
                if ($sent_count > 0) {
                    $this->db->update('invoices', [
                        'reminder_sent' => 1,
                        'reminder_date' => date('Y-m-d H:i:s')
                    ], 'id = ?', [$invoice['id']]);
                }
            }
            
            return [
                'success' => true,
                'message' => "Batch reminders completed. Sent: $sent_count, Failed: $failed_count",
                'sent_count' => $sent_count,
                'failed_count' => $failed_count
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error sending batch reminders: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Ambil statistik notifikasi
     */
    public function getStats() {
        try {
            $stats = [];
            
            // Total notifikasi
            $total = $this->db->selectOne('notifications', ['COUNT(*) as count'])['count'];
            $stats['total'] = $total;
            
            // Berdasarkan status
            $status_stats = $this->db->query(
                "SELECT status, COUNT(*) as count FROM notifications GROUP BY status"
            )->fetchAll();
            
            foreach ($status_stats as $stat) {
                $stats['by_status'][$stat['status']] = $stat['count'];
            }
            
            // Berdasarkan type
            $type_stats = $this->db->query(
                "SELECT type, COUNT(*) as count FROM notifications GROUP BY type"
            )->fetchAll();
            
            foreach ($type_stats as $stat) {
                $stats['by_type'][$stat['type']] = $stat['count'];
            }
            
            // Berdasarkan category
            $category_stats = $this->db->query(
                "SELECT category, COUNT(*) as count FROM notifications GROUP BY category"
            )->fetchAll();
            
            foreach ($category_stats as $stat) {
                $stats['by_category'][$stat['category']] = $stat['count'];
            }
            
            // Hari ini
            $today = $this->db->selectOne('notifications', 
                ['COUNT(*) as count'], 
                'DATE(created_at) = ?', 
                [date('Y-m-d')]
            )['count'];
            $stats['today'] = $today;
            
            return [
                'success' => true,
                'stats' => $stats
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error getting notification stats: ' . $e->getMessage()
            ];
        }
    }
}