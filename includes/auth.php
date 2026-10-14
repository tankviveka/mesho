<?php
/**
 * Authentication + security helpers (PHP sessions, CSRF, input validation).
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    // XAMPP stores sessions in a folder owned by the web-server user. If PHP
    // cannot write there (common when running the built-in dev server as a
    // normal user), fall back to the system temp directory so login still works.
    $savePath = (string) session_save_path();
    $savePath = rtrim($savePath, '/');
    if ($savePath !== '' && (!is_dir($savePath) || !is_writable($savePath))) {
        @session_save_path(sys_get_temp_dir());
    }

    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Escape output for HTML context. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Generate (or reuse) a CSRF token for forms. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Render a hidden CSRF input field. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Validate the CSRF token posted with a form.
 * Fails silently (dies) when the token is missing or wrong.
 */
function csrf_verify(): void
{
    $posted = $_POST['csrf_token'] ?? '';
    if (!is_string($posted) || $posted === '' || !hash_equals(csrf_token(), $posted)) {
        http_response_code(419);
        exit('Invalid or expired form token. Please go back and try again.');
    }
}

/** True when the visitor is logged in. */
function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

/** Require an authenticated session - redirects to login.php otherwise. */
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
    // Basic session hygiene.
    if (isset($_SESSION['last_seen']) && (time() - (int) $_SESSION['last_seen']) > 1800) {
        session_regenerate_id(true);
    }
    $_SESSION['last_seen'] = time();
}

/**
 * Validate login credentials against the `users` table.
 *
 * @return array<string, mixed>|null the user row on success, null on failure
 */
function attempt_login(string $username, string $password): ?array
{
    $username = trim($username);

    if ($username === '' || $password === '') {
        return null;
    }

    $user = db_row(
        'SELECT id, name, username, email, password_hash, role
           FROM users
          WHERE username = ? OR email = ?
          LIMIT 1',
        [$username, $username]
    );

    if ($user === null) {
        // Burn a little time so timing does not reveal whether the user exists.
        password_verify('dummy-password-for-timing', '$2y$10$invalidsaltinvalidsaltinvalidsaltinvalidsaltinvalidsaltin');
        return null;
    }

    if (!password_verify($password, $user['password_hash'])) {
        return null;
    }

    unset($user['password_hash']);
    return $user;
}

/** Log the current user in and start a fresh session id. */
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_name'] = (string) $user['name'];
    $_SESSION['user_username'] = (string) $user['username'];
    $_SESSION['user_role'] = (string) $user['role'];
    $_SESSION['last_seen'] = time();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/** Log out and destroy the session. */
function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
