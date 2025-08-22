<?php
/**
 * Class Invoice - Manajemen Tagihan dan Billing
 * 
 * @author RT/RW Net System
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';

class Invoice {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Generate tagihan bulanan untuk semua pelanggan aktif
     */
    public function generateMonthlyInvoices($month = null, $year = null) {
        try {
            if (!$month) $month = date('m');
            if (!$year) $year = date('Y');
            
            // Get active subscriptions
            $subscriptions = $this->db->query(
                "SELECT s.*, c.name as customer_name, c.email, c.phone, p.name as package_name, p.price 
                 FROM subscriptions s 
                 JOIN customers c ON s.customer_id = c.id 
                 JOIN packages p ON s.package_id = p.id 
                 WHERE s.status = 'active' AND s.end_date >= CURDATE()"
            );
            
            $generated = 0;
            $errors = [];
            
            foreach ($subscriptions as $sub) {
                // Check if invoice already exists for this month
                $existing = $this->db->select('invoices', [], 
                    "customer_id = {$sub['customer_id']} AND MONTH(created_at) = $month AND YEAR(created_at) = $year"
                );
                
                if (empty($existing)) {
                    $invoice_data = [
                        'customer_id' => $sub['customer_id'],
                        'subscription_id' => $sub['id'],
                        'invoice_number' => $this->generateInvoiceNumber(),
                        'created_at' => date('Y-m-d H:i:s'),
                        'due_date' => date('Y-m-d', strtotime('+7 days')),
                        'amount' => $sub['price'],
                        'tax_amount' => 0,
                        'total_amount' => $sub['price'],
                        'status' => 'pending',
                        'description' => "Tagihan {$sub['package_name']} - " . date('F Y', mktime(0, 0, 0, $month, 1, $year))
                    ];
                    
                    if ($this->db->insert('invoices', $invoice_data)) {
                        $generated++;
                    } else {
                        $errors[] = "Gagal generate tagihan untuk {$sub['customer_name']}";
                    }
                }
            }
            
            return [
                'success' => true,
                'message' => "Berhasil generate $generated tagihan",
                'generated' => $generated,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate nomor invoice unik
     */
    private function generateInvoiceNumber() {
        $prefix = 'INV';
        $date = date('Ymd');
        
        // Get last invoice number for today
        $last_invoice = $this->db->query(
            "SELECT invoice_number FROM invoices 
             WHERE invoice_number LIKE '{$prefix}{$date}%' 
             ORDER BY invoice_number DESC LIMIT 1"
        );
        
        $last_invoice_data = $last_invoice->fetch();
        if (!empty($last_invoice_data)) {
            $last_number = substr($last_invoice_data['invoice_number'], -4);
            $next_number = str_pad((int)$last_number + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $next_number = '0001';
        }
        
        return $prefix . $date . $next_number;
    }
    
    /**
     * Create manual invoice
     */
    public function createInvoice($data) {
        try {
            // Validate required fields
            $required = ['customer_id', 'amount', 'description'];
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
            
            // Prepare invoice data
            $invoice_data = [
                'customer_id' => $data['customer_id'],
                'subscription_id' => isset($data['subscription_id']) ? $data['subscription_id'] : null,
                'invoice_number' => $this->generateInvoiceNumber(),
                'created_at' => isset($data['invoice_date']) ? $data['invoice_date'] . ' 00:00:00' : date('Y-m-d H:i:s'),
                'due_date' => isset($data['due_date']) ? $data['due_date'] : date('Y-m-d', strtotime('+7 days')),
                'amount' => $data['amount'],
                'tax_amount' => isset($data['tax_amount']) ? $data['tax_amount'] : 0,
                'total_amount' => $data['amount'] + (isset($data['tax_amount']) ? $data['tax_amount'] : 0),
                'status' => 'pending',
                'description' => $data['description'],
                'notes' => isset($data['notes']) ? $data['notes'] : null
            ];
            
            $invoice_id = $this->db->insert('invoices', $invoice_data);
            
            if ($invoice_id) {
                return [
                    'success' => true,
                    'message' => 'Invoice berhasil dibuat',
                    'invoice_id' => $invoice_id
                ];
            } else {
                return ['success' => false, 'message' => 'Gagal membuat invoice'];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Update invoice
     */
    public function updateInvoice($id, $data) {
        try {
            // Check if invoice exists
            $invoice = $this->getInvoiceById($id);
            if (!$invoice) {
                return ['success' => false, 'message' => 'Invoice tidak ditemukan'];
            }
            
            // Don't allow editing paid invoices
            if ($invoice['status'] === 'paid') {
                return ['success' => false, 'message' => 'Invoice yang sudah dibayar tidak dapat diubah'];
            }
            
            // Prepare update data
            $update_data = [];
            $allowed_fields = ['due_date', 'amount', 'tax_amount', 'description', 'notes'];
            
            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    $update_data[$field] = $data[$field];
                }
            }
            
            // Recalculate total if amount or tax changed
            if (isset($data['amount']) || isset($data['tax_amount'])) {
                $amount = isset($data['amount']) ? $data['amount'] : $invoice['amount'];
                $tax = isset($data['tax_amount']) ? $data['tax_amount'] : $invoice['tax_amount'];
                $update_data['total_amount'] = $amount + $tax;
            }
            
            if (empty($update_data)) {
                return ['success' => false, 'message' => 'Tidak ada data yang diubah'];
            }
            
            $result = $this->db->update('invoices', $update_data, "id = $id");
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Invoice berhasil diupdate'
                ];
            } else {
                return ['success' => false, 'message' => 'Gagal update invoice'];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Mark invoice as paid
     */
    public function markAsPaid($id, $payment_data = []) {
        try {
            $invoice = $this->getInvoiceById($id);
            if (!$invoice) {
                return ['success' => false, 'message' => 'Invoice tidak ditemukan'];
            }
            
            if ($invoice['status'] === 'paid') {
                return ['success' => false, 'message' => 'Invoice sudah dibayar'];
            }
            
            // Update invoice status
            $update_data = [
                'status' => 'paid',
                'paid_date' => date('Y-m-d H:i:s'),
                'paid_amount' => isset($payment_data['amount']) ? $payment_data['amount'] : $invoice['total_amount']
            ];
            
            $result = $this->db->update('invoices', $update_data, "id = $id");
            
            if ($result) {
                // Create payment record if payment data provided
                if (!empty($payment_data)) {
                    $payment_record = [
                        'customer_id' => $invoice['customer_id'],
                        'invoice_id' => $id,
                        'amount' => isset($payment_data['amount']) ? $payment_data['amount'] : $invoice['total_amount'],
                        'payment_method' => isset($payment_data['method']) ? $payment_data['method'] : 'cash',
                        'payment_date' => date('Y-m-d H:i:s'),
                        'reference_number' => isset($payment_data['reference']) ? $payment_data['reference'] : null,
                        'notes' => isset($payment_data['notes']) ? $payment_data['notes'] : null,
                        'status' => 'completed'
                    ];
                    
                    $this->db->insert('payments', $payment_record);
                }
                
                return [
                    'success' => true,
                    'message' => 'Invoice berhasil ditandai sebagai lunas'
                ];
            } else {
                return ['success' => false, 'message' => 'Gagal update status invoice'];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Cancel invoice
     */
    public function cancelInvoice($id, $reason = '') {
        try {
            $invoice = $this->getInvoiceById($id);
            if (!$invoice) {
                return ['success' => false, 'message' => 'Invoice tidak ditemukan'];
            }
            
            if ($invoice['status'] === 'paid') {
                return ['success' => false, 'message' => 'Invoice yang sudah dibayar tidak dapat dibatalkan'];
            }
            
            $update_data = [
                'status' => 'cancelled',
                'notes' => ($invoice['notes'] ? $invoice['notes'] . "\n" : '') . "Dibatalkan: $reason"
            ];
            
            $result = $this->db->update('invoices', $update_data, "id = $id");
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Invoice berhasil dibatalkan'
                ];
            } else {
                return ['success' => false, 'message' => 'Gagal membatalkan invoice'];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get invoice by ID
     */
    public function getInvoiceById($id) {
        $invoices = $this->db->query(
            "SELECT i.*, c.name as customer_name, c.email, c.phone, c.address,
                    s.package_id, p.name as package_name
             FROM invoices i
             JOIN customers c ON i.customer_id = c.id
             LEFT JOIN subscriptions s ON i.subscription_id = s.id
             LEFT JOIN packages p ON s.package_id = p.id
             WHERE i.id = $id"
        );
        
        return !empty($invoices) ? $invoices[0] : null;
    }
    
    /**
     * Get all invoices with pagination and filters
     */
    public function getAllInvoices($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;
        $where_conditions = [];
        $params = [];
        
        // Build where conditions
        if (!empty($filters['customer_id'])) {
            $where_conditions[] = "i.customer_id = ?";
            $params[] = $filters['customer_id'];
        }
        
        if (!empty($filters['status'])) {
            $where_conditions[] = "i.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $where_conditions[] = "(c.name LIKE ? OR i.invoice_number LIKE ?)";
            $search_term = '%' . $filters['search'] . '%';
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "DATE(i.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "DATE(i.created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count
        $count_query = "SELECT COUNT(*) as total FROM invoices i JOIN customers c ON i.customer_id = c.id $where_clause";
        $total_result = $this->db->query($count_query, $params);
        $total = $total_result->fetch()['total'];
        
        // Get invoices
        $query = "SELECT i.*, c.name as customer_name, c.phone, 
                         p.name as package_name
                  FROM invoices i
                  JOIN customers c ON i.customer_id = c.id
                  LEFT JOIN subscriptions s ON i.subscription_id = s.id
                  LEFT JOIN packages p ON s.package_id = p.id
                  $where_clause
                  ORDER BY i.created_at DESC, i.id DESC
                  LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        
        $invoices = $this->db->query($query, $params);
        
        return [
            'data' => $invoices,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ];
    }
    
    /**
     * Get overdue invoices
     */
    public function getOverdueInvoices() {
        return $this->db->query(
            "SELECT i.*, c.name as customer_name, c.phone, c.email
             FROM invoices i
             JOIN customers c ON i.customer_id = c.id
             WHERE i.status = 'pending' AND i.due_date < CURDATE()
             ORDER BY i.due_date ASC"
        );
    }
    
    /**
     * Get invoice statistics
     */
    public function getStatistics($month = null, $year = null) {
        if (!$month) $month = date('m');
        if (!$year) $year = date('Y');
        
        $stats = [];
        
        // Total invoices this month
        $total_result = $this->db->query(
            "SELECT COUNT(*) as total, SUM(amount) as amount FROM invoices 
             WHERE MONTH(created_at) = $month AND YEAR(created_at) = $year"
        );
        $total_data = $total_result->fetch();
        $stats['total_invoices'] = $total_data['total'];
        $stats['total_amount'] = isset($total_data['amount']) ? $total_data['amount'] : 0;
        
        // Paid invoices this month
        $paid_result = $this->db->query(
            "SELECT COUNT(*) as total, SUM(amount) as amount FROM invoices 
             WHERE status = 'paid' AND MONTH(created_at) = $month AND YEAR(created_at) = $year"
        );
        $paid_data = $paid_result->fetch();
        $stats['paid_invoices'] = $paid_data['total'];
        $stats['paid_amount'] = isset($paid_data['amount']) ? $paid_data['amount'] : 0;
        
        // Pending invoices
        $pending_result = $this->db->query(
            "SELECT COUNT(*) as total, SUM(amount) as amount FROM invoices 
             WHERE status = 'pending' AND MONTH(created_at) = $month AND YEAR(created_at) = $year"
        );
        $pending_data = $pending_result->fetch();
        $stats['pending_invoices'] = $pending_data['total'];
        $stats['pending_amount'] = isset($pending_data['amount']) ? $pending_data['amount'] : 0;
        
        // Overdue invoices
        $overdue_result = $this->db->query(
            "SELECT COUNT(*) as total, SUM(amount) as amount FROM invoices 
             WHERE status = 'pending' AND due_date < CURDATE()"
        );
        $overdue_data = $overdue_result->fetch();
        $stats['overdue_invoices'] = $overdue_data['total'];
        $stats['overdue_amount'] = isset($overdue_data['amount']) ? $overdue_data['amount'] : 0;
        
        return $stats;
    }
    
    /**
     * Get customer invoices
     */
    public function getCustomerInvoices($customer_id, $limit = 10) {
        return $this->db->query(
            "SELECT i.*, p.name as package_name
             FROM invoices i
             LEFT JOIN subscriptions s ON i.subscription_id = s.id
             LEFT JOIN packages p ON s.package_id = p.id
             WHERE i.customer_id = $customer_id
             ORDER BY i.created_at DESC
             LIMIT $limit"
        );
    }
    
    /**
     * Send invoice reminder
     */
    public function sendReminder($invoice_id, $method = 'email') {
        try {
            $invoice = $this->getInvoiceById($invoice_id);
            if (!$invoice) {
                return ['success' => false, 'message' => 'Invoice tidak ditemukan'];
            }
            
            if ($invoice['status'] !== 'pending') {
                return ['success' => false, 'message' => 'Hanya invoice pending yang bisa dikirimi reminder'];
            }
            
            // TODO: Implement actual sending logic (email/WhatsApp/SMS)
            // For now, just log the reminder
            $reminder_data = [
                'customer_id' => $invoice['customer_id'],
                'type' => 'invoice_reminder',
                'title' => 'Reminder Tagihan',
                'message' => "Reminder tagihan {$invoice['invoice_number']} jatuh tempo {$invoice['due_date']}",
                'method' => $method,
                'status' => 'sent',
                'sent_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->insert('notifications', $reminder_data);
            
            return [
                'success' => true,
                'message' => 'Reminder berhasil dikirim'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate invoice PDF (placeholder)
     */
    public function generatePDF($invoice_id) {
        // TODO: Implement PDF generation
        // This would typically use libraries like TCPDF or FPDF
        return ['success' => false, 'message' => 'PDF generation not implemented yet'];
    }
}
?>