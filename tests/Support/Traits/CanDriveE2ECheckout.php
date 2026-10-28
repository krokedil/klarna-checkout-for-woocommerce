<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

use Codeception\Exception\ElementNotFound;
use Facebook\WebDriver\Exception\WebDriverException;
use PHPUnit\Framework\Assert;

/**
 * The browser half of a purchase, one step at a time: shape the store and the cart,
 * then drive the shortcode checkout through Kustom's hosted iframe.
 *
 * Sibling of CanDriveCheckout, which cans the API's answers for the Integration suite.
 *
 * Nothing here is a scripted sequence. Kustom decides which screens a purchase sees and
 * in what order, and it does the same for the sign-in window it opens, so every step is
 * the same loop: read the screen, make the one move that gets past it, look again.
 */
trait CanDriveE2ECheckout {

	use \Tests\Support\_generated\EndToEndTesterActions;

	/** The billing address every checkout starts from; null leaves a field untouched. */
	public const BILLING_ADDRESS = [
		'country'    => 'SE',
		'first_name' => 'John',
		'last_name'  => 'Doe',
		'email'      => 'test@example.com',
		'phone'      => '0701234567',
		'address_1'  => 'Testgatan 1',
		'city'       => 'Stockholm',
		'postcode'   => '11152',
	];

	/**
	 * The payment method a purchase takes by default, matched against Kustom's own
	 * option id.
	 *
	 * Card is the only one a test can finish on its own. Kustom's own credit products
	 * send the shopper through a sign-in that asks for a BankID app, and the wallets
	 * want an app of their own; a card is typed and done. Which one is used says nothing
	 * about the plugin either way, and the Kustom order is AUTHORIZED after any of them.
	 */
	public const DEFAULT_KUSTOM_METHOD = 'card';

	/** The card a purchase is paid with, which is Stripe's test card. */
	public const TEST_CARD = [
		'number' => '4242424242424242',
		'expiry' => '12/34',
		'cvc'    => '123',
	];

	/** The container the gateway renders its checkout into on the WooCommerce checkout page. */
	private const CHECKOUT_CONTAINER = '#kco-iframe';

	/** Kustom's own two iframes inside that container: the checkout, and what it draws over it. */
	private const CHECKOUT_IFRAME   = '#klarna-checkout-iframe';
	private const FULLSCREEN_IFRAME = '#klarna-fullscreen-iframe';

	/** How long to keep advancing Kustom's screens before giving up, in seconds. */
	private const KUSTOM_TIMEOUT = 180;

	/** How long to wait between reading Kustom's screen and reading it again. */
	private const KUSTOM_POLL = 1_000_000; // 1s

	/**
	 * How many polls to spend on one screen before calling it stuck. Generous on
	 * purpose: a screen that is merely slow is the common case, and a screen nothing can
	 * be done with is caught by the unknown count below rather than by this.
	 */
	private const KUSTOM_SCREEN_ATTEMPTS = 90;

	/**
	 * How many polls to leave the purchase button alone between presses. Pressing it is
	 * not what finishes the purchase, waiting is, and Kustom keeps the screen up while it
	 * works.
	 */
	private const KUSTOM_PAY_PATIENCE = 5;

	/** How many polls to sit on a screen we have no move for before failing. */
	private const KUSTOM_UNKNOWN_POLLS = 15;

	/** Which of those polls tries the obvious way on. */
	private const KUSTOM_UNKNOWN_NUDGE = 5;

	/**
	 * How many polls to leave a sign-in screen alone before pressing its button again.
	 * Pressing twice starts the sign-in over, so a screen that is merely slow has to be
	 * told apart from one that swallowed the press.
	 */
	private const KUSTOM_SIGN_IN_PATIENCE = 8;

	/**
	 * The WooCommerce billing fields, keyed by the field Kustom asks for them under.
	 * Its inputs are `#billing-<key>`, filled straight rather than typed into: the form
	 * is React's, and only its own value setter tells it a field changed.
	 */
	private const KUSTOM_BILLING_FIELDS = [
		'email'      => 'email',
		'postcode'   => 'postal_code',
		'first_name' => 'given_name',
		'last_name'  => 'family_name',
		'address_1'  => 'street_address',
		'city'       => 'city',
		'phone'      => 'phone',
	];

	/** A Kustom screen before anything has been read. */
	private const EMPTY_KUSTOM_SCREEN = [
		'text'       => '',
		'buttons'    => [],
		'options'    => [],
		'empty'      => [],
		'suggestion' => false,
		'primary'    => null,
		'pay'        => null,
		'overlay'    => false,
		'loading'    => false,
	];

