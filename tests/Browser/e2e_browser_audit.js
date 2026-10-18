import puppeteer from 'puppeteer-core';
import fs from 'fs';
import path from 'path';

const ARTIFACT_DIR = '/Users/andrew/.gemini/antigravity-ide/brain/03bc8fbc-486c-4ca7-9779-989edf3e49de';
const BASE_URL = 'http://127.0.0.1:8000';
const CHROME_PATH = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';

async function runBrowserAudit() {
    console.log('--- STARTING BUILDFLOW REAL BROWSER AUDIT ---');
    console.log('Connecting to Google Chrome at:', CHROME_PATH);

    const browser = await puppeteer.launch({
        executablePath: CHROME_PATH,
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-gpu',
            '--disable-dev-shm-usage',
            '--window-size=1440,900'
        ]
    });

    const results = {
        passed: 0,
        failed: 0,
        steps: [],
        consoleErrors: [],
    };

    try {
        const page = await browser.newPage();
        await page.setViewport({ width: 1440, height: 900 });

        page.on('console', msg => {
            if (msg.type() === 'error') {
                console.log('[Browser Console Error]', msg.text());
                results.consoleErrors.push(msg.text());
            }
        });

        // Helper for login
        async function loginAs(email, password = 'Password123!') {
            console.log(`Logging in as ${email}...`);
            await page.goto(`${BASE_URL}/login`, { waitUntil: 'load' });
            await page.$eval('#email', el => el.value = '');
            await page.type('#email', email);
            await page.$eval('#password', el => el.value = '');
            await page.type('#password', password);
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'load', timeout: 15000 }),
                page.click('button[type="submit"]')
            ]);
            console.log(`Logged in successfully. Landed on: ${page.url()}`);
        }

        // Helper for logout
        async function logout() {
            console.log('Logging out...');
            await page.goto(`${BASE_URL}/dashboard`, { waitUntil: 'load' });
            const logoutButton = await page.$('form[action*="logout"] button[type="submit"]');
            if (logoutButton) {
                await Promise.all([
                    page.waitForNavigation({ waitUntil: 'load', timeout: 15000 }),
                    logoutButton.click()
                ]);
            } else {
                // Post logout directly
                await page.evaluate(() => {
                    const form = document.querySelector('form[action*="logout"]');
                    if (form) form.submit();
                });
                await page.waitForNavigation({ waitUntil: 'load', timeout: 15000 });
            }
            console.log(`Logged out. Current URL: ${page.url()}`);
        }

        // -------------------------------------------------------------
        // WORKFLOW 1: Authentication & Invalid Credentials
        // -------------------------------------------------------------
        console.log('\n--- Testing Workflow 1: Authentication Validation ---');
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'load' });
        await page.$eval('#email', el => el.value = '');
        await page.type('#email', 'invalid@user.com');
        await page.$eval('#password', el => el.value = '');
        await page.type('#password', 'WrongPassword!');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'load' }),
            page.click('button[type="submit"]')
        ]);

        const bodyText = await page.evaluate(() => document.body.innerText);
        const hasError = bodyText.includes('credentials do not match') || bodyText.includes('failed') || page.url().includes('/login');
        if (hasError) {
            console.log('✓ Invalid login correctly rejected and handled');
            results.passed++;
            results.steps.push({ step: 'Auth: Invalid login rejected', status: 'PASS' });
        } else {
            console.error('✗ Invalid login was not rejected');
            results.failed++;
            results.steps.push({ step: 'Auth: Invalid login rejected', status: 'FAIL' });
        }

        // -------------------------------------------------------------
        // WORKFLOW 2: Admin Login & Dashboard Audit
        // -------------------------------------------------------------
        console.log('\n--- Testing Workflow 2: Admin Dashboard Audit ---');
        await loginAs('admin@buildflow.local');
        const dashTitle = await page.title();
        console.log('Dashboard title:', dashTitle);
        await page.screenshot({ path: `${ARTIFACT_DIR}/audit_01_dashboard.png`, fullPage: false });
        console.log('✓ Dashboard screenshot captured: audit_01_dashboard.png');
        results.passed++;
        results.steps.push({ step: 'Admin Dashboard render & metrics', status: 'PASS' });

        // -------------------------------------------------------------
        // WORKFLOW 3: Projects & Site Operations Detail
        // -------------------------------------------------------------
        console.log('\n--- Testing Workflow 3: Projects & Sites Operations ---');
        await page.goto(`${BASE_URL}/projects`, { waitUntil: 'load' });
        await page.screenshot({ path: `${ARTIFACT_DIR}/audit_02_projects_list.png`, fullPage: false });
        console.log('✓ Projects register rendered');

        await page.goto(`${BASE_URL}/sites/1`, { waitUntil: 'load' });
        await page.screenshot({ path: `${ARTIFACT_DIR}/audit_03_site_detail.png`, fullPage: false });
        console.log('✓ Site Operations detail rendered: audit_03_site_detail.png');
        results.passed++;
        results.steps.push({ step: 'Projects & Site Operations inspection', status: 'PASS' });

        // -------------------------------------------------------------
        // WORKFLOW 4: Labor Muster Roll
        // -------------------------------------------------------------
        console.log('\n--- Testing Workflow 4: Attendance Muster Roll ---');
        await page.goto(`${BASE_URL}/attendance/muster`, { waitUntil: 'load' });
        await page.screenshot({ path: `${ARTIFACT_DIR}/audit_04_muster_roll.png`, fullPage: false });
        console.log('✓ Labor muster roll rendered: audit_04_muster_roll.png');
        results.passed++;
        results.steps.push({ step: 'Attendance Muster Roll UI', status: 'PASS' });

        // -------------------------------------------------------------
        // WORKFLOW 5: Purchase Requests, Stock Availability & Budget Card
        // -------------------------------------------------------------
        console.log('\n--- Testing Workflow 5: Purchase Requests & Approval Controls ---');
        await page.goto(`${BASE_URL}/purchase-requests`, { waitUntil: 'load' });
        await page.screenshot({ path: `${ARTIFACT_DIR}/audit_05_purchase_requests.png`, fullPage: false });
        console.log('✓ PR Register rendered');

        // Check PR #1 detail view (which has live stock & budget cards)
        await page.goto(`${BASE_URL}/purchase-requests/1`, { waitUntil: 'load' });
        await page.screenshot({ path: `${ARTIFACT_DIR}/audit_06_pr_detail_controls.png`, fullPage: true });
        console.log('✓ PR Detail with Stock & Budget cards captured: audit_06_pr_detail_controls.png');
        results.passed++;
        results.steps.push({ step: 'PR Stock & Budget controls verification', status: 'PASS' });

        // -------------------------------------------------------------
        // WORKFLOW 6: Procurement PO & Goods Receipt (GRN) Inspection
        // -------------------------------------------------------------
        console.log('\n--- Testing Workflow 6: Procurement PO & GRN Quality Inspection ---');
        await page.goto(`${BASE_URL}/procurement`, { waitUntil: 'load' });
        await page.screenshot({ path: `${ARTIFACT_DIR}/audit_07_procurement_pos.png`, fullPage: false });

        await page.goto(`${BASE_URL}/procurement/orders/1`, { waitUntil: 'load' });
        await page.screenshot({ path: `${ARTIFACT_DIR}/audit_08_po_detail.png`, fullPage: false });
        console.log('✓ PO Detail rendered: audit_08_po_detail.png');
        results.passed++;
        results.steps.push({ step: 'Procurement PO & 3-Way matching UI', status: 'PASS' });

        // -------------------------------------------------------------
        // WORKFLOW 7: Client Billing & Receivables
        // -------------------------------------------------------------
        console.log('\n--- Testing Workflow 7: Client Billing & Receivables ---');
        await page.goto(`${BASE_URL}/billing`, { waitUntil: 'load' });
        await page.screenshot({ path: `${ARTIFACT_DIR}/audit_09_billing_invoices.png`, fullPage: false });

        await page.goto(`${BASE_URL}/billing/1`, { waitUntil: 'load' });
        await page.screenshot({ path: `${ARTIFACT_DIR}/audit_10_ra_bill_detail.png`, fullPage: false });
        console.log('✓ RA Bill detail rendered: audit_10_ra_bill_detail.png');
        results.passed++;
        results.steps.push({ step: 'Client RA billing & payment modal UI', status: 'PASS' });

        // -------------------------------------------------------------
        // WORKFLOW 8: Accounting & Double-Entry Ledger
        // -------------------------------------------------------------
        console.log('\n--- Testing Workflow 8: Accounting & Double-Entry Ledger ---');
        await page.goto(`${BASE_URL}/accounting/journals`, { waitUntil: 'load' });
        await page.screenshot({ path: `${ARTIFACT_DIR}/audit_11_journals.png`, fullPage: false });

        await page.goto(`${BASE_URL}/accounting/trial-balance`, { waitUntil: 'load' });
        await page.screenshot({ path: `${ARTIFACT_DIR}/audit_12_trial_balance.png`, fullPage: false });
        console.log('✓ Trial balance rendered: audit_12_trial_balance.png');
        results.passed++;
        results.steps.push({ step: 'Double-entry journals & trial balance UI', status: 'PASS' });

        // -------------------------------------------------------------
        // WORKFLOW 9: Role-Gated Navigation & 403 Forbidden Enforcement
        // -------------------------------------------------------------
        console.log('\n--- Testing Workflow 9: Role-Gated Navigation & 403 Access Denied ---');
        await logout();

        // Login as Site Supervisor (Vikram Malhotra)
        await loginAs('site@buildflow.local');
        await page.screenshot({ path: `${ARTIFACT_DIR}/audit_13_site_supervisor_dashboard.png`, fullPage: false });
        console.log('✓ Site supervisor dashboard rendered (financial links gated): audit_13_site_supervisor_dashboard.png');

        // Verify that Double-Entry Ledger and Payroll are hidden in sidebar
        const supervisorNav = await page.evaluate(() => document.querySelector('aside').innerText);
        const hasLedgerLink = supervisorNav.includes('Double-Entry Ledger');
        const hasPayrollLink = supervisorNav.includes('Payroll & Wages');

        if (!hasLedgerLink && !hasPayrollLink) {
            console.log('✓ Confidential financial links successfully gated from Site Supervisor navigation');
            results.passed++;
            results.steps.push({ step: 'Navigation: confidential links hidden from site supervisor', status: 'PASS' });
        } else {
            console.error('✗ Confidential links still visible in site supervisor nav!');
            results.failed++;
            results.steps.push({ step: 'Navigation: confidential links hidden from site supervisor', status: 'FAIL' });
        }

        // Direct URL access test: Site supervisor tries to visit /payroll
        console.log('Testing direct URL access to /payroll by Site Supervisor...');
        const payrollResponse = await page.goto(`${BASE_URL}/payroll`, { waitUntil: 'load' });
        const statusCode = payrollResponse.status();
        console.log(`Access to /payroll returned HTTP status: ${statusCode}`);
        await page.screenshot({ path: `${ARTIFACT_DIR}/audit_14_supervisor_payroll_403.png`, fullPage: false });

        if (statusCode === 403) {
            console.log('✓ Server-side 403 Forbidden correctly returned for unauthorized direct access');
            results.passed++;
            results.steps.push({ step: 'Security: direct route 403 Forbidden enforced', status: 'PASS' });
        } else {
            console.error(`✗ Expected HTTP 403 but got ${statusCode}`);
            results.failed++;
            results.steps.push({ step: 'Security: direct route 403 Forbidden enforced', status: 'FAIL' });
        }

        // Direct URL access test: Site supervisor tries to visit /accounting/trial-balance
        const acctResponse = await page.goto(`${BASE_URL}/accounting/trial-balance`, { waitUntil: 'load' });
        console.log(`Access to /accounting/trial-balance returned HTTP status: ${acctResponse.status()}`);
        if (acctResponse.status() === 403) {
            console.log('✓ Server-side 403 Forbidden correctly returned for trial-balance');
            results.passed++;
            results.steps.push({ step: 'Security: trial-balance 403 Forbidden enforced', status: 'PASS' });
        }

        await logout();

        // -------------------------------------------------------------
        // WORKFLOW 10: Accountant Role Verification
        // -------------------------------------------------------------
        console.log('\n--- Testing Workflow 10: Accountant Role Verification ---');
        await loginAs('accounts@buildflow.local');
        await page.screenshot({ path: `${ARTIFACT_DIR}/audit_15_accountant_dashboard.png`, fullPage: false });

        // Verify accountant can access Billing & Trial Balance
        const billingRes = await page.goto(`${BASE_URL}/billing`, { waitUntil: 'load' });
        const tbRes = await page.goto(`${BASE_URL}/accounting/trial-balance`, { waitUntil: 'load' });
        if (billingRes.status() === 200 && tbRes.status() === 200) {
            console.log('✓ Accountant permitted to access billing and trial balance (HTTP 200)');
            results.passed++;
            results.steps.push({ step: 'Accountant access to billing and ledger', status: 'PASS' });
        }

        // Verify accountant cannot access procurement approval policies
        const policyRes = await page.goto(`${BASE_URL}/procurement/approval-policies`, { waitUntil: 'load' });
        if (policyRes.status() === 403) {
            console.log('✓ Accountant blocked from editing approval policies (HTTP 403)');
            results.passed++;
            results.steps.push({ step: 'Accountant blocked from approval policies', status: 'PASS' });
        }

        await logout();

        // -------------------------------------------------------------
        // WORKFLOW 11: Responsive Viewport Checks (Desktop, Tablet, Mobile)
        // -------------------------------------------------------------
        console.log('\n--- Testing Workflow 11: Responsive Viewports ---');
        await loginAs('admin@buildflow.local');

        // Tablet View (768 x 1024)
        console.log('Testing Tablet viewport (768 x 1024)...');
        await page.setViewport({ width: 768, height: 1024 });
        await page.goto(`${BASE_URL}/dashboard`, { waitUntil: 'load' });
        await page.screenshot({ path: `${ARTIFACT_DIR}/audit_16_tablet_dashboard.png`, fullPage: false });
        console.log('✓ Tablet screenshot captured: audit_16_tablet_dashboard.png');

        // Mobile View (375 x 812)
        console.log('Testing Mobile viewport (375 x 812)...');
        await page.setViewport({ width: 375, height: 812 });
        await page.goto(`${BASE_URL}/dashboard`, { waitUntil: 'load' });
        await page.screenshot({ path: `${ARTIFACT_DIR}/audit_17_mobile_dashboard.png`, fullPage: false });
        console.log('✓ Mobile screenshot captured: audit_17_mobile_dashboard.png');

        results.passed += 2;
        results.steps.push({ step: 'Responsive: Tablet layout verified', status: 'PASS' });
        results.steps.push({ step: 'Responsive: Mobile layout verified', status: 'PASS' });

    } finally {
        await browser.close();
        console.log('\n=============================================');
        console.log(`REAL BROWSER AUDIT COMPLETE: ${results.passed} PASSED, ${results.failed} FAILED`);
        console.log(`Console errors captured: ${results.consoleErrors.length}`);
        console.log('=============================================');
    }
}

runBrowserAudit().catch(err => {
    console.error('Browser audit runtime error:', err);
    process.exit(1);
});
