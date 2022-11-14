<?php

namespace Roxblnfk\DeadLink;

use Roxblnfk\DeadLink\Analyze\Comparator;

class StaticHelper
{
    public static Snapshot $snapshot;

    public static function snap(object ...$objects): Snapshot
    {
        if (!isset(static::$snapshot)) {
            static::$snapshot = Snapshot::make(...$objects);
        } else {
            foreach ($objects as $key => $object) {
                static::$snapshot->snapObject($object, \is_string($key) ? $key : null);
            }
        }
        return static::$snapshot;
    }

    public static function leaks(object ...$objects): Snapshot
    {
        $newSnapshot = Snapshot::make(...$objects);

        return (new Comparator())->compare(self::$snapshot, $newSnapshot);
    }

    public static function compare(Snapshot $snapshot): Snapshot
    {
        return (new Comparator())->compare(self::$snapshot, $snapshot);
    }
}
