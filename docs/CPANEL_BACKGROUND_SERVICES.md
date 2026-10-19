# NSD Earth Movers CRM — cPanel Background Services, Queues, Cron & Real-Time Guide

**Project:** NSD Earth Movers & Construction Ltd. — CRM / ERP  
**Target Environment:** cPanel Shared / Reseller / Cloud Hosting  
**Document Version:** 1.0 (Production Release)

---

## 1. Overview of Asynchronous & Scheduled Architecture

Enterprise ERP operations require periodic and asynchronous processing to maintain performance without degrading interactive user requests.

In NSD Earth Movers CRM, background services manage:
1. **Operational Reminders:** Low-stock threshold alerts, overdue purchase requests, supplier invoice payment deadlines.
2. **Notification Dispatching:** Delivering database alerts to managers and site staff.
3. **Queue Processing:** Background delivery of emails, audit logging, and heavy data exports.
4. **Real-Time Event Broadcasting:** Live updates across projects, inventory, attendance, and purchasing.

On dedicated servers or VPS, these operate via persistent daemons managed by `systemd` or `Supervisor`. On shared cPanel hosting, background processing must be tailored to the hosting provider's execution constraints.

---

## 2. Laravel Task Scheduler (`schedule:run`)

### 2.1 Audited Scheduled Tasks in NSDCRM
The CRM registers scheduled jobs in [routes/console.php](file:///Users/andrew/Developer/NSD%20EARTH%20MOVERS%20CRM/routes/console.php):
- **`buildflow:send-reminders` (`everyFourHours()`):**
  Scans the database for:
  - Materials where `current_balance <= min_stock_level` (dispatches critical low-stock alert).
  - Purchase requests in `pending` status older than 3 days (dispatches overdue PR reminder to approvers).
  - Supplier invoices due within 3 days or overdue (dispatches payment due alerts to accountants and owner).
- **Session and Cache Pruning:**
  - Automatically cleans expired session records from the `sessions` table.
  - Purges completed or expired temporary exports.

### 2.2 Configuring the cPanel Cron Job
To drive all Laravel scheduled tasks, cPanel requires a single cron entry that triggers Laravel's scheduler once every minute.

1. Log into your cPanel account.
2. Under the **Advanced** section, click **Cron Jobs**.
3. Under **Add New Cron Job**:
   - Common Settings: Choose **Once Per Minute (`* * * * *`)**.
   - Minute: `*`
   - Hour: `*`
   - Day: `*`
   - Month: `*`
   - Weekday: `*`
4. In the **Command** field, enter the following command:

```cron
/usr/local/bin/php /home/YOUR_CPANEL_USERNAME/crm_app/artisan schedule:run >> /dev/null 2>&1
```

> **Determining Your Exact PHP and Home Paths:**
> - Replace `YOUR_CPANEL_USERNAME` with your actual cPanel username (visible in the top right corner of cPanel).
> - Replace `/usr/local/bin/php` with your specific PHP 8.3 binary path if your host uses a multi-PHP wrapper. Common examples:
>   - cPanel EasyApache 4: `/usr/local/bin/ea-php83`
>   - CloudLinux Alt-PHP: `/opt/alt/php83/usr/bin/php`
>   - Standard default: `/usr/local/bin/php`
> - You can confirm the exact path via cPanel Terminal by running `which php` or `which ea-php83`.

---

## 3. Queue Processing on Shared cPanel

### 3.1 The Hosting Constraint
Shared cPanel hosting environments use process monitors (such as CloudLinux LVE or CageFS) that enforce CPU time and memory caps. If you execute a continuous daemon like `php artisan queue:work`, the server will forcibly kill the process after a few minutes, potentially terminating an active transaction midway.

### 3.2 Safe Shared Hosting Workaround: The Cron-Driven Worker
Instead of a non-terminating daemon, configure a cron job to process pending queue jobs and exit cleanly when the queue is empty:

```cron
*/5 * * * * /usr/local/bin/php /home/YOUR_CPANEL_USERNAME/crm_app/artisan queue:work --stop-when-empty --tries=3 --max-time=240 >> /dev/null 2>&1
```

**How This Works:**
1. The cron executes every 5 minutes.
2. It processes all queued jobs in the `jobs` table sequentially.
3. `--stop-when-empty`: Once all pending jobs are processed, the process terminates gracefully.
4. `--max-time=240`: If there is a massive backlog, the worker automatically exits after 4 minutes (before the next 5-minute cron starts), preventing overlapping workers and server memory saturation.
5. `--tries=3`: If a job fails due to an external network timeout (e.g. SMTP server hiccup), it will retry up to 3 times before moving to `failed_jobs`.

### 3.3 Database Queue Configuration (`.env`)
In your production `.env`, set:
```env
QUEUE_CONNECTION=database
```
This uses the pre-migrated `jobs`, `job_batches`, and `failed_jobs` tables.

### 3.4 Job Idempotency & Safety
- **Financial & Stock Integrity:** Stock issue creation, goods receipts, and payroll disbursement run directly inside atomic database transactions (`DB::transaction`) synchronously to guarantee instantaneous ledger consistency.
- **Queued Operations:** Only non-blocking side effects (such as email delivery, PDF compilation, and reminder dispatching) are dispatched to the queue. Therefore, a delayed queue run will **never** block user transactions or produce duplicate payments.

---

## 4. Email (SMTP) Configuration

The application delivers notifications, password reset links, purchase request alerts, and payslip notifications via Laravel's Mail service.

### 4.1 Configuring cPanel Webmail / Exim
If using an email account created within the same cPanel account:
1. In cPanel, navigate to **Email Accounts**.
2. Click **Create** and set up an address (e.g. `notifications@nsdearthmovers.com`).
3. Click **Connect Devices** to view the exact Mail Client Configuration (Server hostname, IMAP/POP3, and SMTP ports).
4. Configure your `.env` accordingly:

```env
# -------------------------------------------------------------
# MAIL CONFIGURATION (cPanel SMTP)
# -------------------------------------------------------------
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=mail.yourdomain.com          # Or server hostname, e.g. server123.hostingprovider.com
MAIL_PORT=465
MAIL_USERNAME=notifications@yourdomain.com
MAIL_PASSWORD="your-strong-email-password"
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS="notifications@yourdomain.com"
MAIL_FROM_NAME="NSD Earth Movers & Construction Ltd"
```

> **Alternative Port (TLS):** If port 465 is blocked by network filters, set:
> ```env
> MAIL_PORT=587
> MAIL_ENCRYPTION=tls
> ```

### 4.2 External Transactional Mailers (Recommended for High Volume)
If delivering hundreds of automated notices daily, third-party transactional mail services (SendGrid, Mailgun, Amazon SES, or Postmark) offer superior deliverability and SPF/DKIM validation:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD="your-sendgrid-api-key"
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="notifications@nsdearthmovers.com"
MAIL_FROM_NAME="NSD Earth Movers CRM"
```

---

## 5. WebSockets: Laravel Reverb vs. Polling Fallback

### 5.1 The Reverb Requirement vs. Shared Hosting Reality
Laravel Reverb requires:
- A continuous background CLI process (`php artisan reverb:start`).
- An open, listening TCP port (default `8080`).
- A reverse-proxy configuration (Nginx or Apache `mod_proxy_wstunnel`) to route `wss://yourdomain.com/app` to port 8080.

On standard shared cPanel hosting:
- Inbound connections on arbitrary non-standard ports (8080) are blocked by the host firewall.
- Long-running CLI listeners are terminated by CageFS process limits.
- Apache `httpd.conf` cannot be modified by individual shared users to enable WebSocket reverse-proxy tunneling.

### 5.2 The Built-In Fallback Architecture
The NSD Earth Movers CRM was deliberately engineered to handle this gracefully:

1. **No-Refresh AJAX UI (Independent of WebSockets):**
   Interactive workflows (such as submitting daily muster roll attendance, approving or rejecting material requests, toggling dark mode, and updating user profile preferences) use **Alpine.js and client-side `fetch()`**. These provide a smooth, modern, single-page application feel without requiring an active WebSocket connection.
2. **Defensive Broadcasting:**
   In [MaterialIssueController.php](file:///Users/andrew/Developer/NSD%20EARTH%20MOVERS%20CRM/app/Http/Controllers/MaterialIssueController.php), [PurchaseRequestController.php](file:///Users/andrew/Developer/NSD%20EARTH%20MOVERS%20CRM/app/Http/Controllers/PurchaseRequestController.php), [AttendanceController.php](file:///Users/andrew/Developer/NSD%20EARTH%20MOVERS%20CRM/app/Http/Controllers/AttendanceController.php), and [PayrollController.php](file:///Users/andrew/Developer/NSD%20EARTH%20MOVERS%20CRM/app/Http/Controllers/PayrollController.php), all `broadcast(...)` calls are wrapped in `try-catch (\Throwable $e)` blocks. If the WebSocket server is unreachable, the business transaction and database commit succeed 100% without interruption.
3. **Periodic Polling Fallback:**
   The notification dropdown in the application header includes a polling mechanism that queries:
   - `GET /api/notifications/unread-count`
   - `GET /notifications/feed`
   This keeps unread badge counters, project notifications, and approval alerts updated automatically without requiring a WebSocket daemon.

### 5.3 Enabling Real-Time WebSockets via External Service
If real-time instant push notifications are strictly required without upgrading to a VPS:
1. Register for a free or starter account on [Pusher](https://pusher.com) or [Soketi](https://soketi.app).
2. Configure `.env`:
   ```env
   BROADCAST_CONNECTION=pusher
   PUSHER_APP_ID="your-pusher-app-id"
   PUSHER_APP_KEY="your-pusher-app-key"
   PUSHER_APP_SECRET="your-pusher-app-secret"
   PUSHER_APP_CLUSTER="ap2"

   VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
   VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
   ```
3. Rebuild frontend assets (`npm run build`). The application's [echo.js](file:///Users/andrew/Developer/NSD%20EARTH%20MOVERS%20CRM/resources/js/echo.js) supports standard Pusher protocol natively.
