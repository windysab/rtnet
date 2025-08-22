<?php
/**
 * MikroTik API Class untuk Aplikasi RT/RW Net
 * 
 * Class ini digunakan untuk komunikasi dengan MikroTik RouterOS API
 * Mendukung operasi Hotspot, PPPoE, Simple Queue, dan monitoring
 * 
 * @author RT/RW Net System
 * @version 1.0
 */

class MikrotikAPI {
    private $socket;
    private $host;
    private $port;
    private $username;
    private $password;
    private $timeout;
    private $connected = false;
    
    public function __construct($host, $username, $password, $port = 8728, $timeout = 5) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->timeout = $timeout;
    }
    
    /**
     * Koneksi ke MikroTik
     */
    public function connect() {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => $this->timeout, 'usec' => 0));
        socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, array('sec' => $this->timeout, 'usec' => 0));
        
        if (!socket_connect($this->socket, $this->host, $this->port)) {
            throw new Exception("Cannot connect to MikroTik: " . socket_strerror(socket_last_error()));
        }
        
        // Login
        $this->write('/login');
        $response = $this->read();
        
        if (isset($response[0]['ret'])) {
            // MD5 Challenge
            $challenge = $response[0]['ret'];
            $md5 = md5(chr(0) . $this->password . pack('H*', $challenge));
            $this->write('/login', array('name' => $this->username, 'response' => '00' . $md5));
        } else {
            // Plain password
            $this->write('/login', array('name' => $this->username, 'password' => $this->password));
        }
        
        $response = $this->read();
        if (!isset($response[0]['!done'])) {
            throw new Exception("Login failed");
        }
        
        $this->connected = true;
        return true;
    }
    
    /**
     * Disconnect dari MikroTik
     */
    public function disconnect() {
        if ($this->connected && $this->socket) {
            socket_close($this->socket);
            $this->connected = false;
        }
    }
    
    /**
     * Write command ke MikroTik
     */
    private function write($command, $arguments = array()) {
        $data = $this->encodeLength(strlen($command)) . $command;
        foreach ($arguments as $key => $value) {
            $argument = '=' . $key . '=' . $value;
            $data .= $this->encodeLength(strlen($argument)) . $argument;
        }
        $data .= $this->encodeLength(0);
        socket_write($this->socket, $data);
    }
    
    /**
     * Read response dari MikroTik
     */
    private function read() {
        $response = array();
        while (true) {
            $length = $this->decodeLength();
            if ($length === false) break;
            
            if ($length > 0) {
                $data = socket_read($this->socket, $length);
                if ($data === false) break;
                
                if (substr($data, 0, 1) == '!') {
                    $response[] = array($data => true);
                    if ($data == '!done') break;
                } else if (substr($data, 0, 1) == '=') {
                    $pos = strpos($data, '=', 1);
                    if ($pos !== false) {
                        $key = substr($data, 1, $pos - 1);
                        $value = substr($data, $pos + 1);
                        $response[count($response) - 1][$key] = $value;
                    }
                }
            } else {
                break;
            }
        }
        return $response;
    }
    
    /**
     * Encode length untuk protokol API
     */
    private function encodeLength($length) {
        if ($length < 0x80) {
            return chr($length);
        } else if ($length < 0x4000) {
            return chr(($length >> 8) | 0x80) . chr($length & 0xFF);
        } else if ($length < 0x200000) {
            return chr(($length >> 16) | 0xC0) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        } else if ($length < 0x10000000) {
            return chr(($length >> 24) | 0xE0) . chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        } else {
            return chr(0xF0) . chr(($length >> 24) & 0xFF) . chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        }
    }
    
    /**
     * Decode length dari protokol API
     */
    private function decodeLength() {
        $byte = socket_read($this->socket, 1);
        if ($byte === false) return false;
        
        $byte = ord($byte);
        if ($byte < 0x80) {
            return $byte;
        } else if ($byte < 0xC0) {
            $byte2 = socket_read($this->socket, 1);
            return (($byte & 0x7F) << 8) + ord($byte2);
        } else if ($byte < 0xE0) {
            $byte2 = socket_read($this->socket, 2);
            return (($byte & 0x3F) << 16) + (ord($byte2[0]) << 8) + ord($byte2[1]);
        } else if ($byte < 0xF0) {
            $byte2 = socket_read($this->socket, 3);
            return (($byte & 0x1F) << 24) + (ord($byte2[0]) << 16) + (ord($byte2[1]) << 8) + ord($byte2[2]);
        } else {
            $byte2 = socket_read($this->socket, 4);
            return (ord($byte2[0]) << 24) + (ord($byte2[1]) << 16) + (ord($byte2[2]) << 8) + ord($byte2[3]);
        }
    }
    
    /**
     * Eksekusi command dan return response
     */
    public function comm($command, $arguments = array()) {
        $this->write($command, $arguments);
        return $this->read();
    }
    
    // ========== HOTSPOT METHODS ==========
    
    /**
     * Tambah user hotspot
     */
    public function addHotspotUser($username, $password, $profile, $comment = '') {
        $arguments = array(
            'name' => $username,
            'password' => $password,
            'profile' => $profile,
            'comment' => $comment
        );
        return $this->comm('/ip/hotspot/user/add', $arguments);
    }
    
    /**
     * Hapus user hotspot
     */
    public function removeHotspotUser($username) {
        $user = $this->getHotspotUser($username);
        if ($user) {
            return $this->comm('/ip/hotspot/user/remove', array('.id' => $user[0]['.id']));
        }
        return false;
    }
    
    /**
     * Update user hotspot
     */
    public function updateHotspotUser($username, $data) {
        $user = $this->getHotspotUser($username);
        if ($user) {
            $data['.id'] = $user[0]['.id'];
            return $this->comm('/ip/hotspot/user/set', $data);
        }
        return false;
    }
    
    /**
     * Get user hotspot
     */
    public function getHotspotUser($username) {
        return $this->comm('/ip/hotspot/user/print', array('?name' => $username));
    }
    
    /**
     * Get semua user hotspot
     */
    public function getAllHotspotUsers() {
        return $this->comm('/ip/hotspot/user/print');
    }
    
    /**
     * Enable/Disable user hotspot
     */
    public function setHotspotUserStatus($username, $disabled = false) {
        $user = $this->getHotspotUser($username);
        if ($user) {
            return $this->comm('/ip/hotspot/user/set', array(
                '.id' => $user[0]['.id'],
                'disabled' => $disabled ? 'yes' : 'no'
            ));
        }
        return false;
    }
    
    // ========== PPPOE METHODS ==========
    
    /**
     * Tambah user PPPoE
     */
    public function addPPPoEUser($username, $password, $profile, $service = 'any', $comment = '') {
        $arguments = array(
            'name' => $username,
            'password' => $password,
            'profile' => $profile,
            'service' => $service,
            'comment' => $comment
        );
        return $this->comm('/ppp/secret/add', $arguments);
    }
    
    /**
     * Hapus user PPPoE
     */
    public function removePPPoEUser($username) {
        $user = $this->getPPPoEUser($username);
        if ($user) {
            return $this->comm('/ppp/secret/remove', array('.id' => $user[0]['.id']));
        }
        return false;
    }
    
    /**
     * Update user PPPoE
     */
    public function updatePPPoEUser($username, $data) {
        $user = $this->getPPPoEUser($username);
        if ($user) {
            $data['.id'] = $user[0]['.id'];
            return $this->comm('/ppp/secret/set', $data);
        }
        return false;
    }
    
    /**
     * Get user PPPoE
     */
    public function getPPPoEUser($username) {
        return $this->comm('/ppp/secret/print', array('?name' => $username));
    }
    
    /**
     * Get semua user PPPoE
     */
    public function getAllPPPoEUsers() {
        return $this->comm('/ppp/secret/print');
    }
    
    /**
     * Enable/Disable user PPPoE
     */
    public function setPPPoEUserStatus($username, $disabled = false) {
        $user = $this->getPPPoEUser($username);
        if ($user) {
            return $this->comm('/ppp/secret/set', array(
                '.id' => $user[0]['.id'],
                'disabled' => $disabled ? 'yes' : 'no'
            ));
        }
        return false;
    }
    
    // ========== SIMPLE QUEUE METHODS ==========
    
    /**
     * Tambah Simple Queue
     */
    public function addSimpleQueue($name, $target, $maxLimit, $comment = '') {
        $arguments = array(
            'name' => $name,
            'target' => $target,
            'max-limit' => $maxLimit,
            'comment' => $comment
        );
        return $this->comm('/queue/simple/add', $arguments);
    }
    
    /**
     * Hapus Simple Queue
     */
    public function removeSimpleQueue($name) {
        $queue = $this->getSimpleQueue($name);
        if ($queue) {
            return $this->comm('/queue/simple/remove', array('.id' => $queue[0]['.id']));
        }
        return false;
    }
    
    /**
     * Update Simple Queue
     */
    public function updateSimpleQueue($name, $data) {
        $queue = $this->getSimpleQueue($name);
        if ($queue) {
            $data['.id'] = $queue[0]['.id'];
            return $this->comm('/queue/simple/set', $data);
        }
        return false;
    }
    
    /**
     * Get Simple Queue
     */
    public function getSimpleQueue($name) {
        return $this->comm('/queue/simple/print', array('?name' => $name));
    }
    
    /**
     * Get semua Simple Queue
     */
    public function getAllSimpleQueues() {
        return $this->comm('/queue/simple/print');
    }
    
    /**
     * Enable/Disable Simple Queue
     */
    public function setSimpleQueueStatus($name, $disabled = false) {
        $queue = $this->getSimpleQueue($name);
        if ($queue) {
            return $this->comm('/queue/simple/set', array(
                '.id' => $queue[0]['.id'],
                'disabled' => $disabled ? 'yes' : 'no'
            ));
        }
        return false;
    }
    
    // ========== MONITORING METHODS ==========
    
    /**
     * Get active hotspot users
     */
    public function getActiveHotspotUsers() {
        return $this->comm('/ip/hotspot/active/print');
    }
    
    /**
     * Get active PPPoE sessions
     */
    public function getActivePPPoESessions() {
        return $this->comm('/ppp/active/print');
    }
    
    /**
     * Get interface traffic
     */
    public function getInterfaceTraffic($interface = null) {
        $args = array();
        if ($interface) {
            $args['?name'] = $interface;
        }
        return $this->comm('/interface/print', $args);
    }
    
    /**
     * Get system resource
     */
    public function getSystemResource() {
        return $this->comm('/system/resource/print');
    }
    
    /**
     * Disconnect user
     */
    public function disconnectUser($username, $type = 'hotspot') {
        if ($type == 'hotspot') {
            $active = $this->comm('/ip/hotspot/active/print', array('?user' => $username));
            if ($active) {
                return $this->comm('/ip/hotspot/active/remove', array('.id' => $active[0]['.id']));
            }
        } else if ($type == 'pppoe') {
            $active = $this->comm('/ppp/active/print', array('?name' => $username));
            if ($active) {
                return $this->comm('/ppp/active/remove', array('.id' => $active[0]['.id']));
            }
        }
        return false;
    }
    
    /**
     * Test koneksi
     */
    public function testConnection() {
        try {
            $result = $this->comm('/system/identity/print');
            return isset($result[0]['name']);
        } catch (Exception $e) {
            return false;
        }
    }
}
?>