<?php

declare(strict_types=1);

/**
 * @copyright (c) 2009 - present. Mr Alexandre J-S William ELISÉ. All rights reserved.
 * @license       GNU Affero General Public License v3.0 or later (AGPL-3.0-or-later). See LICENSE.txt file
 */

namespace AlexApi\Component\Chococsv\Administrator\Command;

// phpcs:disable PSR1.Files.SideEffects
use AlexApi\Component\Chococsv\Administrator\Behaviour\WebserviceToolboxBehaviour;
use AlexApi\Component\Chococsv\Administrator\Domain\Model\Destination\BasePath;
use AlexApi\Component\Chococsv\Administrator\Domain\Model\Destination\BaseUrl;
use AlexApi\Component\Chococsv\Administrator\Domain\Model\Destination\Destination;
use AlexApi\Component\Chococsv\Administrator\Domain\Model\Destination\TokenIndex;
use AlexApi\Component\Chococsv\Administrator\Domain\Model\State\DeployArticleCommandState;
use AlexApi\Component\Chococsv\Administrator\Domain\Util\CsvUtil;
use Exception;
use InvalidArgumentException;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Application\ConsoleApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\String\PunycodeHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Filesystem\Path;
use Joomla\Http\TransportInterface;
use Joomla\Registry\Registry;
use Joomla\String\StringHelper;
use RuntimeException;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use UnexpectedValueException;

use function array_merge;
use function array_unique;
use function define;
use function defined;
use function fclose;
use function fopen;
use function in_array;
use function ini_set;
use function is_readable;
use function is_resource;
use function json_decode;
use function json_encode;
use function sprintf;

use const ANSI_COLOR_BLUE;
use const ANSI_COLOR_GREEN;
use const ANSI_COLOR_NORMAL;
use const ANSI_COLOR_RED;
use const CUSTOM_LINE_END;
use const E_ALL;
use const E_DEPRECATED;
use const IS_CLI;
use const JSON_THROW_ON_ERROR;
use const PHP_EOL;
use const PHP_SAPI;

\defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects

ini_set('error_reporting', E_ALL & ~E_DEPRECATED);
ini_set('error_log', '');
ini_set('log_errors', 1);
ini_set('log_errors_max_len', 4096);
ini_set('auto_detect_line_endings', 1);

defined('IS_CLI') || define('IS_CLI', PHP_SAPI == 'cli');
defined('CUSTOM_LINE_END') || define('CUSTOM_LINE_END', IS_CLI ? PHP_EOL : '<br>');
defined('ANSI_COLOR_RED') || define('ANSI_COLOR_RED', IS_CLI ? "\033[31m" : '');
defined('ANSI_COLOR_GREEN') || define('ANSI_COLOR_GREEN', IS_CLI ? "\033[32m" : '');
defined('ANSI_COLOR_BLUE') || define('ANSI_COLOR_BLUE', IS_CLI ? "\033[34m" : '');
defined('ANSI_COLOR_NORMAL') || define('ANSI_COLOR_NORMAL', IS_CLI ? "\033[0m" : '');

defined('CSV_SEPARATOR') || define('CSV_SEPARATOR', "\x2C");
defined('CSV_ENCLOSURE') || define('CSV_ENCLOSURE', "\x22");
defined('CSV_ESCAPE') || define('CSV_ESCAPE', "\x22");
defined('CSV_ENDING') || define('CSV_ENDING', "\x0D\x0A");

//Csv starts at line number : 2
defined('CSV_START') || define('CSV_START', 2);

/**
 *
 */
final class DeployArticleCommand implements DeployContentInterface, TestableDeployContentInterface
{
    use WebserviceToolboxBehaviour;

    const LOG_CATEGORY = 'com_chococsv.deploy.article.command';

    /**
     * @var TransportInterface|null
     */
    private TransportInterface|null $transport;

    /**
     * @var StyleInterface|null
     */
    private StyleInterface|null $consoleOutputStyle;

