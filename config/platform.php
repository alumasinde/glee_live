<?php

/*
|--------------------------------------------------------------------------
| Platform (provider) plane
|--------------------------------------------------------------------------
|
| Features are global switches owned by platform staff. They are stored in
| platform_settings.features and default to the values below, so a fresh
| install behaves exactly as before until someone turns one off.
|
| Restricted permissions are tenant permission keys (module.action) that only
| the platform may decide. While a key is restricted, tenant roles cannot
| grant it and tenant users do not receive it at runtime, even if an old
| role_permissions row still exists.
|
*/

return [

    'features' => [

        'tenant_company_settings' => [
            'label'       => 'Tenant company profile & branding',
            'description' => 'Lets tenant admins change their company details and logo.',
            'default'     => true,
        ],

        'tenant_email_settings' => [
            'label'       => 'Tenant email (SMTP) configuration',
            'description' => 'Lets tenant admins configure and test their own mail transport.',
            'default'     => true,
        ],

        'tenant_gate_management' => [
            'label'       => 'Tenant gate & device key management',
            'description' => 'Lets tenant admins create gates and rotate device scan keys.',
            'default'     => true,
        ],

        'public_pre_registration' => [
            'label'       => 'Public visitor pre-registration & QR pass',
            'description' => 'Exposes /pre-register/{code} and /pass/{token} to visitors.',
            'default'     => true,
        ],

        'gate_device_scanning' => [
            'label'       => 'Hardware gate device scanning',
            'description' => 'Exposes the key-authenticated /gate-scan endpoint.',
            'default'     => true,
        ],

    ],

    /*
    | Default restricted permissions. Empty on purpose: restricting a key
    | immediately removes it from every tenant role, so installations opt in.
    */

    'restricted_permissions' => [],

    /*
    | Support access (impersonation). Short-lived by design; the service
    | clamps the value to 5–240 minutes. Every start and end is audited.
    */

    'impersonation' => [
        'ttl_minutes' => env('PLATFORM_IMPERSONATION_TTL_MINUTES', 60),
    ],

];
