<?php

declare(strict_types=1);

namespace Roxblnfk\DeadLink\Analyze;

use Roxblnfk\DeadLink\Snapshot;

final class Analyzer
{
    public function __construct(
        private Snapshot $snapshot,
    ) {
    }

    /**
     * Calc summaries
     */
    public function compareWith(): array
    {
        $result = [];



        foreach ($this->snapshot as $object => $meta) {

        }

        return $result;
    }

    public function findCycles()
    {

    }
}
