<?php
// db_connect.php
$host = 'localhost';
$dbname = 'online_store';
$username = 'root';
$password = '';

try {
    // PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // MySQLi connection (for legacy code)
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        // Just log error, don't die if mysqli fails as long as PDO works
        error_log("MySQLi connection failed: " . $conn->connect_error);
    }
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>