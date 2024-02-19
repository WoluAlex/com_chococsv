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
use Joomla\CMS\MVC\Controller\BaseController;

defined('_JEXEC') || die;

final class CsvController extends BaseController
{
    public function deploy()
    {
        $command = new DeployArticleCommand();
        $command->deploy();
    }
}
