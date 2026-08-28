# Kustom Checkout test suites

Three suites, all booted by [lucatume/wp-browser](https://wpbrowser.wptestkit.dev/)
against a real WordPress + WooCommerce + Kustom Checkout install:

| Suite | Runner | What it covers |
|---|---|---|
| `Integration` | `composer test:integration` | PHP-level tests with WordPress and WooCommerce loaded in-process (WPLoader). No browser, no ngrok, no API calls. |
| `Harness` | `composer test:harness` | The test harness itself: what the WooCommerce Subscriptions fakes answer, and that artifact redaction keeps a Kustom secret out of the report. |
| `EndToEnd` | `composer test:e2e` | Browser-driven tests against the local built-in server, plus an ngrok HTTPS tunnel alongside it that carries nothing but Kustom's own traffic. |

Start with the Integration suite. It needs no credentials and runs in about two
minutes.

`Harness` boots the same WordPress and shares the same SQLite database as
`Integration`, so **never run the two at once**. Concurrent runs surface as bogus
assertion failures rather than lock errors.

[CONVENTIONS.md](CONVENTIONS.md) covers what to write and what to leave out. Read
it before adding a test. This file is the mechanics.

## Prerequisites

All suites:

- **PHP 8.0+** with `pdo_sqlite`, `sqlite3`, `mbstring`, `mysqli`, `gd`, `zip`,
  `curl`, `dom`, `xml`. CI runs 8.4.
- **Composer**.

EndToEnd only:

- **Chrome**, any recent version. `composer test:chromedriver` matches the driver
  to your local Chrome.
- **ngrok** on `PATH`, with an account that supports reserved or branded domains.

Only needed to regenerate the dump fixture: **WP-CLI**, **Node** and **npm**.

## Setup

1. Copy the env template and fill it in:

   ```bash
   cp tests/.env.example tests/.env
   ```

   Integration and Harness need only the *WordPress test install* block. The
   ngrok and Kustom values can stay blank.

   EndToEnd also needs:
   - `NGROK_DOMAIN`, the CLI argument for `ngrok http --domain=`. With an internal
     ngrok domain this is `<name>.internal`, not the public URL.
   - `KCO_WORDPRESS_URL`, the public HTTPS URL that domain resolves to, for example
     `https://kco-test-site.<subdomain>.ngrok.io`. Only Kustom sees it.
   - `WORDPRESS_URL`, which is where the site is actually served and driven:
     `http://localhost:<BUILTIN_SERVER_PORT>`. See
     [Only Kustom goes through the tunnel](#only-kustom-goes-through-the-tunnel).
   - `NGROK_AUTHTOKEN` from <https://dashboard.ngrok.com/get-started/your-authtoken>.
   - `KUSTOM_TEST_MID_EU` and `KUSTOM_TEST_SECRET_EU` from a Kustom test merchant
     account.
   - `WORDPRESS_ADMIN_PASSWORD`, which the suite logs in with. The committed dump
     carries a placeholder password, and
     `_mu-plugins/05-kustom-test-admin-password.php` resets it from this value on
     the first request of each test.

2. Install dependencies:

   ```bash
   composer install   # scaffolds tests/_wordpress/ via post-autoload-dump-dev
   ```

`assets/` and `tests/Support/Data/dump.sql` are both committed, so a fresh clone
needs neither `npm run build` nor a dump regeneration.

Regenerate the dump when the upstream WC or plugin baseline schema changes:

```bash
npm install && npm run build
composer test:regenerate-dump
```

It boots a clean WordPress, activates WooCommerce and the plugin, configures SE/SEK,
creates the shortcode checkout pages and one product, then exports
`tests/Support/Data/dump.sql`. No credentials are written into it.

## Running the tests

```bash
composer test:integration          # whole Integration suite
composer test:harness              # whole Harness suite, never alongside Integration
composer test:e2e                  # whole EndToEnd suite

vendor/bin/codecept run Integration:CheckoutOrderTest                  # one file
vendor/bin/codecept run Integration:CheckoutOrderTest --debug          # with output
vendor/bin/codecept run Integration OrderManagementTest:test_the_operation_reaches_the_api
```

`composer test:e2e` starts the PHP built-in server, ChromeDriver and ngrok,
restores the dump before each test, runs the suite, then tears everything down.

The Integration suite also has an `hpos` env that points WPLoader at a separate
`db-hpos.sqlite`:

```bash
vendor/bin/codecept run Integration --env hpos
```

## CI

There is no CI workflow for these suites in this repository yet. When one is added,
run them in this order: Harness, then Integration, then EndToEnd, since the
redaction tests gate everything that follows and the first two share a database.

The secrets it will need are `NGROK_AUTHTOKEN`, `KUSTOM_TEST_MID_EU`,
`KUSTOM_TEST_SECRET_EU` and `WORDPRESS_ADMIN_PASSWORD`. Fail the build if any is
empty: empty secrets make the redaction tests pass vacuously. Give the ngrok domain
the PR number so two open PRs never share a tunnel, skip EndToEnd on fork PRs, and
run `verify-no-secrets.php` whatever the outcome, uploading `tests/_output/` only on
failure and only after that scan comes back clean.

## The Integration suite

One file per area of the plugin:

| File | Covers |
|---|---|
| `CheckoutOrderTest.php` | `kco_create_or_update_order()`: creating, patching and replacing the Kustom order the iframe renders from, and the body it carries. |
| `CheckoutTest.php` | `process_payment()` and the flow handlers behind it (embedded, redirect via HPP, embedded block). |
| `GatewayAvailabilityTest.php` | Whether the gateway offers itself, and whether the cart needs paying for at all. |
| `OrderManagementTest.php` | Capture, cancel, update and refund, plus the guards all four share. |
| `PendingOrdersTest.php` | The out-of-band fraud verdict Kustom sends after answering `PENDING`. |
| `CallbacksTest.php` | The confirmation path: what Kustom's verdict does to the order. |
| `CredentialsTest.php` | Which merchant account a request signs itself with. |
| `RequestsTest.php` | Host routing, the auth header, and the cart payload builder. |
| `FunctionsTest.php` | The helpers in `includes/kco-functions.php`: country and region conversion, the order total and content guards, and the B2B meta. |
| `SubscriptionsTest.php` | The recurring token and the unattended renewal that charges it. |
| `SampleTest.php` | The smoke test wp-browser scaffolds: the plugin is active and the factories work. |

Most methods are data-provider driven, so the case count runs well ahead of the
method count.

### Writing one

Extend `Tests\Support\IntegrationTestCase` and declare a store profile:

```php
class MyTest extends IntegrationTestCase {

    protected ?string $storeProfile = 'se';

    public function test_something(): void {
        $this->haveCustomerAddress( $this->swedishAddress(), $this->swedishAddress() );
        $this->haveCartWith( [ [ $this->haveSimpleProduct(), 2 ] ] );

        $cart = new \KCO_Request_Cart();
        $cart->process_data();

        $this->assertSame( 25000, $cart->get_order_amount() );
    }
}
```

| Profile | Store |
|---|---|
| `'se'` | SE / SEK, one 25% VAT rate, `eu` test credentials. |
| `'se-no-tax'` | Same, taxes off. |
| `'us'` | US:CA / USD, one 8.5% sales tax rate, a US customer address, `us` credentials. |
| `null` | WooCommerce defaults (US / USD, taxes off) with no credentials. |

The profile is applied before every test. Override `setUp()` only for genuinely
extra arrange, and call `parent::setUp()` first.

The base class resets the store between tests and carries the assertions for
things plugin code leaves behind: order lines (`findOrderLine()`,
`assertHasOrderLine()`), order notes (`orderNotes()`, `assertOrderHasNote()`,
`assertOrderHasNoNote()`) and failures (`assertWpErrorCode()`), plus `reload()`
and `statusOf()` for reading an order back after plugin code has written to it.

It pulls in the fixture traits:

| Trait | Gives you |
|---|---|
| `CanConfigureStore` | `configureStore()`, `configureSwedishStore()`, `configureUsStore()`, `haveTaxRate()`, `haveTaxClass()`, `setGatewaySettings()`, `haveGatewayCredentials()`, `haveGatewayCredentialsForRegions()`, `reloadPaymentGateways()`, `resetGatewaySession()`. |
| `CanManageProducts` | `haveSimpleProduct()`, `haveVariableProduct()`. |
| `CanBuildCartsAndOrders` | `haveCustomerAddress()`, `haveCartWith()`, `haveCartFee()`, `haveChosenFlatRateShipping()`, `simulateCheckoutPage()`, `haveOrder()`, `haveGatewayOrder()`, `haveCapturableGatewayOrder()`, `markAsGatewayOrder()`, `haveRefundForItems()`, plus the `swedishAddress()` and `usAddress()` presets. |
| `CanInterceptHttp` | `willRespondWith()`, `willRejectWith()`, `httpRequests()`, `gatewayRequests()`, `gatewayRequestTo()`, `gatewayRequestsTo()`, `assertGatewayRequestCount()`, `assertNoGatewayRequests()`. |
| `CanDriveOrderManagement` | `willRetrieveManagedOrder()`, `willCapture()`, `willCancel()`, `willAcceptTheUpdate()`, `willRefund()`. |
| `CanDriveCheckout` | `willCreateOrder()`, `willRetrieveOrder()`, `willCreateHpp()`, `willCreateRecurringOrder()`. |
| `CanFakeSubscriptions` | `haveSubscription()`, `haveSubscriptionFor()`, `haveRenewalOrderFor()`, `markAsSubscription()`, `haveCartContaining()`. |
| `CanSnapshotRequests` | `assertRequestMatchesSnapshot()`, `assertMatchesSnapshot()`. |

`haveOrder()` takes `status`, `paid`, `kustom` and `created_via`. `kustom => true`
stamps the meta a completed purchase leaves: `_wc_klarna_order_id`,
`_wc_klarna_country`, `_wc_klarna_environment` and the `kco` payment method. Those
meta keys keep their `klarna` spelling because they are the plugin's own storage
keys, not leftovers.

```php
$order = $this->haveOrder(
    [
        'items'   => [ [ $this->haveSimpleProduct(), 2 ] ],
        'billing' => $this->swedishAddress(),
        'kustom'  => true,          // or [ 'order_id' => ..., 'country' => ... ]
        'paid'    => true,
        'status'  => 'on-hold',
    ]
);

// Or, for the common case, the preset with overrides:
$order = $this->haveGatewayOrder( [ 'status' => 'on-hold' ] );
```

### Request body snapshots

Outgoing payloads are pinned against JSON fixtures in `Support/Data/snapshots/`
rather than asserted key by key:

```php
$this->assertRequestMatchesSnapshot( $this->gatewayRequestTo( '/captures' ), 'om-capture-se' );
```

The fixture records the HTTP method, the full URL and the decoded body.
`Support/Data/snapshots/` is **empty in this repository**, so every snapshot test
fails with "Missing snapshot ..." until the payloads are generated once and
reviewed. To rewrite the fixtures after an intended change:

```bash
composer test:integration:snapshots
```

Then read the diff before committing. That diff is the review. There is more on
placeholders and on what does not belong in a snapshot in
[CONVENTIONS.md](CONVENTIONS.md).

### Outbound HTTP is blocked

`IntegrationTestCase` hooks `pre_http_request` for every test, so nothing reaches
Kustom. An unexpected call is short-circuited with a `WP_Error` and recorded, so
you can assert on it. Queue a canned response for calls that need to succeed:

```php
$this->willRespondWith(
    [ 'status' => 'AUTHORIZED' ],
    200,
    'ordermanagement/v1/orders'   // optional: only answer matching URLs
);
```

A fourth argument sets response headers, for the endpoints that answer with an
empty body. A capture returns its capture id in a `capture-id` header, and
`process_response()` reads it from there.

Responses are consumed in the order they were queued, first URL match wins.
Cancelling, capturing, updating and refunding all retrieve the Kustom order
first, so a test that expects the second request to happen has to queue two
responses. `CanDriveOrderManagement` wraps both halves:

```php
$this->willRetrieveManagedOrder( [ 'status' => 'CAPTURED' ] );   // the lookup
$this->willCancel();                                             // the action
```

`CanDriveCheckout` is the same idea for the purchase itself. The redirect flow
creates an order and then a hosted payment page, so `willCreateOrder()` followed by
`willCreateHpp()`.

Assert on a request by naming its endpoint rather than its position.
`gatewayRequestTo( '/captures' )` and `assertGatewayRequestCount( 1, '/captures' )`
survive the plugin adding another call to the same flow, where
`gatewayRequests()[1]` would not.

### WooCommerce Subscriptions is faked, always

The `wcs_*` functions the plugin calls are declared in
[_subscriptions-fakes.php](_subscriptions-fakes.php), which the Integration and
Harness bootstraps require for the whole run. PHP cannot undefine a function, so
the per-test switch cannot live in `function_exists()`. They answer from
`Tests\Support\Fakes\SubscriptionsRegistry`, which is empty until a test puts
something in it. `CanFakeSubscriptions` is the way in:

```php
$parent       = $this->haveOrder( [ 'kustom' => true ] );
$subscription = $this->haveSubscriptionFor( $parent );   // a shop_subscription
$renewal      = $this->haveRenewalOrderFor( $subscription );
```

Those are real orders. Only the class `wc_get_order()` returns is swapped, for
`Fakes\SubscriptionOrder`, which is a `WC_Order` plus the `shop_subscription`
type, `get_parent()` and `payment_failed()`. It does **not** stand in for
`WC_Subscriptions_Cart` or `WC_Subscriptions_Product`, so a cart holding a new
subscription product is still out of reach. `haveCartContaining( 'renewal' )` and
its siblings cover the states the plugin branches on beyond that.
[Harness/Fixtures/FakeSubscriptionsTest.php](Harness/Fixtures/FakeSubscriptionsTest.php)
pins what the fake answers.

The fake keeps `WC_Order::payment_complete()` rather than `WC_Subscription`'s,
and the subscription carries no line items, so a completed renewal lands the
subscription on `completed` rather than `processing`.

### Other things to know about the environment

- WPLoader wraps each test in a database transaction, but WooCommerce keeps state
  outside the database: the session cart, `WC()->customer`, tax rate caches, the
  shipping method count transient, and whatever third party integrations read from
  `WC()->session`. `IntegrationTestCase::resetStore()` clears all of it. If you
  add a fixture that writes somewhere new, reset it there too.
- Store defaults are US / USD with taxes off. The plugin treats US as a separate
  sales tax market, so a test that sets no store profile runs with
  `separate_sales_tax` on.
- `assertNoGatewayRequests()` filters to `kustom.co` on purpose. WooCommerce core
  makes requests of its own, and rebuilding the payment gateways triggers the
  WooPayments incentive check.
- Two things cache the gateway settings past an `update_option()`:
  `SettingsUtility` holds them in a static, and `KCO_Credentials` reads them once in
  its constructor, which runs at plugin load. `setGatewaySettings()` clears both, so
  the fixtures handle it; call `flushGatewaySettingsCache()` yourself only if a test
  writes the option directly.
- Anything reading `filter_input( INPUT_GET, ... )` or `INPUT_POST` is unreachable
  here. It reads the real request, which is empty in CLI no matter what `$_GET`
  says. `MetaBox::process_kom_actions()` and `KCO_API_Callbacks::push_cb()` are both
  in that category, which is why the confirmation path is covered through
  `kco_confirm_klarna_order()` instead.
- `ReturnFee`, `SellersApp`, `ScheduledActions` and `Ajax` under
  `src/OrderManagement/`, and the checkout block extension under `blocks/`, have no
  Integration coverage yet.

## The EndToEnd suite

One Cest per flow. `CheckoutCest.php` is the shortcode checkout: build the store
and the cart, place the order, finish it in Kustom's iframe, then assert on the
order that came out. `OrderManagementCest.php` picks the order up from there and
manages it from wp-admin. `TunnelCest.php` covers neither, and guards the one thing
both depend on: a browser that arrives through the tunnel lands on the local site.
`ActivationCest.php` is the smoke test: the plugin deactivates and reactivates
cleanly from the plugins screen.

> **Not finished yet.** The iframe half of a purchase is missing.
> `CanDriveE2ECheckout` carries the WooCommerce-side steps and waits for
> `#kco-iframe`, but nothing drives Kustom Checkout's snippet iframe to the thank
> you page, so `CheckoutCest` and `OrderManagementCest` both fail on
> `waitForThankYouPage()`. `TunnelCest` and `ActivationCest` pass. See
> [Kustom's iframe is not a fixed sequence](#kustoms-iframe-is-not-a-fixed-sequence)
> for the shape the missing driver needs.

### Writing one

The flow is written once and driven from a data provider, so a new case is a row
rather than a copy of the method. `scenario()` fills in the defaults (a Swedish
customer buying one 25% VAT product), and a row lists only what differs:

```php
'VAT added at checkout' => self::scenario( [
    'store' => [ 'woocommerce_prices_include_tax' => 'no' ],
    'total' => '124.99',
] ),
```

| Key | Means |
|---|---|
| `store` | Options to set before checkout loads, keyed by option name. |
| `tax_rates` | `TestTaxRates` rates to create. |
| `cart` | `TestProducts` SKUs to buy, each either a SKU or `[ SKU, quantity ]`. |
| `billing` | Overrides for the default billing address, e.g. `country` or `state`. |
| `gateway`, `total`, `meta` | What the finished order must carry: `_payment_method`, `_order_total`, and any further meta. |

**Provider methods have to be `protected`.** Codeception's Cest loader turns every
public method into a test, so a public provider runs as one and fails on its
missing actor argument. The row key is the case name.

The actor is built from traits, one per job, and the flow steps are deliberately
separate so a test can stop partway. Waiting for `#kco-iframe` is itself the
availability assertion, and a test about availability never has to reach Kustom:

| Trait | Gives you |
|---|---|
| `CanManageE2EProducts` | `haveProductInDatabase()`, `haveVariationProductInDatabase()`. |
| `CanManageE2ETaxRates` | `haveTaxClassInDatabase()`. |
| `CanDriveE2ECheckout` | `haveStoreOptionsInDatabase()`, `haveTaxClassesInDatabase()`, `haveCartWith()`, `amOnCheckoutPage()`, `amOnCheckoutPageWithGateway()`, `amOnCheckoutPageWithPaymentMethod()`, `fillBillingAddressForm()`, `waitForCheckoutReady()`, `placeOrder()`, `waitForThankYouPage()`, `verifyOrderOnThankYouPage()`, `grabOrderIdFromThankYouPage()`. |
| `CanDriveE2EOrderManagement` | `haveGatewaySettingsInDatabase()`, `amEditingOrder()`, `seeGatewayOrderStatusIs()`, `changeOrderStatusTo()`, `applyOrderManagementAction()`, `reduceLineItemQuantityTo()`, `refundOrderViaGateway()`, and the read-backs `seeOrderNotes()`, `seeOrderMeta()`, `seeRefundCount()`, `grabLatestRefundId()` with their `dontSee` counterparts. |

### Running one row

A purchase is around forty seconds of browser. `KCO_ONLY` narrows
`CheckoutCest`'s table to the rows whose names match, comma separated:

```bash
KCO_ONLY=rounding composer test:e2e                     # the four rounding rows
KCO_ONLY="four VAT,virtual and" composer test:e2e       # two specific rows
```

It fails loudly listing every row name if nothing matches. It does not touch
`OrderManagementCest`, which still runs its two purchases. To skip those:

```bash
KCO_ONLY=rounding vendor/bin/codecept run EndToEnd CheckoutCest --steps
```

### Why the rounding rows are here and not in Integration

Kustom rejects an order whose amount does not match its own lines to the cent, so
a rounding mistake in the payload shows up as a purchase that never reaches the
thank you page. That is the assertion no Integration test can make, and it is why
`ROUNDING_25_CART` (three lines whose 25% VAT each lands on a half cent) is bought
four times over, once per combination of:

| Setting | What it changes |
|---|---|
| `woocommerce_prices_include_tax` | Whether the total moves with the tax or stays put. |
| `woocommerce_tax_round_at_subtotal` | Whether each line's tax is rounded, or their sum is. |

Those four rows come out as 74.97 / 74.96 (tax added at checkout) and 59.97 twice
with the tax differing, 12.00 against 11.994 (tax in the price). Quantity is not
the same thing: six of one product is one line, and one line rounds the same
either way, which is what `six of one product` pins.

**Get expected totals from WooCommerce, never from arithmetic.** The quickest way
is a throwaway Integration test that configures the same store, builds the same
cart and prints `WC()->cart->get_total( 'edit' )`. Failing that, run the row once:
the failure prints what WooCommerce actually stored.

Watch out for `_order_tax`, which is stored **unrounded** when rounding is
deferred to the subtotal: `14.9925`, not `14.99`.

### Order management: `OrderManagementCest.php`

Two things it reaches that `Integration/OrderManagementTest.php` structurally
cannot:

- **Kustom actually accepting the bodies.** `seeGatewayOrderStatusIs('CAPTURED')`
  reads Kustom's own answer out of the `#kustom-om` metabox, which re-fetches the
  order on every page load. If a capture never reached Kustom the status still says
  `AUTHORIZED`, whatever the order notes claim.
- **`MetaBox::process_kom_actions()`**, which reads `kom_order_actions` with
  `filter_input( INPUT_POST, ... )`.

A Kustom authorization ends exactly once, capture **or** cancel, so the two tests
are two purchases with every operation chained onto them:

| Test | Lifecycle |
|---|---|
| `can_update_capture_and_refund_an_order` | AUTHORIZED → `on-hold` → edit line items (PATCH) → `completed` (capture) → refund |
| `can_cancel_an_order_from_the_metabox` | AUTHORIZED → `cancelled` with `kom_auto_cancel = no`, so nothing is sent → metabox `kom_cancel` + Update |

Order B cancels through the dropdown rather than the status transition on purpose:
the status path is already pinned in Integration, so the dropdown buys the same
API round trip, the merchant-action override and the POST-reading code, on an
order that has to be cancelled anyway.

Two refund details worth knowing before editing that step. `#refund_amount` is
`readonly` whenever taxes are enabled, so the step fills the line item and then
sets the total outright. And amounts are typed with the store's decimal separator,
since WooCommerce's own JS parses them with it and would read `10.00` as ten
thousand. The Kustom return fee is deliberately not covered.

### Only Kustom goes through the tunnel

The browser drives the site on `WORDPRESS_URL`, the built-in server, with no tunnel
in the way. ChromeDriver disables the cache, so every page load re-fetches all of the
assets WooCommerce enqueues; through the tunnel that spent the ngrok account's
request allowance partway into a run, and the suite failed at the same test every
time. Delaying the add-to-cart calls only moves where it lands.

The tunnel still forwards to the same server and carries Kustom's traffic only, which
`_mu-plugins/01-kustom-public-url.php` handles in both directions:

- **Outbound.** Kustom will not take a `localhost` merchant URL, so the site's URL
  becomes `KCO_WORDPRESS_URL` in every body headed for `*.kustom.co`. The filter is on
  `http_request_args`, not the plugin's `kco_wc_api_request_args`, which only covers
  the bodies the checkout requests build: order management and HPP build their own.
- **Inbound.** ngrok forwards the public `Host` header unchanged, and
  `redirect_canonical` answers that with a redirect to the public host carrying the
  local port, which nothing listens on. So a browser request is sent on to the same
  path on `WORDPRESS_URL`, and a callback is served with the host normalised to the
  local one. [TunnelCest.php](EndToEnd/TunnelCest.php) is the guard: it fails in one
  page load rather than as "never reached the order received page".

The site answers as `WORDPRESS_URL` and only that, pinned in `wp-config.php` by
[_bootstrap.php](_bootstrap.php). It has to be the `WP_HOME` / `WP_SITEURL` constants
rather than an option filter: `wp_plugin_directory_constants()` freezes
`WP_CONTENT_URL` and `WP_PLUGIN_URL` from `siteurl` before mu-plugins load, and
`dump.sql` carries the `siteurl` of whoever generated it.

wp-admin needed this most: behind the tunnel, three admin screens per browser session
got through and the fourth hung on subresources that never answered.
`_mu-plugins/06-admin-screens-offline.php` drops the ones that never answer at all
here: WooCommerce Admin's `/wp-json/wc-admin` and `/wp-json/wc-analytics` bursts
(their SQL does not run under the SQLite translation), the dashboard news widget, the
update checks and the script compression test.

A capture made from an admin screen carries `localhost` in its `product_url`, which
is cosmetic here and pinned properly by the Integration snapshots.

### Kustom's iframe is not a fixed sequence

**This driver does not exist yet.** What follows is the shape it has to take, and
why.

Kustom changes its screens without notice, and the same purchase is asked for
BankID, a payment method, a bank account or nothing at all, in whatever order. Two
runs of the same test in the same hour genuinely see different flows.

So the driver must script no sequence. It reads what is on screen, recognises it,
makes the one move that gets past it, and looks again, until the top window is on
`order-received`:

| Screen | Recognised by | Move |
|---|---|---|
| `identify` | a button mentioning BankID, Swish, Vipps or MitID | Click it. The test identity needs no app. |
| `picker` | more than one visible offer card, or "Choose how to pay" in the dialog on top | Select a method, read the picker back, then continue. |
| `confirm` | the buy button, enabled | Buy, but if the screen is confirming a method nobody asked for, reopen the picker first. |
| `account` | a bank-account list or radio | Take the preselected account and continue. |
| `busy` | the buy button is `aria-busy` or `aria-disabled`, a loader is up, or there is nothing to act on yet | Wait. |
| `unknown` | buttons are on offer but none of the above | Try the one that reads like the way on, then fail with the screen's text and buttons. |

What that buys, and the reasons not to write a scripted sequence instead:

- Kustom picking a method nobody asked for is recovered from, not failed on.
- A requested method is a preference, not a gate. After a few tries the purchase
  should finish with whatever is on offer and say so in the step output.
- Kustom renaming things costs a selector, not a test. Match options on the method
  id first and on the card's wording second.
- A dead end must report the screens it went through, the buttons on offer, the
  payment options and the visible text. Anything less is unreadable at 2am.

Two mechanics worth knowing before writing it:

- **A plain driver click is not enough.** Label spans over the buttons swallow it.
  Replay the pointer sequence (`pointerdown`, `mousedown`, `pointerup`, `mouseup`,
  `click`) *and then* call `element.click()`: offer cards tend to react only to the
  sequence, buy buttons only to the native activation.
- **Visibility has to honour `aria-hidden`.** The screen behind a dialog, and
  dialogs that have been closed, stay in the DOM.

When a checkout test fails, read the step output first, then `screenshot.png` and
`browser-console.log` off the report.

### Environment traps that look like plugin bugs

Each of these now fails loudly or is fixed outright, but the shapes are worth
recognising. The tell for all of them: the browser is on a Chrome error page, or
the assertion fails on a number that is right for a smaller cart than the row
asked for.

- **A cart that loses items.** WooCommerce saves the session with
  `INSERT ... ON DUPLICATE KEY UPDATE`, which is only an update when a unique index
  on `session_key` exists. The MySQL schema has one, its SQLite translation does
  not, so every save appended a row and reads picked whichever came first.
  `_mu-plugins/03-woocommerce-sessions-upsert.php` creates the index on every
  request, because WPDb reloads the dump before each test and would otherwise drop
  it.
- **A site that stops answering a few tests in.** The built-in server serves one
  connection per worker, and a browser opens several per host while the tunnel parks
  its own keep-alives. Hence `workers: 24` and `pageload_timeout: 45` in
  `codeception.yml`.
- **A run that fails at the same test every time, on a page that never loads.** An
  ngrok rate limit, or a browser request that reached the tunnel. See
  [Only Kustom goes through the tunnel](#only-kustom-goes-through-the-tunnel).
- **A test that says it never reached the thank you page, with a screenshot of the
  thank you page.** Every driver command waits out a page that never finishes
  loading, and the iframe driver has to read a thrown timeout as "not there yet". Raising
  `pageload_timeout` makes this worse, not better: it buys the loop fewer polls.
  The admin steps that need longer than 45s absorb the timeout themselves in
  `CanDriveE2EOrderManagement::waitOutOrderScreen()`.
- **A tunnel that goes away.** `NgrokController` checks the URL Chrome actually
  uses before every test and restarts the agent if it has stalled. The agent's log
  is kept at `tests/_output/ngrok.log`.

## Test reports

All three suites write [Allure](https://allurereport.org/) results, and one report
renders them together:

```bash
composer test:integration          # and/or
composer test:harness              # and/or
composer test:e2e

composer test:report               # writes tests/_output/report/index.html
```

Results accumulate per suite in `tests/_output/allure-results/<suite>/`, so running
one suite never discards another's. `composer test:report:reset` clears
everything. Rendering is done by the `allure` CLI from `devDependencies`, so no
Java is involved.

**Two output modes.** `test:report` writes a directory that zips well as a CI
artifact. `composer test:report:share` writes one self-contained
`tests/_output/report-share/index.html` for handing to someone outside the dev
team.

**What lands on a test.** Integration tests carry `http-requests.json`, every API
call the test provoked request by request, plus their data-provider arguments as
report parameters. EndToEnd *failures* carry `screenshot.png`,
`page-source.html`, `browser-console.log` (where the checkout SDK reports iframe and
tokenisation errors) and `network.json`. Codeception's `Recorder` extension also
writes HTML step snapshots into `tests/_output/`, and deletes them for tests that
passed.

**Server-side logs land on every E2E test, passing or not.** `debug.log` for PHP
notices, warnings and fatals, plus one attachment per WooCommerce log handle.
`place-order-debug-<uid>.log` is the useful half of a broken checkout. A green
checkout can still log a deprecation, which is why these are not failures-only.

Each test gets **only its own lines**. `WordPressLogReporter` notes how long each
log file is before a test starts and attaches just what arrived by the time it
ends. The logs are wiped once at the start of each suite run: `wp-content/debug.log`
truncated, `wc-logs/*.log` deleted, with WooCommerce's `.htaccess` and `index.html`
left alone. Tests that logged nothing attach nothing.

Two caveats. Logs are written by the built-in server, a *different process* to the
test, so a line written as a test tears down can occasionally land in the next
test's slice. And the Integration suite contributes nothing here, because
Codeception's error handler turns PHP notices into test failures before they reach
`debug.log`.

**The redaction guarantee.** Every *text* artifact is scrubbed through
`Tests\Support\Reporting\Redactor` before it is attached, in every encoding the
plugin can emit: raw, base64, URL-encoded, JSON-escaped, and the
`base64(merchant_id:shared_secret)` pair it puts in its `Authorization` header.
Afterwards `verify-no-secrets.php` re-scans the whole results directory and the raw
`tests/_output` artifacts. If a credential survived, the report is deleted and the
build fails, naming the *env var* that leaked and never its value. It scans the
results rather than the rendered HTML, because Allure base64-encodes attachment
payloads into the report.

The gate covers everything published. It does **not** scan the raw logs under
`tests/_wordpress/wp-content/`, which stay on your machine. Scrub those by hand
before sharing them.

**Adding a new secret.** Add the env var name to `SecretRegistry::SECRET_KEYS`, and
to `BASIC_AUTH_PAIRS` if it is one half of a `mid:secret` pair. Regional
`KUSTOM_TEST_SECRET_<CC>` keys present in `tests/.env` are picked up automatically.

**Adding a new artifact type.** Scrub it through the `Redactor` before attaching
it. The gate is a backstop for mistakes, not the control.

## Layout

| Path | Purpose |
|---|---|
| `tests/CONVENTIONS.md` | What to write and what to leave out. |
| `tests/phpcs.xml` | Test-suite style, applied by `composer lint:tests`. WPCS does not apply under `tests/`. |
| `codeception.yml` (repo root) | Shared config: ChromeDriver, built-in server, Symlinker, Recorder. |
| `tests/Integration.suite.yml`, `Harness.suite.yml`, `EndToEnd.suite.yml` | Per-suite modules and extensions. |
| `tests/Integration/` | PHPUnit-style tests (WPLoader), one file per area of the plugin. |
| `tests/Harness/Fixtures/` | What the subscriptions fakes answer. |
| `tests/Harness/Reporting/` | Redaction and report-attachment tests. |
| `tests/EndToEnd/` | Browser-driven Cests (WPWebDriver). |
| `tests/_subscriptions-fakes.php` | The `wcs_*` stand-ins, required by the Integration and Harness bootstraps. |
| `tests/_bootstrap.php` | Syncs `_mu-plugins/` into the install and writes `WP_HOME` / `WP_SITEURL` into `wp-config.php`. |
| `tests/Support/IntegrationTestCase.php` | Base class: store profiles, store reset, and order line, order note and `WP_Error` assertions. |
| `tests/Support/Traits/CanConfigureStore.php` | Store options, tax rates, gateway settings. |
| `tests/Support/Traits/CanManageProducts.php` | Product fixtures via the WC CRUD layer. |
| `tests/Support/Traits/CanBuildCartsAndOrders.php` | Customer address, cart, shipping and order fixtures. |
| `tests/Support/Traits/CanInterceptHttp.php` | Blocks and records outbound HTTP; queues canned responses. |
| `tests/Support/Traits/CanDriveOrderManagement.php` | Canned order management responses. |
| `tests/Support/Traits/CanDriveCheckout.php` | Canned checkout responses. |
| `tests/Support/Traits/CanFakeSubscriptions.php` | Subscription, renewal-order and cart-state fixtures. |
| `tests/Support/Traits/CanSnapshotRequests.php` | Pins a request or an array against a committed JSON fixture. |
| `tests/Support/Traits/CanManageE2E*.php` | E2E product and tax fixtures written straight to the DB via WPDb. |
| `tests/Support/Traits/CanDriveE2ECheckout.php` | The E2E checkout flow: store and cart setup, the billing form, the thank you page. The iframe driver is missing. |
| `tests/Support/Traits/CanDriveE2EOrderManagement.php` | The E2E wp-admin flow: order screen, KOM metabox, refunds. |
| `tests/Support/Data/TestProducts.php`, `TestTaxRates.php` | The SKUs and rates the E2E rows refer to. |
| `tests/Support/Data/snapshots/` | The request body fixtures. Empty for now; generate with `composer test:integration:snapshots`. |
| `tests/Support/Data/dump.sql` | The fixture WPDb restores before each E2E test. Generated, carries no credentials. |
| `tests/Support/Fakes/` | `SubscriptionsRegistry` (what the `wcs_*` stubs answer) and `SubscriptionOrder` (the `WC_Subscription` stand-in). |
| `tests/Support/Extension/NgrokController.php` | Starts, health checks and stops the ngrok tunnel. |
| `tests/Support/Extension/ArtifactReporter.php` | Attaches screenshot, page source, console log and network summary to failed E2E tests. |
| `tests/Support/Extension/WordPressLogReporter.php` | Clears `debug.log` and `wc-logs/` per suite; attaches each test's own slice of them. |
| `tests/Support/Extension/ExampleNameReporter.php` | Names each data provider row in the report after the provider's own key. |
| `tests/Support/Reporting/` | `Redactor`, `SecretRegistry` and `LogTail`. |
| `tests/_mu-plugins/01-kustom-public-url.php` | Keeps the site local for the browser and public for Kustom, in both directions. |
| `tests/_mu-plugins/02-kustom-test-credentials.php` | Overlays the gateway settings with env-var credentials. |
| `tests/_mu-plugins/03-woocommerce-sessions-upsert.php` | Creates the `session_key` unique index SQLite drops. |
| `tests/_mu-plugins/05-kustom-test-admin-password.php` | Resets the admin password from `WORDPRESS_ADMIN_PASSWORD`. |
| `tests/_mu-plugins/06-admin-screens-offline.php` | Drops the wp-admin requests that never answer here. |
| `tests/_support_scripts/install-test-env.php` | Idempotent WP scaffold, runs on `composer install`. |
| `tests/_support_scripts/regenerate-dump.sh` | Rebuilds `dump.sql` from a clean install. |
| `tests/_support_scripts/strip-actionscheduler-inserts.php` | Strips Action Scheduler data rows from the dump, whose serialized payloads SQLite cannot round-trip. |
| `tests/_support_scripts/verify-no-secrets.php` | The leak gate: fails the build and deletes the report if a credential survived. |
| `tests/_support_scripts/generate-report.sh` | Renders the Allure report from whichever suite results exist, then runs the gate. |
| `tests/_plugins/`, `tests/_themes/` | WooCommerce, SQLite Database Integration and Storefront, symlinked into the install. |

## Troubleshooting

- **`ngrok did not expose ... within 15s`.** Usually a wrong `NGROK_DOMAIN`, which
  must be `<name>.internal` rather than the public URL, or an empty or invalid
  `NGROK_AUTHTOKEN`. Run `ngrok http --domain=$NGROK_DOMAIN 8000` manually against
  a dummy `python -m http.server 8000` to isolate it.
- **`An ngrok process is already running ...`.** A previous run crashed and left
  ngrok orphaned. `pkill ngrok` and retry.
- **The gateway is not visible in an E2E test.** Confirm `KUSTOM_TEST_MID_EU` and
  `KUSTOM_TEST_SECRET_EU` are set and valid. `KCO_Gateway::is_available()` returns
  false on the checkout page when no credential pair resolves.
- **The E2E login fails.** `WORDPRESS_ADMIN_PASSWORD` is missing from `tests/.env`.
  The dump ships a placeholder password and mu-plugin 05 replaces it at runtime.
- **`session not created: This version of ChromeDriver only supports ...`.** Chrome
  auto-updated. Run `composer test:chromedriver`.
- **A snapshot test fails after an unrelated change.** Read the diff first. If the
  change was intended, `composer test:integration:snapshots` and commit the new
  fixture. If it was not, the snapshot just caught a regression.
- **An Integration test passes alone but fails in the suite.** Something is
  leaking state between tests. Check what your fixture writes to `WC()->session`,
  whether `resetStore()` clears it, and whether `SettingsUtility`'s static cache
  needs `flushGatewaySettingsCache()`.
- **A snapshot test fails with "Missing snapshot".** Expected: no fixtures are
  committed yet. Run `composer test:integration:snapshots` once the payloads are
  known to be right, then review the diff before committing.
