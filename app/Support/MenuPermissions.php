<?php

namespace App\Support;

final class MenuPermissions
{
    /**
     * @return array<int, string>
     */
    public static function collect(): array
    {
        $path = resource_path('js/menu/appMenuDefinition.js');

        if (!is_file($path)) {
            return [];
        }

        $content = file_get_contents($path) ?: '';

        // nagyon egyszerű: can: "xxx.yyy"
        preg_match_all('/can\s*:\s*[\'"]([^\'"]+)[\'"]/', $content, $m);

        $perms = $m[1];

        // tisztítás + unique
        $perms = array_values(array_unique(array_filter(array_map('trim', $perms))));

        return $perms;
    }
}
