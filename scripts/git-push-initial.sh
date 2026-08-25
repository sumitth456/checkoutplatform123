#!/usr/bin/env bash
# One-time: publish /var/www/CheckoutPlatform to GitHub (keeps .env.docker untracked).
set -euo pipefail
cd /var/www/CheckoutPlatform

if ! command -v git >/dev/null 2>&1; then
  apt-get update -qq && apt-get install -y -qq git
fi

find scripts -name '*.sh' -exec sed -i 's/\r$//' {} +

if [ ! -f .gitignore ]; then
  echo "Missing .gitignore — upload from repo first."
  exit 1
fi

if [ ! -d .git ]; then
  git init
  git branch -M main
fi

git remote remove origin 2>/dev/null || true
git remote add origin https://github.com/sumitth456/checkoutplatform.git

git add -A

if git diff --cached --quiet; then
  echo "No changes to commit."
else
  git -c user.email="deploy@checkoutplatform.local" -c user.name="CheckoutPlatform Deploy" \
    commit -m "Initial commit: Dockerized Laravel checkout platform"
fi

echo "==> Pushing to origin main (GitHub credentials required)"
git push -u origin main
