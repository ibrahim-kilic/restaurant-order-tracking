<?php

require_once __DIR__ . '/app.php';

class Database
{

    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $port = DB_PORT;
    private $charset = DB_CHARSET;
    private $conn = null;

    public function __construct($opts = array())
    {
        if (isset($opts['host'])) $this->host = $opts['host'];
        if (isset($opts['db_name'])) $this->db_name = $opts['db_name'];
        if (isset($opts['username'])) $this->username = $opts['username'];
        if (isset($opts['password'])) $this->password = $opts['password'];
        if (isset($opts['port'])) $this->port = (int)$opts['port'];
        if (isset($opts['charset'])) $this->charset = $opts['charset'];
    }

    public function getConnection()
    {
        if ($this->conn instanceof PDO) {
            return $this->conn;
        }
        $dsn = 'mysql:host=' . $this->host .
            ';port=' . $this->port .
            ';dbname=' . $this->db_name .
            ';charset=' . $this->charset;
        $options = array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT => false,
        );
        try {
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            $this->conn->exec('SET NAMES ' . $this->charset);
        } catch (PDOException $e) {
            $this->conn = null;
        }
        return $this->conn;
    }

    public function __destruct()
    {
        $this->conn = null;
    }
}
