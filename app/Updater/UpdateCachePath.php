<?php

namespace App\Updater;

class UpdateCachePath
{
    public static function default(): string
    {
        $xdgCacheHome = getenv('XDG_CACHE_HOME');

        if (is_string($xdgCacheHome) && $xdgCacheHome !== '') {
            return $xdgCacheHome.'/scotty';
        }

        $home = getenv('HOME');

        if (! is_string($home) || $home === '') {
            $home = $_SERVER['HOME'] ?? null;
        }

        if (! is_string($home) || $home === '') {
            return sys_get_temp_dir().'/scotty';
        }

        return $home.'/.cache/scotty';
    }
}
