<?php
include 'config.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== EXAM_REQUESTS TABLE STRUCTURE ===\n\n";
$result = $mysqli->query('DESCRIBE exam_requests');
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . " - " . $row['Null'] . " - " . $row['Key'] . " - " . $row['Default'] . "\n";
}

echo "\n=== SAMPLE EXAM_REQUESTS DATA ===\n\n";
$result = $mysqli->query('SELECT * FROM exam_requests LIMIT 1');
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    print_r($row);
} else {
    echo "No data found\n";
}

$mysqli->close();
?>
