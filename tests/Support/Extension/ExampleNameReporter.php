<?php

declare(strict_types=1);

namespace Tests\Support\Extension;

use Codeception\Event\TestEvent;
use Codeception\Events;
use Codeception\Extension;
use Codeception\TestInterface;
use Qameta\Allure\Allure;

/**
 * Names each data provider row in the Allure report after the provider's own key,
 * which Codeception keeps as the test index but never reports.
 */
class ExampleNameReporter extends Extension
{
    public static array $events = [
        Events::TEST_BEFORE => 'nameByExample',
    ];

    public function nameByExample(TestEvent $event): void
    {
        $test = $event->getTest();

        if (! $test instanceof TestInterface) {
            return;
        }

        $index = $test->getMetadata()->getIndex();

        // An int index means the row had no key.
        if (! is_string($index) || trim($index) === '') {
            return;
        }

        Allure::displayName($index);
    }
}
