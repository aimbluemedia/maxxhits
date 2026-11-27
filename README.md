# MaxxHits

Multi-company **marketing operating system** by AimBlue Media: content tracking, AI content
generation, human approval workflow, and automated posting to WordPress + social media.
Plain PHP 8 + MySQL + vanilla JS — no frameworks, no build step, runs on shared hosting (Hostinger/cPanel).

## What it does

- **Dashboard** — revenue, posting today, content pipeline (draft → review → approved → queued → published)
- **Content Studio** — AI-written draft variants per brand + platform (Claude or Grok), using each
  company's saved brand voice; drafts go to review, never straight to publishing
- **Approval Center** — admins approve, approve-and-queue, or reject with a note; edits to an
  approved post automatically send it back to review
- **Companies** — client brands with fees, fee splits, posting targets, brand voice and website
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

## AI content setup

1. **Content Studio** (admin) → *AI settings* → pick Claude (default, model `claude-opus-5-5`)
   or Grok, paste the provider's API key (stored encrypted)
2. Give each company a **brand voice** (Companies → edit) so drafts match the client's tone
3. Generate variants in Content Studio → edit → *Submit for review* → approve in the
   **Approval Center** → queued posts publish automatically via the cron

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
