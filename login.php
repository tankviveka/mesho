<?php
/**
 * Login - PHP session based demo authentication.
 *
 * Demo credentials (also printed on the screen):
 *   username: admin      password: admin123
 *   username: seller     password: seller123
 */

require_once __DIR__ . '/includes/init.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $username = isset($_POST['username']) && is_string($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';

    if (strlen($username) > 100 || strlen($password) > 200) {
        $error = 'Invalid input provided.';
    } else {
        $user = attempt_login($username, $password);
        if ($user !== null) {
            login_user($user);
            flash_set('success', 'Welcome back, ' . $user['name'] . '! You are logged in to the DEMO dashboard.');
            header('Location: dashboard.php');
            exit;
        }
        $error = 'Invalid username or password. Please use the demo credentials below.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Login | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/vendor/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-page">

    <div class="auth-card">
        <div class="auth-head">
            <span class="demo-ribbon"><span class="demo-dot"></span> DEMO MODE</span>
            <div class="logo"><i class="bi bi-shop"></i></div>
            <h2>Meesho Seller Dashboard</h2>
            <p>Demo Edition &mdash; sample data only, no real Meesho account connected</p>
        </div>

        <div class="auth-body">
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger py-2 small mb-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i><?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="login.php" autocomplete="off" novalidate>
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label" for="username">Username or Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input class="form-control" type="text" id="username" name="username"
                               value="<?= e($_POST['username'] ?? 'admin') ?>"
                               placeholder="admin" maxlength="100" required autofocus>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input class="form-control" type="password" id="password" name="password"
                               value="admin123" placeholder="••••••••" maxlength="200" required>
                    </div>
                </div>

                <button class="btn btn-primary w-100 py-2 mt-2" type="submit">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Sign in to Demo Dashboard
                </button>
            </form>

            <div class="demo-banner mt-4">
                <i class="bi bi-info-circle-fill"></i>
                <div>
                    <strong>Demo login</strong><br>
                    Username: <code>admin</code> &nbsp;&bull;&nbsp; Password: <code>admin123</code><br>
                    Alternate: <code>seller</code> / <code>seller123</code>
                </div>
            </div>
        </div>

        <div class="auth-foot">
            This is a locally hosted <strong>DEMO</strong>. Data is generated sample data stored in MySQL
            and is <strong>not</strong> affiliated with or connected to Meesho.
        </div>
    </div>

</div>
</body>
</html>
