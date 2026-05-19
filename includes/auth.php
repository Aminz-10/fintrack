<?php
/**
 * Authentication & session helpers.
 *
 * Every protected page should `require_once __DIR__ . '/../includes/auth.php';`
 * and then call `Auth::require();` at the top.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    // Harden cookies before starting session.
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('FINAPPSID');
    session_start();
}

class Auth
{
    /** Redirect to login if user is not authenticated. */
    public static function require(): void
    {
        if (!self::check()) {
            // Try remember-me cookie before forcing login.
            if (self::tryRememberLogin()) {
                return;
            }
            header('Location: ' . self::baseUrl() . '/login.php');
            exit;
        }

        // Idle session timeout
        if (isset($_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > SESSION_LIFETIME) {
            self::logout();
            header('Location: ' . self::baseUrl() . '/login.php?timeout=1');
            exit;
        }
        $_SESSION['last_seen'] = time();
    }

    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function id(): ?int
    {
        return self::check() ? (int)$_SESSION['user_id'] : null;
    }

    public static function user(): ?array
    {
        if (!self::check()) return null;
        $stmt = Database::pdo()->prepare(
            'SELECT id,name,email,currency,language,theme,created_at FROM users WHERE id = ?'
        );
        $stmt->execute([self::id()]);
        return $stmt->fetch() ?: null;
    }

    /** Validate credentials and start a session. */
    public static function attempt(string $email, string $password, bool $remember = false): bool
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([trim(strtolower($email))]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        self::login((int)$user['id'], $user['name']);

        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $hash  = hash('sha256', $token);
            Database::pdo()->prepare('UPDATE users SET remember_token = ? WHERE id = ?')
                ->execute([$hash, $user['id']]);
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            setcookie('finapp_remember', $user['id'] . ':' . $token, [
                'expires'  => time() + 60 * 60 * 24 * 30,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        return true;
    }

    /** Set the session for a known user id. */
    public static function login(int $userId, string $name = ''): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id']    = $userId;
        $_SESSION['user_name']  = $name;
        $_SESSION['last_seen']  = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    public static function logout(): void
    {
        // Clear remember-me cookie & token
        if (self::check()) {
            Database::pdo()->prepare('UPDATE users SET remember_token = NULL WHERE id = ?')
                ->execute([self::id()]);
        }
        if (isset($_COOKIE['finapp_remember'])) {
            setcookie('finapp_remember', '', time() - 3600, '/');
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'],
                $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    /** Authenticate via remember-me cookie if present. */
    private static function tryRememberLogin(): bool
    {
        if (empty($_COOKIE['finapp_remember'])) return false;
        $parts = explode(':', $_COOKIE['finapp_remember'], 2);
        if (count($parts) !== 2) return false;
        [$uid, $token] = $parts;
        $stmt = Database::pdo()->prepare(
            'SELECT id,name,remember_token FROM users WHERE id = ?'
        );
        $stmt->execute([(int)$uid]);
        $user = $stmt->fetch();
        if (!$user || !$user['remember_token']) return false;
        if (!hash_equals($user['remember_token'], hash('sha256', $token))) return false;

        self::login((int)$user['id'], $user['name']);
        return true;
    }

    // ---------- CSRF helpers ----------
    public static function csrf(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::csrf()) . '">';
    }

    public static function csrfVerify(): void
    {
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals(self::csrf(), (string)$token)) {
            http_response_code(419);
            die('Invalid CSRF token. Please refresh and try again.');
        }
    }

    public static function baseUrl(): string
    {
        return rtrim(APP_BASE_URL, '/');
    }
}

/**
 * Tiny helpers used across the app
 */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return Auth::baseUrl() . '/' . ltrim($path, '/');
}

function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }
    $msg = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $msg;
}

function fmtMoney(float $amount, ?string $currency = null): string
{
    $cur = $currency ?: ($_SESSION['user_currency'] ?? 'RM');
    return $cur . ' ' . number_format($amount, 2);
}

function currentCurrency(): string
{
    if (!empty($_SESSION['user_currency'])) {
        return $_SESSION['user_currency'];
    }
    if (Auth::check()) {
        $u = Auth::user();
        if ($u) {
            $_SESSION['user_currency'] = $u['currency'];
            return $u['currency'];
        }
    }
    return 'RM';
}
