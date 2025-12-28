<?php

namespace App\Services;
use App\Exceptions\RpcException;
use Web3p\EthereumTx\Transaction;

class TokenManage extends Crypto
{

    public function sendAnyChainTokenTransaction($senderAddress, $tokenAddress, $toAddress, $userKey, $rpcUrl, $chainId, $adminAddress, $adminKey, $amount = null, $isFullOut = false)
    {
        if (!$this->isValidAddress($senderAddress) || !$this->isValidAddress($tokenAddress) || !$this->isValidAddress($toAddress)) {
            return $this->apiResponse(false, 'Invalid address');
        }

        // Get token balance
        $balanceInWei = (string) $this->getTokenBalance($rpcUrl, $senderAddress, $tokenAddress);
        $balanceInWei = $this->toPlainString($balanceInWei);

        if (!is_numeric($balanceInWei) || $balanceInWei === '0') {
            return $this->apiResponse(false, 'Token balance is invalid or zero');
        }

        // Determine amount to send
        $decimals = 18;
        if ($isFullOut && $amount === null) {
            $amountInWei = $balanceInWei;
        } else {
            if (!is_numeric($amount)) {
                return $this->apiResponse(false, 'Amount must be a valid number');
            }

            $amountInWei = bcmul((string)$amount, bcpow('10', (string)$decimals), 0);
            $amountInWei = $this->toPlainString($amountInWei);

            if (!is_numeric($amountInWei) || bccomp($balanceInWei, $amountInWei) < 0) {
                return $this->apiResponse(false, 'Insufficient token balance to send the specified amount');
            }
        }

        // Prepare data for token transfer
        $data = '0xa9059cbb' .
            str_pad(substr($this->removeHexPrefix($toAddress), 0, 64), 64, '0', STR_PAD_LEFT) .
            str_pad($this->bcdechex($amountInWei), 64, '0', STR_PAD_LEFT);

        $gasLimit = 80000;
        $gasPrice = 1000000000;
        $gasFeeInWei = bcmul((string)$gasLimit, (string)$gasPrice);

        // Send gas fee to sender address from admin
        $this->sendGasFee($rpcUrl, $senderAddress, $gasFeeInWei, $adminKey, $chainId, $adminAddress);

        //sleep(0.5); // Optional: increase if necessary
        usleep(500000);

        $nativeBalance = $this->getNativeBalance($rpcUrl, $senderAddress) ?? '0';
        $gasFeeInWei   = $gasFeeInWei ?? '0';

// নিশ্চিত করো দুইটাই string আকারে decimal
        $nativeBalance = number_format((float)$nativeBalance, 0, '.', ''); // Wei হলে 0 decimal
        $gasFeeInWei   = number_format((float)$gasFeeInWei, 0, '.', '');

        if (bccomp($nativeBalance, $gasFeeInWei, 0) < 0) {
            return $this->apiResponse(false, 'Gas fee not received by sender address');
        }


        // Prepare transaction
        $nonce = $this->getNonce($rpcUrl, $senderAddress);
        $transaction = [
            'nonce' => '0x' . dechex($nonce),
            'from' => $senderAddress,
            'to' => $tokenAddress,
            'value' => '0x0',
            'gas' => '0x' . dechex($gasLimit),
            'gasPrice' => '0x' . dechex($gasPrice),
            'data' => $data,
            'chainId' => $chainId,
        ];

        // Sign and send
        $tx = new Transaction($transaction);
        $signedTx = $tx->sign($userKey);
        $txHash = $this->sendRawTransaction($rpcUrl, $signedTx);

        // Confirm transaction success via receipt
        for ($i = 0; $i < 10; $i++) {
            $receipt = $this->getTransactionReceipt($rpcUrl, $txHash);
            if ($receipt && isset($receipt['status']) && hexdec($receipt['status']) === 1) {
                return $this->apiResponse(true, 'Transaction sent successfully.', $txHash, number_format(bcdiv($amountInWei, bcpow('10', '18'), 4), 4, '.', ''));
            }
           // sleep(0.2); // wait before retrying
            usleep(500000);
        }

        return $this->apiResponse(false, 'Transaction sent but not confirmed after retries');
    }
    private function sendGasFee($rpcUrl, $toAddress, $estimatedGasFee, $adminKey, $chainId, $adminAddress)
    {
        if (!$this->isValidAddress($toAddress)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid address'
            ]);
        }

        $nonce = $this->getNonce($rpcUrl, $adminAddress);

        $gasLimit = 80000;
        $gasPrice = 1000000000;

        $currentBalance = $this->toPlainString($this->getNativeBalance($rpcUrl, $toAddress));
        $totalNeeded = $this->toPlainString(bcadd($estimatedGasFee, '0'));

        if (bccomp($currentBalance, $totalNeeded) >= 0) {
            return null;
        }

        $requiredTopUp = $this->toPlainString(bcsub($totalNeeded, $currentBalance));

        $transaction = [
            'nonce' => '0x' . dechex($nonce),
            'from' => $adminAddress,
            'to' => $toAddress,
            'value' => '0x' . dechex($requiredTopUp),
            'gas' => '0x' . dechex($gasLimit),
            'gasPrice' => '0x' . dechex($gasPrice),
            'chainId' => $chainId
        ];

        $tx = new Transaction($transaction);
        $signedTx = $tx->sign($adminKey);
        $txHash = $this->sendRawTransaction($rpcUrl, $signedTx);

        $this->waitForTransaction($rpcUrl, $txHash);

        return $txHash;
    }

    private function getNativeBalance($rpcUrl, $address): float|int
    {
        $postData = [
            'jsonrpc' => '2.0',
            'method' => 'eth_getBalance',
            'params' => [$address, 'latest'],
            'id' => 1
        ];
        $response = $this->sendRpcRequest($rpcUrl, $postData);
        return hexdec($this->removeHexPrefix($response['result']));
    }

    private function getTokenBalance($rpcUrl, $address, $tokenAddress)
    {
        $data = '0x70a08231' . str_pad(substr($this->removeHexPrefix($address), 0, 64), 64, '0', STR_PAD_LEFT);
        $postData = [
            'jsonrpc' => '2.0',
            'method' => 'eth_call',
            'params' => [
                [
                    'to' => $tokenAddress,
                    'data' => $data
                ],
                'latest'
            ],
            'id' => 1,
        ];
        $response = $this->sendRpcRequest($rpcUrl, $postData);

        return hexdec($this->removeHexPrefix($response['result']));
    }

    private function toPlainString($number)
    {
        if (!is_numeric($number)) return '0';

        if (stripos($number, 'e') !== false) {
            return number_format($number, 0, '', '');
        }

        return (string) $number;
    }


    private function getNonce($rpcUrl, $address)
    {
        $postData = [
            'jsonrpc' => '2.0',
            'method' => 'eth_getTransactionCount',
            'params' => [$address, 'pending'],
            'id' => 1
        ];
        $response = $this->sendRpcRequest($rpcUrl, $postData);
        return hexdec($response['result']);
    }


    private function sendRawTransaction($rpcUrl, $signedTx)
    {
        $postData = [
            'jsonrpc' => '2.0',
            'method' => 'eth_sendRawTransaction',
            'params' => ['0x' . $signedTx],
            'id' => 1
        ];
        $response = $this->sendRpcRequest($rpcUrl, $postData);
        $txHash = $response['result'];

        // Validate transaction hash format
        if (!preg_match('/^0x[a-fA-F0-9]{64}$/', $txHash)) {
            error_log("Invalid transaction hash returned: " . $txHash);
            throw new \Exception("Invalid transaction hash returned: " . $txHash);
        }

        return $txHash;
    }

    private function waitForTransaction($rpcUrl, $txHash)
    {
        if (!preg_match('/^0x[a-fA-F0-9]{64}$/', $txHash)) {
            throw new \Exception("Invalid transaction hash: " . $txHash);
        }

        $maxAttempts = 10;
        $attempt = 0;
        $delay = 1;

        while ($attempt < $maxAttempts) {
            $postData = [
                'jsonrpc' => '2.0',
                'method' => 'eth_getTransactionReceipt',
                'params' => [$txHash],
                'id' => 1
            ];

            try {
                $response = $this->sendRpcRequest($rpcUrl, $postData);

                if (array_key_exists('result', $response)) {
                    if ($response['result'] !== null) {
                        $status = $response['result']['status'] ?? null;
                        if ($status === '0x1') {
                            return true;
                        } elseif ($status === '0x0') {
                            throw new \Exception("Transaction failed: $txHash");
                        }
                    }
                } else {
                    // No result key present
                    error_log("Transaction $txHash RPC response missing 'result', attempt " . ($attempt + 1) . " of $maxAttempts: " . json_encode($response));
                }

            } catch (\Exception $e) {

            }

            sleep($delay);
            $attempt++;
        }
        throw new \Exception("Transaction timed out: $txHash");
    }


    private function sendRpcRequest($rpcUrl, $postData)
    {
        $ch = curl_init($rpcUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log("RPC request failed for method {$postData['method']}: $curlError");
            throw new \Exception("RPC request failed: $curlError");
        }

        if ($httpCode >= 400) {
            error_log("RPC request returned HTTP error {$httpCode} for method {$postData['method']}: $response");
            throw new \Exception("RPC request returned HTTP error: $httpCode");
        }

        $decodedResponse = json_decode($response, true);
        if ($decodedResponse === null) {
            error_log("Invalid JSON response for method {$postData['method']}: $response");
            throw new \Exception("Invalid JSON response from RPC");
        }

        error_log("RPC response for method {$postData['method']}: " . json_encode($decodedResponse));

        if (isset($decodedResponse['error'])) {
            error_log("RPC error for method {$postData['method']}: " . json_encode($decodedResponse['error']));
            if (isset($decodedResponse['error'])) {
                throw new RpcException("" . $decodedResponse['error']['message'], $decodedResponse['error']);
            }

        }

        if (!isset($decodedResponse['result'])) {
            error_log("RPC response missing 'result' for method {$postData['method']}: " . json_encode($decodedResponse));
            throw new \Exception("RPC response does not contain 'result' key for method {$postData['method']}");
        }

        return $decodedResponse;
    }

    private function bcdechex($dec)
    {
        $hex = '';
        do {
            $last = bcmod($dec, 16);
            $hex = dechex($last) . $hex;
            $dec = bcdiv(bcsub($dec, $last), 16);
        } while ($dec > 0);
        return $hex;
    }

    private function removeHexPrefix($hex)
    {
        return str_starts_with($hex, '0x') ? substr($hex, 2) : $hex;
    }

    private function isValidAddress($address)
    {
        return preg_match('/^0x[a-fA-F0-9]{40}$/', $address) === 1;
    }

    public function getTransactionReceipt($rpcUrl, $txHash)
    {
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($rpcUrl, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method'  => 'eth_getTransactionReceipt',
                    'params'  => [$txHash],
                    'id'      => 1,
                ],
                'timeout' => 10,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (isset($result['result']) && $result['result']) {
                return $result['result'];
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function apiResponse(bool $status, string $message, $txHash = null, $amount  = null): array
    {
        return [
            'status' => $status,
            'message' => $message,
            'txHash' => $txHash,
            'amount' => $amount,
        ];
    }

}

