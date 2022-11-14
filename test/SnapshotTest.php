<?php

declare(strict_types=1);

namespace Roxblnfk\DeadLink\Test;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use stdClass;
use Roxblnfk\DeadLink\Snapshot;

class SnapshotTest extends TestCase
{
    public function testSimpleObject(): void
    {
        $object = (object)['foo' => new stdClass(), 'bar' => new DateTimeImmutable()];

        $snapshot = Snapshot::make(std: $object);

        self::assertCount(3, $snapshot);
    }

    public function testUpdate(): void
    {
        $object = (object)['foo' => new stdClass(), 'bar' => new DateTimeImmutable()];

        $snapshot = Snapshot::make(std: $object);

        $object->baz = new stdClass();
        self::assertCount(3, $snapshot);

        $snapshot->updateMap();
        self::assertCount(4, $snapshot);

        $snapshot->updateMap($object->baz);
        self::assertCount(4, $snapshot);

        $add = new DateTimeImmutable();
        $snapshot->updateMap(test: $add);
        self::assertTrue($snapshot->offsetExists($add));
        self::assertCount(5, $snapshot);
    }
}
