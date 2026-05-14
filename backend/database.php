<?php

// Load .env file
$env = parse_ini_file(__DIR__ . '/../.env');

// Set default values safely
$host = $env['DB_HOST'] ?? 'localhost';
$port = $env['DB_PORT'] ?? 3309;   // Default MySQL port
$user = $env['DB_USER'] ?? 'root';
$pass = $env['DB_PASS'] ?? '';
$db   = $env['DB_NAME'] ?? 'inventory_db';

// Create connection with port
$conn = new mysqli($host, $user, $pass, $db, $port);

// Check connection
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
