<?php

declare(strict_types=1);

namespace Roxblnfk\DeadLink\Analyze;

use Roxblnfk\DeadLink\Snapshot;

/**
 * @psalm-import-type ObjectReferencesList from Snapshot
 */
final class Comparator
{
    /**
     * Make a diff between two snapshots
     */
    public function compare(Snapshot $a, Snapshot $b): Snapshot
    {
        $result = clone $a;
        $temp = clone $b;

        foreach ($a as $object => $offsetA) {
            if (!$temp->offsetExists($object)) {
                continue;
            }

            // Work with offsets
            $offsetB = $temp->offsetGet($object);
            $temp->offsetUnset($object);

            if ($offsetA === $offsetB) {
                $result->offsetUnset($object);
                continue;
            }

            $refList = $this->diffReferenceLists($offsetA, $offsetB);
            if ($refList === []) {
                $result->offsetUnset($object);
                continue;
            }

            $result->offsetSet($object, $refList);
        }
        foreach ($temp as $object => $offsetB) {
            $result->offsetSet($object, $offsetB);
        }

        return $result;
    }

    /**
     * @param ObjectReferencesList $a
     * @param ObjectReferencesList $b
     *
     * @return ObjectReferencesList
     */
    private function diffReferenceLists(array $offsetA, array $offsetB): array
    {
        // $fn = static fn (array $a, array $b): int => (int)($a[0] !== $b[0]);
        $fn = static function (?array $a, ?array $b): int {
            if ($a === null || $b === null) {
                return (int)($a === null xor $b === null);
            }
            return (int)($a[0] !== $b[0]);
        };
        $result1 = \array_udiff($offsetA, $offsetB, $fn);
        $result2 = \array_udiff($offsetB, $offsetA, $fn);
        return [...$result1, ...$result2];
    }
}
