<?php
$pageTitle = $pageTitle ?? 'Admin Portal';
$_adminUser = $pdo->query("SELECT avatar FROM users WHERE id=".(int)$_SESSION['user_id'])->fetch();
$_adminAvatar = $_adminUser['avatar'] ?? null;
$_adminInitial = strtoupper(substr($_SESSION['name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="icon" type="image/png" href="../assets/images/Logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../css/admin.css" rel="stylesheet">