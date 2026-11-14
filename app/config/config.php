<?php
// ExpTix default configuration. Secrets and site overrides go in config/local.php
// (never committed). local.php returns an array that is merged over this one.
$cfg = [
    'app_name'      => 'ExpTix',
    'property_name' => 'Demo Hotel',
    'property_code' => 'DEMO',
    'operator'      => '',                 // shown on the sign-in page when set
    'base_path'     => '',                 // '' at a domain root, or e.g. '/exptix' in a sub-folder
    'base_url'      => 'https://exptix.example.com',
    'timezone'      => 'UTC',              // property time zone, e.g. 'America/Chicago'
    'locale'        => 'en-US',            // date and number formatting
    'currency'      => 'USD',              // ISO 4217 code for charges and purchase requests
    'source_url'    => 'https://github.com/moosesystems/EXPTIX', // AGPL section 13: link to the source you run
    'demo_mode'     => true,               // false in production: real email, signed webhooks only, no daily demo arrivals

    'db' => [
        'driver' => 'sqlite',                 // sqlite | mysql
        'path'   => APP_ROOT . '/data/exptix.sqlite',
        'host'   => '',
        'port'   => 3306,
        'name'   => '',
        'user'   => '',
        'pass'   => '',
    ],

    'security' => [
        'session_idle_minutes'   => 30,      // NIST 800-63B AAL2 reauth on inactivity
        'session_max_hours'      => 12,
        'password_min_length'    => 12,
        'lockout_threshold'      => 5,
        'lockout_minutes'        => 15,
        'mfa_required_ranks_at_or_above' => 999, // e.g. 70 forces MFA for managers and up
        'audit_retention_days'   => 2555,    // 7 years
        'trusted_proxies'        => [],
    ],

    'uploads' => [
        'path'   => APP_ROOT . '/data/uploads',
        'max_mb' => 12,
        'types'  => ['image/jpeg','image/png','image/webp','image/heic','image/gif','application/pdf','text/plain','text/csv'],
    ],

    'mail' => [
        'from'      => 'exptix@example.com',
        'from_name' => 'ExpTix',
        'transport' => 'mail',               // PHP mail(). For SMTP, point the server's sendmail at your relay (msmtp, Postfix)
        'smtp_host' => '', 'smtp_port' => 587, 'smtp_user' => '', 'smtp_pass' => '', 'smtp_secure' => 'tls',
    ],

    'push' => [ 'subject' => 'mailto:admin@example.com' ],
];

$local = APP_ROOT . '/config/local.php';
if (is_file($local)) {
    $cfg = array_replace_recursive($cfg, require $local);
}
return $cfg;
