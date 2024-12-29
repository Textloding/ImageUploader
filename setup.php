<?php
require_once 'config.php';

try {
    // 创建数据库
    $conn = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 创建数据库
    $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    $conn->exec($sql);
    
    // 选择数据库
    $conn->exec("USE " . DB_NAME);
    
    // 创建images表
    $sql = "CREATE TABLE IF NOT EXISTS images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        file_size INT NOT NULL,
        mime_type VARCHAR(100) NOT NULL,
        upload_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        file_path VARCHAR(255) NOT NULL,
        views INT DEFAULT 0,
        last_viewed DATETIME
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->exec($sql);
    echo "数据库和表创建成功！\n";
    
} catch(PDOException $e) {
    die("设置失败: " . $e->getMessage() . "\n");
}
