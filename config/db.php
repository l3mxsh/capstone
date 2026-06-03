<?php
$host = 'localhost:3307';
$db   = 'harvy';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Ensure cancellations can track whether the cancellation was initiated by a client or admin.
    try {
        $columnExists = $pdo->query("SHOW COLUMNS FROM cancellations LIKE 'initiated_by'")->fetch();
        if (!$columnExists) {
            $pdo->exec("ALTER TABLE cancellations ADD initiated_by ENUM('client','admin') DEFAULT 'client' AFTER cancellation_status");
        }
    } catch (PDOException $ignore) {
        // Ignore if table does not exist yet or if migration cannot be performed at runtime.
    }
} catch (PDOException $e) {
    throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
}
