<?php

declare(strict_types=1);

/**
 * @copyright (c) 2009 - present. Mr Alexandre J-S William ELISÉ. All rights reserved.
 * @license       GNU Affero General Public License v3.0 or later (AGPL-3.0-or-later). See LICENSE.txt file
 */

namespace AlexApi\Component\Chococsv\Administrator\Domain\Util;

use AlexApi\Component\Chococsv\Administrator\Domain\Model\Destination\Destination;
use AlexApi\Component\Chococsv\Administrator\Domain\Model\Destination\TokenIndexMismatchException;
use AlexApi\Component\Chococsv\Administrator\Domain\Model\State\DeployArticleCommandState;
use Error;
use InvalidArgumentException;
use LogicException;
use UnexpectedValueException;

use function array_intersect;
use function array_intersect_key;
use function array_merge;
use function array_unique;
use function explode;
use function fclose;
use function feof;
use function in_array;
use function is_resource;
use function range;
use function sort;
use function sprintf;
use function str_contains;
use function str_getcsv;
use function stream_get_line;
use function stream_set_blocking;
use function strlen;

use const PHP_EOL;
use const SORT_ASC;
use const SORT_NATURAL;

final class CsvUtil
{
    /**
     * @return array|int[]
     */
    public static function chooseLinesLikeAPrinter(string $wantedLineNumbers = '', int $csvActualStartLine = 2): array
    {
        // When strictly empty process every Csv lines (Full range)
        if ($wantedLineNumbers === '') {
            return [];
        }

        // Cut-off useless processing when single digit range
        if (strlen($wantedLineNumbers) === 1) {
            return (((int)$wantedLineNumbers) < $csvActualStartLine) ? [$csvActualStartLine] : [((int)$wantedLineNumbers)];
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
                $result1 = ((int)$commaPart) > 1 ? ((int)$commaPart) : $csvActualStartLine;
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
                $result2 = ((int)$commaPart) > 1 ? ((int)$commaPart) : $csvActualStartLine;
                if (!in_array($result2, $output, true)) {
                    $output[] = $result2;
                }
                // Skip to next comma part
                continue;
            }
            // First line is the header, so we MUST start at least at line 2. Hence, 2 or more
            $dashParts[0] = ((int)$dashParts[0]) > 1 ? ((int)$dashParts[0]) : $csvActualStartLine;

            // First line is the header, so we MUST start at least at line 2. Hence, 2 or more
            $dashParts[1] = ((int)$dashParts[1]) > 1 ? ((int)$dashParts[1]) : $csvActualStartLine;

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
     * @param $resource
     * @return void
     */
    public static function computeCsv(
        $resource,
        array $linesYouWant,
        array $mergedKeys,
        callable $success,
        callable $error,
        int $csvActualStartLine = 2
    ): void {
        if (!is_resource($resource)) {
            throw new InvalidArgumentException(
                'Resource provided is invalid. Might be file not found or not readable',
                400
            );
        }

        stream_set_blocking($resource, false);
        $currentCsvLineNumber = $csvActualStartLine;
        $csvHeader = str_getcsv(stream_get_line($resource, 1024 * 1024, PHP_EOL) ?: '');

        $computedCsvHeader = array_intersect($csvHeader, $mergedKeys);

        while (feof($resource) !== true) {
            try {
                $parsed = str_getcsv(stream_get_line($resource, 1024 * 1024, PHP_EOL) ?: '');

                $computed = array_intersect_key($parsed, $computedCsvHeader);
                if ($computed === []) {
                    throw new UnexpectedValueException(
                        sprintf('CSV Line %d could not be parsed or empty line', $currentCsvLineNumber), 422
                    );
                }
// Process only lines you want
                if (($linesYouWant === []) || in_array($currentCsvLineNumber, $linesYouWant, true)) {
                    try {
                        //Ignore Invalid Lines
                        if (count($computedCsvHeader) !== count($computed)) {
                            ++$currentCsvLineNumber;
                            continue;
                        }

                        $success([
                            'csv_line' => $currentCsvLineNumber,
                            'csv_header' => $computedCsvHeader,
                            'csv_parsed' => $computed,
                        ]);
                    } catch (TokenIndexMismatchException) {
                        // Happens when destination is not configured or disabled
                        // For specific CSV line matching that tokenindex
                        // For example when app-002 is disabled in destinations configuration. It is not a failure per-se it's how we manage this in chococsv
                        ++$currentCsvLineNumber;
                        continue;
                    }
                }
                ++$currentCsvLineNumber;
            } catch (LogicException $e) {
                ++$currentCsvLineNumber;
                //Log/Show message
                $error($e);
                continue;
            } catch (Error $e2) {
                $error($e2);
                if (isset($resource) && is_resource($resource)) {
                    fclose($resource);
                }
                break;
            }
        }
        if (isset($resource) && is_resource($resource)) {
            fclose($resource);
        }
    }


    /**
     * @return array
     */
    public static function computeMergedKeys(Destination $currentDestination): array
    {
        return array_unique(
            array_merge(
                DeployArticleCommandState::DEFAULT_ARTICLE_KEYS,
                ($currentDestination?->getExtraDefaultFieldKeys()?->asArray() ?? []),
                ($currentDestination?->getCustomFieldKeys()?->asArray() ?? [])
            )
        );
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
