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
        $encryption_key = $key;
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $encryption_key, 0, $iv);
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
        $domain = $_ENV['APP_URL'] ?? 'https://app.snoozer.cloud';
        return "$domain/actions/exec.php?ID=$id&a=$action&t=$time&vkey=$vkey";
    }

    public static function getAppUrl()
    {
        return $_ENV['APP_URL'] ?? 'https://app.snoozer.cloud';
    }

    /**
     * Parse a time expression and return a future timestamp.
     * Handles rolling forward if the parsed time is in the past.
     *
     * @param string $timeExpr Time expression (e.g., "tomorrow", "monday", "+2 hours")
     * @param int|null $baseTimestamp Base timestamp for relative parsing (default: now)
     * @return int|false Timestamp or false if parsing fails
     */
    public static function parseTimeExpression($timeExpr, $baseTimestamp = null)
    {
        $baseTimestamp = $baseTimestamp ?? time();
        $timeExpr = strtolower(trim($timeExpr));

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
}
