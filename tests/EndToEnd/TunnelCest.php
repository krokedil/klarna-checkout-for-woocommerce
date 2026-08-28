<?php

declare(strict_types=1);

namespace Tests\EndToEnd;

use PHPUnit\Framework\Assert;
use Tests\Support\EndToEndTester;

/**
 * A browser reaches the tunnel once per purchase, on the confirmation URL Kustom sends
 * the shopper to, and has to land on the local site with the path and query it asked for.
 *
 * Broken, it reads as a purchase that never reaches the order received page.
 * See tests/_mu-plugins/01-kustom-public-url.php.
 */
class TunnelCest
{
	public function a_browser_that_arrives_on_the_tunnel_lands_on_the_local_site(EndToEndTester $I): void
	{
		$public = rtrim((string) ($_ENV['KCO_WORDPRESS_URL'] ?? ''), '/');
		$local  = rtrim((string) ($_ENV['WORDPRESS_URL'] ?? ''), '/');

		Assert::assertNotSame('', $public, 'KCO_WORDPRESS_URL is not set in tests/.env.');
		Assert::assertNotSame('', $local, 'WORDPRESS_URL is not set in tests/.env.');
		Assert::assertNotSame($public, $local, 'KCO_WORDPRESS_URL and WORDPRESS_URL are the same host.');

		// With a query string, which Kustom's confirmation URL carries the order key in.
		$path = '/shop/?kco-tunnel-probe=1';

		$I->amOnUrl($public);
		$I->amOnPage($path);

		Assert::assertSame(
			$local . $path,
			(string) $I->executeJS('return window.location.href;'),
			'A browser request through the tunnel did not land on the local site.'
		);

		// A rendered page rather than the driver's own error page.
		$I->seeElement('body.woocommerce-shop, body.post-type-archive-product, body');

		// Leave the driver where every other test expects it.
		$I->amOnUrl($local);
	}
}
