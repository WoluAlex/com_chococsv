<?php

declare(strict_types=1);
/**
 * @copyright (c) 2009 - present. Mr Alexandre J-S William ELISÉ. All rights reserved.
 * @license       GNU Affero General Public License v3.0 or later (AGPL-3.0-or-later). See LICENSE.txt file
 */

namespace AlexApi\Component\Chococsv\Administrator\Domain\Model\Destination;

use AlexApi\Component\Chococsv\Administrator\Domain\Model\Common\AbstractValueObject;

final class TokenIndex extends AbstractValueObject
{
    protected const string REGEX = '([a-zA-Z]{1,20}\-?[a-zA-Z0-9]{1,19})';
}
