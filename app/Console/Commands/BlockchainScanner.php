<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ChainList;
use App\Models\TokenList;
use App\Models\MerchantUserWallet;
use App\Models\BlockchainDeposit;
use App\Models\MerchantWalletScanState;
use App\Services\NativeCoin;
use App\Services\TokenManage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BlockchainScanner extends Command
{
    protected $signature = 'blockchain:scanner';

    protected $description = 'Scan merchant wallets for native and token blockchain deposits';

    private const TRANSFER_TOPIC =
        '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

    public function handle(): int
    {
        $this->info('Blockchain scanner started...');

        $wallets = MerchantUserWallet::query()
            ->where('is_active', true)
            ->with('merchant')
            ->get();

        if ($wallets->isEmpty()) {
            $this->warn('No active merchant wallets found.');
            return self::SUCCESS;
        }

        $chains = ChainList::query()
            ->where('status', true)
            ->get();

        if ($chains->isEmpty()) {
            $this->warn('No active chains found.');
            return self::SUCCESS;
        }

        $stats = [
            'wallets' => 0,
            'chains' => 0,
            'native' => 0,
            'tokens' => 0,
            'transferred' => 0,
            'failed' => 0,
        ];

        foreach ($wallets as $wallet) {

            if (!$wallet->merchant) {
                continue;
            }

            $stats['wallets']++;

            foreach ($chains as $chain) {

                $stats['chains']++;

                try {

                    $result = $this->scanWalletOnChain(
                        $wallet,
                        $chain
                    );

                    $stats['native'] += $result['native'];
                    $stats['tokens'] += $result['tokens'];
                    $stats['transferred'] += $result['transferred'];
                    $stats['failed'] += $result['failed'];

                } catch (\Throwable $e) {

                    $stats['failed']++;

                    Log::error('Blockchain scanner error', [
                        'wallet_id' => $wallet->id,
                        'chain_id' => $chain->chain_id,
                        'error' => $e->getMessage(),
                    ]);

                    $this->error(
                        "Wallet {$wallet->id} / {$chain->chain_name}: {$e->getMessage()}"
                    );
                }
            }

            $wallet->update([
                'last_scanned_at' => now(),
            ]);
        }

        $this->newLine();

        $this->info('Blockchain scanner completed.');

        $this->table(
            [
                'Wallets',
                'Chains',
                'Native',
                'Tokens',
                'Transferred',
                'Failed',
            ],
            [[
                $stats['wallets'],
                $stats['chains'],
                $stats['native'],
                $stats['tokens'],
                $stats['transferred'],
                $stats['failed'],
            ]]
        );

        return self::SUCCESS;
    }


    private function scanWalletOnChain(
        MerchantUserWallet $wallet,
        ChainList $chain
    ): array {

        $result = [
            'native' => 0,
            'tokens' => 0,
            'transferred' => 0,
            'failed' => 0,
        ];

        $rpcUrl = $chain->chain_rpc_url;

        if (!$rpcUrl) {
            return $result;
        }

        /**
         * Get latest block.
         */
        $latestHex = $this->rpc(
            $rpcUrl,
            'eth_blockNumber'
        );

        if (!$latestHex) {
            return $result;
        }

        $latestBlock = (int) $this->hexToDecimal(
            $latestHex
        );

        /**
         * Get scan state.
         */
        $state = MerchantWalletScanState::firstOrCreate(
            [
                'merchant_user_wallet_id' => $wallet->id,
                'chain_list_id' => $chain->id,
            ],
            [
                /**
                 * New wallet:
                 * Don't scan old blocks.
                 */
                'last_scanned_block' => $latestBlock,
                'last_scanned_at' => now(),
            ]
        );

        $lastBlock = (int) $state->last_scanned_block;

        if ($lastBlock >= $latestBlock) {
            return $result;
        }

        /**
         * Maximum blocks per execution.
         */
        $maxBlocks = 100;

        $fromBlock = $lastBlock + 1;

        $toBlock = min(
            $fromBlock + $maxBlocks - 1,
            $latestBlock
        );


        /*
        |--------------------------------------------------------------------------
        | Native Coin
        |--------------------------------------------------------------------------
        */

        $nativeResult = $this->scanNative(
            $wallet,
            $chain,
            $fromBlock,
            $toBlock
        );

        $result['native'] += $nativeResult['deposits'];
        $result['transferred'] += $nativeResult['transferred'];
        $result['failed'] += $nativeResult['failed'];


        /*
        |--------------------------------------------------------------------------
        | Tokens
        |--------------------------------------------------------------------------
        */

        $tokens = TokenList::query()
            ->where('chain_id', $chain->id)
            ->where('status', true)
            ->get();

        foreach ($tokens as $token) {

            try {

                $tokenResult = $this->scanToken(
                    $wallet,
                    $chain,
                    $token,
                    $fromBlock,
                    $toBlock
                );

                $result['tokens'] +=
                    $tokenResult['deposits'];

                $result['transferred'] +=
                    $tokenResult['transferred'];

                $result['failed'] +=
                    $tokenResult['failed'];

            } catch (\Throwable $e) {

                $result['failed']++;

                Log::error('Token scanner error', [
                    'wallet_id' => $wallet->id,
                    'chain_id' => $chain->chain_id,
                    'token_id' => $token->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }


        /**
         * Update scan state.
         */
        $state->update([
            'last_scanned_block' => $toBlock,
            'last_scanned_at' => now(),
        ]);

        return $result;
    }


    private function scanNative(
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

        for (
            $block = $fromBlock;
            $block <= $toBlock;
            $block++
        ) {

            try {

                $data = $this->rpc(
                    $chain->chain_rpc_url,
                    'eth_getBlockByNumber',
                    [
                        '0x' . dechex($block),
                        true,
                    ]
                );

                if (
                    !$data ||
                    empty($data['transactions'])
                ) {
                    continue;
                }

                foreach ($data['transactions'] as $tx) {

                    $to = strtolower(
                        $tx['to'] ?? ''
                    );

                    if (
                        !$to ||
                        $to !== strtolower(
                            $wallet->wallet_address
                        )
                    ) {
                        continue;
                    }

                    $rawAmount = $this->hexToDecimal(
                        $tx['value'] ?? '0x0'
                    );

                    if ($rawAmount === '0') {
                        continue;
                    }

                    /**
                     * Native coin = 18 decimals.
                     */
                    $amount = bcdiv(
                        $rawAmount,
                        '1000000000000000000',
                        18
                    );

                    /**
                     * Prevent duplicate.
                     */
                    $exists = BlockchainDeposit::query()
                        ->where(
                            'chain_id',
                            $chain->chain_id
                        )
                        ->where(
                            'tx_hash',
                            $tx['hash']
                        )
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $deposit = BlockchainDeposit::create([
                        'merchant_id' =>
                            $wallet->merchant_id,

                        'merchant_user_wallet_id' =>
                            $wallet->id,

                        'chain_id' =>
                            $chain->chain_id,

                        'chain_name' =>
                            $chain->chain_name,

                        'network_standard' =>
                            'EVM',

                        'token_name' =>
                            $chain->chain_name,

                        'token_symbol' =>
                            $chain->chain_name,

                        'contract_address' =>
                            null,

                        'token_decimals' =>
                            18,

                        'raw_amount' =>
                            $rawAmount,

                        'amount' =>
                            $amount,

                        'type' =>
                            'native',

                        'tx_hash' =>
                            $tx['hash'],

                        'block_number' =>
                            $block,

                        'log_index' =>
                            0,

                        'from_address' =>
                            $tx['from'] ?? null,

                        'to_address' =>
                            $wallet->wallet_address,

                        'status' =>
                            'detected',

                        'webhook_status' =>
                            'pending',

                        'transfer_status' =>
                            'pending',

                        'detected_at' =>
                            now(),
                    ]);

                    $result['deposits']++;

                    /**
                     * Sweep to merchant wallet.
                     */
                    if (
                        $this->transferNative(
                            $deposit,
                            $wallet,
                            $chain
                        )
                    ) {
                        $result['transferred']++;
                    } else {
                        $result['failed']++;
                    }
                }

            } catch (\Throwable $e) {

                $result['failed']++;

                Log::error(
                    'Native blockchain scan error',
                    [
                        'wallet_id' => $wallet->id,
                        'chain_id' => $chain->chain_id,
                        'block' => $block,
                        'error' => $e->getMessage(),
                    ]
                );
            }
        }

        return $result;
    }


    private function scanToken(
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

        if (!$this->isValidAddress(
            $token->contract_address
        )) {
            return $result;
        }

        $walletTopic = $this->addressToTopic(
            $wallet->wallet_address
        );

        $logs = $this->rpc(
            $chain->chain_rpc_url,
            'eth_getLogs',
            [[
                'fromBlock' =>
                    '0x' . dechex($fromBlock),

                'toBlock' =>
                    '0x' . dechex($toBlock),

                'address' =>
                    $token->contract_address,

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

        $decimals = $this->getTokenDecimals(
            $chain->chain_rpc_url,
            $token->contract_address
        );

        foreach ($logs as $log) {

            try {

                $txHash =
                    $log['transactionHash'] ?? null;

                if (!$txHash) {
                    continue;
                }

                $logIndex =
                    (int) $this->hexToDecimal(
                        $log['logIndex'] ?? '0x0'
                    );

                $exists = BlockchainDeposit::query()
                    ->where(
                        'chain_id',
                        $chain->chain_id
                    )
                    ->where(
                        'tx_hash',
                        $txHash
                    )
                    ->where(
                        'log_index',
                        $logIndex
                    )
                    ->exists();

                if ($exists) {
                    continue;
                }

                $rawAmount = $this->hexToDecimal(
                    $log['data'] ?? '0x0'
                );

                if ($rawAmount === '0') {
                    continue;
                }

                $amount = bcdiv(
                    $rawAmount,
                    bcpow(
                        '10',
                        (string) $decimals,
                        0
                    ),
                    $decimals
                );

                $fromAddress =
                    $this->topicToAddress(
                        $log['topics'][1] ?? null
                    );

                $deposit =
                    BlockchainDeposit::create([
                        'merchant_id' =>
                            $wallet->merchant_id,

                        'merchant_user_wallet_id' =>
                            $wallet->id,

                        'chain_id' =>
                            $chain->chain_id,

                        'chain_name' =>
                            $chain->chain_name,

                        'network_standard' =>
                            'EVM',

                        'token_name' =>
                            $token->token_name,

                        'token_symbol' =>
                            $token->symbol,

                        'contract_address' =>
                            strtolower(
                                $token->contract_address
                            ),

                        'token_decimals' =>
                            $decimals,

                        'raw_amount' =>
                            $rawAmount,

                        'amount' =>
                            $amount,

                        'type' =>
                            'token',

                        'tx_hash' =>
                            $txHash,

                        'block_number' =>
                            (int) $this->hexToDecimal(
                                $log['blockNumber']
                                ?? '0x0'
                            ),

                        'log_index' =>
                            $logIndex,

                        'from_address' =>
                            $fromAddress,

                        'to_address' =>
                            $wallet->wallet_address,

                        'status' =>
                            'detected',

                        'webhook_status' =>
                            'pending',

                        'transfer_status' =>
                            'pending',

                        'detected_at' =>
                            now(),
                    ]);

                $result['deposits']++;

                /**
                 * Full token balance sweep.
                 */
                if (
                    $this->transferToken(
                        $deposit,
                        $wallet,
                        $chain
                    )
                ) {
                    $result['transferred']++;
                } else {
                    $result['failed']++;
                }

            } catch (\Throwable $e) {

                $result['failed']++;

                Log::error(
                    'Token deposit processing error',
                    [
                        'wallet_id' => $wallet->id,
                        'token_id' => $token->id,
                        'tx_hash' =>
                            $log['transactionHash']
                            ?? null,
                        'error' =>
                            $e->getMessage(),
                    ]
                );
            }
        }

        return $result;
    }


    private function transferNative(
        BlockchainDeposit $deposit,
        MerchantUserWallet $wallet,
        ChainList $chain
    ): bool {

        try {

            $merchant = $wallet->merchant;

            if (
                !$merchant ||
                !$merchant->wallet_address
            ) {
                throw new \Exception(
                    'Merchant wallet not found.'
                );
            }

            $nativeCoin =
                app(NativeCoin::class);

            $response =
                $nativeCoin->sendAnyChainNativeBalance(
                    $wallet->wallet_address,
                    $merchant->wallet_address,
                    $wallet->wallet_key,
                    $chain->chain_rpc_url,
                    $chain->chain_id,
                    false,
                    $deposit->amount
                );

            if (
                !empty($response['status']) &&
                !empty($response['txHash'])
            ) {

                $deposit->update([
                    'status' =>
                        'completed',

                    'transfer_status' =>
                        'completed',

                    'transfer_tx_hash' =>
                        $response['txHash'],

                    'transferred_at' =>
                        now(),
                ]);

                $this->sendWebhook(
                    $deposit,
                    $wallet
                );

                return true;
            }

            $deposit->update([
                'status' => 'failed',
                'transfer_status' => 'failed',
                'error_message' =>
                    $response['message']
                    ?? 'Native transfer failed.',
            ]);

            return false;

        } catch (\Throwable $e) {

            $deposit->update([
                'status' => 'failed',
                'transfer_status' => 'failed',
                'error_message' =>
                    $e->getMessage(),
            ]);

            Log::error(
                'Native transfer failed',
                [
                    'deposit_id' => $deposit->id,
                    'error' => $e->getMessage(),
                ]
            );

            return false;
        }
    }


    private function transferToken(
        BlockchainDeposit $deposit,
        MerchantUserWallet $wallet,
        ChainList $chain
    ): bool {

        try {

            $merchant = $wallet->merchant;

            if (
                !$merchant ||
                !$merchant->wallet_address
            ) {
                throw new \Exception(
                    'Merchant wallet not found.'
                );
            }

            if (!$merchant->two_factor_secret) {
                throw new \Exception(
                    'Merchant two factor secret not found.'
                );
            }

            $tokenManage =
                app(TokenManage::class);

            $adminKey =
                decrypt(
                    $merchant->two_factor_secret
                );

            $response =
                $tokenManage
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

                $deposit->update([
                    'status' =>
                        'completed',

                    'transfer_status' =>
                        'completed',

                    'transfer_tx_hash' =>
                        $response['txHash'],

                    'transferred_at' =>
                        now(),
                ]);

                $this->sendWebhook(
                    $deposit,
                    $wallet
                );

                return true;
            }

            $deposit->update([
                'status' => 'failed',
                'transfer_status' => 'failed',
                'error_message' =>
                    $response['message']
                    ?? 'Token transfer failed.',
            ]);

            return false;

        } catch (\Throwable $e) {

            $deposit->update([
                'status' => 'failed',
                'transfer_status' => 'failed',
                'error_message' =>
                    $e->getMessage(),
            ]);

            Log::error(
                'Token transfer failed',
                [
                    'deposit_id' => $deposit->id,
                    'error' => $e->getMessage(),
                ]
            );

            return false;
        }
    }


    private function sendWebhook(
        BlockchainDeposit $deposit,
        MerchantUserWallet $wallet
    ): void {

        if (!$wallet->webhook_url) {
            return;
        }

        try {

            $response = Http::timeout(20)
                ->acceptJson()
                ->post(
                    $wallet->webhook_url,
                    [
                        'event' =>
                            'blockchain.deposit',

                        'deposit_id' =>
                            $deposit->id,

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

                        'token_decimals' =>
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
                    ]
                );

            if ($response->successful()) {

                $deposit->update([
                    'webhook_status' =>
                        'completed',

                    'credited_at' =>
                        now(),
                ]);

            } else {

                $deposit->update([
                    'webhook_status' =>
                        'failed',
                ]);
            }

        } catch (\Throwable $e) {

            Log::error(
                'Deposit webhook failed',
                [
                    'deposit_id' =>
                        $deposit->id,

                    'error' =>
                        $e->getMessage(),
                ]
            );

            $deposit->update([
                'webhook_status' =>
                    'failed',
            ]);
        }
    }


    private function getTokenDecimals(
        string $rpcUrl,
        string $contract
    ): int {

        try {

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

            return (int) $this->hexToDecimal(
                $result
            );

        } catch (\Throwable $e) {

            return 18;
        }
    }


    private function rpc(
        string $rpcUrl,
        string $method,
        array $params = []
    ) {

        $response = Http::timeout(30)
            ->post(
                $rpcUrl,
                [
                    'jsonrpc' => '2.0',
                    'method' => $method,
                    'params' => $params,
                    'id' => 1,
                ]
            );

        if (!$response->successful()) {
            throw new \Exception(
                'RPC HTTP error: ' .
                $response->status()
            );
        }

        $json = $response->json();

        if (isset($json['error'])) {
            throw new \Exception(
                $json['error']['message']
                ?? 'RPC error'
            );
        }

        return $json['result'] ?? null;
    }


    private function hexToDecimal(
        ?string $hex
    ): string {

        if (!$hex) {
            return '0';
        }

        $hex = strtolower(
            $hex
        );

        if (str_starts_with($hex, '0x')) {
            $hex = substr($hex, 2);
        }

        if ($hex === '') {
            return '0';
        }

        $decimal = '0';

        for ($i = 0; $i < strlen($hex); $i++) {

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


    private function addressToTopic(
        string $address
    ): string {

        $address = strtolower(
            $address
        );

        if (str_starts_with($address, '0x')) {
            $address = substr($address, 2);
        }

        return '0x' .
            str_pad(
                $address,
                64,
                '0',
                STR_PAD_LEFT
            );
    }


    private function topicToAddress(
        ?string $topic
    ): ?string {

        if (!$topic) {
            return null;
        }

        $topic = strtolower($topic);

        if (str_starts_with($topic, '0x')) {
            $topic = substr($topic, 2);
        }

        return '0x' .
            substr($topic, -40);
    }


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
