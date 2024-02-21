<?php

declare(strict_types=1);

/**
 * @copyright (c) 2009 - present. Mr Alexandre J-S William ELISÉ. All rights reserved.
 * @license       GNU Affero General Public License v3.0 or later (AGPL-3.0-or-later). See LICENSE.txt file
 */

namespace AlexApi\Component\Chococsv\Administrator\Command;

// phpcs:disable PSR1.Files.SideEffects
use AlexApi\Component\Chococsv\Administrator\Behaviour\WebserviceToolboxBehaviour;
use DomainException;
use Exception;
use InvalidArgumentException;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Application\ConsoleApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\LanguageFactoryInterface;
use Joomla\CMS\String\PunycodeHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\DI\Container;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Path;
use Joomla\Http\TransportInterface;
use Joomla\Language\Language;
use Joomla\Registry\Registry;
use Joomla\String\StringHelper;
use JsonException;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use UnexpectedValueException;

use function array_combine;
use function array_intersect;
use function array_intersect_key;
use function array_merge;
use function array_unique;
use function count;
use function define;
use function defined;
use function explode;
use function fclose;
use function feof;
use function file_exists;
use function fopen;
use function in_array;
use function ini_set;
use function is_object;
use function is_readable;
use function is_resource;
use function is_string;
use function json_decode;
use function json_encode;
use function max;
use function min;
use function range;
use function sort;
use function sprintf;
use function str_contains;
use function str_getcsv;
use function str_replace;
use function str_starts_with;
use function stream_get_line;
use function stream_set_blocking;
use function strlen;
use function trim;

use const ANSI_COLOR_BLUE;
use const ANSI_COLOR_GREEN;
use const ANSI_COLOR_NORMAL;
use const ANSI_COLOR_RED;
use const CSV_ENCLOSURE;
use const CSV_ESCAPE;
use const CSV_PROCESSING_REPORT_FILEPATH;
use const CSV_SEPARATOR;
use const CSV_START;
use const CUSTOM_LINE_END;
use const E_ALL;
use const E_DEPRECATED;
use const IS_CLI;
use const JSON_THROW_ON_ERROR;
use const PHP_EOL;
use const PHP_SAPI;
use const SORT_ASC;
use const SORT_NATURAL;

\defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects

/**
 *
 */
final class DeployArticleCommand implements DeployContentInterface
{
    use WebserviceToolboxBehaviour;

