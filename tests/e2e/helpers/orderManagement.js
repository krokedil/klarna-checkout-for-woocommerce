const timeOutTime = 2500;
import urls from "../helpers/urls"

const completeRefund = async (page, orderID) => {

    await page.goto(urls.ORDER);

    await page.waitForTimeout(timeOutTime);

    let wpDatabaseUpdateRequired = await page.$('.wp-core-ui');

    if (wpDatabaseUpdateRequired) {

        let updateWPDB = await page.$(".button.button-large.button-primary");

        if (updateWPDB) {

            let updateWPDBText = await page.$eval(".button.button-large.button-primary", (e) => e.textContent);

            if (updateWPDBText === "Update WordPress Database") {

                await updateWPDB.focus();
                await updateWPDB.click();

                await page.waitForTimeout(1000);

                let confirmUpdate = await page.$(".button.button-large");
                let confirmUpdateText = await page.$eval(".button.button-large", (e) => e.textContent);

                if (confirmUpdate && confirmUpdateText === "Continue") {

                    await confirmUpdate.focus();
                    await confirmUpdate.click();
                }
            }
        }

    }

    await page.waitForTimeout(1000);

    let loginForm = await page.$('.login');

    if (loginForm) {
        let loginName = await page.$('input[id="user_login"]')
        let loginPassword = await page.$('input[id="user_pass"]')

        await loginName.focus()
        await loginName.click({ clickCount: 3 });
        await loginName.type('admin');

        await loginPassword.focus()
        await loginPassword.click({ clickCount: 3 });
        await loginPassword.type('password');

        let submitLogin = await page.$('input[id="wp-submit"]')
        await submitLogin.focus();
        await submitLogin.click({ clickCount: 3 });

        await page.waitForTimeout(1000);

        let loginRemindLater = await page.$('.admin-email__actions-secondary > a');

        if (loginRemindLater) {
            let loginComplete = await page.$('.admin-email__actions-secondary > a');
            await loginComplete.click()
        }
    }

    await page.waitForTimeout(timeOutTime);

    let final = await page.$(`tr[id="post-${orderID}"]`);

    await page.waitForTimeout(1000);

    await final.focus();
    await final.click();

    await page.waitForTimeout(timeOutTime);

    let currentOrderStatus = await page.$eval('.select2-selection__rendered', e => e.innerText);

    if (currentOrderStatus === 'Processing') {

        // Set order to 'COMPLETED'
        await page.select('#order_status', 'wc-completed');

        let submitOrder = await page.$('.button.save_order.button-primary');

        await submitOrder.click();

    }

    await page.waitForTimeout(timeOutTime);

    let refundButton = await page.$('button.refund-items');

    await refundButton.click()

    await page.waitForTimeout(1500)

    let items = await page.$$('#order_line_items > .item')

    for (let index = 0; index < items.length; index++) {

        let refundItemAmount = await items[index].$eval('.quantity > .edit > input', e => e.value)
        let refundItemInput = await items[index].$('.quantity > .refund > input')

        await refundItemInput.click({ clickCount: 3 });
        await refundItemInput.type(refundItemAmount)
    }

    let shippingAmount = await page.$eval('#order_shipping_line_items > .shipping > .line_cost > .edit > input', e => e.value)
    let shippingInput = await page.$('#order_shipping_line_items > .shipping > .line_cost > .refund > input');

    await shippingInput.click({ clickCount: 3 });
    await shippingInput.type(shippingAmount);

    await page.waitForTimeout(1500)

    // Refund By Klarna button
    let orderTotal = await page.$eval('.wc-order-totals > tbody :nth-child(4) > .total > .woocommerce-Price-amount.amount > bdi', e => e.innerText)
    let refundAmountDisplay = await page.$('#refund_amount')

    await refundAmountDisplay.click({ clickCount: 3 })

    let refundByKlarnaButtonText = await page.$eval('.button.button-primary.do-api-refund >  .wc-order-refund-amount > .woocommerce-Price-amount.amount', e => e.innerText)
    let refundByKlarnaButtonTextAmount = refundByKlarnaButtonText.substr(0, refundByKlarnaButtonText.indexOf(',') + 3);
    let orderTotalAmount = orderTotal.substr(0, orderTotal.indexOf(',') + 3);
    let refundByKlarnaButton = await page.$('.button.button-primary.do-api-refund')

    await page.waitForTimeout(1000)

    page.on("dialog", (dialog) => {
        dialog.accept()
    })

    await refundByKlarnaButton.click()

    await page.waitForTimeout(timeOutTime)

    let notes = await page.$$('.system-note');
    let refundedNoteAmount

    for (let index = 0; index < notes.length; index++) {

        let noteText = await notes[index].$eval('.note_content > p', e => e.innerText)

        if (noteText.includes("refunded via")) {
            refundedNoteAmount = noteText.substr(0, orderTotal.indexOf(',') + 3)
        }
    }

    return [orderTotalAmount, refundByKlarnaButtonTextAmount, refundedNoteAmount]
}

export default {
    completeRefund
}