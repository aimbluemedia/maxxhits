# MaxxHits 2.0 — Claude Code Project Brief

## What This Is
**MaxxHits 2.0** — AimBlue Media's internal **marketing operating system**, rebuilt from scratch around the "GrokBot 2.0" prototype spec. Multi-brand workspaces, AI content generation, mandatory human approval, scheduled automations, and auto-publishing to WordPress + social platforms. Dark-theme internal OS with 9 navigation groups (~45 modules): a working core plus structured placeholder workspaces that carry each future module's build spec.

## Stack
- **Backend**: PHP 8.x, PDO/MySQL — no frameworks, no Composer, no build step
- **Frontend**: Vanilla JS (shared `core.js` with `MH` namespace + one file per page), **no Bootstrap** — custom dark CSS adapted from the prototype (`assets/css/app.css`)
- **Database**: MySQL (Hostinger shared hosting — no CREATE/DROP DATABASE permission)
- **Auth**: PHP sessions, password_hash/verify, CSRF on all mutations; roles `admin` | `staff`
- **Timezone**: America/Phoenix (Arizona) everywhere — scheduling, display, cron math

## File Map
```
index.php / login.php / logout.php   — Entry + auth
config.php                            — DB wizard (writes ../maxxhits.db.config.php + APP_KEY)
setadmin.php                          — One-time admin password setter (delete after use)

dashboard.php        — Overview: pipeline tiles, brand table, exceptions, recent runs
brands.php           — Brand directory (name, color, website, brand voice; archive-safe delete)
content-studio.php   — AI brief → variants → edit → submit for review
approvals.php        — Approval center: review queue, approve / approve+queue / reject(note)
queue.php            — Publishing queue + "Run scheduler now" + publish-now
automation.php?p=X   — ONE page serving all ten Automation nav items (platform workspaces)
accounts.php         — Connected accounts: destinations + encrypted credentials + test
settings.php         — AI provider/model/keys, cron URL, emergency-stop state (admin)
audit.php            — Append-only audit log
users.php            — Staff accounts, admin|staff roles (admin)
module.php?m=slug    — Placeholder workspace for every unbuilt module: records + build spec

includes/
  bootstrap.php      — Session, timezone, config load, db_connect(); required first everywhere
  helpers.php        — e(), uid(), json_ok/json_error, encrypt/decrypt_secret, settings, audit()
  csrf.php           — csrf_token(), csrf_check(), csrf_require_for_api()
  auth.php           — roles, require_login/require_admin, brand workspace selector (session)
  modules.php        — THE REGISTRY: mh_groups(), mh_modules() (nav + specs),
                       mh_platforms() (adapters + credential fields), mh_automation_pages()
  runner.php         — run_publish_post(), run_due_posts(), run_automation(), run_due_automations()
  layout/header.php  — Shell: numbered 9-group nav, brand selector, emergency stop, user chip
  layout/footer.php  — Toast, shared confirm modal, script loading

api/                 — JSON endpoints, all behind auth+CSRF ({ok,data}/{ok,error})
  _init.php          — Bootstrap + $pdo/$method/$body + brand_where()
  dashboard.php, brands.php, posts.php, destinations.php, automations.php,
  ai.php, settings.php, users.php, audit.php, records.php

lib/ai.php           — Claude API default (claude-opus-5-5, raw cURL, server-side refusal
                       fallback) + xAI Grok; encrypted keys in settings
lib/adapters/        — adapter.php dispatcher + wordpress/facebook/instagram/twitter/linkedin
cron/scheduler.php   — Runs due automations THEN publishes due posts; CLI or HTTP+?key=CRON_KEY
sql/maxxhits-schema.sql — Full schema + seeded brand directory + locked admin
```

