const timeOutTime = 2500;
import urls from "../helpers/urls"

// --------------- PERFORM ORDER MANAGEMENT ACTION --------------- //
const OrderManagementAction = async (page, orderID, orderManagementType) => {

    await goToMyOrder(page, orderID)

    await page.waitForTimeout(timeOutTime);

    let currentOrderStatus = await page.$eval('.select2-selection__rendered', e => e.innerText);
    await toggleOrderStatus(page, orderManagementType, currentOrderStatus)

    if (orderManagementType === 'cancel_order') {
        await cancelOrder(page, currentOrderStatus)
    } else {
        await refundOrder(page, orderManagementType, currentOrderStatus)
    }
}

// --------------- CANCEL ORDER -------------------------- //
const cancelOrder = async (page, currentOrderStatus) => {

    let klarnaOrderStatusTab = await page.$eval('.kom-meta-box-content', e => e.innerText)
    let klarnaOrderStatus = klarnaOrderStatusTab.split('\n')[1].substring(klarnaOrderStatusTab.split('\n')[1].indexOf(':') + 2)

    if (currentOrderStatus !== 'Completed') {
        expect(klarnaOrderStatus).toBe('CANCELLED')
    }
}

// --------------- TOGGLE ORDER STATUS -------------------------- //
const toggleOrderStatus = async (page, orderManagementType, currentOrderStatus) => {

    if (orderManagementType === 'cancel_order') {

        if (currentOrderStatus !== 'Completed') {
            // Set order to 'CANCELLED'
            await page.select('#order_status', 'wc-cancelled');

            let submitOrder = await page.$('.button.save_order.button-primary');

            await submitOrder.click();
        }

    } else if (orderManagementType === 'partial_refund' || orderManagementType === 'complete_refund') {

        if (currentOrderStatus === 'Processing') {

            // Set order to 'COMPLETED'
            await page.select('#order_status', 'wc-completed');

            let submitOrder = await page.$('.button.save_order.button-primary');

            await submitOrder.click();
        }
    }

    await page.waitForTimeout(timeOutTime);
}

// --------------- REFUND ORDER -------------------------- //
const refundOrder = async (page, orderManagementType) => {

    let refundButton = await page.$('button.refund-items');

    await refundButton.click()

    await page.waitForTimeout(1500)

    let items = await page.$$('#order_line_items > .item')


    // Refund each item
    for (let index = 0; index < items.length; index++) {

        let refundItemAmount = await items[index].$eval('.quantity > .edit > input', e => e.value)
        let refundItemInput = await items[index].$('.quantity > .refund > input')

        await refundItemInput.click({ clickCount: 3 });

        // Toggle between complete and partial refund
        if (orderManagementType === 'complete_refund') {
            await refundItemInput.type(refundItemAmount)
        } else if (orderManagementType === 'partial_refund') {
            await refundItemInput.type('0,5')
        }

        await page.waitForTimeout(1000)
    }

    // Refund shipping
    let shippingAvailable = await page.$('#order_shipping_line_items > .shipping > .line_cost > .edit > input');

    if (shippingAvailable) {
        let shippingAmount = await page.$eval('#order_shipping_line_items > .shipping > .line_cost > .edit > input', e => e.value);
        let shippingInput = await page.$('#order_shipping_line_items > .shipping > .line_cost > .refund > input');

        let shippingAmountTax = await page.$eval('#order_shipping_line_items > .shipping > .line_tax > .edit > input', e => e.value);
        let shippingTaxInput = await page.$('#order_shipping_line_items > .shipping > .line_tax > .refund > input');

        await shippingInput.click({ clickCount: 3 });
        await shippingInput.type(shippingAmount);

        await shippingTaxInput.click({ clickCount: 3 });
        await shippingTaxInput.type(shippingAmountTax);

        await page.waitForTimeout(1500);
    }

    let refundAmountDisplay = await page.$('#refund_amount')

    await refundAmountDisplay.click({ clickCount: 3 })


    // Refund By Klarna button
    let orderTotal
    let orderTotalFields = await page.$$('.wc-order-refund-items > .wc-order-totals > tbody > tr')

    for (let index = 0; index < orderTotalFields.length; index++) {

        let orderTotalLabel = await orderTotalFields[index].$('.label')
        let orderTotalAmount = await orderTotalFields[index].$('.total > span > bdi')

        let orderTotalLabelText
        let orderTotalAmountText

        if (orderTotalLabel) {
            orderTotalLabelText = await orderTotalFields[index].$eval('.label', e => e.innerText)
        }

        if (orderTotalAmount) {
            orderTotalAmountText = await orderTotalFields[index].$eval('.total > span > bdi', e => e.innerText)
        }

        if (orderTotalLabelText && orderTotalLabelText.includes("Total available to refund:")) {
            orderTotal = orderTotalAmountText.substr(0, orderTotalAmountText.indexOf(',') + 3)

        }
    }


    let refundByKlarnaButton = await page.$('.button.button-primary.do-api-refund')

    await page.waitForTimeout(1000)

    // Pop-up window
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


    await page.waitForTimeout(timeOutTime)

    let totalOrderRefunded = await page.$('.total.refunded-total > span > bdi')
    let totalOrderRefundedAmount
    let totalOrderRefundedAmountText

    if (totalOrderRefunded) {
        totalOrderRefundedAmount = await page.$eval('.total.refunded-total > span > bdi', e => e.innerText)
        totalOrderRefundedAmountText = totalOrderRefundedAmount.substr(0, totalOrderRefundedAmount.indexOf(',') + 3)
    }

    if (orderManagementType === 'complete_refund') {
        expect(orderTotal).toBe(totalOrderRefundedAmountText);
        expect(orderTotal).toBe(refundedNoteAmount);
        expect(refundedNoteAmount).toBe(totalOrderRefundedAmountText);
    }


    if (orderManagementType === 'partial_refund') {
        expect(refundedNoteAmount).toBe(totalOrderRefundedAmountText);
    }

    let klarnaOrderStatusTab = await page.$eval('.kom-meta-box-content', e => e.innerText)
    let klarnaOrderStatus = klarnaOrderStatusTab.split('\n')[1].substring(klarnaOrderStatusTab.split('\n')[1].indexOf(':') + 2)

    expect(klarnaOrderStatus).toBe('CAPTURED')
}


// --------------- REDIRECT TO THE CURRENT ORDER --------------- //
const goToMyOrder = async (page, orderID) => {
    await page.goto(urls.ORDER);

    await page.waitForTimeout(timeOutTime);

    await updateWPDB(page)
    await loginToAdmin(page)


    let final = await page.$(`tr[id="post-${orderID}"]`);

    await page.waitForTimeout(1000);

    await final.focus();
    await final.click();
}


// --------------- HANDLE LOGIN --------------- //
const loginToAdmin = async (page) => {
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
}

// --------------- UPDATE WORDPRESS DATABASE --------------- //
const updateWPDB = async (page) => {
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
}

export default {
    OrderManagementAction,
    loginToAdmin,
    updateWPDB,
    toggleOrderStatus,
    refundOrder,
    cancelOrder
}