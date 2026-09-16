<?php

namespace App\Console\Commands;

use App\Models\ChainList;
use App\Models\MerchantUserWallet;
use App\Models\MerchantWalletScanState;
use App\Models\PaymentJobs;
use App\Models\TokenList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class BlockchainScanner extends Command
{
    protected $signature = 'blockchain:scanner';

    protected $description = 'Scan merchant wallets and create payment jobs for incoming blockchain transfers';

    /**
     * ERC20 Transfer(address,address,uint256)
     */
    private const TRANSFER_TOPIC =
        '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

    /**
     * Maximum blocks per scan.
     */
    private const BLOCK_RANGE = 100;

    public function handle(): int
    {
        $this->info('Blockchain scanner started...');

        $wallets = MerchantUserWallet::query()
            ->where('is_active', true)
            ->get();

        if ($wallets->isEmpty()) {
            $this->info('No active merchant wallets found.');

            return self::SUCCESS;
        }

        $chains = ChainList::query()
            ->where('status', true)
            ->get();

        if ($chains->isEmpty()) {
            $this->info('No active chains found.');

            return self::SUCCESS;
        }

        foreach ($wallets as $wallet) {

            foreach ($chains as $chain) {

                try {

                    $this->scanWallet(
                        $wallet,
                        $chain
                    );

                } catch (Throwable $e) {

                    Log::error('Blockchain scanner error', [
                        'wallet_id' => $wallet->id,
                        'wallet_address' => $wallet->wallet_address,
                        'chain_id' => $chain->chain_id,
                        'chain_name' => $chain->chain_name,
                        'error' => $e->getMessage(),
                    ]);

                    $this->error(
                        "Scanner failed: Wallet {$wallet->id}, Chain {$chain->chain_id}"
                    );
                }
            }
        }

        $this->info('Blockchain scanner finished.');

        return self::SUCCESS;
    }

    /**
     * Scan one merchant wallet on one blockchain.
     */
    private function scanWallet(
        MerchantUserWallet $wallet,
        ChainList $chain
    ): void {

        $rpcUrl = $chain->chain_rpc_url;

        if (!$rpcUrl) {
            return;
        }

        $walletAddress = strtolower(
            trim($wallet->wallet_address)
        );

        /*
        |--------------------------------------------------------------------------
        | Get latest block
        |--------------------------------------------------------------------------
        */

        $latestBlockHex = $this->rpc(
            $rpcUrl,
            'eth_blockNumber',
            []
        );

        if (!$latestBlockHex) {
            return;
        }

        $latestBlock = hexdec($latestBlockHex);

        /*
        |--------------------------------------------------------------------------
        | Find/create scan state
        |--------------------------------------------------------------------------
        */

        $state = MerchantWalletScanState::firstOrCreate(
            [
                'merchant_user_wallet_id' => $wallet->id,
                'chain_list_id' => $chain->id,
            ],
            [
                'last_scanned_block' => max(
                    0,
                    $latestBlock - 1
                ),
                'last_scanned_at' => now(),
            ]
        );

        $lastScannedBlock = (int) $state->last_scanned_block;

        /*
        |--------------------------------------------------------------------------
        | Nothing new
        |--------------------------------------------------------------------------
        */

        if ($lastScannedBlock >= $latestBlock) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Scan only limited number of blocks
        |--------------------------------------------------------------------------
        */

        $fromBlock = $lastScannedBlock + 1;

        $toBlock = min(
            $fromBlock + self::BLOCK_RANGE - 1,
            $latestBlock
        );

        $this->line(
            "Wallet {$wallet->id} | {$chain->chain_name} | Blocks {$fromBlock} -> {$toBlock}"
        );

        /*
        |--------------------------------------------------------------------------
        | 1. Native coin transfers
        |--------------------------------------------------------------------------
        */

        $this->scanNativeTransfers(
            $wallet,
            $chain,
            $rpcUrl,
            $walletAddress,
            $fromBlock,
            $toBlock
        );

        /*
        |--------------------------------------------------------------------------
        | 2. Token transfers
        |--------------------------------------------------------------------------
        */

        $this->scanTokenTransfers(
            $wallet,
            $chain,
            $rpcUrl,
            $walletAddress,
            $fromBlock,
            $toBlock
        );

        /*
        |--------------------------------------------------------------------------
        | Update scanner state
        |--------------------------------------------------------------------------
        */

        $state->update([
            'last_scanned_block' => $toBlock,
            'last_scanned_at' => now(),
        ]);

        $wallet->update([
            'last_scanned_at' => now(),
        ]);
    }

    /**
     * Scan native coin transactions.
     *
     * Example:
     *
     * A -> generated wallet
     *
     * This becomes PaymentJobs.
     */
    private function scanNativeTransfers(
        MerchantUserWallet $wallet,
        ChainList $chain,
        string $rpcUrl,
        string $walletAddress,
        int $fromBlock,
        int $toBlock
    ): void {

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

                    $from = strtolower(
                        $transaction['from'] ?? ''
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Transaction must come TO our generated wallet
                    |--------------------------------------------------------------------------
                    */

                    if (!$to || $to !== $walletAddress) {
                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Must come from another wallet
                    |--------------------------------------------------------------------------
                    */

                    if (!$from || $from === $walletAddress) {
                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Native value
                    |--------------------------------------------------------------------------
                    */

                    $valueHex = $transaction['value'] ?? '0x0';

                    if ($valueHex === '0x0') {
                        continue;
                    }

                    $amount = $this->weiToDecimal(
                        $valueHex,
                        18
                    );

                    if (bccomp($amount, '0', 18) <= 0) {
                        continue;
                    }

                    $txHash = $transaction['hash'] ?? null;

                    if (!$txHash) {
                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Create Payment Job
                    |--------------------------------------------------------------------------
                    */

                    $this->createPaymentJob(
                        wallet: $wallet,
                        chain: $chain,
                        tokenName: $chain->chain_name,
                        type: 'native',
                        contractAddress: null,
                        amount: $amount,
                        receivedAmount: $amount,
                        txHash: $txHash
                    );
                }

            } catch (Throwable $e) {

                Log::error('Native transfer scan failed', [
                    'wallet_id' => $wallet->id,
                    'chain_id' => $chain->chain_id,
                    'block' => $blockNumber,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Scan configured token transfers.
     *
     * token_list.chain_id = chain_list.id
     */
    private function scanTokenTransfers(
        MerchantUserWallet $wallet,
        ChainList $chain,
        string $rpcUrl,
        string $walletAddress,
        int $fromBlock,
        int $toBlock
    ): void {

        /*
        |--------------------------------------------------------------------------
        | Only active tokens of this chain
        |--------------------------------------------------------------------------
        */

        $tokens = TokenList::query()
            ->where('chain_id', $chain->id)
            ->where('status', true)
            ->get();

        if ($tokens->isEmpty()) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Wallet address as topic
        |--------------------------------------------------------------------------
        */

        $walletTopic = '0x' . str_pad(
            substr($walletAddress, 2),
            64,
            '0',
            STR_PAD_LEFT
        );

        foreach ($tokens as $token) {

            $contractAddress = strtolower(
                trim($token->contract_address)
            );

            if (!$contractAddress) {
                continue;
            }

            try {

                /*
                |--------------------------------------------------------------------------
                | Get Transfer events
                |--------------------------------------------------------------------------
                |
                | topics[0] = Transfer event
                | topics[1] = from
                | topics[2] = to
                |
                */

                $logs = $this->rpc(
                    $rpcUrl,
                    'eth_getLogs',
                    [
                        [
                            'fromBlock' => '0x' . dechex($fromBlock),
                            'toBlock' => '0x' . dechex($toBlock),

                            'address' => $contractAddress,

                            'topics' => [
                                self::TRANSFER_TOPIC,
                                null,
                                $walletTopic,
                            ],
                        ]
                    ]
                );

                if (!is_array($logs)) {
                    continue;
                }

                foreach ($logs as $log) {

                    $topics = $log['topics'] ?? [];

                    if (count($topics) < 3) {
                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Get sender
                    |--------------------------------------------------------------------------
                    */

                    $fromAddress = $this->topicToAddress(
                        $topics[1]
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Must be another wallet
                    |--------------------------------------------------------------------------
                    */

                    if (
                        !$fromAddress ||
                        strtolower($fromAddress) === $walletAddress
                    ) {
                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Ignore mint from zero address
                    |--------------------------------------------------------------------------
                    */

                    if (
                        strtolower($fromAddress) ===
                        '0x0000000000000000000000000000000000000000'
                    ) {
                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Get raw token amount
                    |--------------------------------------------------------------------------
                    */

                    $rawAmount = $this->hexToDecimal(
                        $log['data'] ?? '0x0'
                    );

                    if (
                        $rawAmount === null ||
                        bccomp($rawAmount, '0', 0) <= 0
                    ) {
                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Get token decimals from blockchain
                    |--------------------------------------------------------------------------
                    |
                    | token_list doesn't need a decimals column.
                    |
                    */

                    $decimals = $this->getTokenDecimals(
                        $rpcUrl,
                        $contractAddress
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Convert raw amount
                    |--------------------------------------------------------------------------
                    */

                    $amount = $this->rawToDecimal(
                        $rawAmount,
                        $decimals
                    );

                    if (bccomp($amount, '0', 18) <= 0) {
                        continue;
                    }

                    $txHash = $log['transactionHash'] ?? null;

                    if (!$txHash) {
                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Create Payment Job
                    |--------------------------------------------------------------------------
                    */

                    $this->createPaymentJob(
                        wallet: $wallet,
                        chain: $chain,
                        tokenName: $token->token_name ?: $token->symbol,
                        type: 'token',
                        contractAddress: $token->contract_address,
                        amount: $amount,
                        receivedAmount: $amount,
                        txHash: $txHash
                    );

                }

            } catch (Throwable $e) {

                Log::error('Token transfer scan failed', [
                    'wallet_id' => $wallet->id,
                    'chain_id' => $chain->chain_id,
                    'token_id' => $token->id,
                    'token_contract' => $token->contract_address,
                    'from_block' => $fromBlock,
                    'to_block' => $toBlock,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Create PaymentJobs record.
     *
     * Scanner ONLY creates job.
     * It does NOT transfer funds.
     */
    private function createPaymentJob(
        MerchantUserWallet $wallet,
        ChainList $chain,
        string $tokenName,
        string $type,
        ?string $contractAddress,
        string $amount,
        string $receivedAmount,
        string $txHash
    ): void {

        /*
        |--------------------------------------------------------------------------
        | Current PaymentJobs table does not have tx_hash.
        |--------------------------------------------------------------------------
        |
        | Therefore scanner state prevents normal duplicate scanning.
        |
        | PaymentJobs model only receives existing fields.
        |
        */

        $job = PaymentJobs::create([
            'token_name' => $tokenName,

            /*
            | Actual blockchain chain ID.
            | Example BSC = 56
            */
            'chain_id' => $chain->chain_id,

            /*
            | Generated wallet which received payment.
            */
            'wallet_address' => $wallet->wallet_address,

            /*
            | Payment processor will pick pending jobs.
            */
            'status' => 'pending',

            /*
            | Existing encrypted wallet private key.
            */
            'key' => $wallet->wallet_key,

            /*
            | Merchant webhook URL.
            */
            'webhook_url' => $wallet->webhook_url,

            /*
            | RPC URL for this chain.
            */
            'rpc_url' => $chain->chain_rpc_url,

            /*
            | native / token
            */
            'type' => $type,

            /*
            | ERC20/BEP20 contract.
            | Native = null
            */
            'contract_address' => $contractAddress,

            /*
            | Existing PaymentJobs UID.
            */
            'invoice_id' => PaymentJobs::generateUIDCode(),

            /*
            | Merchant user ID.
            */
            'user_id' => $wallet->merchant_id,

            /*
            | Detected payment amount.
            */
            'amount' => $amount,

            /*
            | Same received amount initially.
            */
            'received_amount' => $receivedAmount,
        ]);

        Log::info('Payment job created from blockchain transfer', [
            'payment_job_id' => $job->id,
            'merchant_id' => $wallet->merchant_id,
            'wallet_id' => $wallet->id,
            'wallet_address' => $wallet->wallet_address,
            'chain_id' => $chain->chain_id,
            'chain_name' => $chain->chain_name,
            'type' => $type,
            'token_name' => $tokenName,
            'contract_address' => $contractAddress,
            'amount' => $amount,
            'tx_hash' => $txHash,
        ]);

        $this->info(
            "Payment Job #{$job->id} created | {$tokenName} | {$amount}"
        );
    }

    /**
     * Convert indexed topic to address.
     */
    private function topicToAddress(?string $topic): ?string
    {
        if (!$topic) {
            return null;
        }

        $topic = strtolower(
            str_replace('0x', '', $topic)
        );

        if (strlen($topic) !== 64) {
            return null;
        }

        return '0x' . substr($topic, -40);
    }

    /**
     * Get token decimals from ERC20 contract.
     *
     * decimals() selector = 0x313ce567
     */
    private function getTokenDecimals(
        string $rpcUrl,
        string $contractAddress
    ): int {

        try {

            $result = $this->rpc(
                $rpcUrl,
                'eth_call',
                [
                    [
                        'to' => $contractAddress,
                        'data' => '0x313ce567',
                    ],
                    'latest',
                ]
            );

            if (!$result) {
                return 18;
            }

            $decimals = hexdec($result);

            /*
            |--------------------------------------------------------------------------
            | Safety
            |--------------------------------------------------------------------------
            */

            if ($decimals < 0 || $decimals > 36) {
                return 18;
            }

            return $decimals;

        } catch (Throwable $e) {

            Log::warning('Unable to read token decimals', [
                'contract' => $contractAddress,
                'error' => $e->getMessage(),
            ]);

            /*
            | Existing system generally works with 18 decimals.
            */
            return 18;
        }
    }

    /**
     * Convert raw token amount to decimal amount.
     */
    private function rawToDecimal(
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
            18
        );
    }

    /**
     * Convert wei hex to decimal.
     */
    private function weiToDecimal(
        string $hex,
        int $decimals
    ): string {

        $raw = $this->hexToDecimal($hex);

        if ($raw === null) {
            return '0';
        }

        return $this->rawToDecimal(
            $raw,
            $decimals
        );
    }

    /**
     * Convert hex integer to decimal string using GMP.
     */
    private function hexToDecimal(
        ?string $hex
    ): ?string {

        if (!$hex) {
            return null;
        }

        $hex = strtolower(
            trim($hex)
        );

        $hex = preg_replace(
            '/^0x/',
            '',
            $hex
        );

        if ($hex === '') {
            return '0';
        }

        if (!ctype_xdigit($hex)) {
            return null;
        }

        if (extension_loaded('gmp')) {

            return gmp_strval(
                gmp_init($hex, 16),
                10
            );
        }

        /*
        |--------------------------------------------------------------------------
        | BCMath fallback
        |--------------------------------------------------------------------------
        */

        $decimal = '0';

        $length = strlen($hex);

        for ($i = 0; $i < $length; $i++) {

            $decimal = bcmul(
                $decimal,
                '16',
                0
            );

            $decimal = bcadd(
                $decimal,
                (string) hexdec($hex[$i]),
                0
            );
        }

        return $decimal;
    }

    /**
     * Generic JSON-RPC call.
     */
    private function rpc(
        string $rpcUrl,
        string $method,
        array $params
    ) {

        $response = Http::timeout(30)
            ->acceptJson()
            ->post($rpcUrl, [
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $params,
                'id' => 1,
            ]);

        if (!$response->successful()) {

            throw new \RuntimeException(
                'RPC HTTP error: ' . $response->status()
            );
        }

        $data = $response->json();

        if (isset($data['error'])) {

            throw new \RuntimeException(
                $data['error']['message']
                ?? 'Unknown RPC error'
            );
        }

        return $data['result'] ?? null;
    }
}
