<?php

namespace App\Services;

use GuzzleHttp\Client;

class CheckBalance
{
    public function balance($rpcUrl, $fromAddress, $type = 'native', $contractAddress = null)
    {
        $client = new Client();

        if ($type === 'native') {

            $response = $client->post($rpcUrl, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_getBalance',
                    'params' => [$fromAddress, 'latest'],
                    'id' => 1,
                ]
            ]);

            $result = json_decode($response->getBody(), true)['result'];

        } elseif ($type === 'token' && $contractAddress) {
            $methodId = '0x70a08231';
            $addressNoPrefix = ltrim($fromAddress, '0x');
            $paddedAddress = str_pad($addressNoPrefix, 64, '0', STR_PAD_LEFT);
            $data = $methodId . $paddedAddress;

            $response = $client->post($rpcUrl, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_call',
                    'params' => [[
                        'to' => $contractAddress,
                        'data' => $data
                    ], 'latest'],
                    'id' => 1,
                ]
            ]);

            $result = json_decode($response->getBody(), true)['result'];
        } else {
            return 'Invalid parameters';
        }

        $balanceWei = gmp_init($result, 16);
        $etherValue = gmp_strval($balanceWei);
        $formatted = bcdiv($etherValue, '1000000000000000000', 18);

        return number_format((float) $formatted, 5, '.', '');
    }
}
