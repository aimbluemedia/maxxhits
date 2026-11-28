# MaxxHits 2.0

AimBlue Media's internal **marketing operating system**: multi-brand workspaces, AI content
generation, mandatory human approval, recurring automations, and auto-publishing to WordPress
and social platforms. Plain PHP 8 + MySQL + vanilla JS — no frameworks, no build step, runs on
shared hosting (Hostinger/cPanel). Dark internal-OS interface.

## The platform

Nine navigation groups — **Command · Intelligence · Creative · Automation · Publishing ·
Engagement · Outreach · Growth · Administration** — with a fully working core today and
structured placeholder workspaces (each carrying its build specification) for the rest:

**Working now**
- **Overview** — pipeline tiles, per-brand status, failed-post exceptions, recent automation runs
- **Brands** — the brand directory feeding the workspace selector (seeded with the AimBlue brands)
- **Content studio** — AI draft variants per brand + platform in the brand's voice (Claude by
  default, Grok selectable), edited and submitted for review
- **Approval center** — admins approve, approve-and-queue, or reject with a note; nothing
  publishes without a human decision, and edits to approved posts return them to review
- **Automation** (10 platform workspaces: LinkedIn, Reddit, Pinterest, Blog, Facebook,
  Instagram, YouTube, TikTok, X, Threads) — recurring AI research → drafts into the Approval
  center on a brand-specific Arizona-time schedule; saved paused, test runs never publish,
  duplicate occurrences impossible
- **Publishing queue** — approved+scheduled posts auto-publish via the cron scheduler through
  official APIs (WordPress, Facebook Pages, Instagram Business, X, LinkedIn); other platforms
  are manual handoff
- **Connected accounts** — per-brand destinations with AES-encrypted credentials + connection tests
- **Settings** — AI provider/model/keys, scheduler cron URL, emergency-stop state
- **Audit log** — append-only history of every decision, publish and run
- **Emergency stop** — one header button pauses ALL publishing and automations, enforced server-side

## Install

> Pushes to `main` auto-deploy to Hostinger via GitHub Actions — see [DEPLOYMENT.md](DEPLOYMENT.md).

1. Upload/deploy to the webroot
2. Open `config.php` → enter MySQL credentials
3. Import `sql/maxxhits-schema.sql` in phpMyAdmin
4. Open `setadmin.php` → set the admin password → **delete the file from the server**
5. Sign in (username `admin`) → Settings → add your Claude API key
6. Copy the cron URL from Settings into hPanel → Advanced → Cron Jobs (every 5–15 minutes)

## Daily flow

Brief the **Content studio** (or let an **Automation** run on schedule) → drafts land in the
**Approval center** → an admin approves & queues → the scheduler publishes at the set date/time
→ results, links and failures appear in the **Publishing queue** and **Audit log**.

## Security notes

- DB config + encryption key written above the webroot when possible; gitignored and .htaccess-blocked
- Destination credentials and AI keys are AES-256-CBC encrypted at rest
- CSRF on every mutation, roles enforced server-side, approval gates and the emergency stop
  enforced in the scheduler itself — not just the UI
