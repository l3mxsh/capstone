<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'client/dashboard.php'));
} else {
    header('Location: login.php');
}
exit;
