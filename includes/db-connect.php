<?php
// =====================================================
// Database Connection File
// GlobeTrotter
// =====================================================
$host = "localhost";
$username = "root";
$password = "";   // XAMPP ma normally blank hoy chhe
$database = "globetrotter";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $username, $password, $database);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>