    private function __construct(private DeployArticleCommandState $deployArticleCommandState)
    {
        if (!$this->isSupported()) {
            throw new RuntimeException('This feature is not supported on your platform.', 501);
        }
    }

    public static function fromState(DeployArticleCommandState $deployArticleCommandState): self
    {
        return (new self($deployArticleCommandState));
    }

    /**
     * @return void
     */
    public function deploy(): void
    {
        // Show the ASCII Art banner or not
        $enviromentAwareDisplay = (
        IS_CLI ?
            DeployArticleCommandState::ASCII_BANNER
            : sprintf(
            '<pre>%s</pre>',
            DeployArticleCommandState::ASCII_BANNER
        )
        );

        try {
            if ($this->deployArticleCommandState->shouldShowAsciiBanner()) {
                $this->enqueueMessage(
                    sprintf(
                        '%s %s %s%s',
                        ANSI_COLOR_BLUE,
                        $enviromentAwareDisplay,
                        ANSI_COLOR_NORMAL,
                        CUSTOM_LINE_END
                    )
                );
            }
            $computedDestinations = new Registry($this->deployArticleCommandState->getDestinations());
            $computedDestinationsToObject = $computedDestinations->toObject();

            foreach ($computedDestinationsToObject as $destination) {
                // Ignore when full form is hidden
                if (!$destination->ref->show_form) {
                    continue;
                }
                // Ignore when inactive
                if (!$destination->ref->is_active) {
                    continue;
                }

                if (!$destination?->ref?->tokenindex) {
                    continue;
                }

                $typedDestination = Destination::fromTokenIndex(TokenIndex::fromString($destination->ref->tokenindex));

                // IMPORTANT!: Remember to set back the new state after using a "wither"
                $this->deployArticleCommandState = $this->deployArticleCommandState->withFailedCsvLines(
                    [$typedDestination->getTokenIndex()->asString() => []]
                );
                $this->deployArticleCommandState = $this->deployArticleCommandState->withSuccessfulCsvLines(
                    [$typedDestination->getTokenIndex()->asString() => []]
                );
                $this->deployArticleCommandState = $this->deployArticleCommandState->withDone(false);

                // Public url of the sample csv used in this example (CHANGE WITH YOUR OWN CSV URL OR LOCAL CSV FILE)
                $isLocal = (bool)$destination->ref->is_local;

                // For example: https://example.org/sample-data.csv';
                $givenCsvUrl = PunycodeHelper::urlToUTF8(
                    (string)$destination->ref->remote_file ?? ''
                );
                if ($isLocal) {
                    $localCsvFileFromParams = $destination->ref->local_file ?? '';
                    if (empty($localCsvFileFromParams)) {
                        throw new InvalidArgumentException('CSV Url MUST NOT be empty', 422);
                    }
                    $localCsvFile = Path::clean(
                        sprintf('%s/media/com_chococsv/data/%s', JPATH_ROOT, $localCsvFileFromParams)
                    );
                    if (is_readable($localCsvFile)) {
                        $givenCsvUrl = $localCsvFile;
                    }
                }

                $typedDestination = $typedDestination->withCsvUrl($givenCsvUrl);


// Line numbers we want in any order (e.g. 9,7-7,2-4,10,17-14,21). Leave empty '' to process all lines (beginning at line 2. Same as csv file)
                $whatLineNumbersYouWant = $destination->ref->what_line_numbers_you_want ?? '';

                $typedDestination = $typedDestination->withExpandedLineNumbers($whatLineNumbersYouWant);

                // Your Joomla! website base url
                $typedDestination = $typedDestination->withBaseUrl($destination->ref->base_url ?? '');

                // Your Joomla! Api Token (DO NOT STORE IT IN YOUR REPO USE A VAULT OR A PASSWORD MANAGER)
                $typedDestination = $typedDestination->withToken($destination->ref->auth_apikey ?? '');
                $typedDestination = $typedDestination->withBasePath(
                    $destination->ref->base_path ?? '/api/index.php/v1'
                );

                // Other Joomla articles fields
                $typedDestination = $typedDestination->withExtraDefaultFieldKeys(
                    $destination->ref->extra_default_fields ?? []
                );

// Add custom fields support (shout-out to Marc DECHÈVRE : CUSTOM KING)
// The keys are the columns in the csv with the custom fields names (that's how Joomla! Web Services Api work as of today)
// For the custom fields to work they need to be added in the csv and to exists in the Joomla! site.
                if ($destination?->ref?->toggle_custom_fields) {
                    $givenCustomFields = $destination?->ref?->manually_custom_fields ?? []; // If not defined fallback to empty array
                } else {
                    $givenCustomFields = $destination?->ref?->custom_fields ?? [];
                }
                $typedDestination = $typedDestination->withCustomFieldKeys($givenCustomFields);

                try {
                    $this->csvReader(
                        $this->deployArticleCommandState,
                        $typedDestination
                    );
                } catch (Throwable $e) {
                    $errorMessage = sprintf(
                        '%s Error message: %s, Error code line: %d%s%s',
                        ANSI_COLOR_RED,
                        $e->getMessage(),
                        $e->getLine(),
                        ANSI_COLOR_NORMAL,
                        CUSTOM_LINE_END
                    );
                    if (in_array($this->deployArticleCommandState->getSaveReportToFile()->asInt(), [1, 2])) {
                        Log::add($errorMessage, Log::ERROR, self::LOG_CATEGORY);
                    }
                    if ($this->deployArticleCommandState->getSilent()->asInt() == 1) {
                        $this->enqueueMessage(
                            $errorMessage,
                            'error'
                        );
                    }
                } finally {
                    $this->deployArticleCommandState->withDone(true);
                    $this->enqueueMessage(sprintf('Done%s', CUSTOM_LINE_END));
                }
            }
        } catch (Throwable $e) {
            $this->enqueueMessage(
                sprintf(
                    '[%d] %s %s:%d Trace: %s Previous: %s',
                    $e->getCode(),
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine(),
                    $e->getTraceAsString(),
                    $e->getPrevious() ? $e->getPrevious()->getTraceAsString() : ''
                ),
                'error'
            );
            // Rethrow exception to make the command fail as it should on failure
            throw $e;
        }
    }

