<?php

declare(strict_types=1);

/**
 * @copyright (c) 2009 - present. Mr Alexandre J-S William ELISÉ. All rights reserved.
 * @license       GNU Affero General Public License v3.0 or later (AGPL-3.0-or-later). See LICENSE.txt file
 */


namespace AlexApi\Component\Chococsv\Administrator\Domain\Model\State;

use DomainException;

final class DeployArticleCommandState
{
    public const ASCII_BANNER = <<<TEXT
    __  __     ____         _____                              __                      __
   / / / ___  / / ____     / ___/__  ______  ___  _____       / ____  ____  ____ ___  / ___  __________
  / /_/ / _ \/ / / __ \    \__ \/ / / / __ \/ _ \/ ___/  __  / / __ \/ __ \/ __ `__ \/ / _ \/ ___/ ___/
 / __  /  __/ / / /_/ /   ___/ / /_/ / /_/ /  __/ /     / /_/ / /_/ / /_/ / / / / / / /  __/ /  (__  )
/_/ /_/\___/_/_/\____/   /____/\__,_/ .___/\___/_/      \____/\____/\____/_/ /_/ /_/_/\___/_/  /____/
                                   /_/
TEXT;

    public const REQUEST_TIMEOUT = 3;

    public const DEFAULT_ARTICLE_KEYS = [
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

    public const MAX_RETRIES = 3;

    /**
     * @var array $destinations
     */
    private array $destinations = [];
    private bool $showAsciiBanner = false;

    private array $successfulCsvLines = [];

    private array $failedCsvLines = [];

    private bool $isDone = false;

    private function __construct(
        array $destinations,
        private SilentMode $silent,
        private SaveReportToFile $saveReportToFile
    ) {
        if (empty($destinations)) {
            throw new DomainException(
                'Destinations subform MUST contain at least one destination where your articles will be deployed',
                422
            );
        }

        $this->destinations = $destinations;
    }

    public static function fromState(
        array $givenDestinations,
        int $givenSilent = 0,
        int $givenSaveReportToFile = 0
    ): self {
        return (new self(
            $givenDestinations,
            SilentMode::fromInt($givenSilent),
            SaveReportToFile::fromInt($givenSaveReportToFile)
        ));
    }

    public function withAsciiBanner(bool $showAsciiBanner = false): self
    {
        $cloned = clone $this;
        $cloned->showAsciiBanner = $showAsciiBanner;
        return $cloned;
    }

    public function shouldShowAsciiBanner(): bool
    {
        return $this->showAsciiBanner;
    }

    public function withSuccessfulCsvLines(array $value): self
    {
        $cloned = clone $this;
        $cloned->successfulCsvLines = $value;
        return $cloned;
    }

    public function withFailedCsvLines(array $value): self
    {
        $cloned = clone $this;
        $cloned->failedCsvLines = $value;
        return $cloned;
    }

    public function withDone(bool $value): self
    {
        $cloned = clone $this;
        $cloned->isDone = $value;
        return $cloned;
    }

    public function getDestinations(): array
    {
        return $this->destinations;
    }

    public function isShowAsciiBanner(): bool
    {
        return $this->showAsciiBanner;
    }

    public function getSuccessfulCsvLines(): array
    {
        return $this->successfulCsvLines;
    }

    public function getFailedCsvLines(): array
    {
        return $this->failedCsvLines;
    }

    public function isDone(): bool
    {
        return $this->isDone;
    }

    public function getSilent(): SilentMode
    {
        return $this->silent;
    }

    public function getSaveReportToFile(): SaveReportToFile
    {
        return $this->saveReportToFile;
    }


}