	/**
	 * Stripe's card form, which is an iframe of its own inside Kustom's checkout iframe.
	 * Its name carries a fresh number every time, so it is found by the title instead.
	 */
	private const CARD_IFRAME_TITLE = 'Secure payment input frame';

	/** The card fields in it, keyed by the TEST_CARD entry each one takes. */
	private const CARD_FIELDS = [
		'number' => '#payment-numberInput',
		'expiry' => '#payment-expiryInput',
		'cvc'    => '#payment-cvcInput',
	];

	/** Billing fields that are `<select>` rather than `<input>`. */
	private const BILLING_SELECT_FIELDS = [ 'country', 'state' ];

	/** The last thing read out of a window Kustom opened, for the failure message. */
	private array $lastKustomWindow = [];

	/** The address this purchase was filled in with, so a later step can refill it. */
	private array $kustomAddress = [];

	/** Sets the store options a scenario needs, keyed by option name. */
	public function haveStoreOptionsInDatabase( array $options ): void {
		foreach ( $options as $name => $value ) {
			$this->haveOptionInDatabase( $name, $value );
		}
	}

	/** Creates the tax classes and rates a scenario needs. */
	public function haveTaxClassesInDatabase( array $rates ): void {
		foreach ( $rates as $rate ) {
			$this->haveTaxClassInDatabase( $rate );
		}
	}

	/**
	 * Creates the products a scenario needs and adds them to the cart. An item is a
	 * SKU from TestProducts or `[ SKU, quantity ]`.
	 *
	 * @return array<string, int> The created product ids, keyed by SKU.
	 */
	public function haveCartWith( array $items ): array {
		$product_ids = [];

		foreach ( $items as $item ) {
			[ $sku, $quantity ] = is_array( $item ) ? [ $item[0], $item[1] ?? 1 ] : [ $item, 1 ];

			$product_ids[ $sku ] = $this->haveProductInDatabase( $sku );

			// WooCommerce's own add-to-cart, which leaves the cart in the session the
			// checkout reads. Doing it over wc-ajax builds a second cart instead.
			$this->amOnPage( "/?add-to-cart={$product_ids[ $sku ]}&quantity={$quantity}" );
		}

		return $product_ids;
	}

	/** Opens the WooCommerce checkout page. */
	public function amOnCheckoutPage(): void {
		$this->amOnPage( '/checkout/' );
	}

	/**
	 * Opens the checkout and waits for the gateway to render its checkout, which is the
	 * gateway availability assertion. The container is the plugin's, the iframe in it is
	 * Kustom's answer having arrived.
	 */
	public function amOnCheckoutPageWithGateway( int $timeout = 30 ): void {
		$this->amOnCheckoutPage();
		$this->waitForElement( self::CHECKOUT_CONTAINER, $timeout );
		$this->waitForElement( self::CHECKOUT_CONTAINER . ' ' . self::CHECKOUT_IFRAME, $timeout );
	}

	/**
	 * Opens the checkout and waits for another gateway's radio to render, for the
	 * scenarios that buy with something other than the gateway under test.
	 */
	public function amOnCheckoutPageWithPaymentMethod( string $paymentMethodId, int $timeout = 15 ): void {
		$this->amOnCheckoutPage();
		$this->waitForElement( "#payment_method_{$paymentMethodId}", $timeout );
	}

	/**
	 * Fills the customer's details in Kustom's checkout and leaves it on the screen that
	 * offers the payment methods.
	 *
	 * This is the WooCommerce billing form's replacement, not a way to it: the plugin's
	 * own `#billing_*` inputs sit off-screen and are written from Kustom's order right
	 * before the order is submitted, so the address a purchase ends up with is the one
	 * typed here.
	 */
	public function fillBillingAddressForm( array $overrides = [] ): void {
		$this->kustomAddress = array_replace(
			self::BILLING_ADDRESS,
			[ 'email' => $this->uniqueShopperEmail() ],
			$overrides
		);

		$this->driveKustomCheckout( $this->kustomAddress, self::DEFAULT_KUSTOM_METHOD, false );
	}

	/**
	 * An address Kustom has never seen before, which is what decides whether its sign-in
	 * takes the guest path or asks for the BankID app the shopper signed up with. A
	 * scenario that overrides the email is on its own for that.
	 */
	private function uniqueShopperEmail(): string {
		return 'kco-e2e+' . bin2hex( random_bytes( 6 ) ) . '@example.com';
	}

