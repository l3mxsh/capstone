<?php
session_start();

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Redirect already-logged-in users
if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'client/dashboard.php'));
    exit;
}

require_once 'config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role']    = $user['role'];
        $_SESSION['name']    = $user['name'];

        header('Location: ' . ($user['role'] === 'admin' ? 'admin/dashboard.php' : 'client/dashboard.php'));
        exit;
    }

    $error = 'Invalid credentials. Please try again.';
    } // end CSRF check
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Harvy Mance Films</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background-color: #0d1117;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            background: #161b22;
            border: 1px solid #2a2e35;
            border-radius: 12px;
        }
        .brand-title {
            color: #c9a84c;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .brand-sub {
            color: #8b949e;
            font-size: 0.875rem;
        }
        .form-control {
            background-color: #0d1117;
            border-color: #30363d;
            color: #e6edf3;
        }
        .form-control:focus {
            background-color: #0d1117;
            border-color: #c9a84c;
            color: #e6edf3;
            box-shadow: 0 0 0 0.2rem rgba(201, 168, 76, 0.2);
        }
        .form-label { color: #cdd9e5; }
        .btn-gold {
            background-color: #c9a84c;
            border-color: #c9a84c;
            color: #0d1117;
            font-weight: 600;
        }
        .btn-gold:hover {
            background-color: #b8963e;
            border-color: #b8963e;
            color: #0d1117;
        }
    </style>
</head>
<body>
    <div class="login-card p-4 p-md-5">
        <div class="text-center mb-4">
            <h4 class="brand-title">Harvy Mance Films</h4>
            <p class="brand-sub mb-0">Sign in to your account</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <input type="email" class="form-control" id="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-gold w-100">Login</button>
        </form>
    </div>
</body>
</html>
