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

use function preg_match;
use function str_contains;

final class CsvUrl implements StringAwareValueObjectInterface, ComparableValueObjectInterface
{
    private readonly string $csvUrl;

    private function __construct(string $csvUrl)
    {
        if (
            !(empty($csvUrl)
                xor (!str_contains($csvUrl, '/media/com_chococsv/'))
                xor (preg_match('|^(https?://)|', $csvUrl) !== 1))
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

    public function __debugInfo(): ?array
    {
        return null;
    }

    public function __serialize(): array
    {
        return [];
    }
}