	/** Waits for the checkout to settle after any pending `update_checkout` call. */
	public function waitForCheckoutReady( int $timeout = 20 ): void {
		$this->switchToIFrame();

		$this->waitForJS(
			"return typeof jQuery !== 'undefined'"
			. " && jQuery.active === 0"
			. " && !document.querySelector('form.checkout .blockUI')"
			. " && !!document.querySelector('" . self::CHECKOUT_CONTAINER . ' ' . self::CHECKOUT_IFRAME . "');",
			$timeout
		);
	}

	/**
	 * Buys with Kustom's own purchase button, through whatever it asks for on the way,
	 * and leaves the browser where it sends it.
	 */
	public function placeOrder( string $paymentMethod = self::DEFAULT_KUSTOM_METHOD ): void {
		$this->driveKustomCheckout(
			$this->kustomAddress === [] ? self::BILLING_ADDRESS : $this->kustomAddress,
			$paymentMethod,
			true
		);
	}

	/** Waits for the browser to land on the thank you page. */
	public function waitForThankYouPage( int $timeout = 60 ): void {
		$this->switchToIFrame();
		$this->waitForJS( "return location.pathname.indexOf('order-received') !== -1;", $timeout );
	}

	/** The order the thank you page belongs to, asserted to exist before it is returned. */
	public function grabOrderIdFromThankYouPage(): int {
		$order_id = $this->grabFromCurrentUrl( '/\/checkout\/order-received\/(\d+)\//' );
		if ( $order_id === null ) {
			Assert::fail( 'Could not extract order ID from URL' );
		}

		$this->seeInDatabase(
			'wp_posts',
			[
				'ID'        => $order_id,
				'post_type' => 'shop_order',
			]
		);

		return (int) $order_id;
	}

	/** Verify a WooCommerce order once we are on the thank you page. */
	public function verifyOrderOnThankYouPage( string $paymentMethod, string $orderTotal, array $expectedMeta = [] ): void {
		$order_id = $this->grabOrderIdFromThankYouPage();

		$expectedMeta = array_merge(
			[
				'_payment_method' => $paymentMethod,
				'_order_total'    => $orderTotal,
			],
			$expectedMeta
		);

		foreach ( $expectedMeta as $meta_key => $meta_value ) {
			// Read back rather than asserted, so a failure reports the value
			// WooCommerce actually wrote.
			$actual = $this->grabFromDatabase(
				'wp_postmeta',
				'meta_value',
				[
					'post_id'  => $order_id,
					'meta_key' => $meta_key,
				]
			);

			Assert::assertSame(
				$meta_value,
				$actual,
				"Order {$order_id} has {$meta_key} = " . var_export( $actual, true )
					. ", expected " . var_export( $meta_value, true )
			);
		}
	}

	/**
	 * Advances Kustom's checkout until it offers the payment methods, or, with `$buy`,
	 * until the browser lands on the thank you page.
	 */
	private function driveKustomCheckout( array $address, string $paymentMethod, bool $buy ): void {
		$deadline = microtime( true ) + self::KUSTOM_TIMEOUT;
		$path     = [];
		$attempts = [];
		$windows  = [];
		$unknown  = 0;
		$screen   = [];

		// Held for the whole purchase rather than read back as "the first window": the
		// order the driver lists them in changes once Kustom has opened and closed its
		// own, and the checkout is not necessarily first again afterwards.
		$checkout = $this->grabCheckoutWindow();

		while ( microtime( true ) < $deadline ) {
			// The sign-in window Kustom opens over the purchase, which nothing on the
			// checkout can be read or clicked through.
			if ( $this->advanceKustomSignIn( $checkout, $path, $windows ) ) {
				usleep( self::KUSTOM_POLL );
				continue;
			}

			$page = $this->readTopWindow( $checkout );

			if ( str_contains( $page['uri'], 'order-received' ) ) {
				$this->comment( 'kustom: ' . implode( ' -> ', $path ) . ' -> order received' );
				return;
			}

			// No iframe yet, or Kustom is between documents.
			if ( ! $page['hasIframe'] ) {
				usleep( self::KUSTOM_POLL );
				continue;
			}

			$screen = $this->readKustomScreen();
			$name   = $screen['name'];

			if ( end( $path ) !== $name ) {
				$path[] = $name;
				$this->comment( "kustom: {$name}" );
			}

			// The payment screen is where a purchase stops until it is asked to buy.
			if ( ! $buy && $name === 'pay' ) {
				$this->comment( 'kustom: ' . implode( ' -> ', $path ) );
				return;
			}

			// Kustom is working, or has drawn something we have no move for.
			if ( $name === 'busy' || $name === 'unknown' ) {
				$unknown = $name === 'unknown' ? $unknown + 1 : 0;

				if ( $unknown > self::KUSTOM_UNKNOWN_POLLS ) {
					$this->failOnKustomScreen( 'reached a screen it has no move for', $screen, $path );
				}

				// One nudge at the obvious way on before giving up.
				if ( $unknown === self::KUSTOM_UNKNOWN_NUDGE ) {
					$this->comment( 'kustom: unknown screen, trying the obvious way on' );
					$this->clickKustomOnward();
				}

				usleep( self::KUSTOM_POLL );
				continue;
			}
			$unknown = 0;

			$attempts[ $name ] = ( $attempts[ $name ] ?? 0 ) + 1;
			if ( $attempts[ $name ] > self::KUSTOM_SCREEN_ATTEMPTS ) {
				$this->failOnKustomScreen( "cannot get past the '{$name}' screen", $screen, $path );
			}

			$this->advanceKustomScreen( $screen, $address, $paymentMethod, $attempts[ $name ] );

			usleep( self::KUSTOM_POLL );
		}

		$this->failOnKustomScreen(
			$buy ? 'never reached the order received page' : 'never offered the payment methods',
			$screen,
			$path
		);
	}

