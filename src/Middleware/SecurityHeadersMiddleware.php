<?php

namespace App\Middleware;

use App\Core\Request;

class SecurityHeadersMiddleware
{
    public function handle(?Request $request = null)
    {
        $isProduction = env('APP_ENV', 'local') === 'production';
        $cspEnabled = config('security.csp.enabled', true);
        $cspReportOnly = config('security.csp.report_only', false);

        // Content Security Policy
        if ($cspEnabled) {
            // Allow self, CDN for Font Awesome, and data URIs for images
            $csp = "default-src 'self'; " .
                "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://unpkg.com; " .
                "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; " .
                "img-src 'self' data: https:; " .
                "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com data:; " .
                "connect-src 'self'; " .
                "frame-ancestors 'none'; " .
                "form-action 'self'; " .
                "base-uri 'self';";

            // Add report URI if configured
            $reportUri = config('security.csp.report_uri');
            if ($reportUri) {
                $csp .= " report-uri {$reportUri};";
            }

            $headerName = $cspReportOnly ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
            header("{$headerName}: {$csp}");
        }

        // Prevent MIME type sniffing
        header("X-Content-Type-Options: nosniff");

        // Prevent clickjacking
        header("X-Frame-Options: DENY");

        // Enable XSS protection
        header("X-XSS-Protection: 1; mode=block");

        // Referrer policy
        header("Referrer-Policy: strict-origin-when-cross-origin");

        // Permissions Policy (formerly Feature Policy)
        header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

        // Strict Transport Security (HTTPS only in production)
        $hstsEnabled = config('security.headers.hsts.enabled', $isProduction);
        if ($hstsEnabled && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            $maxAge = config('security.headers.hsts.max_age', 31536000);
            $includeSubdomains = config('security.headers.hsts.include_subdomains', true);
            $preload = config('security.headers.hsts.preload', true);

            $hsts = "max-age={$maxAge}";
            if ($includeSubdomains) {
                $hsts .= "; includeSubDomains";
            }
            if ($preload) {
                $hsts .= "; preload";
            }

            header("Strict-Transport-Security: {$hsts}");
        }

        // Remove server information
        header_remove('Server');
    }
}
