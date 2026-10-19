# NSD Earth Movers CRM — Production Deployment Guide for cPanel

**Application:** NSD Earth Movers & Construction Ltd. — CRM / ERP  
**Target Environment:** cPanel Shared / Reseller / Cloud Hosting  
**Architecture:** Laravel 13.x | PHP 8.3+ | MySQL 8.0+ / MariaDB 10.4+ | Vite Assets  
**Document Version:** 1.0 (Production Release)

---

## Deployment Architecture Overview

```
/home/YOUR_CPANEL_USER/
│
├── crm_app/                         <-- PRIVATE: Application Code (Outside web root)
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── routes/
│   ├── storage/
│   ├── vendor/
│   ├── .env                         <-- NEVER accessible to the web
│   ├── artisan
│   └── composer.json
│
└── public_html/                     <-- PUBLIC: Document Root (Mapped to crm_app/public)
    ├── assets/                      <-- Compiled Vite CSS & JS
    ├── storage/                     <-- Symlink to crm_app/storage/app/public
    ├── index.php                    <-- Front Controller
    ├── .htaccess                    <-- Security Headers & Rewrite Rules
    └── favicon.ico
```

---

## Phase 1: Pre-Deployment Verification

Before uploading any files, log into your cPanel dashboard and verify the hosting specifications:

### 1.1 PHP Version & Extensions Check
1. In cPanel, open **MultiPHP Manager** or **Select PHP Version**.
2. Ensure the PHP version is set to **PHP 8.3** or higher.
3. Open **PHP Extensions** and confirm the following are enabled:
   - `pdo`, `pdo_mysql`
   - `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`
   - `fileinfo` (required for mime-type detection on uploads)
   - `gd` or `imagick` (required for user avatar image processing)
   - `curl`, `zip`

### 1.2 PHP Limits & Directives
In **MultiPHP INI Editor**, verify the following parameters:
- `memory_limit` = `256M` (or `512M` if available)
- `max_execution_time` = `120`
- `upload_max_filesize` = `20M`
- `post_max_size` = `25M`
- `allow_url_fopen` = `On`

---

## Phase 2: Local Packaging & Asset Compilation

Vite assets MUST be compiled before deploying to cPanel, because shared hosting accounts do not have Node.js or npm.

On your local development machine:

```bash
# 1. Install local dependencies
composer install
npm install

# 2. Compile production frontend assets
npm run build

# 3. Verify asset creation in public/build/
ls -la public/build/

# 4. Prepare production vendor folder (excluding dev packages)
composer install --no-dev --optimize-autoloader

# 5. Create deployment archive (excluding git history, tests, and sensitive files)
zip -r deploy_nsdcrm.zip . \
  -x "*.git*" \
  -x "tests/*" \
  -x "node_modules/*" \
  -x ".env" \
  -x ".env.backup" \
  -x "storage/*.key" \
  -x "storage/logs/*.log"
```

---

## Phase 3: cPanel MySQL Database Setup

1. In cPanel, navigate to **MySQL® Databases**.
2. **Create Database:** Enter `nsdcrm` (Full name: `YOUR_CPANEL_USER_nsdcrm`).
3. **Create Database User:** Enter `crmusr` (Full name: `YOUR_CPANEL_USER_crmusr`).
4. Generate a strong 20+ character password and save it securely.
5. **Assign User to Database:** Select user and database, click **Add**, check **ALL PRIVILEGES**, and click **Make Changes**.

---

## Phase 4: Deploying Files Outside the Public Web Root

### 4.1 Upload the Application Archive
1. In cPanel, open **File Manager**.
2. In your home directory (`/home/YOUR_CPANEL_USER/`), create a new directory named:
   ```
   crm_app
   ```
3. Enter `/home/YOUR_CPANEL_USER/crm_app/`.
4. Click **Upload** and upload `deploy_nsdcrm.zip`.
5. Select `deploy_nsdcrm.zip` and click **Extract**.
6. Delete the `.zip` file after extraction.

---

## Phase 5: Setting the Document Root Safely

Select **Option A** (recommended) or **Option B** (fallback for locked primary domains):

### Option A: Subdomain or Addon Domain (Recommended)
1. In cPanel, navigate to **Domains** (or **Subdomains** / **Addon Domains**).
2. Edit the document root for your domain (e.g. `crm.nsdearthmovers.com`) and point it directly to:
   ```
   /home/YOUR_CPANEL_USER/crm_app/public
   ```
3. Save changes. Your web server now directly serves from `public/`.

