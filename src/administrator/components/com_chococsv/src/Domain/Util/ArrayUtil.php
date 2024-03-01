<?php

declare(strict_types=1);

/**
 * @copyright (c) 2009 - present. Mr Alexandre J-S William ELISÉ. All rights reserved.
 * @license       GNU Affero General Public License v3.0 or later (AGPL-3.0-or-later). See LICENSE.txt file
 */

namespace AlexApi\Component\Chococsv\Administrator\Domain\Util;

use function array_filter;
use function array_values;
use function count;

final class ArrayUtil
{
    public static function containsOnlyInstanceOf(array $items, string $givenInstanceName): bool
    {
        return !empty($items) && (
                count($items)
                ===
                count(array_values(array_filter($items, fn($item) => $item instanceof $givenInstanceName)))
            );
    }
}