    /**
     * @return bool
     */
    private function isSupported(): bool
    {
        return ComponentHelper::isInstalled('com_chococsv') && ComponentHelper::isEnabled('com_chococsv');
    }


    /**
     * @return Registry
     */
    private function getParams(): Registry
    {
        return ComponentHelper::getParams('com_chococsv', true);
    }


    /**
     * @param string $message
     * @param string $type
     *
     * @return void
     * @throws Exception
     */
    private function enqueueMessage(
        string $message,
        string $type = 'message'
    ): void {
        // Ignore empty messages
        if (empty($message)) {
            return;
        }

        $app = Factory::getApplication();
        if ($app instanceof ConsoleApplication) {
            $outputFormatter = new SymfonyStyle($app->getConsoleInput(), $app->getConsoleOutput());
            if ($type === 'message') {
                $type = 'success';
            }
            try {
                $outputFormatter->$type($message);
            } catch (Throwable) {
                $outputFormatter->text($message);
            }
        } elseif ($app instanceof CMSApplication) {
            $outputFormatter = [$app, 'enqueueMessage'];
            $outputFormatter($message, $type) || $outputFormatter($message, 'message');
        }
    }


    private function csvReader(
        DeployArticleCommandState $deployArticleCommandState,
        Destination $currentDestination
    ): void {
        $mergedKeys = array_unique(
            array_merge(
                DeployArticleCommandState::DEFAULT_ARTICLE_KEYS,
                $currentDestination?->getExtraDefaultFieldKeys()?->asArray() ?? [],
                $currentDestination?->getCustomFieldKeys()?->asArray() ?? [],
            )
        );

        // Assess robustness of the code by trying random key order
        //shuffle($mergedKeys);

        $resource = fopen($currentDestination->getCsvUrl()->asString(), 'r');

        if ($resource === false) {
            throw new RuntimeException('Could not read csv file', 500);
        }

        $linesYouWant = $currentDestination?->getExpandedLineNumbers()?->asArray() ?? [];

        try {
            CsvUtil::computeCsv(
                $resource,
                $linesYouWant,
                $mergedKeys,
                fn($successData) => $this->processEachCsvLineData(
                    $successData['csv_line'],
                    $successData['csv_parsed'],
                    $currentDestination
                ),
                fn($errorData) => ($deployArticleCommandState->getSilent()->asInt() == 1
                        && $this->enqueueMessage(
                            sprintf(
                                "%s Error message: %s, Error code line: %ds%s%s",
                                ANSI_COLOR_RED,
                                $errorData->getMessage(),
                                $errorData->getLine(),
                                ANSI_COLOR_NORMAL,
                                CUSTOM_LINE_END
                            ),
                            'error'
                        )) || (
                        in_array($deployArticleCommandState->getSaveReportToFile()->asInt(), [1, 2], true)
                        && Log::add(
                            sprintf(
                                "%s Error message: %s, Error code line: %ds%s%s",
                                ANSI_COLOR_RED,
                                $errorData->getMessage(),
                                $errorData->getLine(),
                                ANSI_COLOR_NORMAL,
                                CUSTOM_LINE_END
                            ),
                            Log::ERROR,
                            self::LOG_CATEGORY
                        )
                    )

            );
        } catch (Throwable $e) {
            $errorMessage = sprintf(
                "%s Error message: %s, Error code line: %ds%s%s",
                ANSI_COLOR_RED,
                $e->getMessage(),
                $e->getLine(),
                ANSI_COLOR_NORMAL,
                CUSTOM_LINE_END
            );

            if (in_array($deployArticleCommandState->getSaveReportToFile()->asInt(), [1, 2], true)) {
                Log::add($errorMessage, Log::ERROR, self::LOG_CATEGORY);
            }

            if ($deployArticleCommandState->getSilent()->asInt() == 1) {
                $this->enqueueMessage(
                    $errorMessage,
                    'error'
                );
            }
            throw $e;
        } finally {
            if (isset($resource) && is_resource($resource)) {
                fclose($resource);
            }
        }
    }

