<?php
/**
 * Auth
 * Session helpers and guards. The session stores only user_id, role and
 * full_name; everything else is loaded from the database when needed.
 * The guards answer API calls with JSON and page requests with redirects.
 */
class Auth
{
    /** Starts a logged-in session for $user (a row from the users table). */
    public static function login(array $user): void
    {
        if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);   // new session id stops session fixation
        }
        $_SESSION['user_id']   = (int) $user['user_id'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    /** @return array{user_id:int,role:string,full_name:string}|null */
    public static function currentUser(): ?array
    {
        if (!self::isLoggedIn()) {
            return null;
        }
        return [
            'user_id'   => (int) $_SESSION['user_id'],
            'role'      => $_SESSION['role'],
            'full_name' => $_SESSION['full_name'],
        ];
    }

    /** Where each role lands after login (relative to BASE_URL). */
    public static function dashboardFor(string $role): string
    {
        return $role === 'admin' ? 'admin/dashboard.php' : 'customer/dashboard.php';
    }

    public static function requireLogin(): void
    {
        self::devBypass();
        if (self::isLoggedIn()) {
            return;
        }
        if (self::isApiRequest()) {
            ApiResponse::error('Please log in first', 401);
        }
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }

    public static function requireRole(string $role): void
    {
        self::requireLogin();
        if ($_SESSION['role'] === $role) {
            return;
        }
        if (self::isApiRequest()) {
            ApiResponse::error('You do not have permission to do that', 403);
        }
        header('Location: ' . BASE_URL . self::dashboardFor($_SESSION['role']));
        exit;
    }

    /** Used by login.php / register.php: send people who are already in to their dashboard. */
    public static function redirectIfLoggedIn(): void
    {
        if (self::isLoggedIn()) {
            header('Location: ' . BASE_URL . self::dashboardFor($_SESSION['role']));
            exit;
        }
    }

    private static function isApiRequest(): bool
    {
        return strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false;
    }

    /**
     * DEV ONLY. Lets the frontend open protected pages before login exists,
     * with ?as=customer or ?as=admin. Works only when APP_ENV is 'dev' AND
     * DEV_BYPASS_AUTH is true. DISABLE (keep false) BEFORE SUBMISSION.
     */
    private static function devBypass(): void
    {
        if (APP_ENV !== 'dev' || DEV_BYPASS_AUTH !== true) {
            return;
        }
        $as = $_GET['as'] ?? '';
        if ($as === 'customer' || $as === 'admin') {
            $_SESSION['user_id']   = $as === 'admin' ? 1 : 2;
            $_SESSION['role']      = $as;
            $_SESSION['full_name'] = 'Dev ' . ucfirst($as);
        }
    }
}
