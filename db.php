<?php
// FILE: db.php
$host     = "localhost";
$dbname   = "ccs_sitin";
$username = "root";
$password = ""; // XAMPP default = no password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}
?>
