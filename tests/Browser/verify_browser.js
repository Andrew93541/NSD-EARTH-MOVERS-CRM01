import puppeteer from 'puppeteer-core';

async function main() {
    console.log('Launching Chrome...');
    const browser = await puppeteer.launch({
        executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-gpu']
    });
    
    try {
        const page = await browser.newPage();
        await page.setViewport({ width: 1440, height: 900 });
        
        console.log('Navigating to login...');
        await page.goto('http://127.0.0.1:8000/login', { waitUntil: 'networkidle0' });
        
        console.log('Clearing and typing credentials...');
        await page.$eval('#email', el => el.value = '');
        await page.type('#email', 'admin@buildflow.local');
        await page.$eval('#password', el => el.value = '');
        await page.type('#password', 'Password123!');
        
        console.log('Submitting form...');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'load', timeout: 10000 }),
            page.click('button[type="submit"]')
        ]);
        
        console.log('Current URL after login:', page.url());
        
        const screenshotPath = '/Users/andrew/.gemini/antigravity-ide/brain/03bc8fbc-486c-4ca7-9779-989edf3e49de/dashboard_verified.png';
        await page.screenshot({ path: screenshotPath, fullPage: false });
        console.log('Screenshot saved to:', screenshotPath);
    } finally {
        await browser.close();
    }
}

main().catch(err => {
    console.error('Test failed:', err);
    process.exit(1);
});