## Architecture Decisions
1. **Module registry drives everything.** `includes/modules.php` defines the 9 nav groups and every module. Modules with `page` are built; the rest render through `module.php` showing the prototype's build spec (goal/workflow/entities/api/checks) + a simple record workspace. To build out a module: create its page+js, set `page` in the registry — nothing else changes.
2. **One working page = one PHP file + one JS file.** Shared helpers only in `core.js` (`MH.*`). The ten Automation nav items share `automation.php?p=<platform>` (one workspace parameterized by platform); placeholder modules share `module.php`.
3. **Brand workspaces**: header selector stores the active brand in the session; APIs filter with `brand_where()` ('' = all brands). No per-user brand scoping yet — roles are global `admin`/`staff`.
4. **Content pipeline**: `draft → review → approved → scheduled → publishing → published` (+ `failed`, `rejected`). Enforced in `api/posts.php`: only admins approve/reject; rejection requires a note; **auto posts can't be scheduled unapproved** (admin queueing counts as approval); non-admin material edits to approved/scheduled posts reset them to review; `approved`/`rejected` never settable via save.
5. **Automations** (`automations` + `automation_runs`): per brand+platform recurring AI research → drafts in REVIEW. Saving always stores paused; test runs use a unique occurrence key and **never publish**. Scheduled runs claim a UNIQUE `(automation_id, occurrence_key=date)` row — the same occurrence can never run twice. Frequencies: daily / weekdays / weekly(Mon) / monthly(1st), at `run_time` Arizona.
6. **Destinations** (`destinations`): per-brand platform credentials, AES-256-CBC encrypted (key from `APP_KEY`). Platforms with `adapter: true` (wordpress, facebook, instagram, twitter, linkedin) auto-publish; the rest (reddit, pinterest, tiktok, youtube, threads) are **manual handoff** — drafts only.
7. **Publishing**: `run_publish_post()` claims via `status→publishing` UPDATE (no double-publish), records external_id/post_url/publish_error/attempts, audits everything. Cron = automations first, then due posts.
8. **Emergency stop** (`settings.emergency_stop`): header button (admin). Enforced server-side in the scheduler, publish paths and automation enable/test — not just UI.
9. **AI** (`lib/ai.php`): provider/model/keys in settings. Claude default (`claude-opus-5-5`, Messages API, `fallbacks: 'default'` + refusal stop-reason handling); Grok via OpenAI-compatible endpoint. Brand voice + website feed the system prompt; guardrails: no invented stats/testimonials/medical claims. JSON array of variants, plain-text fallback parse. **AI never publishes** — output lands as review drafts with `ai_generated=1`.
10. **Audit** (`audit_events`): append-only; `audit()` called on logins, saves, approvals, publishes, stops, runs.

## Database Schema (key tables)
```
brands           — id, name, color, website_url, brand_voice, is_active
users            — id, username, password_hash, role(admin|staff), full_name, email, is_active, last_login
destinations     — id, brand_id, platform, name, config_enc, is_active, last_test_at/ok
posts            — id, brand_id, destination_id, automation_id, source(manual|studio|automation),
                   platform, title, content, image_url, status, publish_mode, post_date, publish_time,
                   ai_generated, approved_by/at, review_note, external_id, post_url, published_at,
                   publish_error, publish_attempts, created_by
automations      — id, brand_id, platform, name, destination_id, topic_prompt, frequency, run_time,
                   max_drafts, enabled, last_run_at
automation_runs  — id, automation_id, occurrence_key (UNIQUE pair), kind, status, drafts_created, message
audit_events     — id, actor, action, target_type, target_id, message
module_records   — id, module, brand_id, title, notes, created_by   (placeholder workspaces)
settings         — key/value: emergency_stop, cron_key, ai_provider, ai_model, ai_key_*_enc
```

## Roles & Access Control
| Capability | Admin | Staff |
|---|---|---|
| View all modules / brands | ✓ | ✓ |
| Content studio (AI generate, drafts, submit) | ✓ | ✓ |
| Approve / reject posts | ✓ | ✗ (view queue) |
| Publish now / run scheduler | ✓ | ✗ |
| Automations: save/test/toggle | ✓ | ✓ (delete: admin) |
| Brands / destinations / users / settings / AI keys | ✓ | ✗ (view only) |
| Emergency stop | ✓ | ✗ |

## Deploying (Hostinger)
Push to `main` → GitHub Actions deploys over FTPS (see DEPLOYMENT.md). First-time server setup:
1. `config.php` → MySQL credentials (writes config + APP_KEY above webroot)
2. Import `sql/maxxhits-schema.sql` via phpMyAdmin (seeds the 8 AimBlue brands + locked admin)
3. `setadmin.php` → set admin password → delete the file
4. Settings → add Claude (or Grok) API key; copy cron URL → hPanel → Advanced → Cron Jobs (5–15 min)

## Conventions
- Escape ALL output: `e()` in PHP, `MH.esc()` in JS; prepared statements only
- New API endpoint: start with `require_once __DIR__ . '/_init.php';`
- New page: set `$PAGE = ['id' => <module slug>, 'title', 'js']`, include layout header+footer,
  and point the module's `page` in `includes/modules.php` at it
- Anything that touches external platforms must check `emergency_stopped()` and write `audit()`
