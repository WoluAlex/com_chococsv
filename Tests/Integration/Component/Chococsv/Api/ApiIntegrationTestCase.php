<?php

declare(strict_types=1);
/**
 * @copyright (c) 2009 - present. Mr Alexandre J-S William ELISÉ. All rights reserved.
 * @license       AGPL-3.0-or-later
 * @link          https://apiadept.com
 */

namespace Tests\Integration\Component\Chococsv\Api;

use Joomla\Application\WebApplicationInterface;
use Joomla\CMS\Application\ApiApplication;
use Joomla\CMS\Factory;
use Joomla\Session\Session;
use Joomla\Session\SessionInterface;
use Tests\Integration\IntegrationTestCase;

abstract class ApiIntegrationTestCase extends IntegrationTestCase
{
    protected WebApplicationInterface $app;

    protected function setUp(): void
    {
        parent::setUp();

        // Boot the DI container
        $container = Factory::getContainer();

        /*
         * Alias the session service keys to the web session service as that is the primary session backend for this application
         *
         * In addition to aliasing "common" service keys, we also create aliases for the PHP classes to ensure autowiring objects
         * is supported.  This includes aliases for aliased class names, and the keys for aliased class names should be considered
         * deprecated to be removed when the class name alias is removed as well.
         */
        $container->alias('session', 'session.cli')
            ->alias('JSession', 'session.cli')
            ->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
            ->alias(Session::class, 'session.cli')
            ->alias(SessionInterface::class, 'session.cli');

// Instantiate the application.
        $app = $container->get(ApiApplication::class);

// Set the application as global app
        Factory::$application = $app;

        $this->app = $app;

        $component = $app->bootComponent('joomlaextensionboilerplate');

        $this->app->set('dbo', $this->getTestDatabaseInstance());
    }

}
