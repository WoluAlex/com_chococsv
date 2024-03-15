<?php

declare(strict_types=1);

/**
 * @copyright (c) 2009 - present. Mr Alexandre J-S William ELISÉ. All rights reserved.
 * @license       GNU Affero General Public License v3.0 or later (AGPL-3.0-or-later). See LICENSE.txt file
 */


namespace AlexApi\Component\Chococsv\Administrator\Dispatcher;

use Joomla\CMS\Dispatcher\ComponentDispatcher;
use Joomla\CMS\Log\Log;
use Throwable;

use function defined;
use function sprintf;
use function strtolower;

use const JPATH_BASE;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects


class Dispatcher extends ComponentDispatcher
{
    protected $option = 'com_chococsv';

    protected function loadLanguage(): void
    {
        $this->app->getLanguage()->load($this->option, JPATH_BASE) || $this->app->getLanguage()->load(
            $this->option,
            sprintf('%s/components/%s', JPATH_ADMINISTRATOR, $this->option)
        );
    }

    public function dispatch(): void
    {
        try {
            parent::dispatch();
        } catch (Throwable $e) {
            Log::add(
                sprintf('%s %s %d', $e->getMessage(), basename($e->getFile()), $e->getLine()),
                Log::ERROR,
                sprintf(
                    '%s.%s',
                    $this->option,
                    strtolower(
                        $this->app->getName()
                    )
                )
            );
        }
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
