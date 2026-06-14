<?php

namespace App\Http\Middleware;

use App\Models\DomainLicense;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyLicenseSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $licenseKey = trim($request->header('X-LICENSE-KEY', ''));
        $timestamp  = trim($request->header('X-TIMESTAMP', ''));
        $signature  = trim($request->header('X-SIGNATURE', ''));

        if (
            empty($licenseKey) ||
            empty($timestamp) ||
            empty($signature)
        ) {
            return response()->json([
                'status' => false,
                'message' => 'Authentication headers missing.'
            ], 401);
        }

        $license = DomainLicense::with('user')
            ->where('license_key', $licenseKey)
            ->where('expires_at', '>', now())
            ->first();

        if (!$license) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or expired license.'
            ], 401);
        }

        if (!$license->user) {
            return response()->json([
                'status' => false,
                'message' => 'License owner not found.'
            ], 401);
        }

        if (empty($license->user->user_secret)) {
            return response()->json([
                'status' => false,
                'message' => 'User secret not configured.'
            ], 401);
        }

        /**
         * Timestamp Validation
         * Request expires after 5 minutes
         */
        if (abs(time() - (int)$timestamp) > 300) {
            return response()->json([
                'status' => false,
                'message' => 'Request timestamp expired.'
            ], 401);
        }

        /**
         * Domain Validation
         * webhook_url domain must match licensed domain
         */
        $webhookUrl = $request->input('webhook_url');

        if (!$webhookUrl) {
            return response()->json([
                'status' => false,
                'message' => 'Webhook URL is required.'
            ], 422);
        }

        $webhookHost = parse_url($webhookUrl, PHP_URL_HOST);

        if (!$webhookHost) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid webhook URL.'
            ], 422);
        }

        $webhookHost = strtolower(
            preg_replace('/^www\./', '', $webhookHost)
        );

        $licensedHost = strtolower(
            preg_replace('/^www\./', '', $license->domain)
        );

        $isValidDomain =
            $webhookHost === $licensedHost ||
            str_ends_with($webhookHost, '.' . $licensedHost);

        if (!$isValidDomain) {
            return response()->json([
                'status' => false,
                'message' => 'Webhook domain does not match licensed domain.'
            ], 403);
        }

        /**
         * Signature Verification
         */
        $payload = $request->getContent();

        $expectedSignature = hash_hmac(
            'sha256',
            $timestamp . $payload,
            $license->user->user_secret
        );

        if (!hash_equals($expectedSignature, $signature)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid signature.'
            ], 401);
        }

        $request->attributes->set('license', $license);

        return $next($request);
    }
}