	/**
	 * Acts on a screen `readKustomScreen()` recognised. `$poll` counts how long it has
	 * been up, which is how the purchase button is told apart from the moves that can be
	 * repeated freely.
	 */
	private function advanceKustomScreen( array $screen, array $address, string $paymentMethod, int $poll ): void {
		switch ( $screen['name'] ) {
			case 'overlay':
				// Kustom lost the sign-in window it opened and is offering to open it again.
				$this->clickKustomFullscreenButton( 'Continue' );
				return;

			case 'details':
				$this->fillKustomBillingFields( $address );
				return;

			case 'suggest':
				// The address a scenario asked for, not the one Kustom would rather have.
				$this->clickKustomSelector( '#button-primary' );
				return;

			case 'continue':
				$this->clickKustomSelector( '#button-primary' );
				return;

			case 'pay':
				// Selecting and buying are separate polls: the option is read back before
				// the purchase, since Kustom drops clicks while it is redrawing.
				if ( ! $this->hasKustomOptionSelected( $paymentMethod ) ) {
					$this->comment( "kustom: selecting {$paymentMethod}" );
					$this->selectKustomPaymentOption( $paymentMethod );
					return;
				}

				// A card is asked for on the payment screen itself rather than on one of
				// its own, so filling it is a move on this screen like any other.
				if ( $this->fillKustomCardFields() ) {
					return;
				}

				if ( $poll % self::KUSTOM_PAY_PATIENCE === 1 % self::KUSTOM_PAY_PATIENCE ) {
					$this->comment( 'kustom: pressing pay' );
					$this->clickKustomPayButton();
				}

				return;
		}
	}

	/**
	 * Fills whatever of the address Kustom is asking for, then continues. It asks in
	 * rounds, so this runs once per round rather than once per purchase.
	 */
	private function fillKustomBillingFields( array $address ): void {
		$values = [];
		foreach ( self::KUSTOM_BILLING_FIELDS as $field => $key ) {
			if ( isset( $address[ $field ] ) ) {
				$values[ "billing-{$key}" ] = (string) $address[ $field ];
			}
		}

		$this->kustomJs(
			'const values = ' . json_encode( $values ) . ';'
			. ' for (const id in values) {'
			. '   const el = document.getElementById(id);'
			. '   if (el && kuVisible(el) && !el.value) { kuFill(el, values[id]); }'
			. ' }'
			. ' return true;'
		);

		$this->clickKustomSelector( '#button-primary' );
	}

	/**
	 * Fills Stripe's card form if it is on the screen and still empty, and says whether
	 * it did.
	 *
	 * Typed rather than written: Stripe's fields keep their own state and ignore a value
	 * set on the input, so these are the one place the driver uses the browser's own
	 * keyboard instead of the page's setter.
	 */
	private function fillKustomCardFields(): bool {
		$frame = (string) $this->kustomJs(
			"const frame = Array.from(document.querySelectorAll('iframe'))"
			. " .find(el => el.title === '" . self::CARD_IFRAME_TITLE . "' && kuVisible(el));"
			. " return frame ? frame.name : '';"
		);

		if ( $frame === '' ) {
			$this->comment( 'kustom: no card form on screen' );
			return false;
		}

		try {
			$this->switchToIFrame( $frame );

			$empty = $this->executeJS(
				'return ' . json_encode( self::CARD_FIELDS )
				. ' && Object.fromEntries(Object.entries(' . json_encode( self::CARD_FIELDS ) . ')'
				. '   .filter(([field, selector]) => {'
				. '     const el = document.querySelector(selector);'
				. '     return el && !el.value;'
				. '   }));'
			);
		} catch ( ElementNotFound | WebDriverException $e ) {
			$this->comment( 'kustom: card form would not open' );
			$this->switchToCheckoutIFrame();

			return false;
		}

		$empty = is_array( $empty ) ? $empty : [];

		foreach ( $empty as $field => $selector ) {
			$this->fillField( $selector, self::TEST_CARD[ $field ] );
		}

		// Back out to the checkout, which is where every other move is made. Left in
		// Stripe's frame, the purchase button is simply not in the document.
		$this->switchToCheckoutIFrame();

		return $empty !== [];
	}

