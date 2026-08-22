<?php

// =====================================================
// Database Connection File
// GlobeTrotter
// =====================================================


// MySQL server name
$host = "localhost";

// MySQL username
$username = "root";

// MySQL password
$password = "";   // XAMPP ma normally blank hoy chhe

// Database name
$database = "globetrotter";


// Create connection
$conn = new mysqli(
    $host,
    $username,
    $password,
    $database
);


// Check connection
if ($conn->connect_error) {

    die("Database Connection Failed: " . $conn->connect_error);

}


// Set character encoding
$conn->set_charset("utf8mb4");

?>
