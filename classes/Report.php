<?php
require_once __DIR__ . '/../config/database.php';

class Report {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    // Laporan pelanggan aktif/non-aktif
    public function getCustomerStatusReport($start_date = null, $end_date = null) {
        try {
            $where_clause = "";
            $params = [];
            
            if ($start_date && $end_date) {
                $where_clause = "WHERE c.created_at BETWEEN ? AND ?";
                $params = [$start_date, $end_date];
            }
            
            $sql = "SELECT 
                        c.status,
                        COUNT(*) as total,
                        COUNT(CASE WHEN c.online_status = 'online' THEN 1 END) as online_count,
                        COUNT(CASE WHEN c.online_status = 'offline' THEN 1 END) as offline_count
                    FROM customers c 
                    $where_clause
                    GROUP BY c.status";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting customer status report: " . $e->getMessage());
            return [];
        }
    }
    
    // Laporan pendapatan
    public function getRevenueReport($start_date, $end_date, $group_by = 'month') {
        try {
            $date_format = $group_by === 'day' ? '%Y-%m-%d' : '%Y-%m';
            
            $sql = "SELECT 
                        DATE_FORMAT(p.payment_date, '$date_format') as period,
                        COUNT(p.id) as total_payments,
                        SUM(p.amount) as total_revenue,
                        AVG(p.amount) as avg_payment,
                        COUNT(DISTINCT p.customer_id) as unique_customers
                    FROM payments p
                    WHERE p.status = 'approved' 
                    AND p.payment_date BETWEEN ? AND ?
                    GROUP BY period
                    ORDER BY period DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$start_date, $end_date]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting revenue report: " . $e->getMessage());
            return [];
        }
    }
    
    // Laporan pemakaian bandwidth
    public function getBandwidthReport($start_date, $end_date, $limit = 20) {
        try {
            $sql = "SELECT 
                        c.id,
                        c.full_name,
                        c.username,
                        p.name as package_name,
                        SUM(bm.upload_bytes) as total_upload,
                        SUM(bm.download_bytes) as total_download,
                        SUM(bm.total_bytes) as total_usage,
                        SUM(bm.session_time) as total_session_time,
                        AVG(bm.total_bytes) as avg_daily_usage
                    FROM customers c
                    LEFT JOIN packages p ON c.package_id = p.id
                    LEFT JOIN bandwidth_monitoring bm ON c.id = bm.customer_id
                    WHERE bm.date BETWEEN ? AND ?
                    GROUP BY c.id
                    ORDER BY total_usage DESC
                    LIMIT ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$start_date, $end_date, $limit]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting bandwidth report: " . $e->getMessage());
            return [];
        }
    }
    
    // Top user berdasarkan pemakaian bandwidth
    public function getTopUsers($start_date, $end_date, $limit = 10) {
        try {
            $sql = "SELECT 
                        c.id,
                        c.full_name,
                        c.username,
                        c.phone,
                        p.name as package_name,
                        p.bandwidth_up,
                        p.bandwidth_down,
                        SUM(bm.total_bytes) as total_usage,
                        SUM(bm.session_time) as total_session_time,
                        COUNT(bm.date) as active_days,
                        ROUND(SUM(bm.total_bytes) / (1024*1024*1024), 2) as usage_gb
                    FROM customers c
                    LEFT JOIN packages p ON c.package_id = p.id
                    LEFT JOIN bandwidth_monitoring bm ON c.id = bm.customer_id
                    WHERE bm.date BETWEEN ? AND ?
                    AND c.status = 'active'
                    GROUP BY c.id
                    ORDER BY total_usage DESC
                    LIMIT ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$start_date, $end_date, $limit]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting top users: " . $e->getMessage());
            return [];
        }
    }
    
    // Laporan paket layanan
    public function getPackageReport() {
        try {
            $sql = "SELECT 
                        p.id,
                        p.name,
                        p.price,
                        p.bandwidth_up,
                        p.bandwidth_down,
                        COUNT(c.id) as total_customers,
                        COUNT(CASE WHEN c.status = 'active' THEN 1 END) as active_customers,
                        COUNT(CASE WHEN c.status = 'suspended' THEN 1 END) as suspended_customers,
                        SUM(CASE WHEN c.status = 'active' THEN p.price ELSE 0 END) as monthly_revenue
                    FROM packages p
                    LEFT JOIN customers c ON p.id = c.package_id
                    WHERE p.status = 'active'
                    GROUP BY p.id
                    ORDER BY total_customers DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting package report: " . $e->getMessage());
            return [];
        }
    }
    
    // Laporan pembayaran
    public function getPaymentReport($start_date, $end_date) {
        try {
            $sql = "SELECT 
                        p.payment_method,
                        COUNT(p.id) as total_payments,
                        SUM(p.amount) as total_amount,
                        AVG(p.amount) as avg_amount,
                        COUNT(CASE WHEN p.status = 'approved' THEN 1 END) as approved_count,
                        COUNT(CASE WHEN p.status = 'pending' THEN 1 END) as pending_count,
                        COUNT(CASE WHEN p.status = 'rejected' THEN 1 END) as rejected_count
                    FROM payments p
                    WHERE p.payment_date BETWEEN ? AND ?
                    GROUP BY p.payment_method
                    ORDER BY total_amount DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$start_date, $end_date]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting payment report: " . $e->getMessage());
            return [];
        }
    }
    
    // Laporan invoice
    public function getInvoiceReport($start_date, $end_date) {
        try {
            $sql = "SELECT 
                        i.status,
                        COUNT(i.id) as total_invoices,
                        SUM(i.amount) as total_amount,
                        AVG(i.amount) as avg_amount,
                        COUNT(CASE WHEN i.due_date < CURDATE() AND i.status = 'pending' THEN 1 END) as overdue_count
                    FROM invoices i
                    WHERE DATE(i.created_at) BETWEEN ? AND ?
                    GROUP BY i.status
                    ORDER BY total_amount DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$start_date, $end_date]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting invoice report: " . $e->getMessage());
            return [];
        }
    }
    
    // Dashboard summary
    public function getDashboardSummary() {
        try {
            $summary = [];
            
            // Total customers
            $sql = "SELECT COUNT(*) as total FROM customers";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $summary['total_customers'] = $stmt->fetchColumn();
            
            // Active customers
            $sql = "SELECT COUNT(*) as total FROM customers WHERE status = 'active'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $summary['active_customers'] = $stmt->fetchColumn();
            
            // Online customers
            $sql = "SELECT COUNT(*) as total FROM customers WHERE online_status = 'online'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $summary['online_customers'] = $stmt->fetchColumn();
            
            // Monthly revenue
            $sql = "SELECT SUM(amount) as total FROM payments WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $summary['monthly_revenue'] = $stmt->fetchColumn() ?: 0;
            
            // Pending invoices
            $sql = "SELECT COUNT(*) as total FROM invoices WHERE status = 'unpaid'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $summary['pending_invoices'] = $stmt->fetchColumn();
            
            // Overdue invoices
            $sql = "SELECT COUNT(*) as total FROM invoices WHERE status = 'overdue' OR (status = 'unpaid' AND due_date < CURDATE())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $summary['overdue_invoices'] = $stmt->fetchColumn();
            
            return $summary;
        } catch (Exception $e) {
            error_log("Error getting dashboard summary: " . $e->getMessage());
            return [];
        }
    }
    
    // Export data ke CSV
    public function exportToCSV($data, $filename, $headers = []) {
        try {
            $output = fopen('php://output', 'w');
            
            // Set headers untuk download
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            // Tulis header jika ada
            if (!empty($headers)) {
                fputcsv($output, $headers);
            } elseif (!empty($data)) {
                fputcsv($output, array_keys($data[0]));
            }
            
            // Tulis data
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
            
            fclose($output);
            exit;
        } catch (Exception $e) {
            error_log("Error exporting to CSV: " . $e->getMessage());
            return false;
        }
    }
    
    // Format bytes ke format yang mudah dibaca
    public function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    // Format waktu sesi
    public function formatSessionTime($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;
        
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }
}
?>