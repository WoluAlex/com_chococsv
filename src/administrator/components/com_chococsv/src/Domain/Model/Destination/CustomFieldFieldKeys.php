<?php

declare(strict_types=1);
/**
 *
 * @author Mr Alexandre J-S William ELISÉ <code@apiadept.com>
 * @copyright (c) 2009 - present. Mr Alexandre J-S William ELISÉ. All rights reserved.
 * @license GPL-2.0-and-later GNU General Public License v2.0 or later
 * @link https://apiadept.com
 */

namespace AlexApi\Component\Chococsv\Administrator\Domain\Model\Destination;

use function implode;

final class CustomFieldFieldKeys
{
    private array $customFieldKeys;
    private const array DENIED = [];

    private function __construct(array $customFieldKeys)
    {
        $this->customFieldKeys = array_diff(array_filter($customFieldKeys), self::DENIED);
    }

    public static function fromArray(array $value): self
    {
        return new self($value);
    }

    public function asArray(): array
    {
        return $this->customFieldKeys;
    }

    public function asString(): string
    {
        return implode(',', $this->asArray());
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
