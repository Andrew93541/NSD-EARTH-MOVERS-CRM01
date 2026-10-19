# BuildFlow CRM/ERP — XAMPP Local Demo Runbook
**Organization:** NSD Earth Movers & Construction Ltd  
**Platform:** BuildFlow Construction CRM / ERP Platform  
**Target Environment:** Local Windows XAMPP Demo  
**Audience:** Internal Demo Team & Stakeholders  

---

## 1. Quick Start Summary (For Today's Demo)

The application is **fully configured and ready** on your machine. All database tables, seed data, and frontend assets are already generated.

To run the demo in 30 seconds:

1. **Start XAMPP MySQL:**
   - Open the **XAMPP Control Panel**.
   - Click **Start** next to **MySQL** (port `3306`).
2. **Start the Application Server (PowerShell):**
   ```powershell
   cd C:\xampp\htdocs\NSD-EARTH-MOVERS-CRM01
   & "C:\Users\Andrew\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.5_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" artisan serve --port=8000
   ```
3. **Open in Browser:**
   - URL: [http://127.0.0.1:8000/login](http://127.0.0.1:8000/login)
   - Email: `admin@buildflow.local`
   - Password: `Password123!`

---

## 2. Technical Environment & Architecture

### Why PHP 8.5 CLI + XAMPP MySQL?
* **XAMPP's Default Apache/PHP:** XAMPP includes PHP 8.0.30 by default in its Apache module. Modern Laravel framework dependencies (`symfony/http-foundation` v8.x in `composer.lock`) use **PHP 8.4+ property hooks syntax**, which causes a syntax error if run on PHP 8.0 or 8.3.
* **The Solution:** We installed **PHP 8.5.11** via Windows Winget. It connects seamlessly to **XAMPP's MySQL service** (port `3306`) and runs `php artisan serve` natively with 100% stability, zero socket errors, and instant hot-reloading.
* **Frontend Assets:** Compiled with Vite v8.3.4 into `public/build/`. No background `npm run dev` process is needed during the meeting.

---

## 3. Verified Demo Accounts

All accounts use the password: `Password123!`

| Role | Name | Email | Primary Screens to Demo |
|:---|:---|:---|:---|
| **Company Owner (Admin)** | Rajesh Varma | `admin@buildflow.local` | Executive Dashboard, Company Switcher, Trial Balance, High-value PO approvals |
| **Project Manager** | Amit Sharma | `pm@buildflow.local` | Projects (`/projects`), Site logs (`/sites`), Subcontractor RA Bills (`/subcontractor-bills`) |
| **Accountant** | Sneha Roy | `accounts@buildflow.local` | Trial Balance (`/accounting/trial-balance`), Journal Entries (`/accounting/journals`), Billing (`/billing`) |
| **Site Supervisor** | Vikram Malhotra | `site@buildflow.local` | Muster Roll (`/attendance/muster-roll`), Measurement Book (`/measurement-books`) |
| **Procurement Officer** | Anand Joshi | `procurement@buildflow.local` | Purchase Requests (`/purchase-requests`), Vendor POs (`/procurement`) |
| **Storekeeper** | Gopal Das | `storekeeper@buildflow.local` | Material Stock Ledger (`/inventory/ledger`), Yard Inventory (`/inventory`) |

---

## 4. Step-by-Step Execution Guide (Windows PowerShell)

### Step 1: Verify XAMPP MySQL is Running
In Windows PowerShell:
```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "SHOW DATABASES;"
```
*Expected:* You should see `buildflow_demo` and `buildflow_crm` listed.

### Step 2: (Optional) Set PowerShell Alias for Easy Typing
To avoid typing the long path to PHP 8.5 each time in your current PowerShell session:
```powershell
Set-Alias php "C:\Users\Andrew\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.5_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
```
Verify:
```powershell
php -v
# Output: PHP 8.5.11 (cli) ...
```

### Step 3: Start the Application Server
```powershell
cd C:\xampp\htdocs\NSD-EARTH-MOVERS-CRM01
php artisan serve --host=127.0.0.1 --port=8000
```
Leave this PowerShell terminal window open during your demo.

---

## 5. Recommended Demo Walkthrough (Meeting Flow)

### 1. Login & Workspace Identity (`/login`)
* Navigate to [http://127.0.0.1:8000/login](http://127.0.0.1:8000/login).
* Log in as `admin@buildflow.local` / `Password123!`.
* Point out the top header: active company is **NSD Earth Movers & Construction Ltd**.
* Show the tenant switcher in the top navigation (`NSD Earth Movers` vs `Apex Infra Developers`).

### 2. Multi-Site Project Operations (`/projects`)
* Click **Projects** in the navigation bar.
* Show the 4 seeded construction projects:
  - *Riverside Residency Phase 1* (Nagpur)
  - *Nagpur Ring Road Bypass Package 3*
  - *Bengaluru Metro Elevated Viaduct Pier Works*
  - *Coastal Commercial Hub Excavation*
* Click into *Riverside Residency* to show site logs, worker headcounts, and milestones.

### 3. Subcontractor Work Orders & Measurement Book (`/work-orders`)
* Go to [http://127.0.0.1:8000/work-orders](http://127.0.0.1:8000/work-orders).
* View Work Order `WO-2026-001` with **Apex Heavy Earthmovers Pvt Ltd**.
* Navigate to **Measurement Book** (`/measurement-books`): show the digital L×B×D volumetric formula and truck trip calculations.
* Navigate to **Subcontractor Bills** (`/subcontractor-bills`): show RA Bill 1 with ₹50,000 mobilization advance recovery and 2% Section 194C TDS deduction.

### 4. Workforce & Daily Muster Roll (`/attendance/muster-roll`)
* Open the Muster Roll.
* Show active equipment operators, drivers, and site laborers with check-in, check-out, and approved overtime records.

### 5. Procurement & Stock Ledger (`/inventory/ledger`)
* Open the Stock Ledger.
* Show live material stock for *53-Grade OPC Cement*, *20mm Aggregates*, and *High-Yield Fe500 TMT Steel*.
* Show the moving-average rate calculation that updates automatically on delivery.

### 6. Double-Entry Accounting & Financial Balance (`/accounting/trial-balance`)
* Open the **Trial Balance** at [http://127.0.0.1:8000/accounting/trial-balance](http://127.0.0.1:8000/accounting/trial-balance).
* **Key highlight for clients & management:**
  - Debits and Credits are in **100% mathematically balanced parity**:
    **Total Debits: ₹38,73,344.00 == Total Credits: ₹38,73,344.00**.
  - Shows bank accounts, GST input tax credits, contractor payables, mobilization advances, and contract revenue.

---

## 6. How to Reset the Demo Data (If Needed)

If you perform test transactions during rehearsal and want to reset back to a clean state before the meeting:

```powershell
cd C:\xampp\htdocs\NSD-EARTH-MOVERS-CRM01
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "DROP DATABASE IF EXISTS buildflow_demo; CREATE DATABASE buildflow_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
& "C:\Users\Andrew\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.5_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" artisan migrate --force
& "C:\Users\Andrew\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.5_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" artisan db:seed --force
```
*This completely re-seeds the demo database in ~15 seconds without touching `buildflow_crm` or any other database.*

---

## 7. Common Errors & Quick Fixes

| Symptom | Cause | One-Line Fix |
|:---|:---|:---|
| `Parse error: syntax error, unexpected token "{"` | Executed with old PHP 8.0/8.3 instead of PHP 8.5 | Use the full PHP 8.5 path from Section 1 or set the alias `Set-Alias php ...` |
| `SQLSTATE[HY000] [2002] Connection refused` | XAMPP MySQL daemon is stopped | Open XAMPP Control Panel and click **Start** next to MySQL |
| `Address already in use: 8000` | Port 8000 is occupied | Run `php artisan serve --port=8080` and open [http://127.0.0.1:8080](http://127.0.0.1:8080) |
| Assets not styled / CSS missing | Browser cached old styles | Hard refresh with `Ctrl + F5` in your browser |

---

## 8. Pre-Meeting 5-Minute Checklist

- [ ] XAMPP MySQL shows a green indicator in the XAMPP Control Panel.
- [ ] Terminal running `artisan serve` is active and says `Server running on [http://127.0.0.1:8000]`.
- [ ] Browser is open to [http://127.0.0.1:8000/login](http://127.0.0.1:8000/login).
- [ ] Test login with `admin@buildflow.local` / `Password123!` loads the dashboard cleanly.
- [ ] Bookmarks prepared for:
  - Dashboard: `http://127.0.0.1:8000/dashboard`
  - Projects: `http://127.0.0.1:8000/projects`
  - Subcontractors: `http://127.0.0.1:8000/work-orders`
  - Attendance: `http://127.0.0.1:8000/attendance/muster-roll`
  - Trial Balance: `http://127.0.0.1:8000/accounting/trial-balance`
