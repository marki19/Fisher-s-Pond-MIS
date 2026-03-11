<?php
date_default_timezone_set(timezoneId: 'Asia/Manila');
// Database Connection
$host = 'localhost';
$db = 'fishers_pond_mis';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
try {
    $pdo = new PDO(dsn: $dsn, username: $user, password: $pass, options: $options);
   
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>