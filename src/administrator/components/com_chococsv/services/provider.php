<?php

declare(strict_types=1);
/**
 * Chococsv
 *
 * @package    Chococsv
 *
 * @author     Mr Alexandre J-S William ELISÉ <code@apiadept.com>
 * @copyright  Copyright (c) 2009 - present. https://apiadept.com. All rights reserved
 * @license    AGPL-3.0-or-later
 * @link       https://apiadept.com
 */

defined('_JEXEC') or die;

use AlexApi\Component\Chococsv\Administrator\Extension\ChococsvComponent;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

/**
 * The chococsv service provider.
 * https://github.com/joomla/joomla-cms/pull/20217
 *
 * @since  0.1.0
 */
return new class implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param Container $container The DI container.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function register(Container $container): void
    {
        $container->registerServiceProvider(
            new MVCFactory('\\AlexApi\\Component\\Chococsv')
        );
        $container->registerServiceProvider(
            new ComponentDispatcherFactory('\\AlexApi\\Component\\Chococsv')
        );

        $container->set(
            ComponentInterface::class,
            function (Container $innerContainer) {
                $component = new ChococsvComponent(
                    $innerContainer->get(ComponentDispatcherFactoryInterface::class)
                );

                $component->setMVCFactory($innerContainer->get(MVCFactoryInterface::class));

                return $component;
            }
        );
    }

    public function __debugInfo(): ?array
    {
        return null;
    }

    public function __serialize(): array
    {
        return [];
    }
};
