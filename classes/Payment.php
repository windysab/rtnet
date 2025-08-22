<?php
/**
 * Class Payment - Manajemen Pembayaran
 * 
 * @author RT/RW Net System
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';

class Payment {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Record new payment
     */
    public function recordPayment($data) {
        try {
            // Validate required fields
            $required = ['customer_id', 'amount', 'payment_method'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'message' => "Field $field harus diisi"];
                }
            }
            
            // Validate customer exists
            $customer = $this->db->select('customers', [], "id = {$data['customer_id']}");
            if (empty($customer)) {
                return ['success' => false, 'message' => 'Customer tidak ditemukan'];
            }
            
            // Validate invoice if provided
            if (!empty($data['invoice_id'])) {
                $invoice = $this->db->select('invoices', [], "id = {$data['invoice_id']}");
                if (empty($invoice)) {
                    return ['success' => false, 'message' => 'Invoice tidak ditemukan'];
                }
                
                if ($invoice[0]['status'] === 'paid') {
                    return ['success' => false, 'message' => 'Invoice sudah dibayar'];
                }
            }
            
            // Generate payment reference if not provided
            if (empty($data['reference_number'])) {
                $data['reference_number'] = $this->generatePaymentReference();
            }
            
            // Prepare payment data
            $payment_data = [
                'customer_id' => $data['customer_id'],
                'invoice_id' => isset($data['invoice_id']) ? $data['invoice_id'] : null,
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'],
                'payment_date' => isset($data['payment_date']) ? $data['payment_date'] : date('Y-m-d H:i:s'),
                'reference_number' => $data['reference_number'],
                'notes' => isset($data['notes']) ? $data['notes'] : null,
                'status' => isset($data['status']) ? $data['status'] : 'completed',
                'processed_by' => isset($data['processed_by']) ? $data['processed_by'] : null
            ];
            
            // Handle file upload for payment proof
            if (!empty($data['payment_proof']) && is_uploaded_file($data['payment_proof']['tmp_name'])) {
                $upload_result = $this->uploadPaymentProof($data['payment_proof']);
                if ($upload_result['success']) {
                    $payment_data['payment_proof'] = $upload_result['filename'];
                } else {
                    return $upload_result;
                }
            }
            
            $payment_id = $this->db->insert('payments', $payment_data);
            
            if ($payment_id) {
                // Update invoice status if payment is for specific invoice
                if (!empty($data['invoice_id']) && $payment_data['status'] === 'completed') {
                    $this->updateInvoicePaymentStatus($data['invoice_id'], $data['amount']);
                }
                
                return [
                    'success' => true,
                    'message' => 'Pembayaran berhasil dicatat',
                    'payment_id' => $payment_id,
                    'reference' => $payment_data['reference_number']
                ];
            } else {
                return ['success' => false, 'message' => 'Gagal mencatat pembayaran'];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate unique payment reference number
     */
    private function generatePaymentReference() {
        $prefix = 'PAY';
        $date = date('Ymd');
        
        // Get last payment reference for today
        $last_payment = $this->db->query(
            "SELECT reference_number FROM payments 
             WHERE reference_number LIKE '{$prefix}{$date}%' 
             ORDER BY reference_number DESC LIMIT 1"
        );
        
        if (!empty($last_payment)) {
            $last_number = substr($last_payment[0]['reference_number'], -4);
            $next_number = str_pad((int)$last_number + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $next_number = '0001';
        }
        
        return $prefix . $date . $next_number;
    }
    
    /**
     * Upload payment proof file
     */
    private function uploadPaymentProof($file) {
        try {
            $upload_dir = 'uploads/payments/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            if (!in_array($file['type'], $allowed_types)) {
                return ['success' => false, 'message' => 'Tipe file tidak diizinkan. Gunakan JPG, PNG, GIF, atau PDF'];
            }
            
            // Validate file size (max 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                return ['success' => false, 'message' => 'Ukuran file terlalu besar. Maksimal 5MB'];
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'payment_' . date('YmdHis') . '_' . uniqid() . '.' . $extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                return [
                    'success' => true,
                    'filename' => $filename,
                    'filepath' => $filepath
                ];
            } else {
                return ['success' => false, 'message' => 'Gagal upload file'];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error upload: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Update invoice payment status
     */
    private function updateInvoicePaymentStatus($invoice_id, $payment_amount) {
        try {
            $invoice = $this->db->select('invoices', [], "id = $invoice_id");
            if (empty($invoice)) {
                return false;
            }
            
            $invoice = $invoice[0];
            
            // Calculate total payments for this invoice
            $total_payments = $this->db->query(
                "SELECT SUM(amount) as total FROM payments 
                 WHERE invoice_id = $invoice_id AND status = 'completed'"
            );
            
            $total_payments_data = $total_payments->fetch();
            $paid_amount = isset($total_payments_data['total']) ? $total_payments_data['total'] : 0;
            
            // Update invoice status based on payment amount
            if ($paid_amount >= $invoice['total_amount']) {
                $this->db->update('invoices', [
                    'status' => 'paid',
                    'paid_date' => date('Y-m-d H:i:s'),
                    'paid_amount' => $paid_amount
                ], "id = $invoice_id");
            } elseif ($paid_amount > 0) {
                $this->db->update('invoices', [
                    'status' => 'partial',
                    'paid_amount' => $paid_amount
                ], "id = $invoice_id");
            }
            
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Update payment status
     */
    public function updatePaymentStatus($payment_id, $status, $notes = '') {
        try {
            $payment = $this->getPaymentById($payment_id);
            if (!$payment) {
                return ['success' => false, 'message' => 'Pembayaran tidak ditemukan'];
            }
            
            $allowed_statuses = ['pending', 'completed', 'failed', 'cancelled'];
            if (!in_array($status, $allowed_statuses)) {
                return ['success' => false, 'message' => 'Status tidak valid'];
            }
            
            $update_data = [
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if ($notes) {
                $update_data['notes'] = ($payment['notes'] ? $payment['notes'] . "\n" : '') . $notes;
            }
            
            $result = $this->db->update('payments', $update_data, "id = $payment_id");
            
            if ($result) {
                // Update invoice status if payment is completed/failed
                if (!empty($payment['invoice_id'])) {
                    $this->updateInvoicePaymentStatus($payment['invoice_id'], $payment['amount']);
                }
                
                return [
                    'success' => true,
                    'message' => 'Status pembayaran berhasil diupdate'
                ];
            } else {
                return ['success' => false, 'message' => 'Gagal update status pembayaran'];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get payment by ID
     */
    public function getPaymentById($id) {
        $payments = $this->db->query(
            "SELECT p.*, c.name as customer_name, c.phone, 
                    i.invoice_number, i.total_amount as invoice_amount
             FROM payments p
             JOIN customers c ON p.customer_id = c.id
             LEFT JOIN invoices i ON p.invoice_id = i.id
             WHERE p.id = $id"
        );
        
        return !empty($payments) ? $payments[0] : null;
    }
    
    /**
     * Get all payments with pagination and filters
     */
    public function getAllPayments($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;
        $where_conditions = [];
        $params = [];
        
        // Build where conditions
        if (!empty($filters['customer_id'])) {
            $where_conditions[] = "p.customer_id = ?";
            $params[] = $filters['customer_id'];
        }
        
        if (!empty($filters['status'])) {
            $where_conditions[] = "p.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['payment_method'])) {
            $where_conditions[] = "p.payment_method = ?";
            $params[] = $filters['payment_method'];
        }
        
        if (!empty($filters['search'])) {
            $where_conditions[] = "(c.name LIKE ? OR p.reference_number LIKE ? OR i.invoice_number LIKE ?)";
            $search_term = '%' . $filters['search'] . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "DATE(p.payment_date) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "DATE(p.payment_date) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count
        $count_query = "SELECT COUNT(*) as total FROM payments p 
                        LEFT JOIN invoices i ON p.invoice_id = i.id 
                        LEFT JOIN customers c ON i.customer_id = c.id 
                        $where_clause";
        $total_result = $this->db->query($count_query, $params);
        $total = $total_result->fetch()['total'];
        
        // Get payments
        $query = "SELECT p.*, c.name as customer_name, c.phone,
                         i.invoice_number, i.amount as invoice_amount
                  FROM payments p
                  LEFT JOIN invoices i ON p.invoice_id = i.id
                  LEFT JOIN customers c ON i.customer_id = c.id
                  $where_clause
                  ORDER BY p.payment_date DESC, p.id DESC
                  LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        
        $payments = $this->db->query($query, $params);
        
        return [
            'data' => $payments,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ];
    }
    
    /**
     * Get customer payments
     */
    public function getCustomerPayments($customer_id, $limit = 10) {
        return $this->db->query(
            "SELECT p.*, i.invoice_number
             FROM payments p
             LEFT JOIN invoices i ON p.invoice_id = i.id
             WHERE p.customer_id = $customer_id
             ORDER BY p.payment_date DESC
             LIMIT $limit"
        );
    }
    
    /**
     * Get payment statistics
     */
    public function getStatistics($month = null, $year = null) {
        if (!$month) $month = date('m');
        if (!$year) $year = date('Y');
        
        $stats = [];
        
        // Total payments this month
        $total_result = $this->db->query(
            "SELECT COUNT(*) as total, SUM(amount) as amount FROM payments 
             WHERE MONTH(payment_date) = $month AND YEAR(payment_date) = $year"
        )->fetch(PDO::FETCH_ASSOC);
        $stats['total_payments'] = $total_result['total'];
        $stats['total_amount'] = isset($total_result['amount']) ? $total_result['amount'] : 0;
        
        // Payments by method
        $method_result = $this->db->query(
            "SELECT payment_method, COUNT(*) as count, SUM(amount) as amount 
             FROM payments 
             WHERE MONTH(payment_date) = $month AND YEAR(payment_date) = $year
             GROUP BY payment_method"
        )->fetchAll(PDO::FETCH_ASSOC);
        
        $stats['by_method'] = [];
        foreach ($method_result as $row) {
            $stats['by_method'][$row['payment_method']] = [
                'count' => $row['count'],
                'amount' => $row['amount']
            ];
        }
        
        // Approved payments (all payments in this table are approved)
        $approved_result = $this->db->query(
            "SELECT COUNT(*) as total, SUM(amount) as amount FROM payments"
        )->fetch(PDO::FETCH_ASSOC);
        $stats['approved_payments'] = $approved_result['total'];
        $stats['approved_amount'] = isset($approved_result['amount']) ? $approved_result['amount'] : 0;
        
        // Pending payments (from invoices not yet paid)
        $pending_result = $this->db->query(
            "SELECT COUNT(*) as total, SUM(amount) as amount FROM invoices 
             WHERE status = 'pending'"
        )->fetch(PDO::FETCH_ASSOC);
        $stats['pending_payments'] = $pending_result['total'];
        $stats['pending_amount'] = isset($pending_result['amount']) ? $pending_result['amount'] : 0;
        
        // Daily payments this month
        $daily_result = $this->db->query(
            "SELECT DATE(payment_date) as date, COUNT(*) as count, SUM(amount) as amount 
             FROM payments 
             WHERE MONTH(payment_date) = $month AND YEAR(payment_date) = $year
             GROUP BY DATE(payment_date)
             ORDER BY date"
        )->fetchAll(PDO::FETCH_ASSOC);
        
        $stats['daily_payments'] = [];
        foreach ($daily_result as $row) {
            $stats['daily_payments'][$row['date']] = [
                'count' => $row['count'],
                'amount' => $row['amount']
            ];
        }
        
        return $stats;
    }
    
    /**
     * Get payment methods
     */
    public function getPaymentMethods() {
        return [
            'cash' => 'Tunai',
            'bank_transfer' => 'Transfer Bank',
            'e_wallet' => 'E-Wallet',
            'qris' => 'QRIS',
            'virtual_account' => 'Virtual Account',
            'credit_card' => 'Kartu Kredit',
            'debit_card' => 'Kartu Debit'
        ];
    }
    
    /**
     * Process refund
     */
    public function processRefund($payment_id, $refund_amount, $reason = '') {
        try {
            $payment = $this->getPaymentById($payment_id);
            if (!$payment) {
                return ['success' => false, 'message' => 'Pembayaran tidak ditemukan'];
            }
            
            if ($payment['status'] !== 'completed') {
                return ['success' => false, 'message' => 'Hanya pembayaran completed yang bisa direfund'];
            }
            
            if ($refund_amount > $payment['amount']) {
                return ['success' => false, 'message' => 'Jumlah refund tidak boleh lebih besar dari pembayaran'];
            }
            
            // Create refund record
            $refund_data = [
                'customer_id' => $payment['customer_id'],
                'invoice_id' => $payment['invoice_id'],
                'amount' => -$refund_amount, // Negative amount for refund
                'payment_method' => $payment['payment_method'],
                'payment_date' => date('Y-m-d H:i:s'),
                'reference_number' => 'REF' . $payment['reference_number'],
                'notes' => "Refund untuk {$payment['reference_number']}. Alasan: $reason",
                'status' => 'completed'
            ];
            
            $refund_id = $this->db->insert('payments', $refund_data);
            
            if ($refund_id) {
                // Update original payment notes
                $this->db->update('payments', [
                    'notes' => ($payment['notes'] ? $payment['notes'] . "\n" : '') . "Refund Rp " . number_format($refund_amount) . " pada " . date('Y-m-d H:i:s')
                ], "id = $payment_id");
                
                // Update invoice status if needed
                if (!empty($payment['invoice_id'])) {
                    $this->updateInvoicePaymentStatus($payment['invoice_id'], 0);
                }
                
                return [
                    'success' => true,
                    'message' => 'Refund berhasil diproses',
                    'refund_id' => $refund_id
                ];
            } else {
                return ['success' => false, 'message' => 'Gagal memproses refund'];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate payment receipt (placeholder)
     */
    public function generateReceipt($payment_id) {
        // TODO: Implement receipt generation
        // This would typically generate a PDF receipt
        return ['success' => false, 'message' => 'Receipt generation not implemented yet'];
    }
    
    /**
     * Validate payment proof
     */
    public function validatePaymentProof($payment_id, $is_valid, $notes = '') {
        try {
            $payment = $this->getPaymentById($payment_id);
            if (!$payment) {
                return ['success' => false, 'message' => 'Pembayaran tidak ditemukan'];
            }
            
            $status = $is_valid ? 'completed' : 'failed';
            $validation_notes = $is_valid ? 'Bukti pembayaran valid' : 'Bukti pembayaran tidak valid';
            if ($notes) {
                $validation_notes .= ": $notes";
            }
            
            return $this->updatePaymentStatus($payment_id, $status, $validation_notes);
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
}
?>