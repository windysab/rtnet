<?php
/**
 * Class Monitoring - RT/RW Net
 * 
 * Menangani monitoring status online pelanggan, pemakaian bandwidth,
 * dan notifikasi reminder jatuh tempo
 * 
 * @author RT/RW Net System
 * @version 1.0
 */

require_once 'MikrotikAPI.php';
require_once __DIR__ . '/../config/database.php';

class Monitoring {
    private $db;
    private $mikrotik;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Initialize MikroTik connection
     */
    private function initMikroTik() {
        if ($this->mikrotik && $this->mikrotik->isConnected()) {
            return true;
        }
        
        // Get MikroTik configuration from database
        $config = $this->db->select('SELECT * FROM mikrotik_config WHERE is_active = 1 LIMIT 1');
        
        if (empty($config)) {
            throw new Exception('Konfigurasi MikroTik tidak ditemukan');
        }
        
        $config = $config[0];
        
        $this->mikrotik = new MikrotikAPI(
            $config['host'],
            $config['username'],
            $config['password'],
            $config['port']
        );
        
        $connected = $this->mikrotik->connect();
        
        if (!$connected) {
            throw new Exception('Gagal terhubung ke MikroTik: ' . $this->mikrotik->getError());
        }
        
        return true;
    }
    
    /**
     * Cek status online semua pelanggan
     */
    public function checkOnlineStatus() {
        try {
            $this->initMikroTik();
            
            // Ambil semua pelanggan aktif yang sudah di-provision
            $customers = $this->db->query(
                "SELECT c.*, cp.service_type, cp.username, cp.mikrotik_id 
                 FROM customers c 
                 JOIN customer_provisioning cp ON c.id = cp.customer_id 
                 WHERE c.status = 'active' AND cp.is_active = 1"
            )->fetchAll();
            
            $online_count = 0;
            $offline_count = 0;
            $results = [];
            
            foreach ($customers as $customer) {
                $is_online = false;
                
                // Cek status berdasarkan service type
                switch ($customer['service_type']) {
                    case 'hotspot':
                        $is_online = $this->checkHotspotStatus($customer['username']);
                        break;
                    case 'pppoe':
                        $is_online = $this->checkPPPoEStatus($customer['username']);
                        break;
                    case 'simple_queue':
                        $is_online = $this->checkSimpleQueueStatus($customer['mikrotik_id']);
                        break;
                }
                
                // Update status di database
                $this->updateCustomerOnlineStatus($customer['id'], $is_online);
                
                if ($is_online) {
                    $online_count++;
                } else {
                    $offline_count++;
                }
                
                $results[] = [
                    'customer_id' => $customer['id'],
                    'name' => $customer['name'],
                    'username' => $customer['username'],
                    'service_type' => $customer['service_type'],
                    'is_online' => $is_online,
                    'last_seen' => $customer['last_seen']
                ];
            }
            
            return [
                'success' => true,
                'data' => $results,
                'summary' => [
                    'total' => count($customers),
                    'online' => $online_count,
                    'offline' => $offline_count
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error checking online status: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Cek status hotspot user
     */
    private function checkHotspotStatus($username) {
        try {
            // Cek active sessions
            $this->mikrotik->write('/ip/hotspot/active/print');
            $this->mikrotik->write('?user=' . $username);
            $response = $this->mikrotik->read();
            
            return !empty($response);
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Cek status PPPoE user
     */
    private function checkPPPoEStatus($username) {
        try {
            // Cek active PPPoE sessions
            $this->mikrotik->write('/ppp/active/print');
            $this->mikrotik->write('?name=' . $username);
            $response = $this->mikrotik->read();
            
            return !empty($response);
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Cek status Simple Queue
     */
    private function checkSimpleQueueStatus($queue_id) {
        try {
            // Cek queue statistics
            $this->mikrotik->write('/queue/simple/print');
            $this->mikrotik->write('?=.id=' . $queue_id);
            $response = $this->mikrotik->read();
            
            if (!empty($response)) {
                // Cek apakah ada traffic
                $queue = $response[0];
                $bytes_in = isset($queue['bytes-in']) ? intval($queue['bytes-in']) : 0;
                $bytes_out = isset($queue['bytes-out']) ? intval($queue['bytes-out']) : 0;
                
                return ($bytes_in > 0 || $bytes_out > 0);
            }
            
            return false;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Update status online customer
     */
    private function updateCustomerOnlineStatus($customer_id, $is_online) {
        $status = $is_online ? 'online' : 'offline';
        $last_seen = $is_online ? date('Y-m-d H:i:s') : null;
        
        $this->db->update('customers', [
            'online_status' => $status,
            'last_seen' => $last_seen
        ], 'id = ?', [$customer_id]);
    }
    
    /**
     * Ambil data pemakaian bandwidth
     */
    public function getBandwidthUsage($customer_id = null, $date_from = null, $date_to = null) {
        try {
            $where_conditions = [];
            $params = [];
            
            if ($customer_id) {
                $where_conditions[] = 'bu.customer_id = ?';
                $params[] = $customer_id;
            }
            
            if ($date_from) {
                $where_conditions[] = 'bu.date >= ?';
                $params[] = $date_from;
            }
            
            if ($date_to) {
                $where_conditions[] = 'bu.date <= ?';
                $params[] = $date_to;
            }
            
            $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
            
            $usage_data = $this->db->query(
                "SELECT bu.*, c.name as customer_name, c.phone, p.name as package_name 
                 FROM bandwidth_usage bu 
                 JOIN customers c ON bu.customer_id = c.id 
                 LEFT JOIN packages p ON c.package_id = p.id 
                 $where_clause 
                 ORDER BY bu.date DESC, bu.customer_id",
                $params
            )->fetchAll();
            
            return [
                'success' => true,
                'data' => $usage_data
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error getting bandwidth usage: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Collect bandwidth usage dari MikroTik
     */
    public function collectBandwidthUsage() {
        try {
            $this->initMikroTik();
            
            // Ambil semua pelanggan aktif
            $customers = $this->db->query(
                "SELECT c.*, cp.service_type, cp.username, cp.mikrotik_id 
                 FROM customers c 
                 JOIN customer_provisioning cp ON c.id = cp.customer_id 
                 WHERE c.status = 'active' AND cp.is_active = 1"
            )->fetchAll();
            
            $collected_count = 0;
            $today = date('Y-m-d');
            
            foreach ($customers as $customer) {
                $usage_data = null;
                
                switch ($customer['service_type']) {
                    case 'hotspot':
                        $usage_data = $this->getHotspotUsage($customer['username']);
                        break;
                    case 'pppoe':
                        $usage_data = $this->getPPPoEUsage($customer['username']);
                        break;
                    case 'simple_queue':
                        $usage_data = $this->getSimpleQueueUsage($customer['mikrotik_id']);
                        break;
                }
                
                if ($usage_data) {
                    // Cek apakah sudah ada data untuk hari ini
                    $existing = $this->db->selectOne('bandwidth_usage', 
                        ['id'], 
                        'customer_id = ? AND date = ?', 
                        [$customer['id'], $today]
                    );
                    
                    if ($existing) {
                        // Update existing record
                        $this->db->update('bandwidth_usage', [
                            'bytes_in' => $usage_data['bytes_in'],
                            'bytes_out' => $usage_data['bytes_out'],
                            'packets_in' => $usage_data['packets_in'],
                            'packets_out' => $usage_data['packets_out'],
                            'updated_at' => date('Y-m-d H:i:s')
                        ], 'id = ?', [$existing['id']]);
                    } else {
                        // Insert new record
                        $this->db->insert('bandwidth_usage', [
                            'customer_id' => $customer['id'],
                            'date' => $today,
                            'bytes_in' => $usage_data['bytes_in'],
                            'bytes_out' => $usage_data['bytes_out'],
                            'packets_in' => $usage_data['packets_in'],
                            'packets_out' => $usage_data['packets_out'],
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                    
                    $collected_count++;
                }
            }
            
            return [
                'success' => true,
                'message' => "Successfully collected bandwidth usage for $collected_count customers",
                'collected_count' => $collected_count
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error collecting bandwidth usage: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Ambil usage data hotspot user
     */
    private function getHotspotUsage($username) {
        try {
            $this->mikrotik->write('/ip/hotspot/user/print');
            $this->mikrotik->write('?name=' . $username);
            $response = $this->mikrotik->read();
            
            if (!empty($response)) {
                $user = $response[0];
                return [
                    'bytes_in' => isset($user['bytes-in']) ? intval($user['bytes-in']) : 0,
                    'bytes_out' => isset($user['bytes-out']) ? intval($user['bytes-out']) : 0,
                    'packets_in' => isset($user['packets-in']) ? intval($user['packets-in']) : 0,
                    'packets_out' => isset($user['packets-out']) ? intval($user['packets-out']) : 0
                ];
            }
            
            return null;
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Ambil usage data PPPoE user
     */
    private function getPPPoEUsage($username) {
        try {
            $this->mikrotik->write('/ppp/secret/print');
            $this->mikrotik->write('?name=' . $username);
            $response = $this->mikrotik->read();
            
            if (!empty($response)) {
                $user = $response[0];
                return [
                    'bytes_in' => isset($user['bytes-in']) ? intval($user['bytes-in']) : 0,
                    'bytes_out' => isset($user['bytes-out']) ? intval($user['bytes-out']) : 0,
                    'packets_in' => isset($user['packets-in']) ? intval($user['packets-in']) : 0,
                    'packets_out' => isset($user['packets-out']) ? intval($user['packets-out']) : 0
                ];
            }
            
            return null;
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Ambil usage data Simple Queue
     */
    private function getSimpleQueueUsage($queue_id) {
        try {
            $this->mikrotik->write('/queue/simple/print');
            $this->mikrotik->write('?=.id=' . $queue_id);
            $response = $this->mikrotik->read();
            
            if (!empty($response)) {
                $queue = $response[0];
                return [
                    'bytes_in' => isset($queue['bytes-in']) ? intval($queue['bytes-in']) : 0,
                    'bytes_out' => isset($queue['bytes-out']) ? intval($queue['bytes-out']) : 0,
                    'packets_in' => isset($queue['packets-in']) ? intval($queue['packets-in']) : 0,
                    'packets_out' => isset($queue['packets-out']) ? intval($queue['packets-out']) : 0
                ];
            }
            
            return null;
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Kirim reminder jatuh tempo
     */
    public function sendDueReminders() {
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
            
            foreach ($invoices as $invoice) {
                $reminder_sent = false;
                
                // Kirim via WhatsApp (jika ada nomor HP)
                if ($invoice['phone']) {
                    $wa_result = $this->sendWhatsAppReminder($invoice);
                    if ($wa_result) $reminder_sent = true;
                }
                
                // Kirim via Email (jika ada email)
                if ($invoice['email']) {
                    $email_result = $this->sendEmailReminder($invoice);
                    if ($email_result) $reminder_sent = true;
                }
                
                // Update status reminder
                if ($reminder_sent) {
                    $this->db->update('invoices', [
                        'reminder_sent' => 1,
                        'reminder_date' => date('Y-m-d H:i:s')
                    ], 'id = ?', [$invoice['id']]);
                    
                    $sent_count++;
                }
            }
            
            return [
                'success' => true,
                'message' => "Successfully sent $sent_count reminders",
                'sent_count' => $sent_count
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error sending reminders: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Kirim reminder via WhatsApp
     */
    private function sendWhatsAppReminder($invoice) {
        try {
            // Implementasi WhatsApp API (contoh menggunakan service pihak ketiga)
            $message = "Halo {$invoice['customer_name']},\n\n";
            $message .= "Tagihan Anda dengan nomor {$invoice['invoice_number']} ";
            $message .= "sebesar Rp " . number_format($invoice['total_amount'], 0, ',', '.') . " ";
            $message .= "akan jatuh tempo pada " . date('d/m/Y', strtotime($invoice['due_date'])) . ".\n\n";
            $message .= "Mohon segera lakukan pembayaran untuk menghindari pemutusan layanan.\n\n";
            $message .= "Terima kasih.\n";
            $message .= "RT/RW Net Management";
            
            // TODO: Implementasi actual WhatsApp API
            // Untuk sementara return true (placeholder)
            
            // Log notification
            $this->logNotification($invoice['customer_id'], 'whatsapp', 'reminder', $message);
            
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Kirim reminder via Email
     */
    private function sendEmailReminder($invoice) {
        try {
            $subject = "Reminder Tagihan RT/RW Net - {$invoice['invoice_number']}";
            
            $message = "<h3>Reminder Tagihan</h3>";
            $message .= "<p>Yth. {$invoice['customer_name']},</p>";
            $message .= "<p>Tagihan Anda dengan nomor <strong>{$invoice['invoice_number']}</strong> ";
            $message .= "sebesar <strong>Rp " . number_format($invoice['total_amount'], 0, ',', '.') . "</strong> ";
            $message .= "akan jatuh tempo pada <strong>" . date('d/m/Y', strtotime($invoice['due_date'])) . "</strong>.</p>";
            $message .= "<p>Mohon segera lakukan pembayaran untuk menghindari pemutusan layanan.</p>";
            $message .= "<p>Terima kasih.</p>";
            $message .= "<p><strong>RT/RW Net Management</strong></p>";
            
            // TODO: Implementasi actual email sending
            // Untuk sementara return true (placeholder)
            
            // Log notification
            $this->logNotification($invoice['customer_id'], 'email', 'reminder', $message);
            
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Log notifikasi
     */
    private function logNotification($customer_id, $type, $category, $message) {
        $this->db->insert('notifications', [
            'customer_id' => $customer_id,
            'type' => $type,
            'category' => $category,
            'message' => $message,
            'sent_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Ambil statistik monitoring
     */
    public function getMonitoringStats() {
        try {
            // Total customers
            $total_customers = $this->db->selectOne('customers', 
                ['COUNT(*) as count'], 
                'status = "active"'
            )['count'];
            
            // Online customers
            $online_customers = $this->db->selectOne('customers', 
                ['COUNT(*) as count'], 
                'status = "active" AND online_status = "online"'
            )['count'];
            
            // Offline customers
            $offline_customers = $total_customers - $online_customers;
            
            // Bandwidth usage today
            $today_usage = $this->db->query(
                "SELECT SUM(bytes_in + bytes_out) as total_bytes 
                 FROM bandwidth_usage 
                 WHERE date = ?",
                [date('Y-m-d')]
            )->fetch();
            
            $total_bytes = isset($today_usage['total_bytes']) ? $today_usage['total_bytes'] : 0;
            
            // Pending reminders
            $pending_reminders = $this->db->selectOne('invoices', 
                ['COUNT(*) as count'], 
                'status = "pending" AND due_date <= ? AND reminder_sent = 0',
                [date('Y-m-d', strtotime('+3 days'))]
            )['count'];
            
            return [
                'success' => true,
                'stats' => [
                    'total_customers' => $total_customers,
                    'online_customers' => $online_customers,
                    'offline_customers' => $offline_customers,
                    'online_percentage' => $total_customers > 0 ? round(($online_customers / $total_customers) * 100, 1) : 0,
                    'total_bandwidth_today' => $total_bytes,
                    'total_bandwidth_today_mb' => round($total_bytes / (1024 * 1024), 2),
                    'pending_reminders' => $pending_reminders
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error getting monitoring stats: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Ambil top users berdasarkan pemakaian bandwidth
     */
    public function getTopUsers($limit = 10, $date_from = null, $date_to = null) {
        try {
            $date_from = isset($date_from) ? $date_from : date('Y-m-01'); // Awal bulan
        $date_to = isset($date_to) ? $date_to : date('Y-m-d'); // Hari ini
            
            $top_users = $this->db->query(
                "SELECT c.name, c.phone, p.name as package_name,
                        SUM(bu.bytes_in + bu.bytes_out) as total_bytes,
                        ROUND(SUM(bu.bytes_in + bu.bytes_out) / (1024 * 1024), 2) as total_mb
                 FROM bandwidth_usage bu
                 JOIN customers c ON bu.customer_id = c.id
                 LEFT JOIN packages p ON c.package_id = p.id
                 WHERE bu.date BETWEEN ? AND ?
                 GROUP BY bu.customer_id
                 ORDER BY total_bytes DESC
                 LIMIT ?",
                [$date_from, $date_to, $limit]
            )->fetchAll();
            
            return [
                'success' => true,
                'data' => $top_users,
                'period' => [
                    'from' => $date_from,
                    'to' => $date_to
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error getting top users: ' . $e->getMessage()
            ];
        }
    }
}