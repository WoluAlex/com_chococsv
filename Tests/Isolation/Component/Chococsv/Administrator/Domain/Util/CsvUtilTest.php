<?php

declare(strict_types=1);

/**
 * @copyright (c) 2009 - present. Mr Alexandre J-S William ELISÉ. All rights reserved.
 * @license       GNU Affero General Public License v3.0 or later (AGPL-3.0-or-later). See LICENSE.txt file
 */

namespace Tests\Isolation\Component\Chococsv\Administrator\Domain\Util;

use AlexApi\Component\Chococsv\Administrator\Domain\Model\State\DeployArticleCommandState;
use AlexApi\Component\Chococsv\Administrator\Domain\Util\CsvUtil;
use Tests\Isolation\IsolationTestCase;

use function AlexApi\Component\Chococsv\Administrator\Command\null;
use function fopen;
use function print_r;
use function range;

use const PROJECT_TEST;

final class CsvUtilTest extends IsolationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        defined('CSV_START') || define('CSV_START', 2);
    }


    public function testTestChooseLinesLikeAPrinterWhenLinesYouWantIsEmpty()
    {
        // Given
        $linesYouWant = '';
        // When
        $actual = CsvUtil::chooseLinesLikeAPrinter($linesYouWant);
        // Then
        $expected = [];
        self::assertSame($expected, $actual);
    }

    public function testTestChooseLinesLikeAPrinterWhenLinesYouWantIsZeroItBecomesTwo()
    {
        // Given
        $linesYouWant = '0';
        // When
        $actual = CsvUtil::chooseLinesLikeAPrinter($linesYouWant);
        // Then
        $expected = [2];
        self::assertSame($expected, $actual);
    }

    public function testTestChooseLinesLikeAPrinterWhenLinesYouWantIsOneItBecomesTwo()
    {
        // Given
        $linesYouWant = '1';
        // When
        $actual = CsvUtil::chooseLinesLikeAPrinter($linesYouWant);
        // Then
        $expected = [2];
        self::assertSame($expected, $actual);
    }

    public function testTestChooseLinesLikeAPrinterWhenLinesYouWantUsesHyphenRangeInAscendingOrder()
    {
        // Given
        $linesYouWant = '1-3';
        // When
        $actual = CsvUtil::chooseLinesLikeAPrinter($linesYouWant);
        // Then
        $expected = range(2, 3);
        self::assertSame($expected, $actual);
    }

    public function testTestChooseLinesLikeAPrinterWhenLinesYouWantUsesHyphenRangeInDescendingOrder()
    {
        // Given
        $linesYouWant = '3-1';
        // When
        $actual = CsvUtil::chooseLinesLikeAPrinter($linesYouWant);
        // Then
        $expected = range(2, 3);
        self::assertSame($expected, $actual);
    }

    public function testTestChooseLinesLikeAPrinterWhenLinesYouWantUsesHyphenRangeWithGaps()
    {
        // Given
        $linesYouWant = '1-3,7-8,11';
        // When
        $actual = CsvUtil::chooseLinesLikeAPrinter($linesYouWant);
        // Then
        $expected = [2, 3, 7, 8, 11];
        self::assertSame($expected, $actual);
    }

    public function testComputeCsvLinesWithSpecificLines()
    {
        $resource = fopen(PROJECT_TEST . 'media/com_chococsv/data/sample-data.csv', 'r');

        $orderedSet = CsvUtil::chooseLinesLikeAPrinter('0,8,9,3,4,10');
        $rows = [0 => 1];
        CsvUtil::computeCsv(
            $resource,
            $orderedSet,
            DeployArticleCommandState::DEFAULT_ARTICLE_KEYS,
            function ($successData) use (&$rows, $orderedSet) {
                $rows[] = $successData['csv_line'];
            },
            fn($errorData) => print_r($errorData->getMessage(), true)
        );
        $actual = array_intersect($rows, $orderedSet);
        self::assertSame(
            [1 => 2, 2 => 3, 3 => 4, 4 => 8, 5 => 9, 6 => 10],
            $actual
        );
    }

    public function testComputeCsvLinesWithAllLinesWithDefaultArticleKeysOnly()
    {
        $resource = fopen(PROJECT_TEST . 'media/com_chococsv/data/sample-data.csv', 'r');
        $orderedSet = CsvUtil::chooseLinesLikeAPrinter('');

        CsvUtil::computeCsv(
            $resource,
            $orderedSet,
            DeployArticleCommandState::DEFAULT_ARTICLE_KEYS,
            fn($successData) => self::assertSame(
                DeployArticleCommandState::DEFAULT_ARTICLE_KEYS,
                $successData['csv_header'],
                sprintf(
                    'CSV header columns should contain only default article keys such as %s',
                    implode(',', DeployArticleCommandState::DEFAULT_ARTICLE_KEYS)
                )
            ),
            fn($errorData) => print_r($errorData->getMessage(), true)
        );
    }

    public function testComputeCsvLinesWithAllLinesReturnsAllLinesInTheCsvFile()
    {
        // Given: sample csv file with 42 lines
        // And we want all the lines
        $resource = fopen(PROJECT_TEST . 'media/com_chococsv/data/sample-data.csv', 'r');
        $orderedSet = CsvUtil::chooseLinesLikeAPrinter('');

        // When we call read csv file
        CsvUtil::computeCsv(
            $resource,
            $orderedSet,
            DeployArticleCommandState::DEFAULT_ARTICLE_KEYS,
            fn($successData) => self::assertTrue(
                in_array($successData['csv_line'], range(1, 42), true),
                sprintf('%d This CSV line number is not compliant with expected line range', $successData['csv_line'])
            ),
            fn($errorData) => print_r($errorData->getMessage(), true),
            1
        );
    }


}
