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
     * @param ObjectReferencesList ...$references
     *
     * @return ObjectReferencesList
     */
    private function diffReferenceLists(array ...$references): array
    {
        $result = [];
        $nullable = false;
        $merger = static function (array $refs) use (&$nullable, &$result): void {
            foreach ($refs as $ref) {
                if ($ref === null) {
                    $nullable = true;
                    continue;
                }
                $result[\spl_object_id($ref[0])] = $ref;
            }
        };
        \array_walk($references, $merger);
        if ($nullable) {
            $result[] = null;
        }
        return \array_values($result);
    }
}
