#!/usr/bin/env bash
# Writes MySQL settings into app/config/local.php (mode 600). Run on the server from the repository root.
# Values are base64-wrapped so any character in the password is safe.
set -euo pipefail
cd "$(dirname "$0")/.."
OUT=app/config/local.php
if [[ -e $OUT ]]; then echo "$OUT already exists. Edit it by hand, or move it aside first." >&2; exit 1; fi
read -rp  "MySQL host [localhost]: " H; H=${H:-localhost}
read -rp  "MySQL port [3306]: " PORT; PORT=${PORT:-3306}
read -rp  "Database name [exptix]: " D; D=${D:-exptix}
read -rp  "Database user [exptix]: " U; U=${U:-exptix}
read -rsp "Database password (hidden): " P; echo
b() { printf '%s' "$1" | base64 | tr -d '\n'; }
umask 077
cat > "$OUT" <<PHP
<?php
// ExpTix site overrides. Not in git. Written $(date -u +%FT%TZ)
return [
    'demo_mode' => false,
    'db' => ['driver' => 'mysql', 'host' => base64_decode('$(b "$H")'), 'port' => ${PORT}, 'name' => base64_decode('$(b "$D")'),
             'user' => base64_decode('$(b "$U")'), 'pass' => base64_decode('$(b "$P")')],
];
PHP
unset P
echo "Saved $OUT. Add property name, base_url and time zone from app/config/local.example.php, then run: php app/bin/cli.php migrate"
