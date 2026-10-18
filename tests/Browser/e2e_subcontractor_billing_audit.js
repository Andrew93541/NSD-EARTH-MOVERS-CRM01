import puppeteer from 'puppeteer-core';
import path from 'path';

const CHROME_PATH = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const BASE_URL = 'http://127.0.0.1:8000';
const ARTIFACTS_DIR = '/Users/andrew/.gemini/antigravity-ide/brain/03bc8fbc-486c-4ca7-9779-989edf3e49de';

async function runSubcontractorBrowserAudit() {
    console.log('--- STARTING BUILDFLOW SUBCONTRACTOR & MEASUREMENT BOOK BROWSER AUDIT ---');

    const browser = await puppeteer.launch({
        executablePath: CHROME_PATH,
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
    });

    const page = await browser.newPage();
    await page.setViewport({ width: 1440, height: 900 });

    try {
        // Step 1: Login as Admin
        console.log('Logging in as admin@buildflow.local...');
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'load' });
        await page.$eval('#email', el => el.value = 'admin@buildflow.local');
        await page.$eval('#password', el => el.value = 'Password123!');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'load', timeout: 15000 }),
            page.click('button[type="submit"]')
        ]);
        console.log('Logged in successfully. Current URL:', page.url());

        // Step 2: Navigate to Subcontractor Master Register
        console.log('Navigating to Subcontractor Register (/subcontractors)...');
        await page.goto(`${BASE_URL}/subcontractors`, { waitUntil: 'load' });
        await new Promise(r => setTimeout(r, 600));
        await page.screenshot({ path: path.join(ARTIFACTS_DIR, 'subcon_05_subcontractors_register.png'), fullPage: true });
        console.log('✓ Captured subcon_05_subcontractors_register.png');

        // Step 3: Navigate to Work Order Detail (/work-orders/1)
        console.log('Navigating to Work Order Detail (/work-orders/1)...');
        await page.goto(`${BASE_URL}/work-orders/1`, { waitUntil: 'load' });
        await new Promise(r => setTimeout(r, 600));
        await page.screenshot({ path: path.join(ARTIFACTS_DIR, 'subcon_01_work_order.png'), fullPage: true });
        console.log('✓ Captured subcon_01_work_order.png');

        // Step 4: Navigate to Measurement Book Register (/measurement-books)
        console.log('Navigating to Measurement Book Register (/measurement-books)...');
        await page.goto(`${BASE_URL}/measurement-books`, { waitUntil: 'load' });
        await new Promise(r => setTimeout(r, 600));
        await page.screenshot({ path: path.join(ARTIFACTS_DIR, 'subcon_02_measurement_book.png'), fullPage: true });
        console.log('✓ Captured subcon_02_measurement_book.png');

        // Step 5: Navigate to Measurement Book Entry Detail (/measurement-books/1)
        console.log('Navigating to Measurement Book Entry Detail (/measurement-books/1)...');
        await page.goto(`${BASE_URL}/measurement-books/1`, { waitUntil: 'load' });
        await new Promise(r => setTimeout(r, 600));
        await page.screenshot({ path: path.join(ARTIFACTS_DIR, 'subcon_03_mb_detail.png'), fullPage: true });
        console.log('✓ Captured subcon_03_mb_detail.png');

        // Step 6: Navigate to Subcontractor RA Bill Detail with 3-Way Reconciliation (/subcontractor-bills/1)
        console.log('Navigating to Subcontractor RA Bill Detail (/subcontractor-bills/1)...');
        await page.goto(`${BASE_URL}/subcontractor-bills/1`, { waitUntil: 'load' });
        await new Promise(r => setTimeout(r, 600));
        await page.screenshot({ path: path.join(ARTIFACTS_DIR, 'subcon_04_bill_reconciliation.png'), fullPage: true });
        console.log('✓ Captured subcon_04_bill_reconciliation.png');

        // Step 7: Navigate to Accounting Trial Balance (/accounting/trial-balance)
        console.log('Navigating to Accounting Trial Balance (/accounting/trial-balance)...');
        await page.goto(`${BASE_URL}/accounting/trial-balance`, { waitUntil: 'load' });
        await new Promise(r => setTimeout(r, 600));
        await page.screenshot({ path: path.join(ARTIFACTS_DIR, 'subcon_06_trial_balance.png'), fullPage: true });
        console.log('✓ Captured subcon_06_trial_balance.png');

        console.log('--- ALL BROWSER AUDIT SCREENSHOTS CAPTURED SUCCESSFULLY ---');
    } catch (err) {
        console.error('Browser audit failed:', err);
    } finally {
        await browser.close();
    }
}

runSubcontractorBrowserAudit();
