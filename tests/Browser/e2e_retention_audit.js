import puppeteer from 'puppeteer-core';
import path from 'path';

const CHROME_PATH = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const BASE_URL = 'http://127.0.0.1:8000';
const ARTIFACTS_DIR = '/Users/andrew/.gemini/antigravity-ide/brain/03bc8fbc-486c-4ca7-9779-989edf3e49de';

async function runRetentionBrowserAudit() {
    console.log('--- STARTING BUILDFLOW CLIENT RETENTION BROWSER AUDIT ---');

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

        // Step 2: Navigate to Retention Register
        console.log('Navigating to Retention Register...');
        await page.goto(`${BASE_URL}/retention`, { waitUntil: 'load' });
        await new Promise(r => setTimeout(r, 600));
        await page.screenshot({ path: path.join(ARTIFACTS_DIR, 'retention_01_register.png'), fullPage: true });
        console.log('✓ Captured retention_01_register.png');

        // Step 3: Open Contract 1 Detail
        console.log('Navigating to Contract 1 Detail...');
        await page.goto(`${BASE_URL}/retention/contracts/1`, { waitUntil: 'load' });
        await new Promise(r => setTimeout(r, 600));
        await page.screenshot({ path: path.join(ARTIFACTS_DIR, 'retention_02_contract_detail.png'), fullPage: true });
        console.log('✓ Captured retention_02_contract_detail.png');

        // Step 4: Open Claim Preparation Form for Stage 1
        console.log('Navigating to Claim Preparation Screen...');
        await page.goto(`${BASE_URL}/retention/contracts/1/claims/create?stage_key=stage_1_completion`, { waitUntil: 'load' });
        await new Promise(r => setTimeout(r, 600));
        await page.screenshot({ path: path.join(ARTIFACTS_DIR, 'retention_03_claim_create.png'), fullPage: true });
        console.log('✓ Captured retention_03_claim_create.png');

        // Step 5: Submit Claim Form (Creates Draft Claim)
        console.log('Submitting release claim of ₹50,000...');
        await page.$eval('input[name="claimed_amount"]', el => el.value = '');
        await page.type('input[name="claimed_amount"]', '50000.00');
        await page.type('textarea[name="notes"]', 'Stage 1 release claim following verified Practical Completion Certificate PCC-2026-RR-091.');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'load', timeout: 15000 }),
            page.click('#btnSubmitClaim')
        ]);
        console.log('Claim drafted. Landed on:', page.url());
        await new Promise(r => setTimeout(r, 600));
        await page.screenshot({ path: path.join(ARTIFACTS_DIR, 'retention_04_claim_detail.png'), fullPage: true });
        console.log('✓ Captured retention_04_claim_detail.png');

        // Step 6: If Draft, Submit for Internal Review
        const submitForReviewBtn = await page.$('#btnSubmitForReview');
        if (submitForReviewBtn) {
            console.log('Submitting draft claim for internal review...');
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'load', timeout: 15000 }),
                submitForReviewBtn.click()
            ]);
            console.log('Claim submitted for review.');
        } else {
            console.log('Claim already in submitted review state.');
        }

        // Step 7: Approve Claim via modal form
        console.log('Approving claim...');
        await page.click('#btnOpenApproveModal');
        await new Promise(r => setTimeout(r, 400));
        await page.type('#approveModal textarea[name="approval_remarks"]', 'Independent consulting inspection verified. Approved for client billing.');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'load', timeout: 15000 }),
            page.click('#btnConfirmApprove')
        ]);
        console.log('Claim approved.');

        // Step 8: Mark Submitted to Client
        console.log('Marking claim submitted to client...');
        await page.click('#btnOpenClientSubmitModal');
        await new Promise(r => setTimeout(r, 400));
        await page.type('#clientSubmitModal input[name="client_reference"]', 'CLI-RET-2026-RR-092');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'load', timeout: 15000 }),
            page.click('#btnConfirmClientSubmit')
        ]);
        console.log('Claim marked as client submitted.');

        // Step 9: Record Client Payment Receipt
        console.log('Recording client receipt of ₹50,000...');
        await page.click('#btnOpenReceiptModal');
        await new Promise(r => setTimeout(r, 400));
        await page.type('#receiptModal input[name="reference_number"]', 'UTR-HDFC-992100');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'load', timeout: 15000 }),
            page.click('#btnConfirmReceipt')
        ]);
        console.log('Client receipt recorded.');
        await new Promise(r => setTimeout(r, 600));
        await page.screenshot({ path: path.join(ARTIFACTS_DIR, 'retention_05_claim_settled.png'), fullPage: true });
        console.log('✓ Captured retention_05_claim_settled.png');

        // Step 10: Re-visit Retention Register to inspect updated metrics
        console.log('Navigating back to Retention Register to verify live metrics...');
        await page.goto(`${BASE_URL}/retention`, { waitUntil: 'load' });
        await new Promise(r => setTimeout(r, 600));
        await page.screenshot({ path: path.join(ARTIFACTS_DIR, 'retention_06_register_updated.png'), fullPage: true });
        console.log('✓ Captured retention_06_register_updated.png');

        console.log('========================================================');
        console.log('REAL BROWSER AUDIT: ALL RETENTION WORKFLOW STEPS PASSED!');
        console.log('========================================================');
    } catch (err) {
        console.error('Browser Audit Error:', err);
        throw err;
    } finally {
        await browser.close();
    }
}

runRetentionBrowserAudit();
