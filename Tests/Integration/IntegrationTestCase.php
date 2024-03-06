<?php

declare(strict_types=1);

/**
 * @package        Joomla.UnitTest
 *
 * @copyright  (C) 2019 Open Source Matters, Inc. <https://www.joomla.org>
 * @license        GNU General Public License version 2 or later; see LICENSE.txt
 * @link           http://www.phpunit.de/manual/current/en/installation.html
 */

namespace Tests\Integration;

use Joomla\Database\DatabaseInterface;
use PHPUnit\Framework\TestCase;
use Tests\Helper\DatabaseHelper;

require_once __DIR__ . '/bootstrap.php';

/**
 * Base Integration Test case for common behaviour across unit tests
 *
 * @since   4.0.0
 */
abstract class IntegrationTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('mysqli')) {
            static::markTestSkipped(
                'The MySQLi extension is not available',
            );
        }
    }


    protected function tearDown(): void
    {
        parent::tearDown();
        gc_collect_cycles();
    }


    protected function getTestDatabaseInstance(): DatabaseInterface
    {
        $testOptions = ['database' => 'bdd_chococsv'];

        return DatabaseHelper::createExternalInstance($testOptions);
    }

    public function __debugInfo(): ?array
    {
        return null;
    }

    public function __serialize(): array
    {
        return [];
    }

}
