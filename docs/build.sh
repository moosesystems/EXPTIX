#!/usr/bin/env bash
# Rebuild DEPLOYMENT.html and DEPLOYMENT.docx from DEPLOYMENT.md. Needs pandoc 2.19 or later.
set -euo pipefail
cd "$(dirname "$0")"
FMT=markdown+gfm_auto_identifiers+task_lists+pipe_tables+fenced_code_blocks
pandoc DEPLOYMENT.md -f "$FMT" -t html5 --standalone --embed-resources --css deployment.css \
  --metadata title="ExpTix deployment guide" --metadata lang=en -o DEPLOYMENT.html
pandoc DEPLOYMENT.md -f "$FMT" -t docx -o DEPLOYMENT.docx
echo "Built DEPLOYMENT.html and DEPLOYMENT.docx"
