import puppeteer from 'puppeteer-core';
import path from 'path';

const CHROME_PATH = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const BASE_URL = 'http://127.0.0.1:8000';
const ARTIFACTS_DIR = '/Users/andrew/.gemini/antigravity-ide/brain/03bc8fbc-486c-4ca7-9779-989edf3e49de';

async function runEquipmentBrowserAudit() {
    console.log('--- STARTING BUILDFLOW EQUIPMENT & DEPRECIATION BROWSER AUDIT ---');

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

        // Step 2: Navigate to Loan 1 Detail (Equipment Finance)
        console.log('Navigating to Loan 1 Detail (/loans/1)...');
        await page.goto(`${BASE_URL}/loans/1`, { waitUntil: 'load' });
        await new Promise(r => setTimeout(r, 600));

        // Check if there is an unposted EMI to settle
        const settleBtn = await page.$('#btnOpenPayModal');
        if (settleBtn) {
            console.log('Settling pending EMI installment #1...');
            await page.click('#btnOpenPayModal');
            await new Promise(r => setTimeout(r, 500));
            await page.$eval('#emi_payment_ref', el => el.value = 'AUTODEBIT-NACH-9941');
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'load', timeout: 15000 }),
                page.click('#btnConfirmEmiPay')
            ]);
            console.log('EMI settled successfully.');
        }

        await page.screenshot({ path: path.join(ARTIFACTS_DIR, 'asset_01_loan_emi_settled.png'), fullPage: true });
        console.log('✓ Captured asset_01_loan_emi_settled.png');

        // Step 3: Navigate to Fixed Asset Register
        console.log('Navigating to Fixed Asset Register (/assets)...');
        await page.goto(`${BASE_URL}/assets`, { waitUntil: 'load' });
        await new Promise(r => setTimeout(r, 600));
        await page.screenshot({ path: path.join(ARTIFACTS_DIR, 'asset_02_register.png'), fullPage: true });
        console.log('✓ Captured asset_02_register.png');

        // Step 4: Navigate to Asset 1 Detail
        console.log('Navigating to Asset 1 Detail (/assets/1)...');
        await page.goto(`${BASE_URL}/assets/1`, { waitUntil: 'load' });
        await new Promise(r => setTimeout(r, 600));

        // If calculate modal exists and no posted depreciation, calculate for current month
        const calcBtn = await page.$('#btnOpenCalcModal');
        if (calcBtn) {
            console.log('Calculating depreciation for Asset 1...');
            await page.click('#btnOpenCalcModal');
            await new Promise(r => setTimeout(r, 500));
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'load', timeout: 15000 }),
                page.click('#btnSubmitCalcModal')
            ]);
            console.log('Depreciation calculated successfully.');
        }

        // If post to GL button exists, post it
        const postBtn = await page.$('#btnPostDepr');
        if (postBtn) {
            console.log('Posting depreciation entry to General Ledger...');
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'load', timeout: 15000 }),
                page.click('#btnPostDepr')
            ]);
            console.log('Depreciation posted to GL successfully.');
        }

        await new Promise(r => setTimeout(r, 600));
        await page.screenshot({ path: path.join(ARTIFACTS_DIR, 'asset_03_detail_depreciated.png'), fullPage: true });
        console.log('✓ Captured asset_03_detail_depreciated.png');

        // Step 5: Navigate to Period Review & Batch Posting
        console.log('Navigating to Fleet Period Review (/assets/period-review)...');
        await page.goto(`${BASE_URL}/assets/period-review`, { waitUntil: 'load' });
        await new Promise(r => setTimeout(r, 600));
        await page.screenshot({ path: path.join(ARTIFACTS_DIR, 'asset_04_period_review.png'), fullPage: true });
        console.log('✓ Captured asset_04_period_review.png');

        // Step 6: Navigate to Accounting Trial Balance
        console.log('Navigating to Trial Balance (/accounting/trial-balance)...');
        await page.goto(`${BASE_URL}/accounting/trial-balance`, { waitUntil: 'load' });
        await new Promise(r => setTimeout(r, 600));
        await page.screenshot({ path: path.join(ARTIFACTS_DIR, 'asset_05_trial_balance.png'), fullPage: true });
        console.log('✓ Captured asset_05_trial_balance.png');

        console.log('--- ALL BROWSER AUDIT STEPS COMPLETED SUCCESSFULLY ---');
    } catch (err) {
        console.error('Audit failed with error:', err);
        throw err;
    } finally {
        await browser.close();
    }
}

runEquipmentBrowserAudit();
