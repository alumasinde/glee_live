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
            // Every script is an external file under /assets/js and every
            // inline style attribute was replaced by a utility class, so
            // neither script-src nor style-src needs 'unsafe-inline'.
            $csp = "default-src 'self'; " .
                "script-src 'self' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://unpkg.com; " .
                "style-src 'self' https://cdnjs.cloudflare.com https://fonts.googleapis.com; " .
                "img-src 'self' data:; " .
                "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com data:; " .
                "connect-src 'self'; " .
                "object-src 'none'; " .
                "frame-src 'none'; " .
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

        // Deprecated and ignored by current browsers (CSP is the real control);
        // "0" also stops legacy IE from re-enabling its buggy filter.
        header("X-XSS-Protection: 0");

        // Referrer policy
        header("Referrer-Policy: strict-origin-when-cross-origin");

        // Permissions Policy (formerly Feature Policy)
        header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

        // Strict Transport Security (HTTPS only in production)
        $hstsEnabled = config('security.headers.hsts.enabled', $isProduction);
        // Same scheme detection as the session cookie: behind a TLS-terminating
        // proxy $_SERVER['HTTPS'] is absent, so HSTS would silently never be sent.
        $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;

        if ($hstsEnabled && $isHttps) {
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
