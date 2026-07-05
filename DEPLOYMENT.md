# Deploying MaxxHits to Hostinger automatically

Two ways to auto-upload this repo to Hostinger. **Option A (GitHub Actions +
FTPS)** is already set up in this repo and works on every Hostinger plan.
Option B uses Hostinger's built-in Git feature if your plan has it.

---

## Option A — GitHub Actions → FTPS (recommended, already configured)

Every push to the `main` branch runs `.github/workflows/deploy.yml`, which
uploads only the **changed files** to your Hostinger webspace over FTPS.

### One-time setup

1. **Get FTP credentials from Hostinger**
   - hPanel → **Files → FTP Accounts**
   - Note the *FTP host* (e.g. `ftp.yourdomain.com` or an IP), *username*
     and *password* (create a new FTP account if you prefer a dedicated one).

2. **Add them as GitHub secrets**
   - GitHub repo → **Settings → Secrets and variables → Actions → New repository secret**
   - Create these three secrets:

     | Secret name | Value |
     |---|---|
     | `FTP_SERVER` | your FTP host, e.g. `ftp.yourdomain.com` |
     | `FTP_USERNAME` | the FTP username |
     | `FTP_PASSWORD` | the FTP password |

3. **(Optional) change the target folder**
   - Default is `public_html/`. If your FTP account is already rooted inside
     `public_html`, or you deploy to a subdomain/subfolder, add a repository
     **variable** (same page, "Variables" tab) named `FTP_SERVER_DIR`,
     e.g. `public_html/maxxhits/` or `./`.
   - The value must end with `/`.

4. **Push to `main`** (or run the workflow manually: **Actions → Deploy to
   Hostinger → Run workflow**). Watch the run log — the first deploy uploads
   everything; later deploys only upload what changed.

### What it deliberately does NOT touch

- `db.config.php` / `maxxhits.db.config.php` on the server (your DB
  credentials + encryption key) — the action never deletes files it didn't
  upload itself.
- `sql/`, docs and `.github/` are uploaded/excluded per the workflow's
  `exclude` list (docs and workflow files are not published to the site).

### After the FIRST deploy (one-time app setup on the server)

1. Browse to `https://yourdomain.com/config.php` → enter the MySQL
   credentials from hPanel → **Databases → MySQL Databases**
2. Import `sql/maxxhits-schema.sql` via hPanel → phpMyAdmin
3. Browse to `setadmin.php`, set the admin password, then **delete
   setadmin.php from the server** (File Manager) — note: a later deploy will
   re-upload it only if the file changes in the repo; consider removing it
   from the repo once the password is set
4. Log in and add your cron job: Automation page → copy cron URL →
   hPanel → **Advanced → Cron Jobs** → every 5–15 minutes

---

## Option B — Hostinger's built-in Git deployment

Available on most Hostinger plans (hPanel → **Advanced → GIT**).

1. hPanel → Advanced → GIT → **Create a new repository**
   - Repository: `https://github.com/aimbluemedia/maxxhits.git`
     (use an SSH deploy key if the repo is private: hPanel shows a public
     key to add under GitHub → repo → Settings → Deploy keys)
   - Branch: `main`
   - Directory: leave empty for `public_html` (or a subfolder)
2. Click **Deploy** once to pull the current code.
3. Enable **Auto deployment** — hPanel shows a *webhook URL*. Copy it to
   GitHub → repo → **Settings → Webhooks → Add webhook** (content type
   `application/json`, "Just the push event").
4. Now every push to `main` makes Hostinger pull the repo automatically.

Notes for Option B:
- Hostinger does a `git pull`, so the deploy includes ALL repo files
  (README, sql/, .github/ …). The included `.htaccess` files already block
  the sensitive ones from being served.
- Do the same first-time app setup steps as in Option A.

---

## Which one should you use?

| | Option A (Actions + FTPS) | Option B (hPanel Git) |
|---|---|---|
| Works on every plan | ✓ | Plan-dependent |
| Excludes docs/workflow files | ✓ | ✗ (full repo) |
| Needs secrets in GitHub | FTP credentials | Deploy key only |
| Deploy log visible in GitHub | ✓ Actions tab | ✗ (hPanel only) |

Both trigger on push to `main`. Keep the day-to-day flow simple: develop on a
feature branch, merge to `main`, deployment happens automatically.
