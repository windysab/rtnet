<?php
/**
 * Logout Page - RT/RW Net
 * 
 * @author RT/RW Net System
 * @version 1.0
 */

require_once 'classes/Auth.php';

$auth = new Auth();
$result = $auth->logout();

// Redirect ke halaman login dengan pesan
header('Location: login.php?message=' . urlencode($result['message']));
exit;
?>