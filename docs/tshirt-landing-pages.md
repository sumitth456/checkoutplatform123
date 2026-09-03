# T-Shirt Landing Pages

CheckoutPlatform supports T-shirt campaign landing pages on **one global domain** with **wildcard DNS** and **dynamic slug-based subdomains**.

## Architecture

```
ONE GLOBAL DOMAIN:     famteesworld.com
ONE WILDCARD DNS:      *.famteesworld.com
MANY LANDING SLUGS:    jamescook, seahawks, campaign3, ...
```

Creating a new landing page only requires a new slug in the CRM. **No per-campaign DNS record is needed** when wildcard DNS is configured.

Example:

| Slug | Public URL |
|------|------------|
| `jamescook` | `https://jamescook.famteesworld.com` |
| `seahawks` | `https://seahawks.famteesworld.com` |
| `campaign3` | `https://campaign3.famteesworld.com` |

## Global configuration

1. Open **Admin → Landing Pages → T-Shirt Domain**
2. Set **T-shirt landing page domain** (example: `famteesworld.com`)
3. Save
4. Click **Verify domain** to check root DNS resolution

Environment fallback (optional):

```env
TSHIRT_LANDING_DOMAIN=famteesworld.com
TSHIRT_LANDING_USE_HTTPS=true
```

The admin setting takes precedence over the environment value when saved.

## Wildcard DNS (one-time setup)

Configure **one** wildcard record at your DNS provider (Namecheap or other):

| Type | Host | Value |
|------|------|-------|
| `CNAME` or `A` | `*` | Your external landing page / hosting target |

Example:

```
*.famteesworld.com  →  external-host.example.com
```

After wildcard DNS is live, every new slug automatically works:

```
Slug: jamescook  →  https://jamescook.famteesworld.com
```

**Do not** create individual DNS records for each campaign slug.

## Creating a T-shirt landing page

1. Open **Admin → Landing Pages → Create**
2. Enable **T-shirt landing page**
3. Enter **Campaign name** and **Slug** (example: `jamescook`)
4. Select **Campaign**, **Product**, and **Variant**
5. Review the auto-generated **Public URL**
6. Optionally set **External hosting URL** if HTML is hosted elsewhere
7. Publish the page

The CRM generates:

```
https://jamescook.famteesworld.com
```

Legacy path URLs remain available for non-T-shirt pages:

```
/offer/{slug}
```

## Slug rules

Slugs must be:

- lowercase
- unique
- URL-safe (`a-z`, `0-9`, hyphens)
- free of spaces, dots, slashes, or protocols

Reserved slugs (blocked):

`www`, `admin`, `api`, `app`, `mail`, `smtp`, `ftp`, `cdn`, `assets`, `static`

## Hostname resolution

When a request arrives at:

```
https://jamescook.famteesworld.com
```

CheckoutPlatform:

1. Validates the hostname belongs to the configured T-shirt domain
2. Extracts slug `jamescook`
3. Loads the published T-shirt landing page with that slug
4. Renders the landing page

Draft, archived, and non-T-shirt pages return **404** on the subdomain.

## External hosting

The CRM manages:

- campaign metadata
- slug
- canonical public URL
- product / variant mapping
- optional external hosting fields

The actual HTML may be hosted on an external platform. Point wildcard DNS to that platform and configure the external URL in the landing page record for reference.

## Product mapping

T-shirt pages can reference existing catalog products and variants directly. Use the admin product picker — do not hardcode IDs.

Example first campaign:

- Product: `Mens T- JamesCookWhite - Medium`
- SKU: `G5000-White-MD`

## Troubleshooting

| Issue | Check |
|-------|-------|
| Subdomain 404 | Page published? T-shirt flag enabled? Slug matches subdomain? |
| Wrong page | Duplicate slug? Only one published page per slug |
| Root domain issue | `famteesworld.com` and `www` are reserved — not slug routes |
| DNS not working | Confirm wildcard `*.domain` exists and propagates |
| HTTPS | Set `TSHIRT_LANDING_USE_HTTPS=true` for generated admin URLs |

## What this feature does not change

This feature does **not** modify:

- orders
- payments
- fulfillment
- ShipStation
- Mailjet
- checkout processing

It only adds global domain + slug + hostname routing for T-shirt landing pages.
