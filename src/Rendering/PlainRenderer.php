<?php

declare(strict_types=1);

namespace Roxblnfk\DeadLink\Rendering;

use Roxblnfk\DeadLink\Snapshot;

/**
 * @psalm-import-type ObjectReferencesList from Snapshot
 */
class PlainRenderer
{
    private array $data = [];

    public function __construct(
        private Snapshot $snapshot,
    ) {
    }

    public function data(mixed ...$data): static
    {
        $this->data = [...$this->data, ...$data];
        return $this;
    }

    public function render(bool $skipEmpty = false): object
    {
        $result = $this->data;
        $result['Total count'] = $this->snapshot->count();
        $refs = [];
        /**
         * @var object $object
         * @var ObjectReferencesList $references
         */
        foreach ($this->snapshot as $object => $references) {
            $alias = $this->snapshot->getRootAlias($object);
            $alias = \is_string($alias) ? "[$alias] " : '';
            $details = [];
            $cnt = 0;
            foreach ($references as $reference) {
                if ($reference === null) {
                    // $details[] = 'Root';
                    continue;
                }
                ++$cnt;
                [$ref, $path] = $reference;
                $parent = $ref->get();
                // if ($parent === null) {
                //     continue;
                // }
                $details[] = \sprintf('%s parent: %s', $path, $parent === null ? 'NULL' : $parent::class);
            }

            $id = sprintf('%s%s %s (%d)', $alias, $object::class, \spl_object_id($object), $cnt);
            if ($skipEmpty && $cnt === 0) {
                continue;
            }

            $refs[$id] = $details;
        }

        $result['References'] = $refs;
        return (object)$result;
    }
}