### Option B: Primary Domain Fallback (If Document Root is Locked to `public_html`)
If your hosting plan prevents changing the document root for your primary domain:
1. In cPanel **File Manager**, enter `/home/YOUR_CPANEL_USER/public_html/`.
2. Move or delete any default placeholder files (e.g. `default.html`).
3. Copy **ONLY** the contents of `/home/YOUR_CPANEL_USER/crm_app/public/` into `/home/YOUR_CPANEL_USER/public_html/`.  
   *(This includes `build/`, `favicon.ico`, `robots.txt`, `.htaccess`, and `index.php`).*
4. Edit `/home/YOUR_CPANEL_USER/public_html/index.php` using the cPanel File Editor:
   ```php
   // Change lines 14 and 18 from:
   require __DIR__.'/../vendor/autoload.php';
   $app = require_once __DIR__.'/../bootstrap/app.php';

   // To point to your crm_app directory outside public_html:
   require __DIR__.'/../crm_app/vendor/autoload.php';
   $app = require_once __DIR__.'/../crm_app/bootstrap/app.php';
   ```
   > **SECURITY CONFIRMATION:** Your `.env`, `vendor/`, `storage/`, and application source code remain strictly stored in `/home/YOUR_CPANEL_USER/crm_app/` and are completely inaccessible from the browser.

---

## Phase 6: Environment Configuration (`.env`)

1. In `/home/YOUR_CPANEL_USER/crm_app/`, copy `.env.example` to `.env`.
2. Open `.env` in the cPanel File Editor.
3. Configure the following production variables:

```env
# -------------------------------------------------------------
# APPLICATION SETTINGS
# -------------------------------------------------------------
APP_NAME="NSD Earth Movers & Construction Ltd"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://crm.nsdearthmovers.com

# -------------------------------------------------------------
# SECURITY & SESSIONS
# -------------------------------------------------------------
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
TRUSTED_PROXIES=*

# -------------------------------------------------------------
# DATABASE CONFIGURATION
# -------------------------------------------------------------
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=YOUR_CPANEL_USER_nsdcrm
DB_USERNAME=YOUR_CPANEL_USER_crmusr
DB_PASSWORD="your-secure-mysql-password"
DB_FOREIGN_KEYS=true

# -------------------------------------------------------------
# QUEUE & CACHE
# -------------------------------------------------------------
QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local

# -------------------------------------------------------------
# MAIL CONFIGURATION (cPanel SMTP)
# -------------------------------------------------------------
MAIL_MAILER=smtp
MAIL_HOST=mail.nsdearthmovers.com
MAIL_PORT=465
MAIL_USERNAME=notifications@nsdearthmovers.com
MAIL_PASSWORD="your-email-password"
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS="notifications@nsdearthmovers.com"
MAIL_FROM_NAME="NSD Earth Movers CRM"

# -------------------------------------------------------------
# SCANNER COMPATIBILITY (Shared Hosting)
# -------------------------------------------------------------
# In shared hosting without a local ClamAV daemon:
SCANNER_ENABLED=false
SCANNER_FAIL_CLOSED=false

# -------------------------------------------------------------
# BACKUP & ENCRYPTION KEY
# -------------------------------------------------------------
BACKUP_ENCRYPT=true
BACKUP_ENCRYPTION_CIPHER=AES-256-GCM
```

4. **Generate Application Key:**  
   In cPanel Terminal (or via SSH):
   ```bash
   cd /home/YOUR_CPANEL_USER/crm_app
   php artisan key:generate --force
   ```
   *(If Terminal is unavailable, run `php artisan key:generate --show` locally and paste the key into your production `.env`).*

---

## Phase 7: Storage & File Permissions

### 7.1 Set Directory Permissions
In cPanel File Manager, ensure the following folders have permissions set to `0755` (or `0775`):
- `/home/YOUR_CPANEL_USER/crm_app/storage` (and all subfolders)
- `/home/YOUR_CPANEL_USER/crm_app/bootstrap/cache`

### 7.2 Create Public Storage Symlink
If Terminal is available:
```bash
cd /home/YOUR_CPANEL_USER/crm_app
php artisan storage:link
```

> **Symlink Fallback (If SSH/Terminal is Disabled):**  
> Create a temporary script `/home/YOUR_CPANEL_USER/public_html/symlink_setup.php`:
> ```php
> <?php
> $target = '/home/YOUR_CPANEL_USER/crm_app/storage/app/public';
> $shortcut = '/home/YOUR_CPANEL_USER/crm_app/public/storage';
> symlink($target, $shortcut);
> echo "Symlink created successfully!";
> ```
> Visit `https://yourdomain.com/symlink_setup.php` once in your browser, verify success, and **immediately delete** the file.

---

## Phase 8: Running Database Migrations

