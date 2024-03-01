<?php

declare(strict_types=1);
/**
 * @copyright (c) 2009 - present. Mr Alexandre J-S William ELISÉ. All rights reserved.
 * @license       GNU Affero General Public License v3.0 or later (AGPL-3.0-or-later). See LICENSE.txt file
 */

namespace AlexApi\Component\Chococsv\Administrator\Domain\Model\Destination;

use AlexApi\Component\Chococsv\Administrator\Domain\Model\Common\ComparableValueObjectInterface;
use AlexApi\Component\Chococsv\Administrator\Domain\Model\Common\StringAwareValueObjectInterface;
use InvalidArgumentException;

use function str_contains;
use function str_starts_with;

final class CsvUrl implements StringAwareValueObjectInterface, ComparableValueObjectInterface
{
    private string $csvUrl;

    private function __construct(string $csvUrl)
    {
        if (empty($csvUrl)
            || !str_contains($csvUrl, '/media/com_chococsv/')
            || !str_starts_with($csvUrl, 'https://')
            || !str_starts_with($csvUrl, 'http://')
        ) {
            throw new InvalidArgumentException('CSV Url is invalid', 422);
        }
        $this->csvUrl = $csvUrl;
    }

    public static function fromString(string $value): static
    {
        return new self($value);
    }

    public static function getRegex(): string
    {
        return '';
    }

    public function asString(): string
    {
        return $this->csvUrl;
    }

    public function equals(StringAwareValueObjectInterface $other): bool
    {
        return $this->asString() === $other->asString();
    }
}
