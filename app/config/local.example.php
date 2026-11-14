<?php
// Copy to app/config/local.php and edit. local.php is merged over config.php and is never committed.
// Keep it readable by the web server user only: chmod 600 app/config/local.php
return [
    'property_name' => 'Example Hotel',
    'property_code' => 'EXH',                  // prefix for ticket numbers, e.g. EXH-000123
    'operator'      => '',                     // optional "Operated by ..." line on the sign-in page
    'base_path'     => '',                     // '' at a domain root, '/exptix' in a sub-folder (no trailing slash)
    'base_url'      => 'https://exptix.example.com',
    'timezone'      => 'America/Chicago',
    'locale'        => 'en-US',
    'currency'      => 'USD',
    'source_url'    => 'https://github.com/moosesystems/EXPTIX', // change if you run a modified version (AGPL section 13)
    'demo_mode'     => false,

    'db' => [
        'driver' => 'mysql',                   // or 'sqlite' (stored in app/data/exptix.sqlite)
        'host'   => 'localhost',
        'port'   => 3306,
        'name'   => 'exptix',
        'user'   => 'exptix',
        'pass'   => 'change-me',
    ],

    'security' => [
        'mfa_required_ranks_at_or_above' => 70, // force MFA for managers and up (see docs/DEPLOYMENT.md, roles)
        'trusted_proxies' => [],               // e.g. ['10.0.0.5'] when behind a reverse proxy or load balancer
    ],

    'mail' => [
        'from'      => 'exptix@example.com',
        'from_name' => 'ExpTix',
    ],

    'push' => [ 'subject' => 'mailto:it@example.com' ],
];
