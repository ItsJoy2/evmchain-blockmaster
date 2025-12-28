<?php

namespace App\Services;

use Elliptic\EC;
use kornrunner\Keccak;

class CreateWallet extends Crypto {
    public function createAddress(): object
    {
        $privateKey = bin2hex(random_bytes(32));
        $ec = new EC('secp256k1');
        $keyPair = $ec->keyFromPrivate($privateKey);
        $publicKey = $keyPair->getPublic()->encode('hex');
        $publicKeyBinary = hex2bin($publicKey);
        $address = '0x' . substr(Keccak::hash(substr($publicKeyBinary, 1), 256), 24);
        return (object)[
            'address' => $address,
            'key' => Crypto::encrypt($privateKey),
        ];
    }
}
