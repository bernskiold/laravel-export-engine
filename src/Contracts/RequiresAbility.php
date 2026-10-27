<?php

namespace Bernskiold\ExportEngine\Contracts;

/**
 * An exporter that only a person with an ability can use.
 *
 * The engine checks the ability when the export starts and again when the
 * job runs. A queued export can run a long time after the person asked for
 * it, and in that time they can lose the ability.
 */
interface RequiresAbility
{
    /**
     * The ability the person must have, as a Gate ability.
     */
    public static function exportAbility(): string;
}
