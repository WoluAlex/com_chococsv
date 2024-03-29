<?php

declare(strict_types=1);

/**
 * @copyright (c) 2009 - present. Mr Alexandre J-S William ELISÉ. All rights reserved.
 * @license       GNU Affero General Public License v3.0 or later (AGPL-3.0-or-later). See LICENSE.txt file
 */

namespace AlexApi\Component\Chococsv\Administrator\Controller;

use Joomla\CMS\MVC\Controller\BaseController;

use function defined;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects


final class DisplayController extends BaseController
{
    protected $default_view = 'chococsv';

    public function __debugInfo(): ?array
    {
        return null;
    }

    public function __serialize(): array
    {
        return [];
    }
}
