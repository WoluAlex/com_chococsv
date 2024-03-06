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

namespace AlexApi\Component\Chococsv\Site\Dispatcher;

use Joomla\CMS\Log\Log;
use Throwable;

use function sprintf;
use function strtolower;

defined('_JEXEC') || die;

final class Dispatcher extends \AlexApi\Component\Chococsv\Administrator\Dispatcher\Dispatcher
{
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