    /**
     *
     */
    private const ASCII_BANNER = <<<TEXT
    __  __     ____         _____                              __                      __
   / / / ___  / / ____     / ___/__  ______  ___  _____       / ____  ____  ____ ___  / ___  __________
  / /_/ / _ \/ / / __ \    \__ \/ / / / __ \/ _ \/ ___/  __  / / __ \/ __ \/ __ `__ \/ / _ \/ ___/ ___/
 / __  /  __/ / / /_/ /   ___/ / /_/ / /_/ /  __/ /     / /_/ / /_/ / /_/ / / / / / / /  __/ /  (__  )
/_/ /_/\___/_/_/\____/   /____/\__,_/ .___/\___/_/      \____/\____/\____/_/ /_/ /_/_/\___/_/  /____/
                                   /_/
TEXT;

    /**
     *
     */
    private const REQUEST_TIMEOUT = 3;

    /**
     * @var TransportInterface|null
     */
    private TransportInterface|null $transport;

    /**
     * @var StyleInterface|null
     */
    private StyleInterface|null $consoleOutputStyle;

    /**
     * @var int
     */
    private int $silent = 0;
    /**
     * @var string
     */
    private array $csvUrl = [];
    /**
     * @var array
     */
    private array $extraDefaultFieldKeys = [];

    /**
     * @var array
     */
    private array $customFieldKeys = [];
    /**
     * @var array
     */
    private array $failedCsvLines = [];
    /**
     * @var array
     */
    private array $successfulCsvLines = [];

    /**
     * @var bool
     */
    private array $isDone = [];

    /**
     * @var array
     */
    private array $expandedLineNumbers = [];
    /**
     * @var bool
     */
    private array $isExpanded = [];
    /**
     * @var int
     */
    private int $saveReportToFile = 0;

    /**
     * @var array
     */
    private array $token = [];

    /**
     * @var array
     */
    private array $baseUrl = [];

    /**
     * @var array
     */
    private array $basePath = [];

    /**
     * @var Language|null
     */
    private Language|null $language = null;
    private string $tokenindex = '';

    /**
     *
     */
    public function __construct()
    {
        if (!$this->isSupported()) {
            throw new RuntimeException('This feature is not supported on your platform.', 501);
        }
    }

    /**
     * @return void
     */
    public function deploy(): void
    {
        ini_set('error_reporting', E_ALL & ~E_DEPRECATED);
        ini_set('error_log', '');
        ini_set('log_errors', 1);
        ini_set('log_errors_max_len', 4096);

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
// This MUST be a json file otherwise it might fail
        defined('CSV_PROCESSING_REPORT_FILEPATH') || define(
            'CSV_PROCESSING_REPORT_FILEPATH',
            Path::clean(JPATH_ROOT . '/media/com_chococsv/report/output.json')
        );

// Wether or not to show ASCII banner true to show , false otherwise. Default is to show the ASCII art banner
        $showAsciiBanner = (bool)$this->getParams()->get('params.show_ascii_banner', 0);

// Silent mode
// 0: hide both response result and key value pairs
// 1: show response result only
// 2: show key value pairs only
// Set to 0 if you want to squeeze out performance of this script to the maximum
        $this->silent = (int)$this->getParams()->get('params.silent_mode', 0);


// Do you want a report after processing?
// 0: no report, 1: success & errors, 2: errors only
// When using report feature. Silent mode MUST be set to 1. Otherwise you might have unexpected results.
// Set to 0 if you want to squeeze out performance of this script to the maximum
// If enabled, this will create a output.json file
        $this->saveReportToFile = (int)$this->getParams()->get('params.save_report_to_file', 0);

// Show the ASCII Art banner or not
        $enviromentAwareDisplay = (IS_CLI ? self::ASCII_BANNER : sprintf('<pre>%s</pre>', self::ASCII_BANNER));

        try {
            $destinations = $this->getParams()->get('params.destinations', []);

            if (empty($destinations)) {
                throw new DomainException(
                    'Destinations subform MUST contain at least one destination where your articles will be deployed',
                    422
                );
            }

            $this->enqueueMessage(
                $showAsciiBanner ? sprintf(
                    '%s %s %s%s',
                    ANSI_COLOR_BLUE,
                    $enviromentAwareDisplay,
                    ANSI_COLOR_NORMAL,
                    CUSTOM_LINE_END
                ) : ''
            );

            $computedDestinations         = new Registry($destinations);
            $computedDestinationsToObject = $computedDestinations->toObject();

            foreach ($computedDestinationsToObject as $destination) {
                if (!$destination->ref->is_active) {
                    continue;
                }

                if (empty($destination->ref->tokenindex)) {
                    continue;
                }
                $this->tokenindex = $destination->ref->tokenindex;

                $this->failedCsvLines[$this->tokenindex] = [];
                $this->successfulCsvLines[$this->tokenindex] = [];
                $this->isDone[$this->tokenindex]         = false;


                // Public url of the sample csv used in this example (CHANGE WITH YOUR OWN CSV URL OR LOCAL CSV FILE)
                $isLocal = (bool)$destination->ref->is_local;

// IF THIS URL DOES NOT EXIST IT WILL CRASH THE SCRIPT. CHANGE THIS TO YOUR OWN URL
                // For example: https://example.org/sample-data.csv';
                $this->csvUrl[$this->tokenindex] = PunycodeHelper::urlToUTF8(
                    (string)$destination->ref->remote_file ?? ''
                );
                if ($isLocal) {
                    $localCsvFileFromParams = $destination->ref->local_file ?? '';
                    if (empty($localCsvFileFromParams)) {
                        throw new InvalidArgumentException('CSV Url MUST NOT be empty', 400);
                    }
                    $localCsvFile = Path::clean(
                        sprintf('%s/media/com_chococsv/data/%s', JPATH_ROOT, $localCsvFileFromParams)
                    );
                    if (is_readable($localCsvFile)) {
                        $this->csvUrl[$this->tokenindex] = $localCsvFile;
                    }
                }

                if (empty($this->csvUrl[$this->tokenindex])) {
                    throw new InvalidArgumentException('CSV Url MUST NOT be empty', 400);
                }


// Line numbers we want in any order (e.g. 9,7-7,2-4,10,17-14,21). Leave empty '' to process all lines (beginning at line 2. Same as csv file)
                $whatLineNumbersYouWant = $destination->ref->what_line_numbers_you_want ?? '';


                $this->expandedLineNumbers[$this->tokenindex] = $this->chooseLinesLikeAPrinter($whatLineNumbersYouWant);
                $this->isExpanded[$this->tokenindex]          = ($this->expandedLineNumbers[$this->tokenindex] !== []);


                // Your Joomla! website base url
                $this->baseUrl[$destination->ref->tokenindex] = $destination->ref->base_url ?? '';

                // Your Joomla! Api Token (DO NOT STORE IT IN YOUR REPO USE A VAULT OR A PASSWORD MANAGER)
                $this->token[$destination->ref->tokenindex]    = $destination->ref->auth_apikey ?? '';
                $this->basePath[$destination->ref->tokenindex] = $destination->ref->base_path ?? '/api/index.php/v1';

                // Other Joomla articles fields
                $this->extraDefaultFieldKeys[$destination->ref->tokenindex] = $destination->ref->extra_default_fields ?? [];

// Add custom fields support (shout-out to Marc DECHÈVRE : CUSTOM KING)
// The keys are the columns in the csv with the custom fields names (that's how Joomla! Web Services Api work as of today)
// For the custom fields to work they need to be added in the csv and to exists in the Joomla! site.
                $this->customFieldKeys[$destination->ref->tokenindex] = $destination->ref->custom_fields ?? [];

                try {
                    $this->csvReader(
                        $this->csvUrl[$this->tokenindex],
                        $this->silent,
                        $this->expandedLineNumbers[$this->tokenindex],
                        $this->failedCsvLines[$this->tokenindex],
                        $this->successfulCsvLines[$this->tokenindex],
                        $this->isDone[$this->tokenindex]
                    );
                } catch (DomainException $domainException) {
                    if ($this->silent == 1) {
                        $this->enqueueMessage(
                            sprintf(
                                '%s%s%s%s',
                                ANSI_COLOR_GREEN,
                                $domainException->getMessage(),
                                ANSI_COLOR_NORMAL,
                                CUSTOM_LINE_END
                            )
                        );
                    }
                } catch (Throwable $fallbackCatchAllUncaughtException) {
                    // Ignore silent mode when stumbling upon fallback exception
                    $this->enqueueMessage(
                        sprintf(
                            '%s Error message: %s, Error code line: %d%s%s',
                            ANSI_COLOR_RED,
                            $fallbackCatchAllUncaughtException->getMessage(),
                            $fallbackCatchAllUncaughtException->getLine(),
                            ANSI_COLOR_NORMAL,
                            CUSTOM_LINE_END
                        ),
                        'error'
                    );
                } finally {
                    $this->isDone[$this->tokenindex] = true;

                    if (in_array($this->saveReportToFile, [1, 2], true)) {
                        $errors = [];
                        if (!file_exists(CSV_PROCESSING_REPORT_FILEPATH)) {
                            File::write(CSV_PROCESSING_REPORT_FILEPATH, '');
                        }
                        if (!empty($this->failedCsvLines[$this->tokenindex])) {
                            $errors = ['errors' => $this->failedCsvLines[$this->tokenindex]];
                            if ($this->saveReportToFile === 2) {
                                File::write(CSV_PROCESSING_REPORT_FILEPATH, json_encode($errors));
                            }
                        }
                        if (($this->saveReportToFile === 1) && !empty($this->successfulCsvLines[$this->tokenindex])) {
                            $success = ['success' => $this->successfulCsvLines[$this->tokenindex]];
                            File::write(CSV_PROCESSING_REPORT_FILEPATH, json_encode(array_merge($errors, $success)));
                        }
                    }

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
     * @param   Container|null  $givenContainer
     *
     * @return Language
     */
    private function getComputedLanguage(Container|null $givenContainer = null): Language
    {
        $container = $givenContainer ?? Factory::getContainer();
        // Console uses the default system language
        $config = $container->get('config');
        $locale = $config->get('language');
        $debug  = $config->get('debug_lang');

        return $container->get(LanguageFactoryInterface::class)->createLanguage($locale, $debug);
    }

    /**
     * @param   string  $message
     * @param   string  $type
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

    /**
     * @param   string  $wantedLineNumbers
     *
     * @return array|int[]
     */
    private function chooseLinesLikeAPrinter(string $wantedLineNumbers = ''): array
    {
        // When strictly empty process every Csv lines (Full range)
        if ($wantedLineNumbers === '') {
            return [];
        }

        // Cut-off useless processing when single digit range
        if (strlen($wantedLineNumbers) === 1) {
            return (((int)$wantedLineNumbers) < CSV_START) ? [CSV_START] : [((int)$wantedLineNumbers)];
        }

        $commaParts = explode(',', $wantedLineNumbers);
        if (empty($commaParts)) {
            return [];
        }
        sort($commaParts, SORT_NATURAL);
        $output = [];
        foreach ($commaParts as $commaPart) {
            if (!str_contains($commaPart, '-')) {
                // First line is the header, so we MUST start at least at line 2. Hence, 2 or more
                $result1 = ((int)$commaPart) > 1 ? ((int)$commaPart) : CSV_START;
                // Makes it unique in output array
                if (!in_array($result1, $output, true)) {
                    $output[] = $result1;
                }
                // Skip to next comma part
                continue;
            }
            // maximum 1 dash "group" per comma separated "groups"
            $dashParts = explode('-', $commaPart, 2);
            if (empty($dashParts)) {
                // First line is the header, so we MUST start at least at line 2. Hence, 2 or more
                $result2 = ((int)$commaPart) > 1 ? ((int)$commaPart) : CSV_START;
                if (!in_array($result2, $output, true)) {
                    $output[] = $result2;
                }
                // Skip to next comma part
                continue;
            }
            // First line is the header, so we MUST start at least at line 2. Hence, 2 or more
            $dashParts[0] = ((int)$dashParts[0]) > 1 ? ((int)$dashParts[0]) : CSV_START;

            // First line is the header, so we MUST start at least at line 2. Hence, 2 or more
            $dashParts[1] = ((int)$dashParts[1]) > 1 ? ((int)$dashParts[1]) : CSV_START;

            // Only store one digit if both are the same in the range
            if (($dashParts[0] === $dashParts[1]) && (!in_array($dashParts[0], $output, true))) {
                $output[] = $dashParts[0];
            } elseif ($dashParts[0] > $dashParts[1]) {
                // Store expanded range of numbers
                $output = array_merge($output, range($dashParts[1], $dashParts[0]));
            } else {
                // Store expanded range of numbers
                $output = array_merge($output, range($dashParts[0], $dashParts[1]));
            }
        }
        // De-dupe and sort again at the end to tidy up everything
        $unique = array_unique($output);
        // For some reason out of my understanding sort feature in array_unique won't work as expected for me, so I do sort separately
        sort($unique, SORT_NATURAL | SORT_ASC);

        return $unique;
    }

    /**
     * @param   array  $arr
     * @param   int    $isSilent
     *
     * @return array
     * @throws Exception
     */
    private function nestedJsonDataStructure(array $arr, int $isSilent = 0): array
    {
        $handleComplexValues = [];
        $iterator            = new RecursiveIteratorIterator(
            new RecursiveArrayIterator($arr),
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );
        foreach ($iterator as $key => $value) {
            if (str_starts_with($value, '{')) {
                if ($isSilent == 2) {
                    $this->enqueueMessage(
                        sprintf(
                            "%s item with key: %s with value: %s%s%s",
                            ANSI_COLOR_BLUE,
                            $key,
                            $value,
                            ANSI_COLOR_NORMAL,
                            CUSTOM_LINE_END
                        )
                    );
                }
                // Doesn't seem to make sense at first but this one line allows to show intro/fulltext images and urla,urlb,urlc
                $handleComplexValues[$key] = json_decode(str_replace(["\n", "\r", "\t"], '', trim($value)));
            } elseif (json_decode($value) === false) {
                $handleComplexValues[$key] = json_encode($value);
                if ($isSilent == 2) {
                    $this->enqueueMessage(
                        sprintf(
                            "%s item with key: %s with value: %s%s%s",
                            ANSI_COLOR_BLUE,
                            $key,
                            $value,
                            ANSI_COLOR_NORMAL,
                            CUSTOM_LINE_END
                        )
                    );
                }
            } else {
                $handleComplexValues[$key] = $value;
                if ($isSilent == 2) {
                    $this->enqueueMessage(
                        sprintf(
                            "%s item with key: %s with value: %s%s%s",
                            ANSI_COLOR_BLUE,
                            $key,
                            $value,
                            ANSI_COLOR_NORMAL,
                            CUSTOM_LINE_END
                        )
                    );
                }
            }
        }

        return $handleComplexValues;
    }

    /**
     * @param   string  $url
     * @param   int     $isSilent
     * @param   array   $lineRange
     * @param   array   $failed
     * @param   array   $successful
     *
     * @return void
     * @throws Throwable
     * @throws JsonException
     */
    private function csvReader(
        string $url,
        int $isSilent = 1,
        array $lineRange = [],
        array &$failed = [],
        array &$successful = []
    ): void
    {
        if (empty($url)) {
            throw new InvalidArgumentException('Url MUST NOT be empty', 400);
        }

        $defaultKeys = [
            'id',
            'access',
            'title',
            'alias',
            'catid',
            'articletext',
            'introtext',
            'fulltext',
            'language',
            'metadesc',
            'metakey',
            'state',
            'tokenindex',
        ];

        $mergedKeys = array_unique(
            array_merge(
                $defaultKeys,
                $this->extraDefaultFieldKeys[$this->tokenindex],
                $this->customFieldKeys[$this->tokenindex]
            )
        );

        // Assess robustness of the code by trying random key order
        //shuffle($mergedKeys);

        $resource = fopen($url, 'r');

        if ($resource === false) {
            throw new RuntimeException('Could not read csv file', 500);
        }

        $currentCsvLineNumber = 1;

        try {
            stream_set_blocking($resource, false);

            $firstLine = stream_get_line(
                $resource,
                0,
                "\r\n"
            );

            if (!is_string($firstLine) || empty($firstLine)) {
                throw new RuntimeException('First line MUST NOT be empty. It is the header', 422);
            }

            $csvHeaderKeys = str_getcsv($firstLine);
            $commonKeys    = array_intersect($csvHeaderKeys, $mergedKeys);
            $isExpanded    = ($lineRange !== []);

            if ($isExpanded) {
                if (count($lineRange) === 1) {
                    $minLineNumber = $lineRange[0];
                    $maxLineNumber = $lineRange[0];
                } else {
                    // Rather than starting from 1 which is not that efficient, start from minimum value in CSV line range
                    $minLineNumber = min($lineRange);
                    $maxLineNumber = max($lineRange);
                }
            }

            while (!$this->isDone[$this->tokenindex] && !feof($resource)) {
                $currentLine = stream_get_line(
                    $resource,
                    0,
                    "\r\n"
                );
                if (!is_string($currentLine) || empty($currentLine)) {
                    continue;
                }
                // Again, for a more efficient algorithm. Do not do unecessary processing, unless we have to.
                $isEdgeCaseSingleLineInRange = ($isExpanded && (count($lineRange) === 1));
                if (!$isExpanded || ($isExpanded && count($lineRange) > 1) || $isEdgeCaseSingleLineInRange) {
                    $currentCsvLineNumber += 1;

                    if ($isEdgeCaseSingleLineInRange && ($currentCsvLineNumber < $minLineNumber)) {
                        continue; // Continue until we reach the line we want
                    }
                }

                $extractedContent = str_getcsv($currentLine, CSV_SEPARATOR, CSV_ENCLOSURE, CSV_ESCAPE);

                // Skip empty lines
                if (empty($extractedContent)) {
                    continue;
                }

                // Allow using csv keys in any order
                $commonValues = array_intersect_key($extractedContent, $commonKeys);

                // Skip invalid lines
                if (empty($commonValues)) {
                    continue;
                }

                // Iteration on leafs AND nodes
                $handleComplexValues = $this->nestedJsonDataStructure($commonValues, $isSilent);

                try {
                    $encodedContent = json_encode(
                        array_combine($commonKeys, $handleComplexValues),
                        JSON_THROW_ON_ERROR
                    );

                    // Stop processing immediately if it goes beyond range
                    if (($isExpanded && count($lineRange) > 1) && ($currentCsvLineNumber > $maxLineNumber)) {
                        $this->isDone[$this->tokenindex] = true;
                        throw new DomainException(
                            sprintf(
                                'Processing of CSV file done. Last line processed was line %d',
                                $currentCsvLineNumber
                            ), 200
                        );
                    }

                    if ($encodedContent === false) {
                        throw new RuntimeException('Current line seem to be invalid', 422);
                    } elseif (!$this->isDone[$this->tokenindex] && ((is_string(
                                $encodedContent
                            ) && (($isExpanded && in_array(
                                        $currentCsvLineNumber,
                                        $lineRange,
                                        true
                                    )) || !$isExpanded)))) {
                        $this->processEachCsvLineData(['line' => $currentCsvLineNumber, 'content' => $encodedContent]);

                        // Only 1 element in range. Don't do useless processing after first round.
                        if ($isExpanded && (count(
                                    $lineRange
                                ) === 1 && ($currentCsvLineNumber === $maxLineNumber))) {
                            $this->isDone[$this->tokenindex] = true;
                            throw new DomainException(
                                sprintf(
                                    'Processing of CSV file done. Last line processed was line %d',
                                    $currentCsvLineNumber
                                ), 200
                            );
                        }
                    }
                } catch (DomainException $domainException) {
                    $successful[$currentCsvLineNumber] = $domainException->getMessage();
                    throw $domainException;
                } catch (Throwable $encodeContentException) {
                    $failed[$currentCsvLineNumber] = [
                        'error'      => $encodeContentException->getMessage(),
                        'error_line' => $encodeContentException->getLine()
                    ]; // Store failed CSV line numbers for end report.
                    continue; // Ignore failed CSV lines
                }
            }
        } catch (DomainException $domainException) {
            if (isset($resource) && is_resource($resource)) {
                fclose($resource);
            }
            throw $domainException;
        } catch (Throwable $e) {
            if ($isSilent == 1) {
                $this->enqueueMessage(
                    sprintf(
                        "%s Error message: %s, Error code line: %d, Error CSV Line: %d%s%s",
                        ANSI_COLOR_RED,
                        $e->getMessage(),
                        $e->getLine(),
                        $currentCsvLineNumber,
                        ANSI_COLOR_NORMAL,
                        CUSTOM_LINE_END
                    ),
                    'error'
                );
            }
            if (isset($resource) && is_resource($resource)) {
                fclose($resource);
            }
            throw $e;
        } finally {
            if (isset($resource) && is_resource($resource)) {
                fclose($resource);
            }
        }
    }

    /**
     * @param   array  $dataValue
     *
     * @return void
     * @throws JsonException
     */
    private function processEachCsvLineData(array $dataValue): void
    {
        if (empty($dataValue) || !is_int($dataValue['line'] ?? false) || !isset($dataValue['content'])) {
            throw new InvalidArgumentException('Empty data. Cannot continue', 422);
        }

        $dataCurrentCSVline = $dataValue['line'];
        $dataString         = $dataValue['content'];

        $decodedDataString = false;

        if (is_object($dataString)) {
            $decodedDataString = $dataString;
        } else {
            $decodedDataString = json_decode($dataString, false, 512, JSON_THROW_ON_ERROR);
        }

        try {
            if (($decodedDataString === false) || (!isset($this->token[$decodedDataString->tokenindex]))
            ) {
                throw new InvalidArgumentException('Empty data. Cannot continue', 422);
            }

            // HTTP request headers
            $headers = [
                'Accept: application/vnd.api+json',
                'Content-Type: application/json',
                'Content-Length: ' . strlen($dataString),
                sprintf('X-Joomla-Token: %s', trim($this->token[$decodedDataString->tokenindex])),
            ];

            // Article primary key. Usually 'id'
            $pk = (int)$decodedDataString->id;

            $currentResponse = $this->processHttpRequest(
                $pk ? 'PATCH' : 'POST',
                $this->endpoint(
                    $this->baseUrl[$decodedDataString->tokenindex],
                    $this->basePath[$decodedDataString->tokenindex],
                    $pk
                ),
                $dataString,
                $headers,
                self::REQUEST_TIMEOUT,
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
                    $decodedDataString->alias = StringHelper::increment(
                        StringHelper::strtolower($decodedDataString->alias),
                        'dash'
                    );
                    // Retry
                    $this->processEachCsvLineData(['line' => $dataCurrentCSVline, 'content' => $decodedDataString]);
                }
            } elseif (isset($decodedJsonOutput->data->attributes) && !isset($this->successfulCsvLines[$dataCurrentCSVline])) {
                if ($this->silent == 1) {
                    $this->successfulCsvLines[$dataCurrentCSVline] = sprintf(
                        "%s Deployed to: %s, CSV Line: %d, id: %d, created: %s, title: %s, alias: %s%s%s",
                        ANSI_COLOR_GREEN,
                        $decodedDataString->tokenindex,
                        $dataCurrentCSVline,
                        $decodedJsonOutput->data->id,
                        $decodedJsonOutput->data->attributes->created,
                        $decodedJsonOutput->data->attributes->title,
                        $decodedJsonOutput->data->attributes->alias,
                        ANSI_COLOR_NORMAL,
                        CUSTOM_LINE_END
                    );

                    $this->enqueueMessage($this->successfulCsvLines[$dataCurrentCSVline]);
                }
            }
        } catch (Throwable $e) {
            if ($this->silent == 1) {
                $this->failedCsvLines[$dataCurrentCSVline] = sprintf(
                    "%s Error message: %s, Error code line: %d, Error CSV Line: %d%s%s",
                    ANSI_COLOR_RED,
                    $e->getMessage(),
                    $e->getLine(),
                    $dataCurrentCSVline,
                    ANSI_COLOR_NORMAL,
                    CUSTOM_LINE_END
                );
                $this->enqueueMessage($this->failedCsvLines[$dataCurrentCSVline], 'error');
            }
        }
    }

    /**
     * @param   string  $givenHttpVerb
     * @param   string  $endpoint
     * @param   string  $dataString
     * @param   array   $headers
     * @param   int     $timeout
     *
     * @return string
     */
    private function processHttpRequest(
        string $givenHttpVerb,
        string $endpoint,
        string $dataString,
        array $headers,
        int $timeout = 3
    ): string
    {
        $uri      = (new Uri($endpoint));
        $response = $this->getHttpClient()->request(
            $givenHttpVerb,
            $uri,
            $dataString,
            $headers,
            $timeout,
            self::USER_AGENT
        );

        if (empty($response)) {
            throw new UnexpectedValueException('Invalid response received after Http request. Cannot continue', 422);
        }

        return (string)$response->getBody();
    }

    /**
     * This time we need endpoint to be a function to make it more dynamic
     */
    private function endpoint(string $givenBaseUrl, string $givenBasePath, int $givenResourceId = 0): string
    {
        return $givenResourceId ?
            sprintf('%s%s/%s/%d', $givenBaseUrl, $givenBasePath, 'content/articles', $givenResourceId)
            : sprintf('%s%s/%s', $givenBaseUrl, $givenBasePath, 'content/articles');
    }
}
