<?php
session_start();

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "my_auth_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if user is logged in and is mayor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'mayor') {
    header("Location: login.php");
    exit();
}
?>