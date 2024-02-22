<?php

declare(strict_types=1);

/**
 * @copyright (c) 2009 - present. Mr Alexandre J-S William ELISÉ. All rights reserved.
 * @license       GNU Affero General Public License v3.0 or later (AGPL-3.0-or-later). See LICENSE.txt file
 */

// phpcs:disable PSR1.Files.SideEffects
use Joomla\CMS\Layout\FileLayout;

\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

$renderer = new FileLayout('chococsv.dashboard.default');

echo $renderer->render([]);
