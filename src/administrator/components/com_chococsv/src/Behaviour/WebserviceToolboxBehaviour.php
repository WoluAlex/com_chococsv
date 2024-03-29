<?php

declare(strict_types=1);
/**
 * @copyright (c) 2009 - present. Mr Alexandre J-S William ELISÉ. All rights reserved.
 * @license       GNU Affero General Public License v3.0 or later (AGPL-3.0-or-later)
 */

namespace AlexApi\Component\Chococsv\Administrator\Behaviour;

use Joomla\Http\HttpFactory;
use Joomla\Http\Response;
use Joomla\Http\TransportInterface;
use JsonException;

use function header;
use function http_response_code;
use function is_string;
use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

defined('_JEXEC') || die;

/**
 * A "toolbox" "enable" the caller to use Http Client and other Web Services related utilities
 */
trait WebserviceToolboxBehaviour
{
    private const USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36';

    /**
     * Get Transport Interface instance to act as Http Client
     *
     * @return TransportInterface
     */
    public static function getHttpClient(): TransportInterface
    {
        return (new HttpFactory())->getAvailableDriver();
    }

    /**
     * @param Response $response
     *
     * @return void
     * @throws JsonException
     */
    public static function displayJsonResponse(Response $response): void
    {
        $headers = $response->getHeaders();
        foreach ($headers as $k => $v) {
            if (!is_string($v)) {
                continue;
            }
            header(sprintf('%s: %s', $k, $v));
        }
        http_response_code($response->code);
        echo json_encode($response->body, JSON_THROW_ON_ERROR);
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
