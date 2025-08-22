<?php
/**
 * Class Provisioning - RT/RW Net
 * 
 * Handles automatic provisioning to MikroTik RouterOS
 * Supports Hotspot, PPPoE, Simple Queue, and MAC binding
 * 
 * @author RT/RW Net System
 * @version 1.0
 */

require_once 'MikrotikAPI.php';
require_once __DIR__ . '/../config/database.php';

class Provisioning {
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
        $config = $this->db->select('mikrotik_config', [], 'is_active = 1 LIMIT 1');
        
        if (empty($config)) {
            throw new Exception('Konfigurasi MikroTik tidak ditemukan');
        }
        
        $config = $config[0];
        
        $this->mikrotik = new MikrotikAPI();
        $connected = $this->mikrotik->connect(
            $config['host'],
            $config['username'],
            $config['password'],
            $config['port']
        );
        
        if (!$connected) {
            throw new Exception('Gagal terhubung ke MikroTik: ' . $this->mikrotik->getError());
        }
        
        return true;
    }
    
    /**
     * Create customer account on MikroTik
     */
    public function createCustomerAccount($customer_id, $package_id, $service_type = 'hotspot') {
        try {
            $this->initMikroTik();
            
            // Get customer data
            $customer = $this->db->select('customers', [], 'id = ?', [$customer_id]);
            if (empty($customer)) {
                throw new Exception('Customer tidak ditemukan');
            }
            $customer = $customer[0];
            
            // Get package data
            $package = $this->db->select('packages', [], 'id = ?', [$package_id]);
            if (empty($package)) {
                throw new Exception('Paket tidak ditemukan');
            }
            $package = $package[0];
            
            // Generate username and password
            $username = $this->generateUsername($customer);
            $password = $this->generatePassword();
            
            $result = ['success' => false, 'message' => '', 'data' => []];
            
            switch (strtolower($service_type)) {
                case 'hotspot':
                    $result = $this->createHotspotUser($customer, $package, $username, $password);
                    break;
                    
                case 'pppoe':
                    $result = $this->createPPPoEUser($customer, $package, $username, $password);
                    break;
                    
                case 'simple_queue':
                    $result = $this->createSimpleQueue($customer, $package, $username);
                    break;
                    
                default:
                    throw new Exception('Tipe layanan tidak didukung');
            }
            
            if ($result['success']) {
                // Save provisioning data to database
                $this->saveProvisioningData($customer_id, $package_id, $service_type, $result['data']);
                
                // Log activity
                $this->logActivity($customer_id, 'provision_create', 
                    "Account {$service_type} dibuat: {$username}");
            }
            
            return $result;
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create Hotspot user
     */
    private function createHotspotUser($customer, $package, $username, $password) {
        // Create user profile first
        $profile_name = $package['profile_name'] ?: $package['name'];
        $this->createHotspotProfile($package, $profile_name);
        
        // Create hotspot user
        $user_data = [
            'name' => $username,
            'password' => $password,
            'profile' => $profile_name,
            'comment' => "Customer: {$customer['name']} - {$customer['phone']}"
        ];
        
        if ($customer['mac_address']) {
            $user_data['mac-address'] = $customer['mac_address'];
        }
        
        $result = $this->mikrotik->addHotspotUser($user_data);
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'Hotspot user berhasil dibuat',
                'data' => [
                    'username' => $username,
                    'password' => $password,
                    'profile' => $profile_name,
                    'service_type' => 'hotspot'
                ]
            ];
        } else {
            throw new Exception('Gagal membuat hotspot user: ' . $this->mikrotik->getError());
        }
    }
    
    /**
     * Create PPPoE user
     */
    private function createPPPoEUser($customer, $package, $username, $password) {
        // Create PPPoE profile first
        $profile_name = $package['profile_name'] ?: $package['name'];
        $this->createPPPoEProfile($package, $profile_name);
        
        // Create PPPoE secret
        $secret_data = [
            'name' => $username,
            'password' => $password,
            'profile' => $profile_name,
            'service' => 'ppp',
            'comment' => "Customer: {$customer['name']} - {$customer['phone']}"
        ];
        
        if ($customer['ip_address']) {
            $secret_data['remote-address'] = $customer['ip_address'];
        }
        
        $result = $this->mikrotik->addPPPoESecret($secret_data);
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'PPPoE user berhasil dibuat',
                'data' => [
                    'username' => $username,
                    'password' => $password,
                    'profile' => $profile_name,
                    'service_type' => 'pppoe'
                ]
            ];
        } else {
            throw new Exception('Gagal membuat PPPoE user: ' . $this->mikrotik->getError());
        }
    }
    
    /**
     * Create Simple Queue
     */
    private function createSimpleQueue($customer, $package, $username) {
        if (!$customer['ip_address']) {
            throw new Exception('IP address customer diperlukan untuk Simple Queue');
        }
        
        $queue_data = [
            'name' => $username,
            'target' => $customer['ip_address'],
            'max-limit' => $package['bandwidth_up'] . 'M/' . $package['bandwidth_down'] . 'M',
            'priority' => $package['priority'] ?: '8',
            'comment' => "Customer: {$customer['name']} - {$customer['phone']}"
        ];
        
        // Add burst configuration if available
        if ($package['burst_limit_up'] && $package['burst_limit_down']) {
            $queue_data['burst-limit'] = $package['burst_limit_up'] . 'M/' . $package['burst_limit_down'] . 'M';
        }
        
        if ($package['burst_threshold_up'] && $package['burst_threshold_down']) {
            $queue_data['burst-threshold'] = $package['burst_threshold_up'] . 'M/' . $package['burst_threshold_down'] . 'M';
        }
        
        if ($package['burst_time']) {
            $queue_data['burst-time'] = $package['burst_time'] . 's';
        }
        
        if ($package['limit_at_up'] && $package['limit_at_down']) {
            $queue_data['limit-at'] = $package['limit_at_up'] . 'M/' . $package['limit_at_down'] . 'M';
        }
        
        $result = $this->mikrotik->addSimpleQueue($queue_data);
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'Simple Queue berhasil dibuat',
                'data' => [
                    'queue_name' => $username,
                    'target_ip' => $customer['ip_address'],
                    'bandwidth' => $package['bandwidth_up'] . '/' . $package['bandwidth_down'] . ' Mbps',
                    'service_type' => 'simple_queue'
                ]
            ];
        } else {
            throw new Exception('Gagal membuat Simple Queue: ' . $this->mikrotik->getError());
        }
    }
    
    /**
     * Create Hotspot profile
     */
    private function createHotspotProfile($package, $profile_name) {
        $profile_data = [
            'name' => $profile_name,
            'rate-limit' => $package['bandwidth_up'] . 'M/' . $package['bandwidth_down'] . 'M',
            'session-timeout' => '0',
            'idle-timeout' => '0',
            'keepalive-timeout' => '2m'
        ];
        
        // Add quota if specified
        if ($package['quota_mb']) {
            $profile_data['shared-users'] = '1';
        }
        
        return $this->mikrotik->addHotspotProfile($profile_data);
    }
    
    /**
     * Create PPPoE profile
     */
    private function createPPPoEProfile($package, $profile_name) {
        $profile_data = [
            'name' => $profile_name,
            'rate-limit' => $package['bandwidth_up'] . 'M/' . $package['bandwidth_down'] . 'M',
            'session-timeout' => '0',
            'idle-timeout' => '0'
        ];
        
        if ($package['pool_name']) {
            $profile_data['local-address'] = $package['pool_name'];
        }
        
        return $this->mikrotik->addPPPoEProfile($profile_data);
    }
    
    /**
     * Update customer account
     */
    public function updateCustomerAccount($customer_id, $package_id) {
        try {
            $this->initMikroTik();
            
            // Get current provisioning data
            $provisioning = $this->db->select('customer_provisioning', [], 
                'customer_id = ? AND is_active = 1', [$customer_id]);
            
            if (empty($provisioning)) {
                throw new Exception('Data provisioning tidak ditemukan');
            }
            
            $provisioning = $provisioning[0];
            
            // Get new package data
            $package = $this->db->select('packages', [], 'id = ?', [$package_id]);
            if (empty($package)) {
                throw new Exception('Paket tidak ditemukan');
            }
            $package = $package[0];
            
            $result = false;
            
            switch ($provisioning['service_type']) {
                case 'hotspot':
                    $result = $this->updateHotspotUser($provisioning, $package);
                    break;
                    
                case 'pppoe':
                    $result = $this->updatePPPoEUser($provisioning, $package);
                    break;
                    
                case 'simple_queue':
                    $result = $this->updateSimpleQueue($provisioning, $package);
                    break;
            }
            
            if ($result) {
                // Update provisioning data
                $this->db->update('customer_provisioning', 
                    ['package_id' => $package_id, 'updated_at' => date('Y-m-d H:i:s')],
                    'id = ?', [$provisioning['id']]
                );
                
                // Log activity
                $this->logActivity($customer_id, 'provision_update', 
                    "Account {$provisioning['service_type']} diupdate ke paket: {$package['name']}");
                
                return [
                    'success' => true,
                    'message' => 'Account berhasil diupdate'
                ];
            } else {
                throw new Exception('Gagal mengupdate account: ' . $this->mikrotik->getError());
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete customer account
     */
    public function deleteCustomerAccount($customer_id) {
        try {
            $this->initMikroTik();
            
            // Get provisioning data
            $provisioning = $this->db->select('customer_provisioning', [], 
                'customer_id = ? AND is_active = 1', [$customer_id]);
            
            if (empty($provisioning)) {
                throw new Exception('Data provisioning tidak ditemukan');
            }
            
            $provisioning = $provisioning[0];
            
            $result = false;
            
            switch ($provisioning['service_type']) {
                case 'hotspot':
                    $result = $this->mikrotik->removeHotspotUser($provisioning['username']);
                    break;
                    
                case 'pppoe':
                    $result = $this->mikrotik->removePPPoESecret($provisioning['username']);
                    break;
                    
                case 'simple_queue':
                    $result = $this->mikrotik->removeSimpleQueue($provisioning['username']);
                    break;
            }
            
            if ($result) {
                // Deactivate provisioning data
                $this->db->update('customer_provisioning', 
                    ['is_active' => 0, 'deleted_at' => date('Y-m-d H:i:s')],
                    'id = ?', [$provisioning['id']]
                );
                
                // Log activity
                $this->logActivity($customer_id, 'provision_delete', 
                    "Account {$provisioning['service_type']} dihapus: {$provisioning['username']}");
                
                return [
                    'success' => true,
                    'message' => 'Account berhasil dihapus'
                ];
            } else {
                throw new Exception('Gagal menghapus account: ' . $this->mikrotik->getError());
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Enable/Disable customer account
     */
    public function toggleCustomerAccount($customer_id, $enable = true) {
        try {
            $this->initMikroTik();
            
            // Get provisioning data
            $provisioning = $this->db->select('customer_provisioning', [], 
                'customer_id = ? AND is_active = 1', [$customer_id]);
            
            if (empty($provisioning)) {
                throw new Exception('Data provisioning tidak ditemukan');
            }
            
            $provisioning = $provisioning[0];
            
            $result = false;
            
            switch ($provisioning['service_type']) {
                case 'hotspot':
                    $result = $this->mikrotik->toggleHotspotUser($provisioning['username'], $enable);
                    break;
                    
                case 'pppoe':
                    $result = $this->mikrotik->togglePPPoESecret($provisioning['username'], $enable);
                    break;
                    
                case 'simple_queue':
                    $result = $this->mikrotik->toggleSimpleQueue($provisioning['username'], $enable);
                    break;
            }
            
            if ($result) {
                // Log activity
                $action = $enable ? 'enable' : 'disable';
                $this->logActivity($customer_id, "provision_{$action}", 
                    "Account {$provisioning['service_type']} " . ($enable ? 'diaktifkan' : 'dinonaktifkan'));
                
                return [
                    'success' => true,
                    'message' => 'Status account berhasil diubah'
                ];
            } else {
                throw new Exception('Gagal mengubah status account: ' . $this->mikrotik->getError());
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get customer provisioning data
     */
    public function getCustomerProvisioning($customer_id) {
        return $this->db->select('customer_provisioning cp', 
            ['cp.*', 'p.name as package_name', 'c.name as customer_name'],
            'cp.customer_id = ? AND cp.is_active = 1',
            [$customer_id],
            'LEFT JOIN packages p ON cp.package_id = p.id ' .
            'LEFT JOIN customers c ON cp.customer_id = c.id'
        );
    }
    
    /**
     * Get all provisioning data with pagination
     */
    public function getAllProvisioning($page = 1, $limit = 20, $search = '', $service_type = '') {
        $offset = ($page - 1) * $limit;
        $where_conditions = ['cp.is_active = 1'];
        $params = [];
        
        if ($search) {
            $where_conditions[] = '(c.name LIKE ? OR cp.username LIKE ? OR c.phone LIKE ?)';
            $search_param = "%{$search}%";
            $params = array_merge($params, [$search_param, $search_param, $search_param]);
        }
        
        if ($service_type) {
            $where_conditions[] = 'cp.service_type = ?';
            $params[] = $service_type;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get total count
        $total_query = "SELECT COUNT(*) as total FROM customer_provisioning cp 
                       LEFT JOIN customers c ON cp.customer_id = c.id 
                       WHERE {$where_clause}";
        $total_result = $this->db->select($total_query, $params);
        $total = $total_result[0]['total'];
        
        // Get data
        $data_query = "SELECT cp.*, c.name as customer_name, c.phone, p.name as package_name 
                      FROM customer_provisioning cp 
                      LEFT JOIN customers c ON cp.customer_id = c.id 
                      LEFT JOIN packages p ON cp.package_id = p.id 
                      WHERE {$where_clause} 
                      ORDER BY cp.created_at DESC 
                      LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        $data = $this->db->select($data_query, $params);
        
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ];
    }
    
    /**
     * Sync all customers with MikroTik
     */
    public function syncAllCustomers() {
        try {
            $this->initMikroTik();
            
            $success_count = 0;
            $error_count = 0;
            $errors = [];
            
            // Get all active subscriptions
            $subscriptions = $this->db->select(
                "SELECT s.*, c.name as customer_name, p.name as package_name 
                 FROM subscriptions s 
                 LEFT JOIN customers c ON s.customer_id = c.id 
                 LEFT JOIN packages p ON s.package_id = p.id 
                 WHERE s.status = 'active' AND s.end_date >= CURDATE()"
            );
            
            foreach ($subscriptions as $subscription) {
                // Check if provisioning already exists
                $existing = $this->db->select('customer_provisioning', [], 
                    'customer_id = ? AND is_active = 1', [$subscription['customer_id']]);
                
                if (empty($existing)) {
                    // Create new provisioning
                    $result = $this->createCustomerAccount(
                        $subscription['customer_id'], 
                        $subscription['package_id'], 
                        'hotspot' // Default to hotspot
                    );
                    
                    if ($result['success']) {
                        $success_count++;
                    } else {
                        $error_count++;
                        $errors[] = "Customer {$subscription['customer_name']}: {$result['message']}";
                    }
                }
            }
            
            return [
                'success' => true,
                'message' => "Sync selesai. Berhasil: {$success_count}, Error: {$error_count}",
                'data' => [
                    'success_count' => $success_count,
                    'error_count' => $error_count,
                    'errors' => $errors
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate username for customer
     */
    private function generateUsername($customer) {
        $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $customer['name']));
        $base = substr($base, 0, 8);
        
        // Add customer ID to ensure uniqueness
        return $base . $customer['id'];
    }
    
    /**
     * Generate random password
     */
    private function generatePassword($length = 8) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        return substr(str_shuffle($chars), 0, $length);
    }
    
    /**
     * Save provisioning data to database
     */
    private function saveProvisioningData($customer_id, $package_id, $service_type, $data) {
        $provisioning_data = [
            'customer_id' => $customer_id,
            'package_id' => $package_id,
            'service_type' => $service_type,
            'username' => $data['username'],
            'password' => $data['password'],
            'profile_name' => isset($data['profile']) ? $data['profile'] : (isset($data['queue_name']) ? $data['queue_name'] : ''),
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->db->insert('customer_provisioning', $provisioning_data);
    }
    
    /**
     * Update Hotspot user
     */
    private function updateHotspotUser($provisioning, $package) {
        $profile_name = $package['profile_name'] ?: $package['name'];
        $this->createHotspotProfile($package, $profile_name);
        
        return $this->mikrotik->updateHotspotUser($provisioning['username'], [
            'profile' => $profile_name
        ]);
    }
    
    /**
     * Update PPPoE user
     */
    private function updatePPPoEUser($provisioning, $package) {
        $profile_name = $package['profile_name'] ?: $package['name'];
        $this->createPPPoEProfile($package, $profile_name);
        
        return $this->mikrotik->updatePPPoESecret($provisioning['username'], [
            'profile' => $profile_name
        ]);
    }
    
    /**
     * Update Simple Queue
     */
    private function updateSimpleQueue($provisioning, $package) {
        $queue_data = [
            'max-limit' => $package['bandwidth_up'] . 'M/' . $package['bandwidth_down'] . 'M',
            'priority' => $package['priority'] ?: '8'
        ];
        
        if ($package['burst_limit_up'] && $package['burst_limit_down']) {
            $queue_data['burst-limit'] = $package['burst_limit_up'] . 'M/' . $package['burst_limit_down'] . 'M';
        }
        
        if ($package['limit_at_up'] && $package['limit_at_down']) {
            $queue_data['limit-at'] = $package['limit_at_up'] . 'M/' . $package['limit_at_down'] . 'M';
        }
        
        return $this->mikrotik->updateSimpleQueue($provisioning['username'], $queue_data);
    }
    
    /**
     * Log activity
     */
    private function logActivity($customer_id, $action, $description) {
        $this->db->insert('customer_activity_logs', [
            'customer_id' => $customer_id,
            'action' => $action,
            'description' => $description,
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Get provisioning statistics
     */
    public function getStatistics() {
        $stats = [];
        
        // Total provisioned customers
        $total = $this->db->select(
            "SELECT COUNT(*) as total FROM customer_provisioning WHERE is_active = 1"
        );
        $stats['total_provisioned'] = $total[0]['total'];
        
        // By service type
        $by_service = $this->db->select(
            "SELECT service_type, COUNT(*) as count 
             FROM customer_provisioning 
             WHERE is_active = 1 
             GROUP BY service_type"
        );
        
        $stats['by_service_type'] = [];
        foreach ($by_service as $service) {
            $stats['by_service_type'][$service['service_type']] = $service['count'];
        }
        
        // Recent provisioning
        $recent = $this->db->select(
            "SELECT COUNT(*) as count 
             FROM customer_provisioning 
             WHERE is_active = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        $stats['recent_provisioned'] = $recent[0]['count'];
        
        return $stats;
    }
    
    /**
     * Test MikroTik connection
     */
    public function testConnection() {
        try {
            $this->initMikroTik();
            
            // Test basic command
            $result = $this->mikrotik->executeCommand('/system/identity/print');
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Koneksi MikroTik berhasil',
                    'data' => $result
                ];
            } else {
                throw new Exception('Gagal menjalankan test command');
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Cleanup inactive provisioning
     */
    public function cleanupInactiveProvisioning() {
        try {
            $this->initMikroTik();
            
            // Get inactive subscriptions
            $inactive_subscriptions = $this->db->select(
                "SELECT cp.* FROM customer_provisioning cp 
                 LEFT JOIN subscriptions s ON cp.customer_id = s.customer_id 
                 WHERE cp.is_active = 1 AND (s.status != 'active' OR s.end_date < CURDATE())"
            );
            
            $cleaned_count = 0;
            
            foreach ($inactive_subscriptions as $provisioning) {
                $result = $this->deleteCustomerAccount($provisioning['customer_id']);
                if ($result['success']) {
                    $cleaned_count++;
                }
            }
            
            return [
                'success' => true,
                'message' => "Cleanup selesai. {$cleaned_count} account dihapus",
                'data' => ['cleaned_count' => $cleaned_count]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
?>