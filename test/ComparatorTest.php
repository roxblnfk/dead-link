<?php

declare(strict_types=1);

namespace Roxblnfk\DeadLink\Test;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use stdClass;
use Roxblnfk\DeadLink\Analyze\Comparator;
use Roxblnfk\DeadLink\Snapshot;

class ComparatorTest extends TestCase
{
    public function testTheSame(): void
    {
        $object = (object)['foo' => new stdClass(), 'bar' => new DateTimeImmutable()];

        $snapshot1 = Snapshot::make(std: $object);
        $snapshot2 = Snapshot::make(std: $object);
        $result = $this->createComparator()->compare($snapshot1, $snapshot2);

        self::assertCount(0, $result);
    }

    public function testSameObjectDifferentStates(): void
    {
        $object = (object)['foo' => new stdClass(), 'bar' => new DateTimeImmutable()];
        $snapshot1 = Snapshot::make(std: $object);

        $object->baz = new DateTimeImmutable();
        $snapshot2 = Snapshot::make(std: $object);

        $result = $this->createComparator()->compare($snapshot1, $snapshot2);

        self::assertCount(1, $result);
        self::assertInstanceOf(DateTimeImmutable::class, $result->getIterator()->key());
        $links = $result->getIterator()->current();
        self::assertCount(1, $links);
        [$parent, $path] = $links[0];
        self::assertSame($object, $parent->get());
        self::assertSame('std.baz', $path);
    }

    private function createComparator(): Comparator
    {
        return new Comparator();
    }
}
