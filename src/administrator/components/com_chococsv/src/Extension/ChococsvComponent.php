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

namespace AlexApi\Component\Chococsv\Administrator\Extension;

defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Psr\Container\ContainerInterface;

/**
 * Component class for com_chococsv
 *
 * @since  0.1.0
 */
class ChococsvComponent
    extends MVCComponent
    implements BootableExtensionInterface

{

    /**
     * Booting the extension. This is the function to set up the environment of the extension like
     * registering new class loaders, etc.
     *
     * If required, some initial set up can be done from services of the container, eg.
     * registering HTML services.
     *
     * @param ContainerInterface $container The container
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function boot(ContainerInterface $container)
    {
        //NO-OP
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