	/** Puts the browser back in Kustom's checkout iframe. */
	private function switchToCheckoutIFrame(): void {
		$this->switchToIFrame();
		$this->switchToIFrame( self::CHECKOUT_IFRAME );
	}

	/** Picks a payment option, matched on Kustom's own option id and on its card's text. */
	private function selectKustomPaymentOption( string $paymentMethod ): bool {
		$needle = addslashes( $paymentMethod );

		return $this->clickKustomElement( "(kuOption('{$needle}') || {}).card" );
	}

	/** Whether the payment screen has the method we asked for selected. */
	private function hasKustomOptionSelected( string $paymentMethod ): bool {
		$needle = addslashes( $paymentMethod );

		return (bool) $this->kustomJs(
			"const option = kuOption('{$needle}');"
			. ' return option ? kuChecked(option) : false;'
		);
	}

	/** Presses Kustom's own purchase button. */
	private function clickKustomPayButton(): bool {
		return $this->clickKustomElement(
			'kuButtons().find(button => /pay order|betala|k[oö]p|buy/i.test(kuLabel(button)))'
		);
	}

	/**
	 * Drives the window Kustom opens to sign the shopper in and confirm the purchase,
	 * and says whether one was there.
	 *
	 * Nothing is typed into it. Its national number field is the playground's way of
	 * choosing an identity, and left empty it signs in as a generated Swedish test
	 * shopper, which is the one a test can use: any real number wants a BankID app.
	 *
	 * @param string            $checkout The window the checkout itself is in.
	 * @param list<string>      $path     The screens seen so far, appended to.
	 * @param array<string,int> $attempts How long each of its screens has been up.
	 */
	private function advanceKustomSignIn( string $checkout, array &$path, array &$attempts ): bool {
		$windows = array_values( array_diff( $this->grabWindowHandles(), [ $checkout ] ) );
		if ( $windows === [] ) {
			return false;
		}

		$acted = false;

		foreach ( $windows as $handle ) {
			try {
				$this->switchToWindowHandle( $handle );
				$this->switchToIFrame();

				$window = $this->kustomJs(
					<<<'JS'
					const buy = document.querySelector('#buy_button, [data-testid="confirm-and-pay"]');
					const signIn = document.querySelector('#signInWithBankIdButton');

					return {
						name: (buy && kuVisible(buy)) ? 'confirm'
							: ((signIn && kuVisible(signIn)) ? 'sign-in' : (kuButtons().length ? 'buttons' : 'busy')),
						url: location.host + location.pathname,
						buttons: kuButtons().map(kuLabel).filter(label => label !== ''),
						text: kuText(),
					};
					JS
				);

				$window = is_array( $window ) ? $window : [];
				$name   = (string) ( $window['name'] ?? 'busy' );

				// The one worth reporting is whichever window is asking for something;
				// Kustom keeps a second one on a loader for the whole purchase.
				if ( $name !== 'busy' ) {
					$this->lastKustomWindow = $window;
				}

				if ( end( $path ) !== "window:{$name}" ) {
					$path[] = "window:{$name}";
					$this->comment( "kustom: window:{$name}" );
				}

				$acted = true;

				// Act when a screen first appears, and again only once it has sat there
				// long enough to have swallowed the press rather than be working on it.
				$key  = "{$handle}:{$name}";
				$seen = $attempts[ $key ] ?? 0;

				$attempts[ $key ] = $seen + 1;

				// Its screens are pressed only once they have been up for a poll: pressed
				// the moment they render, the sign-in offers the app rather than the
				// guest path, and pressed twice it starts over.
				if ( $seen === 0 || ( $seen > 1 && $seen % self::KUSTOM_SIGN_IN_PATIENCE !== 0 ) ) {
					continue;
				}

				if ( $name === 'confirm' ) {
					$this->clickKustomElement( "document.querySelector('#buy_button, [data-testid=\"confirm-and-pay\"]')" );
				} elseif ( $name === 'sign-in' ) {
					// Activated once, natively. The pointer sequence the rest of the
					// driver needs counts as a second press here, and the second press
					// is what turns the guest sign-in into a BankID app prompt.
					$this->activateKustomElement( "document.querySelector('#signInWithBankIdButton')" );
				} elseif ( $name === 'buttons' ) {
					$this->clickKustomOnward();
				}
			} catch ( WebDriverException $e ) {
				// The window closed itself while we were reading it, which is the flow
				// having moved on rather than a failure.
				continue;
			}
		}

		try {
			$this->switchToWindowHandle( $checkout );
		} catch ( WebDriverException $e ) {
			$this->comment( 'kustom: lost the checkout window' );
		}

		return $acted;
	}

