<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $s3Bucket = config('filesystems.disks.chatme-media.bucket', 'chatme-media-mx');
        $s3Region = config('filesystems.disks.chatme-media.region', 'us-east-1');
        $s3Domain = "https://{$s3Bucket}.s3.{$s3Region}.amazonaws.com https://{$s3Bucket}.s3.amazonaws.com";

        $csp = config('security.csp', "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; font-src 'self' https://fonts.bunny.net; img-src 'self' data: {$s3Domain}; media-src 'self' {$s3Domain}; connect-src 'self' wss:;");
        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
