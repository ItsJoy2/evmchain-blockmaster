<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySignature
{
    public function handle(Request $request, Closure $next): Response
    {
        // Authentication fields from request body
        $merchantId = trim((string) $request->input('merchant_id'));
        $timestamp  = trim((string) $request->input('timestamp'));
        $signature  = trim((string) $request->input('signature'));

        if (!$merchantId || !$timestamp || !$signature) {
            return response()->json([
                'status' => false,
                'message' => 'Authentication fields missing.'
            ], 401);
        }

        // Timestamp validation (5 minutes)
        if (!is_numeric($timestamp) || abs(time() - (int) $timestamp) > 300) {
            return response()->json([
                'status' => false,
                'message' => 'Request timestamp expired.'
            ], 401);
        }

        // Find merchant
        $merchant = User::where('merchant_id', $merchantId)->first();

        if (!$merchant) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid Merchant ID.'
            ], 401);
        }

        if (empty($merchant->user_secret)) {
            return response()->json([
                'status' => false,
                'message' => 'Merchant secret not configured.'
            ], 401);
        }

        // Build payload
        if ($request->isMethod('GET')) {
            $payload = array_merge(
                // $request->route()->parameters(),
                $request->query()
            );
        } elseif ($request->isJson()) {
            $payload = $request->json()->all();
        } else {
            $payload = $request->all();
        }

        // Remove authentication fields
        unset(
            $payload['merchant_id'],
            $payload['timestamp'],
            $payload['signature'],
            $payload['_token'],
            $payload['_method']
        );

        // Sort payload recursively
        $payload = $this->sortRecursive($payload);

        // Generate signature
        $message = $merchantId
            . $timestamp
            . json_encode($payload, JSON_UNESCAPED_SLASHES);

        $expectedSignature = hash_hmac(
            'sha256',
            $message,
            $merchant->user_secret
        );

        if (!hash_equals($expectedSignature, $signature)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid signature.',
                // Uncomment for debugging
                // 'expected' => $expectedSignature,
                // 'received' => $signature,
                // 'payload' => $payload,
                // 'message' => $message,
            ], 401);
        }

        // Store merchant in request
        $request->attributes->set('merchant', $merchant);

        return $next($request);
    }

    private function sortRecursive(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->sortRecursive($value);
            }
        }

        ksort($array);

        return $array;
    }
}
