<?php
/**
 * Test Customer class database connection
 */

try {
    require_once 'config/database.php';
    
    echo "Step 1: Creating Database instance...\n";
    $database = new Database();
    
    echo "Step 2: Getting connection...\n";
    $conn = $database->getConnection();
    
    if ($conn === null) {
        echo "ERROR: Connection is null\n";
    } else {
        echo "SUCCESS: Connection established\n";
        echo "Connection type: " . get_class($conn) . "\n";
    }
    
    echo "Step 3: Testing Customer class...\n";
    require_once 'classes/Customer.php';
    $customer = new Customer();
    echo "SUCCESS: Customer class instantiated\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
?>