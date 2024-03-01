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

final class BasePath implements StringAwareValueObjectInterface, ComparableValueObjectInterface
{
    private string $basePath = '';

    private function __construct(string $givenBasePath)
    {
        $basePath = trim($givenBasePath);

        if (preg_match('|^(/api/(index\.php/)?v1)|', $basePath) !== 1) {
            throw new InvalidArgumentException('Base path is invalid', 422);
        }

        $this->basePath = $basePath;
    }


    public static function fromString(string $value): static
    {
        return (new self($value));
    }

    public static function getRegex(): string
    {
        return '';
    }

    public function asString(): string
    {
        return trim($this->basePath);
    }

    public function equals(StringAwareValueObjectInterface $other): bool
    {
        return $this->asString() === $other->asString();
    }

}
