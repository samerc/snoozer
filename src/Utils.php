<?php

class Utils
{
    public static function generateCsrfToken()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCsrfToken($token)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function csrfField()
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::generateCsrfToken()) . '">';
    }

    /**
     * Validate CSRF token for AJAX requests.
     * Checks X-CSRF-Token header first, then falls back to csrf_token in JSON body.
     *
     * @param array|null $jsonBody Decoded JSON body (optional)
     * @return bool True if valid, false otherwise
     */
    public static function validateAjaxCsrf($jsonBody = null)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check header first
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!empty($headerToken) && isset($_SESSION['csrf_token'])) {
            return hash_equals($_SESSION['csrf_token'], $headerToken);
        }

        // Fall back to JSON body
        if ($jsonBody && isset($jsonBody['csrf_token']) && isset($_SESSION['csrf_token'])) {
            return hash_equals($_SESSION['csrf_token'], $jsonBody['csrf_token']);
        }

        return false;
    }

    /**
     * Output a meta tag for CSRF token (for JS to read)
     */
    public static function csrfMeta()
    {
        return '<meta name="csrf-token" content="' . htmlspecialchars(self::generateCsrfToken()) . '">';
    }

    /**
     * Validate an email address
     *
     * @param string $email Email address to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValidEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Sanitize an email address (lowercase, trim)
     *
     * @param string $email Email address to sanitize
     * @return string Sanitized email
     */
    public static function sanitizeEmail($email)
    {
        return strtolower(trim($email));
    }

    public static function dataEncrypt($data, $key)
    {
        // Matches legacy logic: Base64(Encrypted . :: . IV)
        $iv = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }

    public static function dataDecrypt($data, $key)
    {
        $encryption_key = $key;
        list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
        return openssl_decrypt($encrypted_data, 'aes-256-cbc', $encryption_key, 0, $iv);
    }

    public static function getActionUrl($id, $messageId, $action, $time, $sslKey)
    {
        $vkey = rawurlencode(self::dataEncrypt($messageId, $sslKey));
        $domain = $_ENV['APP_URL'] ?? 'http://localhost:8000';
        return "$domain/actions/exec.php?ID=$id&a=$action&t=$time&vkey=$vkey";
    }

    public static function getAppUrl()
    {
        return $_ENV['APP_URL'] ?? 'http://localhost:8000';
    }

    /**
     * Get the mail domain for snoozer addresses
     */
    public static function getMailDomain()
    {
        return $_ENV['MAIL_DOMAIN'] ?? 'snoozer.cloud';
    }

    /**
     * Parse a time expression and return a future timestamp.
     * Handles rolling forward if the parsed time is in the past.
     *
     * Supported formats beyond PHP strtotime defaults:
     * - "eod"         → today at $defaultHour:00 (tomorrow if already past)
     * - "eow"         → next Friday at $defaultHour:00
     * - "next-monday" → "next monday" (hyphens treated as spaces)
     * - "31dec"       → "31 dec" (digit+month or month+digit without space)
     *
     * @param string $timeExpr   Time expression (e.g., "tomorrow", "monday", "31dec", "eod")
     * @param int|null $baseTimestamp Base timestamp for relative parsing (default: now)
     * @param int $defaultHour   Hour to use for eod/eow (user's DefaultReminderTime, default 17)
     * @return int|false Timestamp or false if parsing fails
     */
    public static function parseTimeExpression($timeExpr, $baseTimestamp = null, $defaultHour = 17)
    {
        $baseTimestamp = $baseTimestamp ?? time();
        $timeExpr = strtolower(trim($timeExpr));
        $defaultHour = max(0, min(23, (int) $defaultHour));

        // Normalise hyphens to spaces ("next-tuesday" → "next tuesday")
        $timeExpr = str_replace('-', ' ', $timeExpr);

        // Add space between a digit run and a month name, or vice-versa
        // Handles "31dec" → "31 dec" and "dec31" → "dec 31"
        $months = 'jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec';
        $timeExpr = preg_replace('/(\d+)(' . $months . ')/i', '$1 $2', $timeExpr);
        $timeExpr = preg_replace('/(' . $months . ')(\d+)/i', '$1 $2', $timeExpr);

        // "eod" — end of (work) day
        if ($timeExpr === 'eod') {
            $candidate = mktime($defaultHour, 0, 0);
            return $candidate > time() ? $candidate : mktime($defaultHour, 0, 0, date('n'), date('j') + 1);
        }

        // "eow" — end of week (next Friday)
        if ($timeExpr === 'eow') {
            $friday = strtotime('friday', $baseTimestamp);
            $candidate = mktime($defaultHour, 0, 0, date('n', $friday), date('j', $friday), date('Y', $friday));
            return $candidate > time() ? $candidate : strtotime('+1 week', $candidate);
        }

        // Parse the time expression
        $actionTimestamp = strtotime($timeExpr, $baseTimestamp);
        if ($actionTimestamp === false) {
            return false;
        }

        // Roll forward if time is in the past
        if ($actionTimestamp < time()) {
            if (preg_match("/(?:sat|sun|mon|tue|wed|thu|fri)/", $timeExpr)) {
                // Weekday reference → add 1 week
                $actionTimestamp = strtotime('+1 week', $actionTimestamp);
            } elseif (preg_match("/(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)/", $timeExpr)) {
                // Month reference → add 1 year
                $actionTimestamp = strtotime('+1 year', $actionTimestamp);
            } elseif (!preg_match("/(?:min|hour|day|week|month)/", $timeExpr)) {
                // Not a relative expression → add 1 day
                $actionTimestamp = strtotime('+1 day', $actionTimestamp);
            }
        }

        // Midnight adjustment: if landing exactly on midnight, shift to 6am
        // (unless explicitly requesting midnight)
        if (!preg_match("/(?:midnight|0000)/", $timeExpr)) {
            if ($actionTimestamp == strtotime("midnight", $actionTimestamp)) {
                $actionTimestamp = strtotime("+6 hours", $actionTimestamp);
            }
        }

        return $actionTimestamp;
    }

    public static function time_elapsed_string($datetime, $full = false)
    {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        $string = array(
            'y' => 'year',
            'm' => 'month',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        );
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full)
            $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ($diff->invert ? ' ago' : ' remaining') : 'just now';
    }

    /**
     * Get the client's IP address.
     * Only trusts proxy headers when the direct connection is from a known
     * trusted proxy (private/loopback address or TRUSTED_PROXY env var).
     */
    public static function getClientIp()
    {
        $remoteAddr   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $trustedProxy = $_ENV['TRUSTED_PROXY']  ?? null;

        $isTrusted = $trustedProxy
            ? ($remoteAddr === $trustedProxy)
            : (filter_var($remoteAddr, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false);

        if ($isTrusted && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip  = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : 'unknown';
    }
}
