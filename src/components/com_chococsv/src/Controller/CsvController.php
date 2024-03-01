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

namespace AlexApi\Component\Chococsv\Site\Controller;

use AlexApi\Component\Chococsv\Administrator\Command\DeployArticleCommand;
use AlexApi\Component\Chococsv\Administrator\Domain\Model\State\DeployArticleCommandState;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Registry\Registry;

defined('_JEXEC') || die;

final class CsvController extends BaseController
{
    public function deploy()
    {
        // Wether or not to show ASCII banner true to show , false otherwise. Default is to show the ASCII art banner
        $givenShowAsciiBanner = (bool)$this->getParams()->get('show_ascii_banner', 0);

// Silent mode
// 0: hide both response result and key value pairs
// 1: show response result only
// 2: show key value pairs only
// Set to 0 if you want to squeeze out performance of this script to the maximum
        $givenSilent = (int)$this->getParams()->get('silent_mode', 0);


// Do you want a report after processing?
// 0: no report, 1: success & errors, 2: errors only
// When using report feature. Silent mode MUST be set to 1. Otherwise you might have unexpected results.
// Set to 0 if you want to squeeze out performance of this script to the maximum
// If enabled, this will create a output.json file
        $givenSaveReportToFile = (int)$this->getParams()->get('save_report_to_file', 0);

        $givenDestinations = (array)$this->getParams()->get('destinations', []);

        $deployArticleCommandState = DeployArticleCommandState::fromState(
            $givenDestinations,
            $givenSilent,
            $givenSaveReportToFile
        );
        $deployArticleCommandState->withAsciiBanner($givenShowAsciiBanner);
        $command = DeployArticleCommand::fromState($deployArticleCommandState);
        $command->deploy();
    }

    private function getParams(): Registry
    {
        return ComponentHelper::getParams($this->option ?? 'com_chococsv', true);
    }
}
