<?php

namespace Api\Model\General;

class ScriptsComposer
{
    /**
     * Clears the project cache.
     */
    public static function clearCache($event)
    {
        $cacheDir = 'var/cache/';

        //exec('sudo rm -rf '.$cacheDir.'*');
    }
}