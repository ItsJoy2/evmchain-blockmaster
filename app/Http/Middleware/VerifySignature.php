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
        $merchantId = trim($request->header('X-MERCHANT-ID', ''));
        $timestamp  = trim($request->header('X-TIMESTAMP', ''));
        $signature  = trim($request->header('X-SIGNATURE', ''));

        if (!$merchantId || !$timestamp || !$signature) {
            return response()->json([
                'status' => false,
                'message' => 'Authentication headers missing.'
            ], 401);
        }

        if (!is_numeric($timestamp) || abs(time() - (int)$timestamp) > 300) {
            return response()->json([
                'status' => false,
                'message' => 'Request timestamp expired.'
            ], 401);
        }

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

        if ($request->isMethod('GET')) {

            $payload = array_merge(
                $request->route()->parameters(),
                $request->query()
            );

        } elseif ($request->isJson()) {

            $payload = $request->json()->all();

        } else {

            $payload = $request->all();

        }

        unset(
            $payload['_token'],
            $payload['_method'],
            $payload['signature']
        );

        $payload = $this->sortRecursive($payload);

        $expectedSignature = hash_hmac(
            'sha256',
            $timestamp . json_encode($payload, JSON_UNESCAPED_SLASHES),
            $merchant->user_secret
        );

if (!hash_equals($expectedSignature, $signature)) {

    return response()->json([
        'status'    => false,
        'message'   => 'Invalid signature.',
        'expected'  => $expectedSignature,
        'received'  => $signature,
        'payload'   => $payload,
        'json'      => json_encode($payload, JSON_UNESCAPED_SLASHES),
        'headers'   => [
            'merchant' => $merchantId,
            'timestamp' => $timestamp,
        ],
    ], 401);
}

        $request->attributes->set('merchant', $merchant);

        return $next($request);
    }

    private function sortRecursive(array $array): array
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $value = $this->sortRecursive($value);
            }
        }

        ksort($array);

        return $array;
    }
}