In cPanel Terminal (or via SSH):
```bash
cd /home/YOUR_CPANEL_USER/crm_app

# Run all 20 migrations safely against MySQL:
php artisan migrate --force

# Verify all migrations ran cleanly:
php artisan migrate:status
```

---

## Phase 9: Creating Initial Owner Account

To establish the initial primary administrative account without seeding demo data:
```bash
cd /home/YOUR_CPANEL_USER/crm_app

php artisan tinker --execute="
\$company = App\Models\Company::firstOrCreate(
    ['code' => 'NSD'],
    [
        'name' => 'NSD Earth Movers & Construction Ltd',
        'legal_name' => 'NSD Earth Movers & Construction Limited',
        'slug' => 'nsd-earth-movers',
        'status' => 'active',
    ]
);

\$role = App\Models\Role::firstOrCreate(
    ['company_id' => \$company->id, 'slug' => 'owner'],
    [
        'name' => 'Owner',
        'permissions' => ['*'],
        'is_system' => true,
    ]
);

\$user = App\Models\User::firstOrCreate(
    ['email' => 'admin@nsdearthmovers.com'],
    [
        'name' => 'Director Admin',
        'password' => Illuminate\Support\Facades\Hash::make('ChangeThisStrongPassword123!'),
    ]
);

\$user->companies()->syncWithoutDetaching([
    \$company->id => [
        'role_id' => \$role->id,
        'status' => 'active',
        'is_primary' => true,
    ]
]);

echo 'Primary owner account configured successfully!\n';
"
```

---

## Phase 10: Performance Optimization & Caching

Cache Laravel's routes, configuration, and Blade templates:
```bash
cd /home/YOUR_CPANEL_USER/crm_app

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> **Maintenance Rule:** If you ever edit `.env` or update routes, you must run:
> ```bash
> php artisan optimize:clear
> php artisan config:cache
> php artisan route:cache
> ```

---

## Phase 11: Configuring cPanel Cron Jobs

Navigate to cPanel **Cron Jobs** and configure the two scheduled entries:

### 11.1 Task Scheduler (Every Minute)
- **Schedule:** `* * * * *`
- **Command:**
  ```cron
  /usr/local/bin/php /home/YOUR_CPANEL_USER/crm_app/artisan schedule:run >> /dev/null 2>&1
  ```

### 11.2 Queue Processing (Every 5 Minutes)
- **Schedule:** `*/5 * * * *`
- **Command:**
  ```cron
  /usr/local/bin/php /home/YOUR_CPANEL_USER/crm_app/artisan queue:work --stop-when-empty --tries=3 --max-time=240 >> /dev/null 2>&1
  ```

---

## Phase 12: SSL / HTTPS Verification

1. In cPanel, navigate to **SSL/TLS Status**.
2. Run **AutoSSL** (or install Let's Encrypt SSL certificate).
3. Confirm that all requests redirect automatically from `http://` to `https://`.
4. The CRM's `AppServiceProvider` automatically enforces HTTPS scheme on production asset links and form actions.

---

## Phase 13: Post-Deployment Smoke Test Checklist

Conduct manual verification across all modules:

- [ ] **Authentication:** Login with the owner account; verify avatar and profile dropdown render in the header.
- [ ] **Projects:** Create a new project (e.g. "JSW Pelleting Unit"), assign a manager, verify financial metrics.
- [ ] **Workforce & Muster Roll:** Access `/attendance/muster-roll`, enter daily attendance, submit via AJAX, verify no-refresh submission and toast notification.
- [ ] **Inventory & Stock Ledger:** Create warehouse, create material, issue stock to site; verify running balance updates accurately in the stock ledger.
- [ ] **Procurement:** Create a purchase request, approve it as owner; verify status updates to approved without reloading.
- [ ] **Profile & Security:** Access `/profile`, change theme preference (Dark/Light), upload an avatar image, and update password.
- [ ] **Document Security:** Upload a confidential project document; log out or log in as an unauthorized user and verify that direct access is blocked (HTTP 403).
- [ ] **Log Inspection:** Check `/home/YOUR_CPANEL_USER/crm_app/storage/logs/laravel.log` and confirm zero critical exceptions.

---

## Phase 14: Rollback & Emergency Recovery

If a deployment blocker occurs:

1. **Activate Maintenance Mode:**
   ```bash
   php artisan down
   ```
2. **Database Rollback:**
   Restore the pre-deployment database dump using phpMyAdmin or terminal:
   ```bash
   mysql -u YOUR_CPANEL_USER_crmusr -p YOUR_CPANEL_USER_nsdcrm < pre_deployment_backup.sql
   ```
3. **Clear Caches:**
   ```bash
   php artisan optimize:clear
   ```
4. **Deactivate Maintenance Mode:**
   ```bash
   php artisan up
   ```
