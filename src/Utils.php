<?php

class Utils
{
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
        // Use the actual domain or env var for domain
        $domain = "https://app.snoozer.cloud";
        return "$domain/actions/exec.php?ID=$id&a=$action&t=$time&vkey=$vkey";
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
