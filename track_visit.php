<?php
require_once 'config.php';

// Create visits table if not exists
getDB()->exec("CREATE TABLE IF NOT EXISTS visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page VARCHAR(100) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255),
    visited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Record this visit
$page = $_GET['page'] ?? 'unknown';
$ip   = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ua   = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

getDB()->prepare("INSERT INTO visits (page, ip, user_agent) VALUES (?,?,?)")
    ->execute([$page, $ip, $ua]);

echo json_encode(['recorded' => true]);
