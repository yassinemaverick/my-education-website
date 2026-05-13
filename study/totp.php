<?php
/**
 * totp.php — RFC 6238 TOTP implementation (no Composer needed)
 * Compatible with Google Authenticator, Authy, and any TOTP app.
 */

class TOTP {
    const DIGITS  = 6;
    const PERIOD  = 30;  // seconds
    const WINDOW  = 1;   // allow 1 period before/after for clock drift

    /** Generate a random base32 secret (160-bit = 32 chars) */
    public static function generateSecret(): string {
        $bytes  = random_bytes(20);
        return self::base32encode($bytes);
    }

    /** Verify a 6-digit code against a secret */
    public static function verify(string $secret, string $code): bool {
        $code = preg_replace('/\s+/', '', $code);
        if (!preg_match('/^\d{6}$/', $code)) return false;

        $timestamp = (int)floor(time() / self::PERIOD);
        for ($i = -self::WINDOW; $i <= self::WINDOW; $i++) {
            if (hash_equals(self::generateCode($secret, $timestamp + $i), $code)) {
                return true;
            }
        }
        return false;
    }

    /** Generate the current TOTP code */
    public static function generateCode(string $secret, ?int $timestamp = null): string {
        if ($timestamp === null) {
            $timestamp = (int)floor(time() / self::PERIOD);
        }
        $key     = self::base32decode($secret);
        $msgPack = pack('N*', 0) . pack('N*', $timestamp);
        $hash    = hash_hmac('sha1', $msgPack, $key, true);
        $offset  = ord($hash[strlen($hash) - 1]) & 0x0F;
        $code    = (
            ((ord($hash[$offset])     & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) <<  8) |
            ((ord($hash[$offset + 3]) & 0xFF))
        ) % (10 ** self::DIGITS);
        return str_pad((string)$code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /** Generate a QR code URL using Google Charts API */
    public static function getQrUrl(string $secret, string $account, string $issuer = 'Upskill Education'): string {
        $issuer  = rawurlencode($issuer);
        $account = rawurlencode($account);
        $secret  = strtoupper($secret);
        $otpauth = "otpauth://totp/{$issuer}:{$account}?secret={$secret}&issuer={$issuer}&digits=6&period=30";
        return 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . rawurlencode($otpauth);
    }

    /** Generate the otpauth URI (for manual entry) */
    public static function getOtpAuthUri(string $secret, string $account, string $issuer = 'Upskill'): string {
        return "otpauth://totp/" . rawurlencode($issuer) . ":" . rawurlencode($account)
             . "?secret=" . strtoupper($secret) . "&issuer=" . rawurlencode($issuer);
    }

    /** Base32 encode */
    private static function base32encode(string $bytes): string {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary   = '';
        foreach (str_split($bytes) as $byte) {
            $binary .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $result = '';
        foreach (str_split(str_pad($binary, (int)(ceil(strlen($binary)/5)*5), '0'), 5) as $chunk) {
            $result .= $alphabet[bindec($chunk)];
        }
        return $result;
    }

    /** Base32 decode */
    private static function base32decode(string $base32): string {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32   = strtoupper(preg_replace('/\s+/', '', $base32));
        $binary   = '';
        foreach (str_split($base32) as $char) {
            $pos     = strpos($alphabet, $char);
            if ($pos === false) continue;
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $result = '';
        foreach (str_split(substr($binary, 0, (int)(floor(strlen($binary)/8)*8)), 8) as $chunk) {
            $result .= chr(bindec($chunk));
        }
        return $result;
    }
}
