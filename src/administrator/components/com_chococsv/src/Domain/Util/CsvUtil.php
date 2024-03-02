<?php

declare(strict_types=1);

/**
 * @copyright (c) 2009 - present. Mr Alexandre J-S William ELISÉ. All rights reserved.
 * @license       GNU Affero General Public License v3.0 or later (AGPL-3.0-or-later). See LICENSE.txt file
 */

namespace AlexApi\Component\Chococsv\Administrator\Domain\Util;

use Throwable;

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
use function str_contains;
use function str_getcsv;
use function stream_get_line;
use function stream_set_blocking;
use function strlen;

use const CSV_START;
use const PHP_EOL;
use const SORT_ASC;
use const SORT_NATURAL;

final class CsvUtil
{
    /**
     * @param string $wantedLineNumbers
     *
     * @return array|int[]
     */
    public static function chooseLinesLikeAPrinter(string $wantedLineNumbers = ''): array
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
     * @param $resource
     * @param array $linesYouWant
     * @param callable $success
     * @param callable $error
     * @return void
     */
    public static function computeCsv(
        $resource,
        array $linesYouWant,
        array $mergedKeys,
        callable $success,
        callable $error
    ) {
        stream_set_blocking($resource, false);
        $currentCsvLineNumber = 1;
        $csvHeader = str_getcsv(stream_get_line($resource, 1024 * 1024, PHP_EOL) ?? '');
        $flippedMergedKeys = array_flip($mergedKeys);
        while (!feof($resource)) {
            try {
                if ($linesYouWant && !in_array($currentCsvLineNumber, $linesYouWant, true)) {
                    continue;
                }
                $parsed = str_getcsv(stream_get_line($resource, 1024 * 1024, PHP_EOL) ?? '');
                $success([
                    'csv_line' => $currentCsvLineNumber,
                    'csv_header' => array_intersect_key($csvHeader, $flippedMergedKeys),
                    'csv_parsed' => $parsed,
                ]);
            } catch (Throwable $e) {
                ++$currentCsvLineNumber;
                //Log/Show message
                $error($e);
                continue;
            }
            ++$currentCsvLineNumber;
        }
        if (($resource !== null) && is_resource($resource)) {
            fclose($resource);
        }
    }

}
