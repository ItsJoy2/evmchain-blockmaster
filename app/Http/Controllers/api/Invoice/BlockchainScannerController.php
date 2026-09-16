<?php

namespace App\Http\Controllers\api\Invoice;

use App\Http\Controllers\Controller;
use App\Models\ChainList;
use App\Models\TokenList;
use App\Models\MerchantUserWallet;
use App\Models\BlockchainDeposit;
use App\Models\MerchantWalletScanState;
use App\Models\User;
use App\Services\NativeCoin;
use App\Services\TokenManage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BlockchainScannerController extends Controller
{
    private const TRANSFER_TOPIC =
        '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

    /**
     * Scan all merchant deposit wallets.
     *
     * This replaces the old PaymentJobs based system.
     */
    public function scan()
    {
        $summary = [
            'wallets' => 0,
            'chains' => 0,
            'native_deposits' => 0,
            'token_deposits' => 0,
            'transferred' => 0,
            'failed' => 0,
        ];

        try {

            $wallets = MerchantUserWallet::query()
                ->where('is_active', true)
                ->with('merchant')
                ->get();

            if ($wallets->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No active merchant wallets found.',
                    'summary' => $summary,
                ]);
            }

            $chains = ChainList::query()
                ->where('status', true)
                ->get();

            if ($chains->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No active blockchain found.',
                    'summary' => $summary,
                ]);
            }

            foreach ($wallets as $wallet) {

                $summary['wallets']++;

                if (!$wallet->merchant) {
                    continue;
                }

                foreach ($chains as $chain) {

                    $summary['chains']++;

                    try {

                        $result = $this->scanWalletOnChain(
                            $wallet,
                            $chain
                        );

                        $summary['native_deposits'] +=
                            $result['native_deposits'];

                        $summary['token_deposits'] +=
                            $result['token_deposits'];

                        $summary['transferred'] +=
                            $result['transferred'];

                        $summary['failed'] +=
                            $result['failed'];

                    } catch (\Throwable $e) {

                        Log::error('Blockchain wallet scan failed', [
                            'wallet_id' => $wallet->id,
                            'wallet_address' => $wallet->wallet_address,
                            'chain_id' => $chain->chain_id,
                            'chain_name' => $chain->chain_name,
                            'error' => $e->getMessage(),
                        ]);

                        $summary['failed']++;
                    }
                }

                $wallet->last_scanned_at = now();
                $wallet->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Blockchain scanning completed.',
                'summary' => $summary,
            ]);

        } catch (\Throwable $e) {

            Log::error('Blockchain scanner fatal error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Blockchain scanner failed.',
                'error' => $e->getMessage(),
                'summary' => $summary,
            ], 500);
        }
    }


    /**
     * Scan one generated wallet on one blockchain.
     */
    private function scanWalletOnChain(
        MerchantUserWallet $wallet,
        ChainList $chain
    ): array {

        $result = [
            'native_deposits' => 0,
            'token_deposits' => 0,
            'transferred' => 0,
            'failed' => 0,
        ];

        $rpcUrl = $chain->chain_rpc_url;

        if (empty($rpcUrl)) {
            return $result;
        }

        /**
         * Get current block.
         */
        $latestBlock = $this->rpc(
            $rpcUrl,
            'eth_blockNumber'
        );

        if (!$latestBlock) {
            return $result;
        }

        $latestBlockNumber = $this->hexToDecimal($latestBlock);

        /**
         * Get scanner state.
         *
         * chain_list.id is stored in chain_list_id.
         * chain_list.chain_id is actual EVM chain ID.
         */
        $state = MerchantWalletScanState::firstOrCreate(
            [
                'merchant_user_wallet_id' => $wallet->id,
                'chain_list_id' => $chain->id,
            ],
            [
                'last_scanned_block' => $latestBlockNumber,
                'last_scanned_at' => now(),
            ]
        );

        /**
         * New wallet/chain:
         *
         * Start from current block.
         * This prevents old blockchain deposits from being
         * treated as new deposits.
         */
        if ((int) $state->last_scanned_block >= $latestBlockNumber) {

            $state->last_scanned_at = now();
            $state->save();

            return $result;
        }

        /**
         * Scan only a reasonable number of blocks per execution.
         *
         * This prevents RPC timeout.
         */
        $fromBlock = (int) $state->last_scanned_block + 1;

        $maxBlocksPerRun = 100;

        $toBlock = min(
            $fromBlock + $maxBlocksPerRun - 1,
            $latestBlockNumber
        );

        /**
         * ---------------------------------------------------------
         * 1. NATIVE COIN
         * ---------------------------------------------------------
         */
        $nativeResult = $this->scanNativeDeposits(
            $wallet,
            $chain,
            $fromBlock,
            $toBlock
        );

        $result['native_deposits'] += $nativeResult['deposits'];
        $result['transferred'] += $nativeResult['transferred'];
        $result['failed'] += $nativeResult['failed'];


        /**
         * ---------------------------------------------------------
         * 2. ERC20 TOKENS
         * ---------------------------------------------------------
         */
        $tokens = TokenList::query()
            ->where('chain_id', $chain->id)
            ->where('status', true)
            ->get();

        foreach ($tokens as $token) {

            try {

                $tokenResult = $this->scanTokenDeposits(
                    $wallet,
                    $chain,
                    $token,
                    $fromBlock,
                    $toBlock
                );

                $result['token_deposits'] +=
                    $tokenResult['deposits'];

                $result['transferred'] +=
                    $tokenResult['transferred'];

                $result['failed'] +=
                    $tokenResult['failed'];

            } catch (\Throwable $e) {

                Log::error('Token scan failed', [
                    'wallet_id' => $wallet->id,
                    'chain_id' => $chain->chain_id,
                    'token_id' => $token->id,
                    'token' => $token->contract_address,
                    'error' => $e->getMessage(),
                ]);

                $result['failed']++;
            }
        }


        /**
         * Update scanner state ONLY after successful scan.
         */
        $state->last_scanned_block = $toBlock;
        $state->last_scanned_at = now();
        $state->save();

        return $result;
    }


    /**
     * Scan native coin transfers.
     */
    private function scanNativeDeposits(
        MerchantUserWallet $wallet,
        ChainList $chain,
        int $fromBlock,
        int $toBlock
    ): array {

        $result = [
            'deposits' => 0,
            'transferred' => 0,
            'failed' => 0,
        ];

        $rpcUrl = $chain->chain_rpc_url;

        for ($blockNumber = $fromBlock; $blockNumber <= $toBlock; $blockNumber++) {

            try {

                $blockHex = '0x' . dechex($blockNumber);

                $block = $this->rpc(
                    $rpcUrl,
                    'eth_getBlockByNumber',
                    [
                        $blockHex,
                        true
                    ]
                );

                if (!$block || empty($block['transactions'])) {
                    continue;
                }

                foreach ($block['transactions'] as $transaction) {

                    $to = strtolower(
                        $transaction['to'] ?? ''
                    );

                    if (!$to) {
                        continue;
                    }

                    /**
                     * Only incoming transaction.
                     */
                    if ($to !== strtolower($wallet->wallet_address)) {
                        continue;
                    }

                    $valueHex = $transaction['value'] ?? '0x0';

                    if ($valueHex === '0x0') {
                        continue;
                    }

                    $rawAmount = $this->hexToDecimal(
                        $valueHex
                    );

                    if ($rawAmount === '0') {
                        continue;
                    }

                    /**
                     * Native EVM coin = 18 decimals.
                     */
                    $amount = bcdiv(
                        $rawAmount,
                        '1000000000000000000',
                        18
                    );

                    /**
                     * Duplicate protection.
                     */
                    $exists = BlockchainDeposit::query()
                        ->where('chain_id', $chain->chain_id)
                        ->where('tx_hash', $transaction['hash'])
                        ->where(function ($q) {
                            $q->whereNull('log_index')
                              ->orWhere('log_index', 0);
                        })
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    /**
                     * Create deposit record.
                     */
                    $deposit = BlockchainDeposit::create([
                        'merchant_id' => $wallet->merchant_id,
                        'merchant_user_wallet_id' => $wallet->id,

                        'chain_id' => $chain->chain_id,
                        'chain_name' => $chain->chain_name,
                        'network_standard' => 'EVM',

                        'token_name' => $chain->chain_name,
                        'token_symbol' => $chain->chain_name,

                        'contract_address' => null,
                        'token_decimals' => 18,

                        'raw_amount' => $rawAmount,
                        'amount' => $amount,

                        'type' => 'native',

                        'tx_hash' => $transaction['hash'],
                        'block_number' => $blockNumber,
                        'log_index' => 0,

                        'from_address' =>
                            $transaction['from'] ?? null,

                        'to_address' =>
                            $wallet->wallet_address,

                        'status' => 'detected',
                        'webhook_status' => 'pending',
                        'transfer_status' => 'pending',

                        'detected_at' => now(),
                    ]);

                    $result['deposits']++;

                    /**
                     * Immediately sweep to merchant.
                     */
                    $transferResult = $this->transferNativeDeposit(
                        $deposit,
                        $wallet,
                        $chain
                    );

                    if ($transferResult) {
                        $result['transferred']++;
                    } else {
                        $result['failed']++;
                    }
                }

            } catch (\Throwable $e) {

                Log::error('Native block scan failed', [
                    'wallet_id' => $wallet->id,
                    'chain_id' => $chain->chain_id,
                    'block' => $blockNumber,
                    'error' => $e->getMessage(),
                ]);

                $result['failed']++;
            }
        }

        return $result;
    }


    /**
     * Scan ERC20 Transfer events.
     */
    private function scanTokenDeposits(
        MerchantUserWallet $wallet,
        ChainList $chain,
        TokenList $token,
        int $fromBlock,
        int $toBlock
    ): array {

        $result = [
            'deposits' => 0,
            'transferred' => 0,
            'failed' => 0,
        ];

        if (!$this->isValidAddress($token->contract_address)) {
            return $result;
        }

        $rpcUrl = $chain->chain_rpc_url;

        /**
         * ERC20 Transfer event:
         *
         * Transfer(address indexed from,
         *          address indexed to,
         *          uint256 value)
         *
         * topics[2] = destination wallet.
         */
        $walletTopic = $this->addressToTopic(
            $wallet->wallet_address
        );

        /**
         * Scan max 100 blocks at a time.
         */
        $logs = $this->rpc(
            $rpcUrl,
            'eth_getLogs',
            [[
                'fromBlock' => '0x' . dechex($fromBlock),
                'toBlock' => '0x' . dechex($toBlock),

                'address' => $token->contract_address,

                'topics' => [
                    self::TRANSFER_TOPIC,
                    null,
                    $walletTopic,
                ],
            ]]
        );

        if (!is_array($logs)) {
            return $result;
        }

        /**
         * Get token decimals from blockchain.
         *
         * We store actual decimals in deposit table.
         */
        $decimals = $this->getTokenDecimals(
            $rpcUrl,
            $token->contract_address
        );

        foreach ($logs as $log) {

            try {

                $txHash = $log['transactionHash'] ?? null;

                if (!$txHash) {
                    continue;
                }

                $logIndexHex = $log['logIndex'] ?? '0x0';

                $logIndex = $this->hexToDecimal(
                    $logIndexHex
                );

                /**
                 * Duplicate protection.
                 */
                $exists = BlockchainDeposit::query()
                    ->where('chain_id', $chain->chain_id)
                    ->where('tx_hash', $txHash)
                    ->where('log_index', $logIndex)
                    ->exists();

                if ($exists) {
                    continue;
                }

                /**
                 * Raw ERC20 amount.
                 */
                $rawAmount = $this->hexToDecimal(
                    $log['data'] ?? '0x0'
                );

                if ($rawAmount === '0') {
                    continue;
                }

                $amount = $this->fromRawAmount(
                    $rawAmount,
                    $decimals
                );

                /**
                 * Decode sender.
                 */
                $fromAddress = $this->topicToAddress(
                    $log['topics'][1] ?? null
                );

                /**
                 * Create deposit.
                 */
                $deposit = BlockchainDeposit::create([
                    'merchant_id' => $wallet->merchant_id,
                    'merchant_user_wallet_id' => $wallet->id,

                    'chain_id' => $chain->chain_id,
                    'chain_name' => $chain->chain_name,
                    'network_standard' => 'EVM',

                    'token_name' => $token->token_name,
                    'token_symbol' => $token->symbol,

                    'contract_address' =>
                        strtolower($token->contract_address),

                    'token_decimals' => $decimals,

                    'raw_amount' => $rawAmount,
                    'amount' => $amount,

                    'type' => 'token',

                    'tx_hash' => $txHash,

                    'block_number' =>
                        $this->hexToDecimal(
                            $log['blockNumber'] ?? '0x0'
                        ),

                    'log_index' => $logIndex,

                    'from_address' => $fromAddress,
                    'to_address' => $wallet->wallet_address,

                    'status' => 'detected',
                    'webhook_status' => 'pending',
                    'transfer_status' => 'pending',

                    'detected_at' => now(),
                ]);

                $result['deposits']++;

                /**
                 * Sweep token balance to merchant.
                 */
                $transferResult = $this->transferTokenDeposit(
                    $deposit,
                    $wallet,
                    $chain
                );

                if ($transferResult) {
                    $result['transferred']++;
                } else {
                    $result['failed']++;
                }

            } catch (\Throwable $e) {

                Log::error('ERC20 deposit processing failed', [
                    'wallet_id' => $wallet->id,
                    'chain_id' => $chain->chain_id,
                    'token' => $token->contract_address,
                    'tx_hash' => $log['transactionHash'] ?? null,
                    'error' => $e->getMessage(),
                ]);

                $result['failed']++;
            }
        }

        return $result;
    }


    /**
     * Transfer native deposit using existing NativeCoin service.
     *
     * NativeCoin is NOT modified.
     */
    private function transferNativeDeposit(
        BlockchainDeposit $deposit,
        MerchantUserWallet $wallet,
        ChainList $chain
    ): bool {

        try {

            $merchant = $wallet->merchant;

            if (!$merchant) {
                throw new \Exception(
                    'Merchant not found.'
                );
            }

            if (!$merchant->wallet_address) {
                throw new \Exception(
                    'Merchant wallet address not found.'
                );
            }

            $nativeCoin = app(NativeCoin::class);

            /**
             * wallet_key is encrypted cast.
             *
             * Laravel automatically decrypts it when accessed.
             */
            $walletKey = $wallet->wallet_key;

            if (!$walletKey) {
                throw new \Exception(
                    'Deposit wallet private key not found.'
                );
            }

            /**
             * IMPORTANT:
             *
             * NativeCoin:
             *
             * isFullOut = false
             * amount = detected amount
             *
             * This means the service itself checks:
             *
             * balance >= amount + gas
             */
            $response = $nativeCoin->sendAnyChainNativeBalance(
                $wallet->wallet_address,
                $merchant->wallet_address,
                $walletKey,
                $chain->chain_rpc_url,
                $chain->chain_id,
                false,
                $deposit->amount
            );

            if (
                !empty($response['status']) &&
                !empty($response['txHash'])
            ) {

                $deposit->transfer_status = 'completed';
                $deposit->transfer_tx_hash =
                    $response['txHash'];

                $deposit->transferred_at = now();
                $deposit->status = 'completed';

                $deposit->save();

                /**
                 * Send merchant webhook.
                 */
                $this->sendDepositWebhook(
                    $deposit,
                    $wallet
                );

                return true;
            }

            $deposit->transfer_status = 'failed';
            $deposit->status = 'failed';
            $deposit->error_message =
                $response['message'] ??
                'Native transfer failed.';

            $deposit->save();

            return false;

        } catch (\Throwable $e) {

            Log::error('Native deposit transfer failed', [
                'deposit_id' => $deposit->id,
                'wallet_id' => $wallet->id,
                'error' => $e->getMessage(),
            ]);

            $deposit->transfer_status = 'failed';
            $deposit->status = 'failed';
            $deposit->error_message = $e->getMessage();
            $deposit->save();

            return false;
        }
    }


    /**
     * Transfer token using existing TokenManage service.
     *
     * TokenManage is NOT modified.
     */
    private function transferTokenDeposit(
        BlockchainDeposit $deposit,
        MerchantUserWallet $wallet,
        ChainList $chain
    ): bool {

        try {

            $merchant = $wallet->merchant;

            if (!$merchant) {
                throw new \Exception(
                    'Merchant not found.'
                );
            }

            if (!$merchant->wallet_address) {
                throw new \Exception(
                    'Merchant wallet address not found.'
                );
            }

            if (!$merchant->two_factor_secret) {
                throw new \Exception(
                    'Merchant two factor secret not found.'
                );
            }

            $tokenManage = app(TokenManage::class);

            /**
             * Merchant/admin key.
             *
             * This follows the same approach used
             * in your previous PaymentJobs code.
             */
            $adminKey = decrypt(
                $merchant->two_factor_secret
            );

            /**
             * TokenManage uses:
             *
             * senderAddress
             * tokenAddress
             * toAddress
             * userKey
             * rpcUrl
             * chainId
             * adminAddress
             * adminKey
             * amount
             * isFullOut
             *
             * We intentionally use full-out.
             *
             * This is important because TokenManage
             * calculates the actual current token balance
             * itself.
             */
            $response = $tokenManage
                ->sendAnyChainTokenTransaction(
                    $wallet->wallet_address,
                    $deposit->contract_address,
                    $merchant->wallet_address,
                    $wallet->wallet_key,
                    $chain->chain_rpc_url,
                    $chain->chain_id,
                    $merchant->wallet_address,
                    $adminKey,
                    null,
                    true
                );

            if (
                !empty($response['status']) &&
                !empty($response['txHash'])
            ) {

                $deposit->transfer_status = 'completed';

                $deposit->transfer_tx_hash =
                    $response['txHash'];

                $deposit->transferred_at = now();
                $deposit->status = 'completed';

                $deposit->save();

                /**
                 * Merchant webhook.
                 */
                $this->sendDepositWebhook(
                    $deposit,
                    $wallet
                );

                return true;
            }

            $deposit->transfer_status = 'failed';
            $deposit->status = 'failed';

            $deposit->error_message =
                $response['message'] ??
                'Token transfer failed.';

            $deposit->save();

            return false;

        } catch (\Throwable $e) {

            Log::error('Token deposit transfer failed', [
                'deposit_id' => $deposit->id,
                'wallet_id' => $wallet->id,
                'token' => $deposit->contract_address,
                'error' => $e->getMessage(),
            ]);

            $deposit->transfer_status = 'failed';
            $deposit->status = 'failed';
            $deposit->error_message = $e->getMessage();
            $deposit->save();

            return false;
        }
    }


    /**
     * Send webhook to merchant.
     */
    private function sendDepositWebhook(
        BlockchainDeposit $deposit,
        MerchantUserWallet $wallet
    ): void {

        if (empty($wallet->webhook_url)) {
            return;
        }

        try {

            $payload = [
                'event' => 'blockchain.deposit',

                'deposit_id' => $deposit->id,

                'merchant_id' =>
                    $deposit->merchant_id,

                'wallet_id' =>
                    $deposit->merchant_user_wallet_id,

                'chain_id' =>
                    $deposit->chain_id,

                'chain_name' =>
                    $deposit->chain_name,

                'network_standard' =>
                    $deposit->network_standard,

                'type' =>
                    $deposit->type,

                'token_name' =>
                    $deposit->token_name,

                'token_symbol' =>
                    $deposit->token_symbol,

                'contract_address' =>
                    $deposit->contract_address,

                'decimals' =>
                    $deposit->token_decimals,

                'amount' =>
                    $deposit->amount,

                'raw_amount' =>
                    $deposit->raw_amount,

                'tx_hash' =>
                    $deposit->tx_hash,

                'transfer_tx_hash' =>
                    $deposit->transfer_tx_hash,

                'block_number' =>
                    $deposit->block_number,

                'log_index' =>
                    $deposit->log_index,

                'from_address' =>
                    $deposit->from_address,

                'to_address' =>
                    $deposit->to_address,

                'status' =>
                    $deposit->status,

                'transfer_status' =>
                    $deposit->transfer_status,

                'detected_at' =>
                    optional($deposit->detected_at)
                        ->toIso8601String(),

                'transferred_at' =>
                    optional($deposit->transferred_at)
                        ->toIso8601String(),
            ];

            $response = Http::timeout(20)
                ->acceptJson()
                ->post(
                    $wallet->webhook_url,
                    $payload
                );

            if ($response->successful()) {

                $deposit->webhook_status = 'completed';
                $deposit->credited_at = now();

            } else {

                $deposit->webhook_status = 'failed';

                $deposit->error_message =
                    'Webhook HTTP ' .
                    $response->status() .
                    ': ' .
                    substr(
                        $response->body(),
                        0,
                        500
                    );
            }

            $deposit->save();

        } catch (\Throwable $e) {

            Log::error('Deposit webhook failed', [
                'deposit_id' => $deposit->id,
                'webhook_url' => $wallet->webhook_url,
                'error' => $e->getMessage(),
            ]);

            $deposit->webhook_status = 'failed';
            $deposit->error_message =
                'Webhook error: ' .
                $e->getMessage();

            $deposit->save();
        }
    }


    /**
     * Get token decimals using decimals() RPC call.
     */
    private function getTokenDecimals(
        string $rpcUrl,
        string $contract
    ): int {

        try {

            /**
             * decimals()
             *
             * selector = 0x313ce567
             */
            $result = $this->rpc(
                $rpcUrl,
                'eth_call',
                [[
                    'to' => $contract,
                    'data' => '0x313ce567',
                ], 'latest']
            );

            if (!$result) {
                return 18;
            }

            $decimals = $this->hexToDecimal($result);

            $decimals = (int) $decimals;

            if ($decimals < 0 || $decimals > 36) {
                return 18;
            }

            return $decimals;

        } catch (\Throwable $e) {

            Log::warning('Unable to detect token decimals', [
                'contract' => $contract,
                'error' => $e->getMessage(),
            ]);

            return 18;
        }
    }


    /**
     * Convert raw token amount to human amount.
     */
    private function fromRawAmount(
        string $rawAmount,
        int $decimals
    ): string {

        if ($decimals === 0) {
            return $rawAmount;
        }

        $divisor = bcpow(
            '10',
            (string) $decimals,
            0
        );

        return bcdiv(
            $rawAmount,
            $divisor,
            $decimals
        );
    }


    /**
     * Ethereum JSON-RPC helper.
     */
    private function rpc(
        string $rpcUrl,
        string $method,
        array $params = []
    ) {

        $response = Http::timeout(30)
            ->post($rpcUrl, [
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $params,
                'id' => 1,
            ]);

        if (!$response->successful()) {
            throw new \Exception(
                'RPC HTTP error: ' .
                $response->status()
            );
        }

        $json = $response->json();

        if (isset($json['error'])) {
            throw new \Exception(
                $json['error']['message'] ??
                'RPC error'
            );
        }

        return $json['result'] ?? null;
    }


    /**
     * Hexadecimal -> decimal without integer overflow.
     *
     * Do NOT use hexdec() for token amounts.
     */
    private function hexToDecimal(
        ?string $hex
    ): string {

        if (!$hex) {
            return '0';
        }

        $hex = strtolower(
            ltrim($hex, '0x')
        );

        if ($hex === '') {
            return '0';
        }

        $decimal = '0';

        $length = strlen($hex);

        for ($i = 0; $i < $length; $i++) {

            $digit = hexdec(
                $hex[$i]
            );

            $decimal = bcadd(
                bcmul(
                    $decimal,
                    '16',
                    0
                ),
                (string) $digit,
                0
            );
        }

        return $decimal;
    }


    /**
     * Convert address into indexed topic.
     */
    private function addressToTopic(
        string $address
    ): string {

        return '0x' .
            str_pad(
                strtolower(
                    ltrim($address, '0x')
                ),
                64,
                '0',
                STR_PAD_LEFT
            );
    }


    /**
     * Convert topic back to Ethereum address.
     */
    private function topicToAddress(
        ?string $topic
    ): ?string {

        if (!$topic) {
            return null;
        }

        return '0x' .
            substr(
                strtolower(
                    ltrim($topic, '0x')
                ),
                -40
            );
    }


    /**
     * Basic EVM address validation.
     */
    private function isValidAddress(
        ?string $address
    ): bool {

        return is_string($address)
            && preg_match(
                '/^0x[a-fA-F0-9]{40}$/',
                $address
            );
    }
}