	/**
	 * What the top window is doing, read outside any iframe.
	 *
	 * @return array{uri: string, hasIframe: bool}
	 */
	private function readTopWindow( string $checkout ): array {
		try {
			$this->switchToWindowHandle( $checkout );
			$this->switchToIFrame();

			$window = $this->executeJS(
				'return { uri: location.pathname + location.search, hasIframe: !!document.querySelector("'
				. self::CHECKOUT_IFRAME . '") };'
			);
		} catch ( WebDriverException $e ) {
			// The window is between documents; read it again next poll.
			return [
				'uri'       => '',
				'hasIframe' => false,
			];
		}

		$window = is_array( $window ) ? $window : [];

		return [
			'uri'       => (string) ( $window['uri'] ?? '' ),
			'hasIframe' => (bool) ( $window['hasIframe'] ?? false ),
		];
	}

	/**
	 * Everything we branch on inside Kustom's checkout iframe, in one round trip, plus
	 * the name of the screen it adds up to. Leaves the browser switched into the iframe.
	 *
	 * @return array{name: string, text: string, buttons: list<string>, options: list<string>, empty: list<string>, suggestion: bool, primary: ?array, pay: ?array, overlay: bool, loading: bool}
	 */
	private function readKustomScreen(): array {
		// Kustom draws its own overlay in a second iframe over the checkout, and nothing
		// underneath it can be acted on while it is up.
		if ( $this->readKustomOverlay() ) {
			return array_replace( self::EMPTY_KUSTOM_SCREEN, [ 'name' => 'overlay' ] );
		}

		try {
			$this->switchToCheckoutIFrame();

			$screen = $this->kustomJs(
				<<<'JS'
				const buttons = kuButtons().map(button => ({
					label: kuLabel(button),
					id: button.id || '',
					disabled: button.disabled === true || button.getAttribute('aria-disabled') === 'true',
				}));

				const primary = document.querySelector('#button-primary');
				const pay = kuButtons().find(button => /pay order|betala|k[oö]p|buy/i.test(kuLabel(button)));

				return {
					text: kuText(),
					// The furniture Kustom keeps on every screen says nothing about which
					// one it is, so it is not counted as something to act on.
					buttons: buttons
						.map(button => button.label)
						.filter(label => label !== '' && !/test data|manage autofill|view details|change/i.test(label)),
					// The payment offers, named for the failure message and for picking one.
					options: kuOptions().map(option => kuOptionName(option)),
					// The fields it is asking for this round, which is the ones still empty.
					empty: Array.from(document.querySelectorAll('input[id^="billing-"]'))
						.filter(el => kuVisible(el) && !el.value)
						.map(el => el.id),
					// Its own suggestion for an address it did not recognise.
					suggestion: kuButtons().some(button => /use this address/i.test(kuLabel(button))),
					primary: primary && kuVisible(primary)
						? { label: kuLabel(primary), disabled: primary.disabled === true }
						: null,
					pay: pay ? { label: kuLabel(pay), disabled: false } : null,
					overlay: false,
					loading: !!document.querySelector('[class*="spinner"], [class*="loader"]'),
				};
				JS
			);
		} catch ( ElementNotFound | WebDriverException $e ) {
			// The iframe reloaded or went away; treat it as still working, since the next
			// poll reads the top window where success is decided.
			$screen = [ 'loading' => true ];
		}

		$screen = array_replace( self::EMPTY_KUSTOM_SCREEN, is_array( $screen ) ? $screen : [] );

		$screen['name'] = $this->nameKustomScreen( $screen );

		return $screen;
	}

	/** Whether Kustom's fullscreen iframe is showing something, and what it says. */
	private function readKustomOverlay(): bool {
		try {
			$this->switchToIFrame();
			$this->switchToIFrame( self::FULLSCREEN_IFRAME );

			return (bool) $this->kustomJs( 'return kuButtons().length > 0;' );
		} catch ( ElementNotFound | WebDriverException $e ) {
			return false;
		}
	}

