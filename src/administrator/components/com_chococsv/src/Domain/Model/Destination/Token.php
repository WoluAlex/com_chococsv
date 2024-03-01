<?php

declare(strict_types=1);
/**
 * @copyright (c) 2009 - present. Mr Alexandre J-S William ELISÉ. All rights reserved.
 * @license       GNU Affero General Public License v3.0 or later (AGPL-3.0-or-later). See LICENSE.txt file
 */

namespace AlexApi\Component\Chococsv\Administrator\Domain\Model\Destination;

use AlexApi\Component\Chococsv\Administrator\Domain\Model\Common\ComparableValueObjectInterface;
use AlexApi\Component\Chococsv\Administrator\Domain\Model\Common\StringAwareValueObjectInterface;

final class Token implements StringAwareValueObjectInterface, ComparableValueObjectInterface
{
    private string $token;


    private function __construct(string $value)
    {
        $this->token = trim($value);
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
        return trim($this->token);
    }

    public function equals(StringAwareValueObjectInterface $other): bool
    {
        return $this->asString() === $other->asString();
    }

}
