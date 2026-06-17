<?php
/**
 * PHP Script: Export MySQL table data to CSV
 * Requirements: PHP 7.4+ and MySQLi extension enabled
 */

// Database connection settings
$host     = "localhost";
$username = "root";
$password = "";
$database = "test_db";
$table    = "users"; // Change to your table name

// Connect to MySQL
$mysqli = new mysqli($host, $username, $password, $database);

// Check connection
if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}

// Prepare SQL query (adjust columns as needed)
$sql = "SELECT id, name, email, created_at FROM $table";
$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    die("SQL prepare failed: " . $mysqli->error);
}

$stmt->execute();
$result = $stmt->get_result();

// Set headers to force download as CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="export_' . date('Y-m-d_H-i-s') . '.csv"');

// Open PHP output stream
$output = fopen('php://output', 'w');

// Output column headings
if ($result->num_rows > 0) {
    $fields = $result->fetch_fields();
    $headers = [];
    foreach ($fields as $field) {
        $headers[] = $field->name;
    }
    fputcsv($output, $headers);
}

// Output rows
while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}

// Close resources
fclose($output);
$stmt->close();
$mysqli->close();
exit;
?>
