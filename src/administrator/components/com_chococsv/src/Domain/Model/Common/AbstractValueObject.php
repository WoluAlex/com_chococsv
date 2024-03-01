<?php

declare(strict_types=1);

/**
 * @copyright (c) 2009 - present. Mr Alexandre J-S William ELISÉ. All rights reserved.
 * @license       GNU Affero General Public License v3.0 or later (AGPL-3.0-or-later). See LICENSE.txt file
 */


namespace AlexApi\Component\Chococsv\Administrator\Domain\Model\Common;

use InvalidArgumentException;

abstract class AbstractValueObject implements StringAwareValueObjectInterface, ComparableValueObjectInterface
{
    protected string $value;
    protected const string REGEX = '(([a-zA-Z0-9\']|[à-ü]|[À-Ü]|œ|Œ|\p{Greek}){1,10}\s?-?\s?([a-zA-Z0-9\']|[à-ü]|[À-Ü]|œ|Œ|\p{Greek}){0,10}\s?-?\s?([a-zA-Z0-9\']|[à-ü]|[À-Ü]|œ|Œ|\p{Greek}){0,10}\s?-?\s?([a-zA-Z0-9\']|[à-ü]|[À-Ü]|œ|Œ|\p{Greek}){0,10})';

    private function __construct(string $value)
    {
        if (preg_match('/^' . static::REGEX . '$/', $value) !== 1) {
            throw new InvalidArgumentException('Invalid argument provided. Cannot continue.', 422);
        }
        $this->value = $value;
    }

    public static function fromString(string $value): static
    {
        return (new static($value));
    }

    public static function getRegex(): string
    {
        return static::REGEX;
    }

    public function asString(): string
    {
        return $this->value;
    }

    public function equals(StringAwareValueObjectInterface $other): bool
    {
        return $this->asString() === $other->asString();
    }

}