    /**
     * @param int $dataCurrentCsvLine
     * @param $data
     * @param Destination $currentDestination
     * @return void
     * @throws Exception
     */
    private function processEachCsvLineData(int $dataCurrentCsvLine, $data, Destination $currentDestination): void
    {
        static $retries = 0;
        try {
            $computedTokenIndex = TokenIndex::fromString($data['tokenindex']);

            // If it's not matching token index stop here
            if (!$currentDestination->equals($computedTokenIndex)) {
                throw new UnexpectedValueException('Token index mismatch. Skipping...', 422);
            }

            // HTTP request headers
            $headers = [
                'Accept' => 'application/vnd.api+json',
                'Content-Type' => 'application/json',
                'X-Joomla-Token' => $currentDestination->getToken()->asString(),
            ];

            // Article primary key. Usually 'id'
            $pk = (int)$data['id'];

            $currentResponse = self::processHttpRequest(
                $pk ? 'PATCH' : 'POST',
                self::endpoint(
                    $currentDestination->getBaseUrl(),
                    $currentDestination->getBasePath(),
                    $pk
                ),
                $data,
                $headers,
                DeployArticleCommandState::REQUEST_TIMEOUT,
                self::USER_AGENT
            );

            $decodedJsonOutput = json_decode(
                $currentResponse,
                false,
                512,
                JSON_THROW_ON_ERROR
            );

            // don't show errors, handle them gracefully
            if (isset($decodedJsonOutput->errors)) {
                // If article is potentially a duplicate (already exists with same alias)
                if (isset($decodedJsonOutput->errors[0]->code) && $decodedJsonOutput->errors[0]->code === 400) {
                    // Change the alias
                    $data['alias'] = StringHelper::increment(
                        StringHelper::strtolower($data['alias']),
                        'dash'
                    );
                    // Retry
                    if ($retries < DeployArticleCommandState::MAX_RETRIES) {
                        ++$retries;
                        $this->processEachCsvLineData($dataCurrentCsvLine, $data, $currentDestination);
                    } else {
                        throw new RuntimeException(
                            'Max retries reached. Could not process the request. Maybe a network issue .Stopping here',
                            0
                        );
                    }
                }
            } elseif (isset($decodedJsonOutput->data->attributes) && !isset($this->successfulCsvLines[$dataCurrentCsvLine])) {
                if ($this->deployArticleCommandState->getSilent()->asInt() == 1) {
                    $sucessfulMessage = sprintf(
                        "%s Deployed to: %s, CSV Line: %d, id: %d, created: %s, title: %s, alias: %s%s%s",
                        ANSI_COLOR_GREEN,
                        $data['tokenindex'],
                        $dataCurrentCsvLine,
                        $decodedJsonOutput->data->id,
                        $decodedJsonOutput->data->attributes->created,
                        $decodedJsonOutput->data->attributes->title,
                        $decodedJsonOutput->data->attributes->alias,
                        ANSI_COLOR_NORMAL,
                        CUSTOM_LINE_END
                    );
                    Log::add($sucessfulMessage, Log::DEBUG, '');
                    $this->enqueueMessage($this->successfulCsvLines[$dataCurrentCsvLine]);
                }
            }
        } catch (Throwable $e) {
            if ($this->deployArticleCommandState->getSilent()->asInt() == 1) {
                Log::add(
                    sprintf(
                        "%s Error message: %s, Error code line: %d, Error CSV Line: %d%s%s",
                        ANSI_COLOR_RED,
                        $e->getMessage(),
                        $e->getLine(),
                        $dataCurrentCsvLine,
                        ANSI_COLOR_NORMAL,
                        CUSTOM_LINE_END
                    ),
                    Log::ERROR,
                    self::LOG_CATEGORY
                );
                $this->enqueueMessage($this->failedCsvLines[$dataCurrentCsvLine], 'error');
            }
        }
    }

