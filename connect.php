<?php
// db.php
$host = 'localhost';
$db = 'dentistme';  // Make sure this is the correct database name
$user = 'root';     // Default username in XAMPP
$pass = '';         // Default password in XAMPP is empty

$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
$conn = new mysqli($host, $user, $pass, $db);

?>
