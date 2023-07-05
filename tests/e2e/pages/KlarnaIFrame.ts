import { FrameLocator, Locator, Page, expect } from '@playwright/test';
import { request } from 'http';
//import internal = require('stream');

export class KlarnaIFrame {
    readonly page: Page;
    readonly iframe: FrameLocator | null;

    constructor(page: Page) {
        this.page = page;
        this.iframe = page.frameLocator('#klarna-checkout-iframe');
    }

    async AddCompanyDetails() {
        // Click and change 'Buying as a' option
        await this.iframe.getByLabel('Buying as a:').click();
        await this.iframe.getByText('Organization/Company').click();

        await this.iframe.getByLabel('Organization number').fill('002031-0132');
        await this.iframe.getByLabel('Company name').fill('Testcompany-se');
        // The filled in text can disapear, fill in again. Might be possible to improve
        await this.iframe.getByLabel('Organization number').fill('002031-0132');
        await this.iframe.getByLabel('Company name').fill('Testcompany-se');
    }
    async FillInPersonalIdNumPopup() {
        var fIframe = this.page.frameLocator('#klarna-fullscreen-iframe'); // Find popup iframe
        await fIframe.getByLabel('National Identification Number').fill('410321-9202');
        await fIframe.getByRole('button', { name: 'Pay order' }).click();
    }

    async ChangeShippingAddress(asCompany: boolean) {
        // Click the shipping container button to bring up shipping iframe
        await this.iframe.locator('#SHIPMO-container').click();

        // Find shipping iframe, and enter details
        var fIframe = this.page.frameLocator('#klarna-fullscreen-iframe');
        await fIframe.getByLabel('Email address').fill('testmail.alternativ@test.com');
        await fIframe.getByLabel('ZIP code').fill('99999');
        await fIframe.getByLabel('First name').fill('Test');

        // Fill in twice, might be possible to improve
        await fIframe.getByLabel('Last name').fill('Mottagare');
        await fIframe.getByLabel('Last name').fill('Mottagare'); 

        if (asCompany) await fIframe.getByLabel('Address', { exact: true }).fill('testgata');
        else await fIframe.getByLabel('AddressAdd C/O').fill('testgata');

        await fIframe.getByLabel('City').fill('teststad');
        await fIframe.getByLabel('Mobile phone').fill('0765260001');
        await fIframe.getByRole('button', { name: 'Confirm' }).click();
        await fIframe.getByRole('button', { name: 'Continue anyway' }).click();
    }

    static async WaitForCheckoutInitRequests(page: Page){ // Specifically find the response from klarna xhr request that does not include 'initial' in url
        await page.waitForResponse(
            response => response.url().match(/js.playground.klarna.com(?!.*initial)/) && response.status() === 200
        );
    }

    async WaitForIframeToLoad() { // Wait for no element with data-cid "overlay-loading" to be present
        // Find all overlays and then expect() each of them to not be visible
        var overlays = await this.iframe.locator('[data-cid="overlay-loading"]').all();

        for (const overlay of overlays) {
            await expect(overlay).not.toBeVisible();
        }
        
        await this.page.waitForTimeout(500);
    }

    async FillInPersonDetails() {
        await this.WaitForIframeToLoad();

        // Fill in Email and postal code, then press continue
        await this.iframe.getByLabel('Email address').fill('checkout-se@testdrive.klarna.com');
        await this.iframe.getByLabel('Postal code').fill('12345');
        await this.iframe.getByRole('button', { name: 'Continue' }).click(); //Allow double click, in case checkout doesn't progress by itself

        // Fill in the rest
	    await this.iframe.getByLabel('First name').fill('Testperson-se');
        await this.iframe.getByLabel('Last name').fill('Approved');
        await this.iframe.getByLabel('C/O').fill('Stårgatan 1');
        await this.iframe.getByLabel('City').fill('Ankeborg');
        await this.iframe.getByLabel('Mobile phone').fill('0765260000');
    }

    async ConfirmBillingDetails() { // Code currently works, but might be possible to improve
        // Press continue and then press continue anyways
        await this.iframe.getByRole('button', { name: 'Continue' }).click();
        await Promise.race([ //In case button does not appear
            new Promise(f => setTimeout(f, 5000)),
            this.iframe.getByRole('button', { name: 'Continue anyway' }).click()
        ]);
    }

    async HandleIFrame(separateShipping: boolean, asCompany: boolean) {
        // Fill in the required details
        await this.FillInPersonDetails();

        // Add additional company details when paying as a company
        if ( asCompany ) {
            await this.AddCompanyDetails();
        }
        
        // Press confirm buttons
        await this.ConfirmBillingDetails(); 

        // Wait for checkoutbox to be fully loaded
        await this.page.waitForRequest('**/?wc-ajax=update_order_review'); 
        await this.WaitForIframeToLoad();
        
        // Add separate shipping adress if requested to
        if ( separateShipping ) { 
            await this.ChangeShippingAddress(asCompany); 
            await this.page.waitForRequest('**/?wc-ajax=update_order_review'); 
            await this.WaitForIframeToLoad();
        }

        // Press Pay order, so that the popup appears, and also avoiding await to let other methods listen to events in time
        this.iframe.getByRole('button', { name: 'Pay order' }).click(); 

        // Extra popup appears when paying as company
        if ( asCompany ) { 
            await this.FillInPersonalIdNumPopup(); 
        }
    }
}
