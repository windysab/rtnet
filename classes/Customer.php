<?php
/**
 * Customer Class untuk Aplikasi RT/RW Net
 * 
 * Class ini menangani semua operasi CRUD untuk data pelanggan
 * termasuk upload foto KTP dan rumah
 * 
 * @author RT/RW Net System
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';

class Customer {
    private $db;
    private $upload_dir = 'uploads/customers/';
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        
        // Buat direktori upload jika belum ada
        if (!file_exists($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }
    }
    
    /**
     * Generate customer code
     */
    private function generateCustomerCode() {
        $prefix = 'CUST';
        $year = date('Y');
        
        // Get last customer number for this year
        $sql = "SELECT customer_code FROM customers WHERE customer_code LIKE ? ORDER BY customer_code DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$prefix . $year . '%']);
        $last_customer = $stmt->fetch();
        
        if ($last_customer) {
            $last_number = intval(substr($last_customer['customer_code'], -4));
            $new_number = $last_number + 1;
        } else {
            $new_number = 1;
        }
        
        return $prefix . $year . str_pad($new_number, 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * Upload file (KTP atau foto rumah)
     */
    private function uploadFile($file, $type, $customer_code) {
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return null;
        }
        
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        // Validasi tipe file
        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception("Tipe file tidak diizinkan. Gunakan JPG, JPEG, atau PNG.");
        }
        
        // Validasi ukuran file
        if ($file['size'] > $max_size) {
            throw new Exception("Ukuran file terlalu besar. Maksimal 5MB.");
        }
        
        // Generate nama file
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $customer_code . '_' . $type . '_' . time() . '.' . $extension;
        $filepath = $this->upload_dir . $filename;
        
        // Upload file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return $filename;
        } else {
            throw new Exception("Gagal mengupload file.");
        }
    }
    
    /**
     * Tambah pelanggan baru
     */
    public function create($data, $files = []) {
        try {
            $this->db->beginTransaction();
            
            // Validasi data wajib
            $required_fields = ['full_name', 'phone', 'address'];
            foreach ($required_fields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Field {$field} wajib diisi");
                }
            }
            
            // Generate customer code
            $customer_code = $this->generateCustomerCode();
            
            // Upload files jika ada
            $ktp_photo = null;
            $house_photo = null;
            
            if (isset($files['ktp_photo'])) {
                $ktp_photo = $this->uploadFile($files['ktp_photo'], 'ktp', $customer_code);
            }
            
            if (isset($files['house_photo'])) {
                $house_photo = $this->uploadFile($files['house_photo'], 'house', $customer_code);
            }
            
            // Insert customer
            $sql = "INSERT INTO customers (
                        customer_code, full_name, email, phone, address, rt_rw, 
                        kelurahan, kecamatan, kota, kode_pos, latitude, longitude,
                        ktp_number, ktp_photo, house_photo, installation_date, 
                        status, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $customer_code,
                $data['full_name'],
                isset($data['email']) ? $data['email'] : null,
                isset($data['phone']) ? $data['phone'] : null,
                isset($data['address']) ? $data['address'] : null,
                isset($data['rt_rw']) ? $data['rt_rw'] : null,
                isset($data['kelurahan']) ? $data['kelurahan'] : null,
                isset($data['kecamatan']) ? $data['kecamatan'] : null,
                isset($data['kota']) ? $data['kota'] : null,
                isset($data['kode_pos']) ? $data['kode_pos'] : null,
                isset($data['latitude']) ? $data['latitude'] : null,
                isset($data['longitude']) ? $data['longitude'] : null,
                isset($data['ktp_number']) ? $data['ktp_number'] : null,
                $ktp_photo,
                $house_photo,
                isset($data['installation_date']) ? $data['installation_date'] : date('Y-m-d'),
                isset($data['status']) ? $data['status'] : 'active',
                isset($data['notes']) ? $data['notes'] : null
            ]);
            
            $customer_id = $this->db->lastInsertId();
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Pelanggan berhasil ditambahkan',
                'customer_id' => $customer_id,
                'customer_code' => $customer_code
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            
            // Hapus file yang sudah diupload jika ada error
            if (isset($ktp_photo) && file_exists($this->upload_dir . $ktp_photo)) {
                unlink($this->upload_dir . $ktp_photo);
            }
            if (isset($house_photo) && file_exists($this->upload_dir . $house_photo)) {
                unlink($this->upload_dir . $house_photo);
            }
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update data pelanggan
     */
    public function update($id, $data, $files = []) {
        try {
            $this->db->beginTransaction();
            
            // Get existing customer data
            $existing = $this->getById($id);
            if (!$existing) {
                throw new Exception("Pelanggan tidak ditemukan");
            }
            
            // Upload files baru jika ada
            $ktp_photo = $existing['ktp_photo'];
            $house_photo = $existing['house_photo'];
            
            if (isset($files['ktp_photo']) && !empty($files['ktp_photo']['tmp_name'])) {
                // Hapus file lama
                if ($ktp_photo && file_exists($this->upload_dir . $ktp_photo)) {
                    unlink($this->upload_dir . $ktp_photo);
                }
                $ktp_photo = $this->uploadFile($files['ktp_photo'], 'ktp', $existing['customer_code']);
            }
            
            if (isset($files['house_photo']) && !empty($files['house_photo']['tmp_name'])) {
                // Hapus file lama
                if ($house_photo && file_exists($this->upload_dir . $house_photo)) {
                    unlink($this->upload_dir . $house_photo);
                }
                $house_photo = $this->uploadFile($files['house_photo'], 'house', $existing['customer_code']);
            }
            
            // Update customer
            $sql = "UPDATE customers SET 
                        full_name = ?, email = ?, phone = ?, address = ?, rt_rw = ?,
                        kelurahan = ?, kecamatan = ?, kota = ?, kode_pos = ?,
                        latitude = ?, longitude = ?, ktp_number = ?, ktp_photo = ?,
                        house_photo = ?, status = ?, notes = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['full_name'],
                isset($data['email']) ? $data['email'] : null,
                $data['phone'],
                $data['address'],
                isset($data['rt_rw']) ? $data['rt_rw'] : null,
                isset($data['kelurahan']) ? $data['kelurahan'] : null,
                isset($data['kecamatan']) ? $data['kecamatan'] : null,
                isset($data['kota']) ? $data['kota'] : null,
                isset($data['kode_pos']) ? $data['kode_pos'] : null,
                isset($data['latitude']) ? $data['latitude'] : null,
                isset($data['longitude']) ? $data['longitude'] : null,
                isset($data['ktp_number']) ? $data['ktp_number'] : null,
                $ktp_photo,
                $house_photo,
                isset($data['status']) ? $data['status'] : 'active',
                isset($data['notes']) ? $data['notes'] : null,
                $id
            ]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Data pelanggan berhasil diupdate'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Hapus pelanggan
     */
    public function delete($id) {
        try {
            $this->db->beginTransaction();
            
            // Get customer data untuk hapus file
            $customer = $this->getById($id);
            if (!$customer) {
                throw new Exception("Pelanggan tidak ditemukan");
            }
            
            // Cek apakah ada subscription aktif
            $sql = "SELECT COUNT(*) as count FROM subscriptions WHERE customer_id = ? AND status = 'active'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $active_subscriptions = $stmt->fetch()['count'];
            
            if ($active_subscriptions > 0) {
                throw new Exception("Tidak dapat menghapus pelanggan yang memiliki subscription aktif");
            }
            
            // Hapus customer
            $sql = "DELETE FROM customers WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            
            // Hapus file foto
            if ($customer['ktp_photo'] && file_exists($this->upload_dir . $customer['ktp_photo'])) {
                unlink($this->upload_dir . $customer['ktp_photo']);
            }
            if ($customer['house_photo'] && file_exists($this->upload_dir . $customer['house_photo'])) {
                unlink($this->upload_dir . $customer['house_photo']);
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Pelanggan berhasil dihapus'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get pelanggan by ID
     */
    public function getById($id) {
        $sql = "SELECT c.*, 
                       s.username as subscription_username,
                       s.status as subscription_status,
                       p.name as package_name
                FROM customers c
                LEFT JOIN subscriptions s ON c.id = s.customer_id AND s.status = 'active'
                LEFT JOIN packages p ON s.package_id = p.id
                WHERE c.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Get pelanggan by customer code
     */
    public function getByCode($customer_code) {
        $sql = "SELECT * FROM customers WHERE customer_code = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$customer_code]);
        return $stmt->fetch();
    }
    
    /**
     * Get semua pelanggan dengan pagination dan filter
     */
    public function getAll($page = 1, $limit = 20, $search = '', $status = '') {
        $offset = ($page - 1) * $limit;
        
        // Build WHERE clause
        $where_conditions = [];
        $params = [];
        
        if (!empty($search)) {
            $where_conditions[] = "(c.full_name LIKE ? OR c.customer_code LIKE ? OR c.phone LIKE ?)";
            $search_param = "%{$search}%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        if (!empty($status)) {
            $where_conditions[] = "c.status = ?";
            $params[] = $status;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count
        $count_sql = "SELECT COUNT(*) as total FROM customers c {$where_clause}";
        $stmt = $this->db->prepare($count_sql);
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];
        
        // Get data
        $sql = "SELECT c.*, 
                       s.username as subscription_username,
                       s.status as subscription_status,
                       p.name as package_name
                FROM customers c
                LEFT JOIN subscriptions s ON c.id = s.customer_id AND s.status = 'active'
                LEFT JOIN packages p ON s.package_id = p.id
                {$where_clause}
                ORDER BY c.created_at DESC
                LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $customers = $stmt->fetchAll();
        
        return [
            'data' => $customers,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ];
    }
    
    /**
     * Update status pelanggan
     */
    public function updateStatus($id, $status) {
        try {
            $valid_statuses = ['active', 'suspended', 'terminated'];
            if (!in_array($status, $valid_statuses)) {
                throw new Exception("Status tidak valid");
            }
            
            $sql = "UPDATE customers SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$status, $id]);
            
            return [
                'success' => true,
                'message' => 'Status pelanggan berhasil diupdate'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get statistics pelanggan
     */
    public function getStatistics() {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended,
                    SUM(CASE WHEN status = 'terminated' THEN 1 ELSE 0 END) as terminated
                FROM customers";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetch();
    }
    
    /**
     * Search pelanggan
     */
    public function search($query, $limit = 10) {
        $sql = "SELECT id, customer_code, full_name, phone, status 
                FROM customers 
                WHERE full_name LIKE ? OR customer_code LIKE ? OR phone LIKE ?
                ORDER BY full_name ASC
                LIMIT ?";
        
        $search_param = "%{$query}%";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$search_param, $search_param, $search_param, $limit]);
        
        return $stmt->fetchAll();
    }
}
?>