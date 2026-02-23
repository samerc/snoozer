<?php
require_once 'Database.php';

class RateLimiter
{
    private $db;
    private $maxAttempts;
    private $decayMinutes;

    /**
     * @param int $maxAttempts Maximum failed attempts before lockout
     * @param int $decayMinutes Minutes until attempts reset
     */
    public function __construct($maxAttempts = 5, $decayMinutes = 15)
    {
        $this->db = Database::getInstance();
        $this->maxAttempts = $maxAttempts;
        $this->decayMinutes = $decayMinutes;
    }

    /**
     * Check if the given key (IP or email) is rate limited
     */
    public function tooManyAttempts($key)
    {
        $this->clearOldAttempts($key);
        $attempts = $this->getAttempts($key);
        return $attempts >= $this->maxAttempts;
    }

    /**
     * Record a failed attempt
     */
    public function hit($key)
    {
        $this->db->query(
            "INSERT INTO login_attempts (attempt_key, attempted_at) VALUES (?, NOW())",
            [$key]
        );
    }

    /**
     * Clear attempts for a key (call after successful login)
     */
    public function clear($key)
    {
        $this->db->query(
            "DELETE FROM login_attempts WHERE attempt_key = ?",
            [$key]
        );
    }

    /**
     * Get remaining seconds until lockout expires
     */
    public function availableIn($key)
    {
        $rows = $this->db->fetchAll(
            "SELECT attempted_at FROM login_attempts
             WHERE attempt_key = ?
             ORDER BY attempted_at ASC
             LIMIT 1",
            [$key]
        );

        if (empty($rows)) {
            return 0;
        }

        $firstAttempt = strtotime($rows[0]['attempted_at']);
        $unlocksAt = $firstAttempt + ($this->decayMinutes * 60);
        $remaining = $unlocksAt - time();

        return max(0, $remaining);
    }

    /**
     * Get number of attempts for a key
     */
    private function getAttempts($key)
    {
        $rows = $this->db->fetchAll(
            "SELECT COUNT(*) as count FROM login_attempts WHERE attempt_key = ?",
            [$key]
        );
        return (int)($rows[0]['count'] ?? 0);
    }

    /**
     * Clear attempts older than decay period
     */
    private function clearOldAttempts($key)
    {
        $this->db->query(
            "DELETE FROM login_attempts
             WHERE attempt_key = ?
             AND attempted_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            [$key, $this->decayMinutes],
            'si'
        );
    }

    /**
     * Get client IP address.
     *
     * Proxy headers (X-Forwarded-For, CF-Connecting-IP, etc.) can be spoofed
     * by any client unless the request actually arrives from a trusted proxy.
     * We only trust them when REMOTE_ADDR is a private/loopback address (i.e.
     * the request came through a local reverse proxy) OR when the TRUSTED_PROXY
     * environment variable explicitly names the proxy's IP.
     */
    public static function getClientIp()
    {
        $remoteAddr    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $trustedProxy  = $_ENV['TRUSTED_PROXY']  ?? null;

        // Determine whether the direct connection is from a trusted proxy
        $isTrusted = $trustedProxy
            ? ($remoteAddr === $trustedProxy)
            : (filter_var($remoteAddr, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false);
            // i.e., REMOTE_ADDR is a private/loopback address → behind a local proxy

        if ($isTrusted) {
            foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'] as $header) {
                if (!empty($_SERVER[$header])) {
                    $ip = $_SERVER[$header];
                    // X-Forwarded-For may contain a list; take the first (client) entry
                    if (strpos($ip, ',') !== false) {
                        $ip = trim(explode(',', $ip)[0]);
                    }
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }

        return filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : '0.0.0.0';
    }
}
