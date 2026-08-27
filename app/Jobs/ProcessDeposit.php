<?php

namespace App\Jobs;

use App\Models\BlockchainDeposit;
use App\Models\ChainList;
use App\Models\MerchantUserWallet;
use App\Models\TokenList;
use App\Models\User;
use App\Services\CheckBalance;
use App\Services\Crypto;
use App\Services\NativeCoin;
use App\Services\TokenManage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

class ProcessDeposit implements ShouldQueue
{
    use Queueable;

    /*
    |--------------------------------------------------------------------------
    | Job Configuration
    |--------------------------------------------------------------------------
    */

    public int $tries = 3;

    public int $timeout = 300;

    public array $backoff = [10, 30, 60];

    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    public function __construct(
        public int $walletId
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Execute Job
    |--------------------------------------------------------------------------
    */

    public function handle(
        Crypto $crypto,
        TokenManage $tokenManage,
        NativeCoin $nativeCoin
    ): void {
        $wallet = MerchantUserWallet::find($this->walletId);

        if (!$wallet || !$wallet->is_active) {
            return;
        }

        /*
         * merchant_user_wallets.merchant_id
         * =
         * users.id
         */
        $merchant = User::find($wallet->merchant_id);

        if (!$merchant) {
            return;
        }

        if (empty($merchant->wallet_address)) {
            return;
        }

        /*
         * All supported EVM chains.
         */
        $chains = ChainList::query()
            ->where('status', true)
            ->get();

        foreach ($chains as $chain) {
            try {
                /*
                 * ----------------------------------
                 * NATIVE COIN
                 * ----------------------------------
                 */
                $this->processNative(
                    $wallet,
                    $merchant,
                    $chain,
                    $nativeCoin,
                    $crypto
                );

                /*
                 * ----------------------------------
                 * TOKENS
                 * ----------------------------------
                 */
                $this->processTokens(
                    $wallet,
                    $merchant,
                    $chain,
                    $tokenManage,
                    $crypto
                );
            } catch (Throwable $e) {
                report($e);

                /*
                 * One chain fail করলে
                 * অন্য chain চলবে।
                 */
                continue;
            }
        }

        $wallet->update([
            'last_scanned_at' => now(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Native Deposit
    |--------------------------------------------------------------------------
    */

    protected function processNative(
        MerchantUserWallet $wallet,
        User $merchant,
        ChainList $chain,
        NativeCoin $nativeCoin,
        Crypto $crypto
    ): void {
        if (!$chain->chain_rpc_url) {
            return;
        }

        $chainId = (int) $chain->chain_id;

        /*
         * Latest block.
         */
        $latest = $this->rpc(
            $chain->chain_rpc_url,
            'eth_blockNumber',
            []
        );

        if (!$latest) {
            return;
        }

        $latestBlock = hexdec($latest);

        /*
         * Scan recent blocks.
         */
        $fromBlock = max(0, $latestBlock - 20);

        for (
            $blockNumber = $fromBlock;
            $blockNumber <= $latestBlock;
            $blockNumber++
        ) {
            $block = $this->rpc(
                $chain->chain_rpc_url,
                'eth_getBlockByNumber',
                [
                    '0x' . dechex($blockNumber),
                    true,
                ]
            );

            if (empty($block['transactions'])) {
                continue;
            }

            foreach ($block['transactions'] as $tx) {
                $to = strtolower($tx['to'] ?? '');

                if ($to !== strtolower($wallet->wallet_address)) {
                    continue;
                }

                $valueHex = $tx['value'] ?? '0x0';

                $rawAmount = $this->hexToDecimal($valueHex);

                if (bccomp($rawAmount, '0', 0) <= 0) {
                    continue;
                }

                $txHash = strtolower($tx['hash'] ?? '');

                if (!$txHash) {
                    continue;
                }

                /*
                 * Duplicate protection.
                 */
                $depositExists = BlockchainDeposit::query()
                    ->where('chain_id', $chainId)
                    ->where('tx_hash', $txHash)
                    ->where('type', 'native')
                    ->exists();

                if ($depositExists) {
                    continue;
                }

                $amount = bcdiv(
                    $rawAmount,
                    '1000000000000000000',
                    18
                );

                $symbol = $chain->native_symbol
                    ?? strtoupper($chain->chain_name);

                /*
                 * Save deposit.
                 */
                $deposit = BlockchainDeposit::create([
                    'merchant_id' => $wallet->merchant_id,
                    'merchant_user_wallet_id' => $wallet->id,
                    'chain_id' => $chainId,
                    'chain_name' => $chain->chain_name,
                    'network_standard' => 'EVM',
                    'token_name' => $symbol,
                    'token_symbol' => $symbol,
                    'contract_address' => null,
                    'token_decimals' => 18,
                    'raw_amount' => $rawAmount,
                    'amount' => $amount,
                    'type' => 'native',
                    'tx_hash' => $txHash,
                    'block_number' => $blockNumber,
                    'from_address' => strtolower($tx['from'] ?? ''),
                    'to_address' => strtolower($wallet->wallet_address),
                    'status' => 'detected',
                    'webhook_status' => 'pending',
                    'transfer_status' => 'pending',
                    'detected_at' => now(),
                ]);

                /*
                 * First webhook.
                 *
                 * ONLY tx_hash.
                 */
                $this->sendWebhook(
                    $wallet,
                    $deposit
                );

                /*
                 * Immediately move native balance
                 * to merchant wallet.
                 */
                $this->transferNative(
                    $wallet,
                    $merchant,
                    $chain,
                    $deposit,
                    $nativeCoin,
                    $crypto
                );
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Token Deposits
    |--------------------------------------------------------------------------
    */

    protected function processTokens(
        MerchantUserWallet $wallet,
        User $merchant,
        ChainList $chain,
        TokenManage $tokenManage,
        Crypto $crypto
    ): void {
        /*
         * IMPORTANT:
         *
         * token_list.chain_id
         * =
         * chain_list.id
         *
         * NOT chain_list.chain_id
         */
        $tokens = TokenList::query()
            ->where('chain_id', $chain->id)
            ->where('status', true)
            ->whereNotNull('contract_address')
            ->get();

        if ($tokens->isEmpty()) {
            return;
        }

        $latest = $this->rpc(
            $chain->chain_rpc_url,
            'eth_blockNumber',
            []
        );

        if (!$latest) {
            return;
        }

        $latestBlock = hexdec($latest);

        $fromBlock = max(
            0,
            $latestBlock - 20
        );

        /*
         * ERC20 Transfer event.
         */
        $transferTopic =
            '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a0b';

        /*
         * Generated wallet as "to".
         */
        $walletTopic = '0x' . str_pad(
            strtolower(
                substr(
                    $wallet->wallet_address,
                    2
                )
            ),
            64,
            '0',
            STR_PAD_LEFT
        );

        $contracts = [];

        $tokenMap = [];

        foreach ($tokens as $token) {
            $address = strtolower($token->contract_address);

            $contracts[] = $address;
            $tokenMap[$address] = $token;
        }

        /*
         * Get all token transfers
         * to generated wallet.
         */
        $logs = $this->rpc(
            $chain->chain_rpc_url,
            'eth_getLogs',
            [[
                'fromBlock' => '0x' . dechex($fromBlock),
                'toBlock' => '0x' . dechex($latestBlock),
                'address' => $contracts,
                'topics' => [
                    $transferTopic,
                    null,
                    $walletTopic,
                ],
            ]]
        );

        if (!is_array($logs) || empty($logs)) {
            return;
        }

        foreach ($logs as $log) {
            $contract = strtolower($log['address'] ?? '');

            if (!isset($tokenMap[$contract])) {
                continue;
            }

            $token = $tokenMap[$contract];

            $this->saveTokenDeposit(
                $wallet,
                $merchant,
                $chain,
                $token,
                $log,
                $tokenManage,
                $crypto
            );
        }
    }

    protected function saveTokenDeposit(
        MerchantUserWallet $wallet,
        User $merchant,
        ChainList $chain,
        TokenList $token,
        array $log,
        TokenManage $tokenManage,
        Crypto $crypto
    ): void {
        $txHash = strtolower($log['transactionHash'] ?? '');

        if (!$txHash) {
            return;
        }

        $logIndex = isset($log['logIndex'])
            ? hexdec($log['logIndex'])
            : 0;

        $exists = BlockchainDeposit::query()
            ->where('chain_id', $chain->chain_id)
            ->where('tx_hash', $txHash)
            ->where('log_index', $logIndex)
            ->exists();

        if ($exists) {
            return;
        }

        $rawAmount = $this->hexToDecimal(
            $log['data'] ?? '0x0'
        );

        if (bccomp($rawAmount, '0', 0) <= 0) {
            return;
        }

        $decimals = $this->getTokenDecimals(
            $chain->chain_rpc_url,
            $token->contract_address
        );

        $amount = bcdiv(
            $rawAmount,
            bcpow(
                '10',
                (string) $decimals,
                0
            ),
            $decimals
        );

        $deposit = BlockchainDeposit::create([
            'merchant_id' => $wallet->merchant_id,
            'merchant_user_wallet_id' => $wallet->id,
            'chain_id' => $chain->chain_id,
            'chain_name' => $chain->chain_name,
            'network_standard' => 'EVM',
            'token_name' => $token->token_name,
            'token_symbol' => $token->symbol,
            'contract_address' => strtolower($token->contract_address),
            'token_decimals' => $decimals,
            'raw_amount' => $rawAmount,
            'amount' => $amount,
            'type' => 'token',
            'tx_hash' => $txHash,

            'block_number' => isset($log['blockNumber'])
                ? hexdec($log['blockNumber'])
                : null,

            'log_index' => $logIndex,

            'from_address' => $this->topicToAddress(
                $log['topics'][1] ?? null
            ),

            'to_address' => $this->topicToAddress(
                $log['topics'][2] ?? null
            ),

            'status' => 'detected',
            'webhook_status' => 'pending',
            'transfer_status' => 'pending',
            'detected_at' => now(),
        ]);

        $this->sendWebhook(
            $wallet,
            $deposit
        );

        $this->transferToken(
            $wallet,
            $merchant,
            $chain,
            $deposit,
            $tokenManage,
            $crypto
        );
    }

    protected function transferNative(
        MerchantUserWallet $wallet,
        User $merchant,
        ChainList $chain,
        BlockchainDeposit $deposit,
        NativeCoin $nativeCoin,
        Crypto $crypto
    ): void {
        if ($deposit->transfer_status === 'completed') {
            return;
        }

        try {

            $privateKey = $crypto->decrypt(
                $wallet->wallet_key
            );

            if (!$privateKey) {
                throw new \Exception(
                    'Unable to decrypt wallet key.'
                );
            }

            $deposit->update([
                'transfer_status' => 'processing',
            ]);

            $result = $nativeCoin->sendAnyChainNativeBalance(
                $wallet->wallet_address,
                $merchant->wallet_address,
                $privateKey,
                $chain->chain_rpc_url,
                (int) $chain->chain_id,
                true,
                null
            );

            if (
                empty($result['status']) ||
                empty($result['txHash'])
            ) {
                $deposit->update([
                    'transfer_status' => 'pending',
                    'error_message' => $result['message']
                        ?? 'Native transfer failed.',
                ]);

                return;
            }

            $deposit->update([
                'status' => 'completed',
                'transfer_status' => 'completed',
                'transfer_tx_hash' => $result['txHash'],
                'transferred_at' => now(),
                'credited_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);

            $deposit->update([
                'transfer_status' => 'pending',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    protected function transferToken(
        MerchantUserWallet $wallet,
        User $merchant,
        ChainList $chain,
        BlockchainDeposit $deposit,
        TokenManage $tokenManage,
        Crypto $crypto
    ): void {
        if ($deposit->transfer_status === 'completed') {
            return;
        }

        try {

            $walletKey = $crypto->decrypt(
                $wallet->wallet_key
            );

            if (!$walletKey) {
                throw new \Exception(
                    'Unable to decrypt wallet key.'
                );
            }

            $deposit->update([
                'transfer_status' => 'processing',
            ]);

            $result = $tokenManage->sendAnyChainTokenTransaction(

                $wallet->wallet_address,

                $deposit->contract_address,

                $merchant->wallet_address,
                $walletKey,

                $chain->chain_rpc_url,
                (int) $chain->chain_id,

                $merchant->wallet_address,

                $crypto->decrypt(
                    $merchant->two_factor_secret
                ),

                null,

                true
            );

            if (
                empty($result['status']) ||
                empty($result['txHash'])
            ) {
                $deposit->update([
                    'transfer_status' => 'pending',
                    'error_message' => $result['message']
                        ?? 'Token transfer failed.',
                ]);

                return;
            }

            $deposit->update([
                'status' => 'completed',
                'transfer_status' => 'completed',
                'transfer_tx_hash' => $result['txHash'],
                'transferred_at' => now(),
                'credited_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);

            $deposit->update([
                'transfer_status' => 'pending',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    protected function sendWebhook(
        MerchantUserWallet $wallet,
        BlockchainDeposit $deposit
    ): void {
        if ($deposit->webhook_status === 'completed') {
            return;
        }

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->post(
                    $wallet->webhook_url,
                    [
                        'tx_hash' => $deposit->tx_hash,
                    ]
                );

            if ($response->successful()) {
                $deposit->update([
                    'webhook_status' => 'completed',
                ]);
            } else {
                $deposit->update([
                    'webhook_status' => 'failed',
                    'error_message' => 'Webhook HTTP '
                        . $response->status(),
                ]);
            }
        } catch (Throwable $e) {
            report($e);

            $deposit->update([
                'webhook_status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    protected function getTokenDecimals(
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

            $decimals = hexdec($result);

            return (
                $decimals >= 0 &&
                $decimals <= 36
            )
                ? $decimals
                : 18;
        } catch (Throwable $e) {
            report($e);

            return 18;
        }
    }

    protected function rpc(
        string $rpcUrl,
        string $method,
        array $params
    ) {
        $response = Http::timeout(30)
            ->post(
                $rpcUrl,
                [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => $method,
                    'params' => $params,
                ]
            );

        if (!$response->successful()) {
            throw new \Exception(
                'RPC HTTP error: ' . $response->status()
            );
        }

        $data = $response->json();

        if (isset($data['error'])) {
            throw new \Exception(
                $data['error']['message']
                    ?? 'RPC error.'
            );
        }

        return $data['result'] ?? null;
    }

    protected function hexToDecimal(
        string $hex
    ): string {
        $hex = strtolower($hex);

        if (str_starts_with($hex, '0x')) {
            $hex = substr($hex, 2);
        }

        if ($hex === '') {
            return '0';
        }

        $decimal = '0';

        foreach (str_split($hex) as $digit) {
            $decimal = bcmul(
                $decimal,
                '16',
                0
            );

            $decimal = bcadd(
                $decimal,
                (string) hexdec($digit),
                0
            );
        }

        return $decimal;
    }

    protected function topicToAddress(
        ?string $topic
    ): ?string {
        if (!$topic) {
            return null;
        }

        return '0x' . strtolower(
            substr(
                $topic,
                -40
            )
        );
    }

    public function failed(
        Throwable $exception
    ): void {
        report($exception);
    }
}
