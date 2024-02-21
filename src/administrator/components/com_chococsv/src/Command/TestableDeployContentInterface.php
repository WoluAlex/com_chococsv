<?php

/**
 * @copyright (c) 2009 - present. Mr Alexandre J-S William ELISÉ. All rights reserved.
 * @license       GNU Affero General Public License v3.0 or later (AGPL-3.0-or-later). See LICENSE.txt file
 */

namespace AlexApi\Component\Chococsv\Administrator\Command;

// phpcs:disable PSR1.Files.SideEffects

\defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects

interface TestableDeployContentInterface
{
    public function testChooseLinesLikeAPrinter($linesYouWant);

    public function testNestedJsonDataStructure($data);

    public function testCsvReader();

    public function testProcessEachCsvLineData($dataCurrentCSVline, $data);

    public function testProcessHttpRequest($givenHttpVerb, $endpoint, $data, $headers, $timeout);

    public function testEndpoint($givenBaseUrl, $givenBasePath, $givenResourceId);

}
