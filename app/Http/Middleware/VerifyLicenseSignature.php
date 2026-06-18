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

        /**
         * License check (only key + expiry)
         */
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

        if (!$license->user || empty($license->user->user_secret)) {
            return response()->json([
                'status' => false,
                'message' => 'User secret not configured.'
            ], 401);
        }

        /**
         * Timestamp validation (5 minutes window)
         */
        if (abs(time() - (int) $timestamp) > 300) {
            return response()->json([
                'status' => false,
                'message' => 'Request timestamp expired.'
            ], 401);
        }

        /**
         * Signature verification
         * (license based, no domain)
         */
        $expectedSignature = hash_hmac(
            'sha256',
            $timestamp . $licenseKey,
            $license->user->user_secret
        );

        if (!hash_equals($expectedSignature, $signature)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid signature.'
            ], 401);
        }

        /**
         * Pass license to controller
         */
        $request->attributes->set('license', $license);

        return $next($request);
    }
}
