<?php

declare(strict_types=1);

/**
 * @copyright (c) 2009 - present. Mr Alexandre J-S William ELISÉ. All rights reserved.
 * @license       GNU Affero General Public License v3.0 or later (AGPL-3.0-or-later). See LICENSE.txt file
 */


namespace AlexApi\Component\Chococsv\Administrator\View\Chococsv;

// phpcs:disable PSR1.Files.SideEffects
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;

use function defined;

defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects

final class HtmlView extends \Joomla\CMS\MVC\View\HtmlView
{
    public function display($tpl = null): void
    {
        ToolbarHelper::title(Text::_('COM_CHOCOCSV'), 'table');

        if ($this->getCurrentUser()->authorise('core.manage', $this->option ?? 'com_chococsv')) {
            ToolbarHelper::preferences($this->option ?? 'com_chococsv');
        }

        parent::display($tpl);
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
