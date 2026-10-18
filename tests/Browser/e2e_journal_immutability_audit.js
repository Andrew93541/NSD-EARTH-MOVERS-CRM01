import puppeteer from 'puppeteer-core';
import path from 'path';

const CHROME_PATH = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const BASE_URL = 'http://127.0.0.1:8000';
const ARTIFACTS_DIR = '/Users/andrew/.gemini/antigravity-ide/brain/03bc8fbc-486c-4ca7-9779-989edf3e49de';

async function runJournalAudit() {
    console.log('--- STARTING BUILDFLOW POSTED JOURNAL AUDIT ---');

    const browser = await puppeteer.launch({
        executablePath: CHROME_PATH,
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
    });

    const page = await browser.newPage();
    await page.setViewport({ width: 1440, height: 900 });

    try {
        // Step 1: Login as Senior Accountant
        console.log('Logging in as accounts@buildflow.local...');
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'load' });
        await page.$eval('#email', el => el.value = 'accounts@buildflow.local');
        await page.$eval('#password', el => el.value = 'Password123!');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'load', timeout: 15000 }),
            page.click('button[type="submit"]')
        ]);
        console.log('Logged in successfully.');

        // Step 2: Navigate to General Journal Vouchers (/accounting/journals)
        console.log('Navigating to General Journals (/accounting/journals)...');
        await page.goto(`${BASE_URL}/accounting/journals`, { waitUntil: 'load' });
        await new Promise(r => setTimeout(r, 600));
        await page.screenshot({ path: path.join(ARTIFACTS_DIR, 'journal_01_vouchers_register.png'), fullPage: true });
        console.log('✓ Captured journal_01_vouchers_register.png');

        // Step 3: Navigate to Trial Balance (/accounting/trial-balance)
        console.log('Navigating to Trial Balance (/accounting/trial-balance)...');
        await page.goto(`${BASE_URL}/accounting/trial-balance`, { waitUntil: 'load' });
        await new Promise(r => setTimeout(r, 600));
        await page.screenshot({ path: path.join(ARTIFACTS_DIR, 'journal_02_trial_balance_reconciled.png'), fullPage: true });
        console.log('✓ Captured journal_02_trial_balance_reconciled.png');

        console.log('--- JOURNAL AUDIT COMPLETE ---');
    } catch (err) {
        console.error('Audit failed:', err);
    } finally {
        await browser.close();
    }
}

runJournalAudit();
