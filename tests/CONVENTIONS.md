# Test conventions

Read this before adding a test. [README.md](README.md) covers the mechanics of the
suites and the fixture API; this file is about what to write and what to leave out.

The suite exists to catch regressions in what the plugin sends to Kustom and what it
does with the answer. It is not a coverage exercise. Every test costs something to read
and to keep working, so the bar is that a reviewer can tell what broke from the
test name and the failure message alone.

## One test per thing

A "thing" is a decision the plugin makes, not a branch in the code. Whether a
capture reaches Kustom is one thing. Whether it reaches Kustom for four different
order states is still one thing, expressed as four provider rows.

Reach for a data provider whenever you would otherwise copy a test method and
change two lines. The row name is the documentation, and it prints in the test
output, so `'cancel, already cancelled'` beats a comment explaining the same
thing inside a method body.

```php
/**
 * The guards that stop an operation before the API is ever contacted.
 *
 * @dataProvider provide_ineligible_orders
 */
public function test_an_ineligible_order_is_left_alone( string $op, string $guard, ?string $error_code ): void {
```

Order management is the reference case. Capture, cancel, update and refund differ
in endpoint and body, but they share every guard, so the guards live in one provider
over all four operations rather than four times across four files. If you find
yourself writing a second copy of "ignores an order paid with another gateway", add
a row instead.

Providers are worth it at three rows. Below that, two plain methods usually read
better than a provider plus its table.

A Cest works the same way with two differences: the provider has to be `protected`,
since Codeception loads every public method as a test, and the row arrives as a
`Codeception\Example` after the actor. The row threshold above does not apply there.
A browser flow is long enough that duplicating it even once is worse than the
provider, which is why `CheckoutCest` is table-driven at two rows.

```php
/**
 * @dataProvider provide_purchases
 */
public function can_purchase( EndToEndTester $I, Example $case ): void {
```

## Where a test belongs

| Suite | Use it for |
|---|---|
| `Integration` | Anything reachable from PHP with WordPress and WooCommerce loaded. This is where almost everything goes. |
| `EndToEnd` | Only what genuinely needs a browser: the Kustom checkout iframe, the hosted payment page round trip, and anything reading `filter_input( INPUT_GET, ... )` or `INPUT_POST`, which are empty under CLI no matter what you put in `$_GET`. |
| `Harness` | Tests of the test harness itself, not the plugin. Currently the subscriptions fakes and the artifact redaction. |

Default to Integration. E2E tests are slow, need ngrok and live Kustom
credentials, and break when Kustom changes their checkout UI. If a behaviour can
be pinned from PHP, pin it from PHP.

`filter_input()` is the usual reason something cannot be tested from Integration.
It reads the real request, not the superglobal, so setting `$_POST` in a test does
nothing. When you hit this, say so in a one line comment and move the coverage to
E2E rather than working around it.

## Request bodies go in snapshots

The biggest single category of assertion here is "what JSON does the plugin send
to Kustom". Do not hand-assert those key by key. Build the scenario, run the code,
and pin the whole request against a committed fixture:

```php
$this->assertRequestMatchesSnapshot(
    $this->gatewayRequestTo( '/captures' ),
    'om-capture-se'
);
```

The fixture in `Support/Data/snapshots/` is the assertion. It records the method,
the full URL (so the regional endpoint is pinned too) and the decoded body. When
you intend to change a payload, run:

```bash
composer test:integration:snapshots
```

then read the diff before committing it. That diff is the review: if a field you
did not mean to touch moved, you will see it there.

Two rules for snapshots. Give products explicit names and SKUs so the fixture is
stable, and pass anything genuinely volatile through the placeholder argument:

```php
$this->assertRequestMatchesSnapshot( $request, 'om-refund-se', [
    '<refund-id>'    => $this->refundIdOf( $order ),
    '<order-number>' => $order->get_order_number(),
] );
```

Numeric placeholders are matched on digit boundaries, so an order number of 125
will not be substituted inside an amount of 12500. The site URL and any known
credential are masked for you.

`assertMatchesSnapshot()` does the same for a plain array, which is how the
order lines a cart builds are pinned.

Keep normal assertions for anything that is a rule rather than a shape. "The order
lines add up to the order amount" is a rule Kustom enforces, and it deserves a
real assertion because a snapshot would happily record a body that does not add
up.

## Do not pin known bugs

Never write a test that asserts wrong behaviour so that fixing it would fail the
suite. A green test is a statement that the behaviour is correct, and using one to
record a defect makes the suite lie about the plugin.