	/**
	 * Names the screen the iframe is showing. Order matters: the payment offers and the
	 * purchase button share a screen with the address Kustom has already taken.
	 */
	private function nameKustomScreen( array $screen ): string {
		if ( $screen['suggestion'] ) {
			return 'suggest';
		}

		if ( $screen['options'] !== [] ) {
			return 'pay';
		}

		if ( $screen['empty'] !== [] ) {
			return 'details';
		}

		if ( $screen['primary'] !== null && ! $screen['primary']['disabled'] ) {
			return 'continue';
		}

		// Nothing to act on at all is Kustom still drawing, not an unknown screen.
		if ( $screen['loading'] || $screen['buttons'] === [] ) {
			return 'busy';
		}

		return 'unknown';
	}

	/** Clicks the first visible element matching any of the given selectors. */
	private function clickKustomSelector( string ...$selectors ): bool {
		$list = addslashes( implode( ', ', $selectors ) );

		return $this->clickKustomElement( "Array.from(document.querySelectorAll('{$list}')).find(kuVisible)" );
	}

	/** Clicks a button in Kustom's fullscreen overlay, which is a second iframe. */
	private function clickKustomFullscreenButton( string $text ): bool {
		$needle = addslashes( $text );

		$this->switchToIFrame();
		$this->switchToIFrame( self::FULLSCREEN_IFRAME );

		return $this->clickKustomElement(
			"kuButtons().find(button => kuLabel(button).toLowerCase().includes('{$needle}'.toLowerCase()))"
		);
	}

	/**
	 * Clicks whatever looks like the way on, for a screen we have no rule for. Anything
	 * that reads like a way back is left alone.
	 */
	private function clickKustomOnward(): bool {
		return $this->clickKustomElement(
			<<<'JS'
			(() => {
				const away = /cancel|close|back|change|another|other|disconnect|edit|remove|avbryt|st[aä]ng/i;
				const onward = /continue|confirm|pay|next|done|approve|accept|ok|buy|forts[aä]tt|betala|bankid/i;
				const buttons = kuButtons().filter(button => !away.test(kuLabel(button)));

				return buttons.find(button => onward.test(kuLabel(button)))
					|| (buttons.length === 1 ? buttons[0] : null);
			})()
			JS
		);
	}

	/** Activates the element a JavaScript expression finds, once, with nothing around it. */
	private function activateKustomElement( string $findJs ): bool {
		return (bool) $this->kustomJs(
			"const el = {$findJs};"
			. ' if (!el) return false;'
			. " el.scrollIntoView({ block: 'center' });"
			. ' el.click();'
			. ' return true;'
		);
	}

	/**
	 * Clicks the element a JavaScript expression finds, through kuClick: the label spans
	 * over Kustom's offer cards swallow the driver's own click.
	 */
	private function clickKustomElement( string $findJs ): bool {
		return (bool) $this->kustomJs(
			"const el = {$findJs};"
			. ' if (!el) return false;'
			. " el.scrollIntoView({ block: 'center' });"
			. ' return kuClick(el);'
		);
	}

