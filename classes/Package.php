<?php
/**
 * Package Management Class - RT/RW Net
 * 
 * Handles package management operations including CRUD operations,
 * bandwidth configuration, pricing, and package statistics.
 * 
 * @author RT/RW Net System
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';

class Package {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Create new package
     * 
     * @param array $data Package data
     * @return array Result with success status and message
     */
    public function create($data) {
        try {
            // Validate required fields
            $required_fields = ['name', 'bandwidth_up', 'bandwidth_down', 'price', 'duration_days'];
            foreach ($required_fields as $field) {
                if (empty($data[$field])) {
                    return [
                        'success' => false,
                        'message' => 'Field ' . $field . ' wajib diisi'
                    ];
                }
            }
            
            // Validate bandwidth values
            if (!is_numeric($data['bandwidth_up']) || !is_numeric($data['bandwidth_down'])) {
                return [
                    'success' => false,
                    'message' => 'Bandwidth harus berupa angka'
                ];
            }
            
            // Validate price
            if (!is_numeric($data['price']) || $data['price'] < 0) {
                return [
                    'success' => false,
                    'message' => 'Harga harus berupa angka positif'
                ];
            }
            
            // Check if package name already exists
            $existing = $this->db->select(
                "SELECT id FROM packages WHERE name = ? AND deleted_at IS NULL",
                [$data['name']]
            );
            
            if (!empty($existing)) {
                return [
                    'success' => false,
                    'message' => 'Nama paket sudah digunakan'
                ];
            }
            
            // Prepare data for insertion
            $insert_data = [
                'name' => trim($data['name']),
                'description' => trim(isset($data['description']) ? $data['description'] : ''),
                'bandwidth_up' => (int)$data['bandwidth_up'],
                'bandwidth_down' => (int)$data['bandwidth_down'],
                'burst_limit_up' => !empty($data['burst_limit_up']) ? (int)$data['burst_limit_up'] : null,
                'burst_limit_down' => !empty($data['burst_limit_down']) ? (int)$data['burst_limit_down'] : null,
                'burst_threshold_up' => !empty($data['burst_threshold_up']) ? (int)$data['burst_threshold_up'] : null,
                'burst_threshold_down' => !empty($data['burst_threshold_down']) ? (int)$data['burst_threshold_down'] : null,
                'burst_time' => !empty($data['burst_time']) ? (int)$data['burst_time'] : null,
                'limit_at_up' => !empty($data['limit_at_up']) ? (int)$data['limit_at_up'] : null,
                'limit_at_down' => !empty($data['limit_at_down']) ? (int)$data['limit_at_down'] : null,
                'price' => (float)$data['price'],
                'duration_days' => (int)$data['duration_days'],
                'quota_mb' => !empty($data['quota_mb']) ? (int)$data['quota_mb'] : null,
                'priority' => !empty($data['priority']) ? (int)$data['priority'] : 8,
                'pool_name' => trim(isset($data['pool_name']) ? $data['pool_name'] : ''),
                'profile_name' => trim(isset($data['profile_name']) ? $data['profile_name'] : ''),
                'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $result = $this->db->insert('packages', $insert_data);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Paket berhasil ditambahkan',
                    'package_id' => $result
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Gagal menambahkan paket'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Package creation error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan sistem'
            ];
        }
    }
    
    /**
     * Update existing package
     * 
     * @param int $id Package ID
     * @param array $data Updated package data
     * @return array Result with success status and message
     */
    public function update($id, $data) {
        try {
            // Check if package exists
            $existing = $this->getById($id);
            if (!$existing) {
                return [
                    'success' => false,
                    'message' => 'Paket tidak ditemukan'
                ];
            }
            
            // Validate required fields
            $required_fields = ['name', 'bandwidth_up', 'bandwidth_down', 'price', 'duration_days'];
            foreach ($required_fields as $field) {
                if (empty($data[$field])) {
                    return [
                        'success' => false,
                        'message' => 'Field ' . $field . ' wajib diisi'
                    ];
                }
            }
            
            // Check if package name already exists (excluding current package)
            $name_check = $this->db->select(
                "SELECT id FROM packages WHERE name = ? AND id != ? AND deleted_at IS NULL",
                [$data['name'], $id]
            );
            
            if (!empty($name_check)) {
                return [
                    'success' => false,
                    'message' => 'Nama paket sudah digunakan'
                ];
            }
            
            // Prepare data for update
            $update_data = [
                'name' => trim($data['name']),
                'description' => trim(isset($data['description']) ? $data['description'] : ''),
                'bandwidth_up' => (int)$data['bandwidth_up'],
                'bandwidth_down' => (int)$data['bandwidth_down'],
                'burst_limit_up' => !empty($data['burst_limit_up']) ? (int)$data['burst_limit_up'] : null,
                'burst_limit_down' => !empty($data['burst_limit_down']) ? (int)$data['burst_limit_down'] : null,
                'burst_threshold_up' => !empty($data['burst_threshold_up']) ? (int)$data['burst_threshold_up'] : null,
                'burst_threshold_down' => !empty($data['burst_threshold_down']) ? (int)$data['burst_threshold_down'] : null,
                'burst_time' => !empty($data['burst_time']) ? (int)$data['burst_time'] : null,
                'limit_at_up' => !empty($data['limit_at_up']) ? (int)$data['limit_at_up'] : null,
                'limit_at_down' => !empty($data['limit_at_down']) ? (int)$data['limit_at_down'] : null,
                'price' => (float)$data['price'],
                'duration_days' => (int)$data['duration_days'],
                'quota_mb' => !empty($data['quota_mb']) ? (int)$data['quota_mb'] : null,
                'priority' => !empty($data['priority']) ? (int)$data['priority'] : 8,
                'pool_name' => trim(isset($data['pool_name']) ? $data['pool_name'] : ''),
                'profile_name' => trim(isset($data['profile_name']) ? $data['profile_name'] : ''),
                'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $result = $this->db->update('packages', $update_data, ['id' => $id]);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Paket berhasil diperbarui'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Gagal memperbarui paket'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Package update error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan sistem'
            ];
        }
    }
    
    /**
     * Soft delete package
     * 
     * @param int $id Package ID
     * @return array Result with success status and message
     */
    public function delete($id) {
        try {
            // Check if package exists
            $existing = $this->getById($id);
            if (!$existing) {
                return [
                    'success' => false,
                    'message' => 'Paket tidak ditemukan'
                ];
            }
            
            // Check if package is being used by active subscriptions
            $active_subscriptions = $this->db->select(
                "SELECT COUNT(*) as count FROM subscriptions WHERE package_id = ? AND status = 'active'",
                [$id]
            );
            
            if ($active_subscriptions[0]['count'] > 0) {
                return [
                    'success' => false,
                    'message' => 'Paket tidak dapat dihapus karena masih digunakan oleh langganan aktif'
                ];
            }
            
            // Soft delete
            $result = $this->db->update(
                'packages',
                ['deleted_at' => date('Y-m-d H:i:s')],
                ['id' => $id]
            );
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Paket berhasil dihapus'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Gagal menghapus paket'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Package deletion error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan sistem'
            ];
        }
    }
    
    /**
     * Get package by ID
     * 
     * @param int $id Package ID
     * @return array|null Package data or null if not found
     */
    public function getById($id) {
        try {
            $result = $this->db->select(
                "SELECT * FROM packages WHERE id = ? AND deleted_at IS NULL",
                [$id]
            );
            
            return !empty($result) ? $result[0] : null;
            
        } catch (Exception $e) {
            error_log("Package getById error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all packages with pagination and filtering
     * 
     * @param int $page Page number
     * @param int $limit Items per page
     * @param string $search Search term
     * @param string $status Filter by status (active/inactive)
     * @return array Packages data with pagination info
     */
    public function getAll($page = 1, $limit = 20, $search = '', $status = '') {
        try {
            $offset = ($page - 1) * $limit;
            $where_conditions = ["deleted_at IS NULL"];
            $params = [];
            
            // Add search condition
            if (!empty($search)) {
                $where_conditions[] = "(name LIKE ? OR description LIKE ?)";
                $search_term = "%{$search}%";
                $params[] = $search_term;
                $params[] = $search_term;
            }
            
            // Add status filter
            if ($status === 'active') {
                $where_conditions[] = "is_active = 1";
            } elseif ($status === 'inactive') {
                $where_conditions[] = "is_active = 0";
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            // Get total count
            $count_query = "SELECT COUNT(*) as total FROM packages WHERE {$where_clause}";
            $total_result = $this->db->select($count_query, $params);
            $total_records = $total_result[0]['total'];
            $total_pages = ceil($total_records / $limit);
            
            // Get packages with subscription count
            $query = "SELECT p.*, 
                             COUNT(s.id) as subscription_count,
                             COUNT(CASE WHEN s.status = 'active' THEN 1 END) as active_subscriptions
                      FROM packages p 
                      LEFT JOIN subscriptions s ON p.id = s.package_id 
                      WHERE {$where_clause}
                      GROUP BY p.id 
                      ORDER BY p.created_at DESC 
                      LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
            
            $packages = $this->db->select($query, $params);
            
            return [
                'data' => $packages,
                'page' => $page,
                'limit' => $limit,
                'total_records' => $total_records,
                'total_pages' => $total_pages
            ];
            
        } catch (Exception $e) {
            error_log("Package getAll error: " . $e->getMessage());
            return [
                'data' => [],
                'page' => 1,
                'limit' => $limit,
                'total_records' => 0,
                'total_pages' => 0
            ];
        }
    }
    
    /**
     * Get active packages for dropdown/selection
     * 
     * @return array Active packages
     */
    public function getActivePackages() {
        try {
            return $this->db->select(
                "SELECT id, name, bandwidth_up, bandwidth_down, price, duration_days 
                 FROM packages 
                 WHERE is_active = 1 AND deleted_at IS NULL 
                 ORDER BY price ASC"
            );
        } catch (Exception $e) {
            error_log("Package getActivePackages error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get package statistics
     * 
     * @return array Package statistics
     */
    public function getStatistics() {
        try {
            $stats = [];
            
            // Total packages
            $total = $this->db->select(
                "SELECT COUNT(*) as count FROM packages WHERE deleted_at IS NULL"
            );
            $stats['total_packages'] = $total[0]['count'];
            
            // Active packages
            $active = $this->db->select(
                "SELECT COUNT(*) as count FROM packages WHERE is_active = 1 AND deleted_at IS NULL"
            );
            $stats['active_packages'] = $active[0]['count'];
            
            // Most popular package
            $popular = $this->db->select(
                "SELECT p.name, COUNT(s.id) as subscription_count 
                 FROM packages p 
                 LEFT JOIN subscriptions s ON p.id = s.package_id 
                 WHERE p.deleted_at IS NULL 
                 GROUP BY p.id 
                 ORDER BY subscription_count DESC 
                 LIMIT 1"
            );
            $stats['most_popular'] = !empty($popular) ? $popular[0] : null;
            
            // Average price
            $avg_price = $this->db->select(
                "SELECT AVG(price) as avg_price FROM packages WHERE is_active = 1 AND deleted_at IS NULL"
            );
            $stats['average_price'] = isset($avg_price[0]['avg_price']) ? $avg_price[0]['avg_price'] : 0;
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Package getStatistics error: " . $e->getMessage());
            return [
                'total_packages' => 0,
                'active_packages' => 0,
                'most_popular' => null,
                'average_price' => 0
            ];
        }
    }
    
    /**
     * Toggle package status (active/inactive)
     * 
     * @param int $id Package ID
     * @return array Result with success status and message
     */
    public function toggleStatus($id) {
        try {
            $package = $this->getById($id);
            if (!$package) {
                return [
                    'success' => false,
                    'message' => 'Paket tidak ditemukan'
                ];
            }
            
            $new_status = $package['is_active'] ? 0 : 1;
            
            $result = $this->db->update(
                'packages',
                ['is_active' => $new_status, 'updated_at' => date('Y-m-d H:i:s')],
                ['id' => $id]
            );
            
            if ($result) {
                $status_text = $new_status ? 'diaktifkan' : 'dinonaktifkan';
                return [
                    'success' => true,
                    'message' => "Paket berhasil {$status_text}"
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Gagal mengubah status paket'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Package toggleStatus error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan sistem'
            ];
        }
    }
    
    /**
     * Get packages by price range
     * 
     * @param float $min_price Minimum price
     * @param float $max_price Maximum price
     * @return array Packages in price range
     */
    public function getByPriceRange($min_price, $max_price) {
        try {
            return $this->db->select(
                "SELECT * FROM packages 
                 WHERE price BETWEEN ? AND ? 
                 AND is_active = 1 AND deleted_at IS NULL 
                 ORDER BY price ASC",
                [$min_price, $max_price]
            );
        } catch (Exception $e) {
            error_log("Package getByPriceRange error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get packages by bandwidth range
     * 
     * @param int $min_bandwidth Minimum bandwidth (down)
     * @param int $max_bandwidth Maximum bandwidth (down)
     * @return array Packages in bandwidth range
     */
    public function getByBandwidthRange($min_bandwidth, $max_bandwidth) {
        try {
            return $this->db->select(
                "SELECT * FROM packages 
                 WHERE bandwidth_down BETWEEN ? AND ? 
                 AND is_active = 1 AND deleted_at IS NULL 
                 ORDER BY bandwidth_down ASC",
                [$min_bandwidth, $max_bandwidth]
            );
        } catch (Exception $e) {
            error_log("Package getByBandwidthRange error: " . $e->getMessage());
            return [];
        }
    }
}
?>