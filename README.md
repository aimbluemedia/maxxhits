# MaxxHits

Multi-company content tracking and **automated posting** platform by AimBlue Media.
Plain PHP 8 + MySQL + vanilla JS — no frameworks, no build step, runs on shared hosting (Hostinger/cPanel).

## What it does

- **Dashboard** — monthly revenue, companies posting today, per-company progress, team earnings
- **Companies** — client companies with fees, fee splits, posting targets and posting days
- **Company board** — per-company post calendar with month filter, platform filter and post editor
- **Automation** — connect WordPress + social accounts as *channels* and auto-publish scheduled posts:
  - WordPress (REST API + application passwords, incl. featured-image sideload)
  - Facebook Pages (Graph API)
  - Instagram Business (Graph API, image posts)
  - X / Twitter (API v2)
  - LinkedIn (pages or profiles)
- **Social logins** — AES-encrypted credentials vault, shared per company
- **Content managers / Sales people** — team records linked to companies
- **Users** — login accounts with dual roles (admin / CM / SP), company-scoped visibility

## Architecture

Every navigation item is its **own page** (`dashboard.php`, `companies.php`, `company.php`,
`automation.php`, …) with its **own JS file** — editing one page cannot break the others.
Shared code lives in `includes/` (auth, helpers, layout) and `assets/js/core.js`.
All data flows through JSON endpoints in `api/`, protected by sessions + CSRF tokens.

## Install

> **Automatic deployment:** pushes to `main` auto-upload to Hostinger via
> GitHub Actions — see [DEPLOYMENT.md](DEPLOYMENT.md) for the one-time setup.

1. Upload everything to your webroot (or a subfolder) — or let the deploy workflow do it
2. Open `config.php` in the browser → enter MySQL credentials
3. Import `sql/maxxhits-schema.sql` in phpMyAdmin
4. Open `setadmin.php` → set the admin password → **delete this file from the server**
5. Sign in at `login.php` (username `admin`)

## Automated posting setup

1. **Automation page** → *Add channel* → pick the company + platform and paste credentials
   (each platform's fields are explained inline)
2. Use the plug button to **test** the connection
3. On a company board, create a post, set *Publishing* to **Auto-publish via channel**,
   pick the channel, date and time
4. Add the **cron URL** (shown on the Automation page, admin only) to
   Hostinger → Advanced → Cron Jobs, every 5–15 minutes — or run
   `php cron/publish-scheduler.php` from a CLI cron
5. Watch the queue and activity log on the Automation page; use **Run now** to trigger
   a run manually, or the send button on a post to publish it immediately

## Security notes

- DB config (with the encryption key) is written above the webroot when possible and gitignored
- Channel credentials and vault passwords are AES-256-CBC encrypted at rest
- All API mutations require a CSRF token; company visibility is enforced server-side
- `.htaccess` files block direct access to `includes/`, `lib/`, `sql/` and config/SQL files
