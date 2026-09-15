<?php
/**
 * PDOCache - A simple PDO connection caching class
 * 
 * This class provides a singleton pattern for PDO connections,
 * allowing reuse of existing connections and improving performance.
 */
class PDOCache {
    /**
     * @var array Holds the PDO connection instances
     */
    private static $instances = [];
    
    /**
     * @var array Holds connection configuration
     */
    private static $config = [];
    
    /**
     * Get a PDO connection instance
     * 
     * @param string $server Database server hostname
     * @param string $dbname Database name
     * @param string $username Database username
     * @param string $password Database password
     * @param array $options PDO connection options
     * @return PDO The PDO connection instance
     */
    public static function getInstance($server, $dbname, $username, $password, $options = []) {
        // Create a unique key for this connection
        $key = md5($server . $dbname . $username . $password);
        
        // If the connection doesn't exist or is closed, create a new one
        if (!isset(self::$instances[$key]) || !self::isConnectionAlive(self::$instances[$key])) {
            // Default PDO options if not provided
            if (empty($options)) {
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 5,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ];
            }
            
            try {
                // Create a new PDO instance
                $dsn = "mysql:host={$server};dbname={$dbname};charset=utf8mb4";
                self::$instances[$key] = new PDO($dsn, $username, $password, $options);
                self::$config[$key] = [
                    'server' => $server,
                    'dbname' => $dbname,
                    'username' => $username,
                    'created' => time()
                ];
            } catch (PDOException $e) {
                // Log error or handle exception as needed
                error_log("PDOCache connection error: " . $e->getMessage());
                throw $e;
            }
        }
        
        return self::$instances[$key];
    }
    
    /**
     * Check if a PDO connection is still alive
     * 
     * @param PDO $connection The PDO connection to check
     * @return bool True if connection is alive, false otherwise
     */
    private static function isConnectionAlive($connection) {
        if (!$connection) {
            return false;
        }
        
        try {
            // Try a simple query to check if connection is alive
            $connection->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Close a specific PDO connection
     * 
     * @param string $server Database server hostname
     * @param string $dbname Database name
     * @param string $username Database username
     * @param string $password Database password
     * @return bool True if connection was closed, false otherwise
     */
    public static function closeConnection($server, $dbname, $username, $password) {
        $key = md5($server . $dbname . $username . $password);
        
        if (isset(self::$instances[$key])) {
            self::$instances[$key] = null;
            unset(self::$instances[$key]);
            unset(self::$config[$key]);
            return true;
        }
        
        return false;
    }
    
    /**
     * Close all PDO connections
     */
    public static function closeAllConnections() {
        foreach (self::$instances as $key => $instance) {
            self::$instances[$key] = null;
            unset(self::$instances[$key]);
            unset(self::$config[$key]);
        }
    }
    
    /**
     * Get connection statistics
     * 
     * @return array Connection statistics
     */
    public static function getStats() {
        $stats = [
            'total_connections' => count(self::$instances),
            'connections' => []
        ];
        
        foreach (self::$config as $key => $config) {
            $stats['connections'][] = [
                'server' => $config['server'],
                'dbname' => $config['dbname'],
                'username' => $config['username'],
                'age' => time() - $config['created']
            ];
        }
        
        return $stats;
    }
}
