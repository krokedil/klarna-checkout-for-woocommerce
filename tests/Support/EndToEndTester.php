<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Inherited Methods
 * @method void wantTo($text)
 * @method void wantToTest($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method void pause($vars = [])
 *
 * @SuppressWarnings(PHPMD)
*/
class EndToEndTester extends \Codeception\Actor
{
    use _generated\EndToEndTesterActions;

    // The steps a browser test is written out of. Each trait is one half of the
    // flow: shape the store, buy through the checkout, then manage the order.
    use Traits\CanManageE2EProducts;
    use Traits\CanManageE2ETaxRates;
    use Traits\CanDriveE2ECheckout;
    use Traits\CanDriveE2EOrderManagement;
}
