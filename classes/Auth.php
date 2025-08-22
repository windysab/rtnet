<?php
/**
 * Authentication Class untuk Aplikasi RT/RW Net
 * 
 * Class ini menangani autentikasi admin, session management,
 * dan authorization untuk akses ke sistem
 * 
 * @author RT/RW Net System
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';

class Auth {
    private $db;
    private $session_timeout = 3600; // 1 jam
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        
        // Start session jika belum dimulai
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Login admin
     */
    public function login($username, $password) {
        try {
            $sql = "SELECT * FROM admins WHERE username = ? AND is_active = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
            
            if ($admin && password_verify($password, $admin['password'])) {
                // Set session
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_name'] = $admin['full_name'];
                $_SESSION['admin_role'] = $admin['role'];
                $_SESSION['login_time'] = time();
                $_SESSION['last_activity'] = time();
                
                // Update last login
                $this->updateLastLogin($admin['id']);
                
                return [
                    'success' => true,
                    'message' => 'Login berhasil',
                    'admin' => [
                        'id' => $admin['id'],
                        'username' => $admin['username'],
                        'full_name' => $admin['full_name'],
                        'role' => $admin['role']
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Username atau password salah'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Logout admin
     */
    public function logout() {
        // Hapus semua session
        session_unset();
        session_destroy();
        
        return [
            'success' => true,
            'message' => 'Logout berhasil'
        ];
    }
    
    /**
     * Cek apakah admin sudah login
     */
    public function isLoggedIn() {
        if (!isset($_SESSION['admin_id']) || !isset($_SESSION['last_activity'])) {
            return false;
        }
        
        // Cek timeout session
        if (time() - $_SESSION['last_activity'] > $this->session_timeout) {
            $this->logout();
            return false;
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    /**
     * Get data admin yang sedang login
     */
    public function getCurrentAdmin() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['admin_id'],
            'username' => $_SESSION['admin_username'],
            'full_name' => $_SESSION['admin_name'],
            'role' => $_SESSION['admin_role']
        ];
    }
    
    /**
     * Cek permission berdasarkan role
     */
    public function hasPermission($permission) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        $role = $_SESSION['admin_role'];
        
        // Super admin memiliki semua permission
        if ($role === 'super_admin') {
            return true;
        }
        
        // Permission mapping
        $permissions = [
            'admin' => [
                'view_customers', 'add_customers', 'edit_customers',
                'view_packages', 'add_packages', 'edit_packages',
                'view_invoices', 'add_invoices', 'edit_invoices',
                'view_payments', 'add_payments',
                'view_reports', 'mikrotik_provision'
            ],
            'operator' => [
                'view_customers', 'add_customers', 'edit_customers',
                'view_packages', 'view_invoices', 'add_payments',
                'view_reports'
            ]
        ];
        
        return isset($permissions[$role]) && in_array($permission, $permissions[$role]);
    }
    
    /**
     * Require login - redirect jika belum login
     */
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: /rtnet/login.php');
            exit;
        }
    }
    
    /**
     * Require permission - redirect jika tidak ada permission
     */
    public function requirePermission($permission) {
        $this->requireLogin();
        
        if (!$this->hasPermission($permission)) {
            header('Location: /rtnet/unauthorized.php');
            exit;
        }
    }
    
    /**
     * Update last login time
     */
    private function updateLastLogin($admin_id) {
        try {
            $sql = "UPDATE admins SET updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$admin_id]);
        } catch (Exception $e) {
            // Log error tapi jangan stop proses login
            error_log("Failed to update last login: " . $e->getMessage());
        }
    }
    
    /**
     * Change password
     */
    public function changePassword($admin_id, $old_password, $new_password) {
        try {
            // Validasi password lama
            $sql = "SELECT password FROM admins WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$admin_id]);
            $admin = $stmt->fetch();
            
            if (!$admin || !password_verify($old_password, $admin['password'])) {
                return [
                    'success' => false,
                    'message' => 'Password lama tidak sesuai'
                ];
            }
            
            // Validasi password baru
            if (strlen($new_password) < 6) {
                return [
                    'success' => false,
                    'message' => 'Password baru minimal 6 karakter'
                ];
            }
            
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE admins SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$hashed_password, $admin_id]);
            
            return [
                'success' => true,
                'message' => 'Password berhasil diubah'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Create new admin (hanya super_admin)
     */
    public function createAdmin($data) {
        if (!$this->hasPermission('manage_admins')) {
            return [
                'success' => false,
                'message' => 'Tidak memiliki permission untuk membuat admin'
            ];
        }
        
        try {
            // Validasi data
            $required_fields = ['username', 'password', 'full_name', 'role'];
            foreach ($required_fields as $field) {
                if (empty($data[$field])) {
                    return [
                        'success' => false,
                        'message' => "Field {$field} wajib diisi"
                    ];
                }
            }
            
            // Cek username sudah ada
            $sql = "SELECT id FROM admins WHERE username = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$data['username']]);
            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Username sudah digunakan'
                ];
            }
            
            // Hash password
            $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
            
            // Insert admin baru
            $sql = "INSERT INTO admins (username, password, full_name, email, phone, role, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, 1)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['username'],
                $hashed_password,
                $data['full_name'],
                isset($data['email']) ? $data['email'] : null,
                isset($data['phone']) ? $data['phone'] : null,
                $data['role']
            ]);
            
            return [
                'success' => true,
                'message' => 'Admin berhasil dibuat',
                'admin_id' => $this->db->lastInsertId()
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            if (function_exists('openssl_random_pseudo_bytes')) {
                $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
            } else {
                // Fallback for older PHP versions
                $_SESSION['csrf_token'] = md5(uniqid(mt_rand(), true));
            }
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     */
    public function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Get session timeout remaining
     */
    public function getSessionTimeoutRemaining() {
        if (!$this->isLoggedIn()) {
            return 0;
        }
        
        $remaining = $this->session_timeout - (time() - $_SESSION['last_activity']);
        return max(0, $remaining);
    }
}
?>