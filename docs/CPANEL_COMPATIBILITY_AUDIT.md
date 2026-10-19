# NSD Earth Movers CRM — cPanel Hosting Compatibility Audit

**Project:** NSD Earth Movers & Construction Ltd. — CRM / ERP  
**Repository:** `https://github.com/Andrew93541/NSD-EARTH-MOVERS-CRM01`  
**Framework:** Laravel 13.x (PHP 8.3+)  
**Document Version:** 1.0 (Production Release)

---

## 1. Executive Summary

This audit assesses the architectural and operational requirements of the NSD Earth Movers & Construction CRM application against typical shared cPanel hosting environments.

The CRM is a single-company enterprise application featuring Project Management, Workforce & Muster Roll Attendance, Inventory & Stock Ledgers, Procurement & Supplier Invoicing, Quotations, Accounting, and User Profile Management.

**Key Finding:**  
The core CRM business workflows (database operations, Blade UI, Alpine.js no-refresh interactions, authorization policies, document downloads, audit logging, and reporting) are **100% compatible** with standard shared cPanel hosting running **PHP 8.3+** and **MySQL 8.0+ / MariaDB 10.4+**.  
However, long-running daemon processes (such as Laravel Reverb WebSocket servers and persistent queue listeners) require specific configurations or cron-based workarounds on shared hosting accounts.

---

## 2. Requirements Classification Matrix

Each system component is categorized into one of three support tiers:
- **Tier 1 (Supported):** Runs natively on standard shared cPanel hosting without special host interventions.
- **Tier 2 (Conditional):** Supported, but requires specific cPanel settings or hosting provider enablement (e.g., PHP version selector, cron jobs, symlink permission).
- **Tier 3 (Workaround / Upgrade Required):** Unsupported as a native daemon on shared cPanel; requires a scheduled workaround, external service, or VPS upgrade.