	/** Runs a script in whatever frame the browser is in, with the ku* helpers in scope. */
	private function kustomJs( string $script ) {
		return $this->executeJS(
			<<<'JS'
			// Kustom leaves covered screens and closed dialogs in the DOM, both marked
			// aria-hidden, and the plugin's own WooCommerce form is parked off-screen.
			const kuVisible = el => {
				if (!el || !el.getClientRects().length) return false;

				const style = getComputedStyle(el);
				if (style.visibility === 'hidden' || style.display === 'none' || Number(style.opacity) === 0) {
					return false;
				}

				return !el.closest('[aria-hidden="true"], [inert]');
			};
			const kuButtons = () => Array.from(document.querySelectorAll('button, [role="button"], input[type="submit"]'))
				.filter(kuVisible)
				.filter(button => button.disabled !== true && button.getAttribute('aria-disabled') !== 'true');
			const kuLabel = el => ((el.innerText || el.getAttribute('aria-label') || el.title || '') + '')
				.replace(/\s+/g, ' ')
				.trim()
				.slice(0, 80);
			// The country picker's several hundred options would be the whole of it.
			const kuText = () => {
				const body = document.body ? document.body.cloneNode(true) : null;
				if (!body) return '';

				body.querySelectorAll('select, option, style, script').forEach(el => el.remove());

				return (body.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 600);
			};
			// A payment offer is a radio, which carries Kustom's own id, plus the card
			// around it, which carries the click handler and the wording.
			const kuNeedle = value => (value + '').toLowerCase().replace(/[^a-z0-9]/g, '');
			const kuOptions = () => Array.from(document.querySelectorAll('input[type="radio"], [role="radio"]'))
				.map(radio => ({ radio, card: radio.closest('label') || radio.parentElement || radio }))
				.filter(option => kuVisible(option.card));
			const kuOptionKey = option => kuNeedle([
				option.radio.id || '',
				option.radio.value || '',
				option.radio.getAttribute('data-testid') || '',
			].join(' '));
			const kuOptionName = option => (option.radio.id || kuLabel(option.card) || kuOptionKey(option));
			const kuOption = target => {
				const needle = kuNeedle(target);
				const options = kuOptions();

				return options.find(option => kuOptionKey(option).includes(needle))
					|| options.find(option => kuNeedle(kuLabel(option.card)).includes(needle))
					// One offer and no match: it is the only way on.
					|| (options.length === 1 ? options[0] : null);
			};
			const kuChecked = option => option.radio.checked === true
				|| option.radio.getAttribute('aria-checked') === 'true';
			// React tracks its own value, so a plain assignment leaves the field looking
			// empty to the form it belongs to.
			const kuFill = (el, value) => {
				if (!el) return false;

				const proto = el instanceof HTMLTextAreaElement
					? HTMLTextAreaElement.prototype
					: (el instanceof HTMLSelectElement ? HTMLSelectElement.prototype : HTMLInputElement.prototype);

				el.focus();
				Object.getOwnPropertyDescriptor(proto, 'value').set.call(el, value);
				for (const type of ['input', 'change', 'blur']) {
					el.dispatchEvent(new Event(type, { bubbles: true }));
				}

				return true;
			};
			const kuClick = el => {
				const box = el.getBoundingClientRect();
				const init = {
					bubbles: true,
					cancelable: true,
					composed: true,
					view: window,
					clientX: box.left + box.width / 2,
					clientY: box.top + box.height / 2,
					button: 0,
					buttons: 0,
				};
				for (const type of ['pointerdown', 'mousedown', 'pointerup', 'mouseup', 'click']) {
					const Ctor = type.startsWith('pointer') ? PointerEvent : MouseEvent;
					try { el.dispatchEvent(new Ctor(type, { ...init, pointerType: 'mouse' })); }
					catch (e) { el.dispatchEvent(new MouseEvent(type, init)); }
				}

				// Native activation on top: the offer cards react only to the sequence
				// above, the purchase button only to this.
				try { el.click(); } catch (e) { /* not every element has one */ }

				return true;
			};
			JS
			. "\n" . $script
		);
	}

	/**
	 * The window the checkout is in, taken while it is the only one open. Kustom opens
	 * and closes windows of its own from here on, and the driver's list of them is in no
	 * order the checkout can be found by afterwards.
	 */
	private function grabCheckoutWindow(): string {
		$windows = $this->grabWindowHandles();

		Assert::assertNotEmpty( $windows, 'The browser has no window open.' );

		return $windows[0];
	}

	/** Every window the browser has open. */
	private function grabWindowHandles(): array {
		$handles = [];

		$this->executeInSelenium(
			static function ( $webDriver ) use ( &$handles ) {
				$handles = $webDriver->getWindowHandles();
			}
		);

		return is_array( $handles ) ? array_values( $handles ) : [];
	}

	/** Moves the driver to one of those windows. */
	private function switchToWindowHandle( string $handle ): void {
		$this->executeInSelenium(
			static function ( $webDriver ) use ( $handle ) {
				$webDriver->switchTo()->window( $handle );
			}
		);
	}

	/** Fails the test with everything we know about the screen we stopped on. */
	private function failOnKustomScreen( string $why, array $screen, array $path ): void {
		$window = $this->lastKustomWindow === []
			? ''
			: sprintf(
				"\nIts own window was on %s\n  buttons: %s\n  showing: %s",
				$this->lastKustomWindow['url'] ?? '?',
				empty( $this->lastKustomWindow['buttons'] ) ? 'none' : implode( ' | ', $this->lastKustomWindow['buttons'] ),
				$this->lastKustomWindow['text'] ?? '(nothing read)'
			);

		Assert::fail(
			sprintf(
				"Kustom's checkout %s.\nScreens: %s\nButtons: %s\nPayment options: %s\nAsking for: %s\nOn screen: %s%s",
				$why,
				$path === [] ? 'none' : implode( ' -> ', $path ),
				empty( $screen['buttons'] ) ? 'none' : implode( ' | ', $screen['buttons'] ),
				empty( $screen['options'] ) ? 'none' : implode( ' | ', $screen['options'] ),
				empty( $screen['empty'] ) ? 'nothing' : implode( ' | ', $screen['empty'] ),
				$screen['text'] ?? '(nothing read)',
				$window
			)
		);
	}
}
