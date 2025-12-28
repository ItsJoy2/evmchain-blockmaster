<?php

namespace App\Services;

class Crypto
{
    private $cipher = 'AES-256-CBC';
    private function getKey(string $password): string
    {
        return hash('sha256', $password, true); // 32 bytes key
    }

    public function encrypt(string $plaintext): string
    {
        $key = $this->getKey(env('APP_CODE'));
        $iv = random_bytes(openssl_cipher_iv_length($this->cipher));

        $encrypted = openssl_encrypt($plaintext, $this->cipher, $key, OPENSSL_RAW_DATA, $iv);

        return base64_encode($iv . $encrypted);
    }

    public function decrypt(string $encoded): ?string
    {
        $key = $this->getKey(env('APP_CODE'));
        $data = base64_decode($encoded);

        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);

        $decrypted = openssl_decrypt($encrypted, $this->cipher, $key, OPENSSL_RAW_DATA, $iv);

        return $decrypted ?: null;
    }
}
