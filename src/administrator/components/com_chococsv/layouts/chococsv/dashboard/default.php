<?php

declare(strict_types=1);

/**
 * @copyright (c) 2009 - present. Mr Alexandre J-S William ELISÉ. All rights reserved.
 * @license       GNU Affero General Public License v3.0 or later (AGPL-3.0-or-later). See LICENSE.txt file
 */

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

extract($displayData ?? [], EXTR_SKIP);
?>
<div class="d-grid gap-4 d-md-flex justify-content-md-start">
    <a class="btn btn-primary me-md-2" href="<?php echo Route::link('administrator', 'index.php?option=com_plugins&view=plugins&filter[folder]=console&filter[element]=chococsv', false); ?>"  target="_blank" rel="noopener"><?php echo Text::_('COM_CHOCOCSV_DEPLOY_ARTICLES_FROM_CONSOLE'); ?></a>

    <a class="btn btn-success me-md-2" href="<?php echo Route::link('site', 'index.php?option=com_chococsv&task=csv.deploy', false); ?>"  target="_blank" rel="noopener"><?php echo Text::_('COM_CHOCOCSV_DEPLOY_ARTICLES_FROM_SITE'); ?></a>
</div>
