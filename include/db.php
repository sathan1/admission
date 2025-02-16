<?php
$host = "localhost";  // Change from "0.0.0.0" to "localhost" or "127.0.0.1"
$user = "root";
$password = "";
$dbname = "college_portal";  // Ensure this matches the actual database name

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


?>