Report the bug internally instead and leave it out of the suite until it is fixed.
Then add a test that asserts the correct behaviour, which reads like every other
test in the suite.

A snapshot that happens to record odd behaviour is fine, because the fixture is
data rather than a claim. Just do not write a test method whose name says the
behaviour is wrong.

## Comments

Keep the comment budget tight. Prose about a fixture goes stale faster than the
fixture does, and a wrong comment is worse than none.

- A method gets at most one or two sentences, and only when the name does not
  already say it.
- No `@param` or `@return` blocks on test methods. PHPCS does not ask for them.
  The exception is a genuinely untyped parameter, where one line naming the type
  earns its place.
- Providers keep a single line `@return array<string, array{...}>`, because that
  is the only place the row shape is written down.
- Inline comments are a single line. If you need a paragraph, the fixture is
  probably too clever; simplify it instead.
- Class docblock is one line plus the `@covers` tags.

When you reach for a comment to explain why a fixture looks the way it does, or
why a request count is what it is, put it in the provider row name or the
assertion message instead. Both show up in the failure output, where someone will
actually read them.

## Naming

Test methods read as sentences about the plugin, not about the code:

```php
public function test_an_order_that_cannot_be_updated_is_replaced(): void
public function test_a_total_mismatch_stops_the_confirmation(): void
public function test_a_capture_made_outside_woocommerce_is_adopted(): void
```

Say what the behaviour is and, where it is not obvious, why it matters. Avoid
naming the method under test; `@covers` already does that, and a name like
`test_get_session_returns_array` tells a reviewer nothing about what broke.

Provider keys follow the same idea, in lower case and without a verb:
`'the order already paid'`, `'cancel, already cancelled'`.

## Use the fixtures, do not hand-roll state

`IntegrationTestCase` and its traits exist so tests describe a store rather than
build one. Set a store profile and use the builders:

```php
class MyTest extends IntegrationTestCase {

    protected ?string $storeProfile = 'se';   // SE / SEK / 25% VAT plus test credentials

    public function test_something(): void {
        $order = $this->haveCapturableGatewayOrder();
```

Profiles are `'se'`, `'se-no-tax'`, `'us'` (US / USD with 8.5% sales tax and a US
customer), or `null` for WooCommerce defaults with no credentials. The credential
set follows the store's base country: `US` reads the `us` pair, everything else
the `eu` one. If you catch
yourself writing four `update_option()` calls, the profile or a trait method
already covers it.

If you add a fixture that writes state somewhere new, clear it in `resetStore()` at
the same time. WPLoader wraps each test in a database transaction, but WooCommerce
keeps plenty outside the database: the session cart, `WC()->customer`, tax caches,
and whatever a third party integration reads from `WC()->session`.

Watch for that last one in particular. A leftover `WC()->session` key such as
`pw-gift-card-data` or `kco_wc_order_id` does not fail the test that set it. It
fails a later test in an unrelated file, usually as an arithmetic mismatch that
looks nothing like a state leak.

## Assert on endpoints, not positions

`gatewayRequestTo( '/captures' )` survives the plugin adding another call to the
same flow. `gatewayRequests()[1]` does not. Same for counts: prefer
`assertGatewayRequestCount( 1, '/captures' )` over a bare total.

Where the total genuinely is the point, say why in the message. Order management
looks the Kustom order up before it acts, so a test that only asserts the action
happened would still pass if the lookup disappeared and the action ate its canned
response.

## Outbound HTTP is blocked, and that is a feature

Nothing in the Integration suite reaches the network. An unqueued call comes back
as a `WP_Error`, which is a path production code has to survive anyway, and it is
recorded so you can assert on it. That means "this code path must not call
Kustom" is a one line test:

```php
$this->assertNoGatewayRequests( 'The availability check must not block checkout on a round trip.' );
```

Queue responses with `willRespondWith()`, or better, with the intent helpers in
`CanDriveCheckout` and `CanDriveOrderManagement`. A test that says
`willRetrieveManagedOrder( [ 'status' => 'CAPTURED' ] )` then `willCancel()` reads
as what Kustom answers, not as two response envelopes in the right order.

## Checklist before you open the PR

- Would a reviewer know what broke from the test name and the failure message?
- Is this a new provider row rather than a new method?
- Did you regenerate and read the snapshot diff, rather than just accepting it?
- Any comment longer than two sentences, or any `@param` on a test method?
- Does the new fixture state get cleared in `resetStore()`?
- Run `composer lint:tests`, then `composer test:integration` twice. The second
  run catches state leaking between tests that a single run hides.
