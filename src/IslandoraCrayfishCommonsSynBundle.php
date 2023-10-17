<?php

namespace Islandora\Crayfish\Commons\Syn;

use Islandora\Crayfish\Commons\Syn\DependencyInjection\CrayfishCommonsSynExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Bundle definition.
 *
 * XXX: The "Islandora" prefix seems to be called for, due to the namespace? Was
 * not detecting the bundle correctly without it.
 */
class IslandoraCrayfishCommonsSynBundle extends Bundle
{
    protected function getContainerExtensionClass()
    {
        return CrayfishCommonsSynExtension::class;
    }
}
