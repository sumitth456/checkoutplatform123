#!/usr/bin/env bash
# After adding a GitHub deploy key or PAT on the VPS, run once to publish main.
set -euo pipefail
cd /var/www/CheckoutPlatform
git push -u origin main
