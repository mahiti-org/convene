# Shared Hosting Deployment Runbook (cPanel/Plesk mode)

For NGO instances hosted on shared hosting with no root/SSH and no Docker. Where the host supports
Git-based deploys (cPanel "Git Version Control" / `.cpanel.yml`), steps 3–5 automate; otherwise follow
them manually via FTP/File Manager.

## One-time setup

1. **Provision on the host**: create a MySQL/MariaDB database + user via the host's control panel;
   note the DB host/name/user/password (usually `localhost` and a prefixed name like `cpaneluser_convene`).
2. **PHP version**: set the domain's PHP version to >= 8.2 in the host's "MultiPHP Manager" (or equivalent).
   Enable extensions: `pdo_mysql`, `mbstring`, `xml`, `curl`, `zip`, `gd`.
3. **Upload the API**: either push via the host's Git deploy hook (`.cpanel.yml` in this directory), or
   upload `apps/api` (after running `composer install --no-dev --optimize-autoloader` **locally**, since
   Composer is often unavailable on the host) via FTP to a directory *outside* `public_html`
   (e.g. `~/convene-api`), with only `apps/api/public` symlinked/pointed to as the domain's document root.
4. **Upload the web build**: run `npm run build` locally in `apps/web`, upload the resulting `dist/`
   contents to the web app's `public_html` (or a subdomain), and set `apps/web/dist/.htaccess` (below)
   for client-side routing.
5. **Configure `.env`**: upload a `.env` (based on `apps/api/.env.example`) with
   `CONVENE_HOSTING_MODE=shared`, `CONVENE_DEFAULT_DISK=local_storage`, `CONVENE_QUEUE_MODE=cron-batch`,
   `QUEUE_CONNECTION=sync` or `database`, `FILESYSTEM_DISK=local_storage`, and the DB credentials from step 1.
6. **Run migrations**:
   - If the host provides SSH (some do, without root): `php artisan migrate --force`.
   - If not: use the host's "Terminal" app (many cPanel hosts include one), or as a last resort run the
     token-gated `deploy/shared-hosting/migrate.php` fallback (see below) — delete or re-lock it immediately after.
7. **Cron jobs** (host's "Cron Jobs" UI — this replaces the VM mode's persistent `scheduler`/`queue-worker` containers):
   ```
   * * * * * php /home/<user>/convene-api/artisan schedule:run >> /dev/null 2>&1
   */5 * * * * php /home/<user>/convene-api/artisan queue:work --stop-when-empty >> /dev/null 2>&1
   ```
8. **Backups**: enable the host's built-in backup feature if available, **and** add a cron job:
   ```
   0 2 * * * mysqldump -u <user> -p'<password>' <database> | gzip > /home/<user>/backups/convene-$(date +\%Y\%m\%d).sql.gz
   ```
   If the host offers no off-site backup, periodically sync `/home/<user>/backups` off-host (e.g. via `rclone` if installable, or manual download).

## Upgrades (subsequent releases)

Repeat steps 3–4 with the new release, then step 6 (migrations only run forward, never destructive
without a reviewed migration). Keep the previous release's `apps/api` directory alongside the new one
until the upgrade is verified, so rollback is a document-root symlink swap, not a re-upload.

## Known degradations vs. VM/Docker mode

- No persistent queue worker — jobs run on a 5-minute cron cadence (`--stop-when-empty`), not instantly.
- No containerized rollback — rollback is manual (symlink swap + restore the previous DB backup).
- File storage is local disk, subject to the host's disk quota; monitor `storage/app` usage (photo uploads).
