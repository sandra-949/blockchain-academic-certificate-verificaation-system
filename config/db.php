<?php
// config/db.php - Database Connection
// Edit these values to match your local setup

define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Your MySQL username
define('DB_PASS', '');            // Your MySQL password
define('DB_NAME', 'certverify_db');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die('<div style="font-family:sans-serif;color:red;padding:20px;">
        <h3>Database Connection Failed</h3>
        <p>Error: ' . $conn->connect_error . '</p>
        <p>Please check your config/db.php settings and make sure MySQL is running.</p>
    </div>');
}

$conn->set_charset("utf8");

// Session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
