# Push from VPS to GitHub

The full codebase is committed on the VPS at `/var/www/CheckoutPlatform` (branch `main`).  
Push failed with: `could not read Username for 'https://github.com'` — configure GitHub auth once.

## Option A — Personal access token (HTTPS)

```bash
cd /var/www/CheckoutPlatform
git push -u origin main
```

Use your GitHub username and a PAT as the password when prompted.

## Option B — Deploy key (SSH)

Generate a key on the VPS, add the `.pub` file as a **write-enabled** deploy key on the repo, then:

```bash
git remote set-url origin git@github.com:sumitth456/checkoutplatform.git
git push -u origin main
```

## Routine deploy (after push works)

```bash
cd /var/www/CheckoutPlatform
git pull
bash scripts/deploy.sh
```