| Component | Target Requirement | Support Tier | Evaluation & cPanel Strategy |
| :--- | :--- | :--- | :--- |
| **PHP Runtime** | PHP >= 8.3 (Tested with 8.5) | **Tier 2** | Available on all modern cPanel hosts via **cPanel MultiPHP Manager** or **Select PHP Version**. Must be set to PHP 8.3 or higher. |
| **PHP Extensions** | `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `curl`, `zip`, `gd` | **Tier 1** | Standard on cPanel PHP installations. `gd` or `imagick` is needed for user profile avatar uploads. |
| **Database Engine** | MySQL 8.0+ / MariaDB 10.4+ | **Tier 1** | Native cPanel MySQL Database Wizard. InnoDB engine with `utf8mb4_unicode_ci`. |
| **Web Server Root** | Document root pointing to `/public` | **Tier 2** | Native on Addon Domains / Subdomains. For Primary Domain (`public_html`), requires setting document root or using the secure outside-root directory layout. |
| **Composer** | Composer 2.x | **Tier 2** | Available if cPanel Terminal/SSH is enabled. If disabled, dependencies can be pre-packaged locally via `composer install --no-dev`. |
| **Frontend Assets** | Vite / Node.js / npm | **Tier 3 (Build-time)** | Node.js is NOT required at runtime. Assets (`public/build`) are compiled locally or in CI and deployed as static CSS/JS files. |
| **Storage Permissions** | Writable `storage/` & `bootstrap/cache/` | **Tier 1** | Supported. Permissions set to `0755` (or `0775` depending on user/group mapping) via cPanel File Manager. |
| **Storage Symlink** | `public/storage -> storage/app/public` | **Tier 2** | Created via cPanel Terminal `php artisan storage:link`, or via a one-time PHP bridge script if SSH is disabled. |
| **Session Driver** | `database` or `file` | **Tier 1** | Fully supported. Uses `sessions` table in MySQL or file storage in `storage/framework/sessions/`. |
| **Cache Driver** | `database` or `file` | **Tier 1** | Fully supported. Uses `cache` table in MySQL or file cache. No Redis dependency required. |
| **Queue Worker** | Background Job Processing | **Tier 3 (Workaround)** | Shared cPanel kills continuous background daemons. **Workaround:** Scheduled cron job running `php artisan queue:work --stop-when-empty` every 5 minutes. |
| **Task Scheduler** | `php artisan schedule:run` | **Tier 2** | Supported via **cPanel Cron Jobs** interface running once per minute (`* * * * *`). |
| **Email Service** | SMTP Delivery | **Tier 1** | Supported natively via cPanel Webmail (Exim) accounts or external transactional mailers (SendGrid, Mailgun). |
| **WebSockets** | Laravel Reverb (`reverb:start`) | **Tier 3 (Workaround)** | Cannot run persistent custom-port daemon on shared cPanel without root. **Workaround:** The CRM's built-in AJAX and polling fallback handles updates seamlessly. |
| **Anti-Malware Scanner** | ClamAV Daemon (`clamd`) | **Tier 3 (Workaround)** | ClamAV daemon is rarely enabled for shared users. `SCANNER_ENABLED=false` or `SCANNER_FAIL_CLOSED=false` must be set in `.env` to avoid upload rejection. |

---

## 3. Deep Dive: Hosting Subsystems

### 3.1 PHP Runtime, Memory Limits & Timezone
- **Minimum PHP Version:** 8.3.0 (`composer.json` specifies `"php": "^8.3"`).
- **Tested Versions:** PHP 8.3, 8.4, 8.5.
- **Timezone & Locale:** Configured for Indian Standard Time (`APP_TIMEZONE=Asia/Kolkata`, UTC+5:30). Fully validated against all daily attendance muster rolls, overtime logging, payroll periods, and deadline reminders with 339 tests passing.
- **cPanel Configuration:** In **MultiPHP Manager** or **Select PHP Version**:
  - Set PHP version to **8.3** (or highest available 8.x).
  - Configure `memory_limit` to at least `256M` (recommended: `512M` for heavy PDF generation and Excel export reports).
  - Configure `max_execution_time` to `120` seconds.
  - Set `upload_max_filesize` to `20M` and `post_max_size` to `25M` to allow document, drawing, and invoice uploads.

### 3.2 Web Server Architecture and Document Root Security
- **The Threat:** Exposing the root directory of a Laravel application allows public access to `.env`, `.git`, `storage/logs`, and configuration files containing database passwords and application keys.
- **The Rule:** The public web server MUST serve requests exclusively from the `public/` folder.
- **cPanel Domain Configurations:**
  - **Addon Domain / Subdomain:** In cPanel **Domains**, set the Document Root explicitly to `/home/username/crm_app/public`.
  - **Primary Domain (`public_html` locked by host):**
    - Place all application code in `/home/username/crm_app/` (outside `public_html`).
    - Create a symbolic link from `/home/username/public_html` to `/home/username/crm_app/public`, OR
    - Copy only the contents of `public/` into `public_html/` and update `public_html/index.php` to reference `/home/username/crm_app/vendor/autoload.php` and `/home/username/crm_app/bootstrap/app.php`.
    - **Never** upload the entire project directly into `public_html` without isolating the document root.

### 3.3 Database (MySQL / MariaDB)
- **Supported Versions:** MySQL 8.0+ or MariaDB 10.4+.
- **Collation & Charset:** `utf8mb4` with `utf8mb4_unicode_ci`.
- **Database Engine:** `InnoDB` (required for row-level locking, foreign key integrity, and atomic transactions in stock ledgers and accounting journals).
- **Naming Conventions:** cPanel prefixes database names and usernames with the cPanel account name (e.g. `cpaneluser_crm` and `cpaneluser_crmuser`).

### 3.4 File Storage and Document Protection
- **Public Storage:** Avatars and public assets reside in `storage/app/public/` and are served via `public/storage`.
- **Confidential Document Protection:**
  - Contracts, quotations, employee payslips, vendor invoices, retention certificates, and tax reports reside in `storage/app/private/`.
  - These files are **never** accessible through direct URLs.
  - Downloads are guarded by [DocumentPolicy.php](file:///Users/andrew/Developer/NSD%20EARTH%20MOVERS%20CRM/app/Policies/DocumentPolicy.php) and authorized through controller routes with company scoping (`company_id`) and role permission checks.
- **ClamAV Anti-Malware Notice:**
  - The application includes an enterprise ClamAV scanner integration in [filesystems.php](file:///Users/andrew/Developer/NSD%20EARTH%20MOVERS%20CRM/config/filesystems.php).
  - In production, if ClamAV daemon is unreachable and `SCANNER_FAIL_CLOSED=true`, file uploads will be blocked.
  - On shared cPanel where `clamd` is not running, set `SCANNER_ENABLED=false` or `SCANNER_FAIL_CLOSED=false` in `.env`.

### 3.5 Cron Jobs & Task Scheduling
- The application relies on Laravel's scheduler to run operational reminders (`buildflow:send-reminders` every 4 hours), clean up expired sessions, and check overdue items.
- Shared cPanel supports scheduling via the **Cron Jobs** menu.
- Command pattern:
  ```cron
  * * * * * /usr/local/bin/php /home/username/crm_app/artisan schedule:run >> /dev/null 2>&1
  ```

### 3.6 Queue Workers
- The application uses `QUEUE_CONNECTION=database` for background tasks and reminder dispatches.
- On shared cPanel, running a persistent daemon (`queue:work` or Supervisor) is prohibited because LVE/CageFS monitors process longevity and kills long-running scripts.
- **Safe cPanel Workaround:**
  Schedule a cron job every 5 minutes:
  ```cron
  */5 * * * * /usr/local/bin/php /home/username/crm_app/artisan queue:work --stop-when-empty --tries=3 --max-time=240 >> /dev/null 2>&1
  ```
  This processes all pending jobs and exits cleanly before the hosting process watchdog intervenes.

### 3.7 WebSockets (Laravel Reverb) vs. No-Refresh AJAX
- **Reverb Daemon Limitation:**
  Laravel Reverb requires a persistent process (`php artisan reverb:start`) listening on a dedicated port (e.g. 8080). Shared cPanel firewalls typically block non-standard incoming ports, and CageFS terminates background daemons.
- **Preserved User Experience:**
  - The CRM's interactive interfaces (muster roll attendance, purchase request approvals/rejections, profile updates, theme changes) operate using **Alpine.js and asynchronous `fetch()` requests**.
  - These no-refresh interactions do NOT require WebSockets and work 100% on shared cPanel.
  - All broadcast events implement try-catch fallbacks: if the WebSocket server is unavailable, the business transaction completes without error.
  - The notification center provides polling endpoints (`/notifications/feed` and `/api/notifications/unread-count`) to maintain fresh alerts without WebSockets.
- **Optional Upgrade Path:** If true real-time WebSockets are mandatory, the organization can deploy a standalone Reverb or Pusher instance on a micro-VPS ($5/mo) and configure the cPanel `.env` with `BROADCAST_CONNECTION=pusher`.

---

## 4. Audit Checklist for Hosting Provider Verification

Before purchasing or deploying to a cPanel shared hosting account, verify with the provider:

- [ ] Does the cPanel account provide PHP 8.3 or higher?
- [ ] Is `max_execution_time` configurable to at least 120 seconds?
- [ ] Is `memory_limit` configurable to at least 256MB?
- [ ] Can custom cron jobs be scheduled every 1 minute (`* * * * *`)?
- [ ] Is SSH / Terminal access available (or can Composer dependencies be uploaded via FTP)?
- [ ] Can the document root of the primary domain or subdomain be set to a custom subdirectory (`/public`)?
- [ ] Are symbolic links permitted within the user home directory?
