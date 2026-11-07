<?php

namespace App\Middleware;

use App\Core\PlatformPolicy;
use App\Core\Request;
use App\Core\Response;

/**
 * Global feature switches owned by the platform. Applies to authenticated
 * screens and to public endpoints, so turning a feature off also closes the
 * public surface it exposes.
 */
class FeatureMiddleware
{
    private const MAP = [
        '/settings/company' => 'tenant_company_settings',
        '/settings/email'   => 'tenant_email_settings',
        '/settings/gates'   => 'tenant_gate_management',
        '/pre-register'     => 'public_pre_registration',
        '/pass'             => 'public_pre_registration',
        '/gate-scan'        => 'gate_device_scanning',
    ];

    public function handle(?Request $request = null): void
    {
        $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        $path = '/' . trim($path, '/');

        $match  = null;
        $length = 0;

        foreach (self::MAP as $prefix => $feature) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                if (strlen($prefix) > $length) {
                    $match  = $feature;
                    $length = strlen($prefix);
                }
            }
        }

        if ($match !== null && !PlatformPolicy::feature($match)) {
            Response::abort(404, 'This feature is not available.');
        }
    }
}