    /**
     * @param string $givenHttpVerb
     * @param string $endpoint
     * @param array|null $data
     * @param array $headers
     * @param int $timeout
     *
     * @return string
     */
    private static function processHttpRequest(
        string $givenHttpVerb,
        string $endpoint,
        array|null $data,
        array $headers,
        int $timeout = 3
    ): string {
        $uri = (new Uri($endpoint));
        $response = self::getHttpClient()->request(
            $givenHttpVerb,
            $uri,
            ($data ? json_encode($data) : null),
            $headers,
            $timeout,
            self::USER_AGENT
        );

        if (empty($response)) {
            throw new UnexpectedValueException('Invalid response received after Http request. Cannot continue', 422);
        }

        return $response->body;
    }

    /**
     * This time we need endpoint to be a function to make it more dynamic
     */
    private static function endpoint(
        BaseUrl $givenBaseUrl,
        BasePath $givenBasePath,
        int|string|null $givenResourceId = null
    ): string {
        $initial = sprintf('%s%s/%s', $givenBaseUrl->asString(), $givenBasePath->asString(), 'content/articles');
        if (empty($givenResourceId)) {
            return $initial;
        }

        return sprintf('%s/%s', $initial, $givenResourceId);
    }

    public function testCsvReader(
        DeployArticleCommandState $deployArticleCommandState,
        Destination $currentDestination
    ): void {
        $this->csvReader(
            $deployArticleCommandState,
            $currentDestination
        );
    }

    public function testProcessEachCsvLineData($dataCurrentCsvLine, $data, $currentDestination): void
    {
        $this->processEachCsvLineData($dataCurrentCsvLine, $data, $currentDestination);
    }

    public static function testProcessHttpRequest($givenHttpVerb, $endpoint, $data, $headers, $timeout): string
    {
        return self::processHttpRequest($givenHttpVerb, $endpoint, $data, $headers, $timeout);
    }

    public static function testEndpoint($givenBaseUrl, $givenBasePath, $givenResourceId): string
    {
        return self::endpoint($givenBaseUrl, $givenBasePath, $givenResourceId);
    }
}
