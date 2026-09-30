<?php

namespace App\Services\Auth;

class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    public function provisioningUri(string $email, string $secret): string
    {
        $label = rawurlencode('DPPS:'.$email);

        return 'otpauth://totp/'.$label
            .'?secret='.$secret
            .'&issuer='.rawurlencode('DPPS')
            .'&algorithm=SHA1&digits=6&period=30';
    }

    public function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $slice = (int) floor(time() / 30);

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->codeAt($secret, $slice + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    public function currentCode(string $secret, ?int $timestamp = null): string
    {
        $slice = (int) floor(($timestamp ?? time()) / 30);

        return $this->codeAt($secret, $slice);
    }

    private function codeAt(string $secret, int $slice): string
    {
        $hash = hash_hmac('sha1', pack('J', $slice), $this->base32Decode($secret), true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $binary = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        );

        return str_pad((string) ($binary % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $bytes): string
    {
        $bits = '';

        foreach (str_split($bytes) as $character) {
            $bits .= str_pad(decbin(ord($character)), 8, '0', STR_PAD_LEFT);
        }

        $secret = '';

        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }

            $secret .= self::ALPHABET[bindec($chunk)];
        }

        return $secret;
    }

    private function base32Decode(string $secret): string
    {
        $secret = strtoupper((string) preg_replace('/[^A-Z2-7]/i', '', $secret));
        $bits = '';

        foreach (str_split($secret) as $character) {
            $position = strpos(self::ALPHABET, $character);
            $bits .= str_pad(decbin($position === false ? 0 : $position), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';

        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }

        return $bytes;
    }
}
