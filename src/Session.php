<?php

class Session
{
    // Session timeout in seconds (30 minutes)
    const TIMEOUT = 1800;

    /**
     * Send security headers to protect against common attacks.
     * Call this before any output.
     */
    public static function sendSecurityHeaders()
    {
        // Prevent clickjacking
        header('X-Frame-Options: SAMEORIGIN');

        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');

        // Enable XSS filter in older browsers
        header('X-XSS-Protection: 1; mode=block');

        // Control referrer information
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Content Security Policy
        // Allow scripts/styles from self and CDNs used by the app
        $csp = "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com https://cdnjs.cloudflare.com https://stackpath.bootstrapcdn.com; " .
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://stackpath.bootstrapcdn.com https://cdnjs.cloudflare.com; " .
            "font-src 'self' https://fonts.gstatic.com; " .
            "img-src 'self' data:; " .
            "connect-src 'self' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://stackpath.bootstrapcdn.com; " .
            "frame-ancestors 'self';";
        header("Content-Security-Policy: $csp");

        // Permissions Policy (formerly Feature-Policy)
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }

    /**
     * Initialize a secure session with proper cookie settings.
     * Call this INSTEAD of session_start() on all pages.
     */
    public static function start()
    {
        // Send security headers first
        self::sendSecurityHeaders();
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Secure session cookie settings
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        session_set_cookie_params([
            'lifetime' => 0,                    // Session cookie (expires when browser closes)
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,               // Only send over HTTPS
            'httponly' => true,                 // Not accessible via JavaScript
            'samesite' => 'Lax'                 // CSRF protection
        ]);

        session_start();

        // Check for session timeout
        if (isset($_SESSION['last_activity'])) {
            $inactiveTime = time() - $_SESSION['last_activity'];
            if ($inactiveTime > self::TIMEOUT) {
                self::destroy();
                return;
            }
        }

        // Update last activity timestamp
        $_SESSION['last_activity'] = time();
    }

    /**
     * Destroy the session completely.
     */
    public static function destroy()
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    /**
     * Check if user is logged in.
     */
    public static function isLoggedIn()
    {
        return isset($_SESSION['user_id']);
    }

    /**
     * Require authentication, redirect to login if not logged in.
     */
    public static function requireAuth()
    {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }

    /**
     * Require admin role.
     */
    public static function requireAdmin()
    {
        self::requireAuth();
        if (($_SESSION['user_role'] ?? 'user') !== 'admin') {
            http_response_code(403);
            die('Access denied.');
        }
    }

    /**
     * Get remaining session time in seconds.
     */
    public static function getRemainingTime()
    {
        if (!isset($_SESSION['last_activity'])) {
            return 0;
        }
        $remaining = self::TIMEOUT - (time() - $_SESSION['last_activity']);
        return max(0, $remaining);
    }
}
