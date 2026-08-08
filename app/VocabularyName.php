<?php

namespace App;

use Illuminate\Support\Str;

final class VocabularyName
{
    public static function display(string $name): string
    {
        return Str::squish($name);
    }

    public static function normalize(string $name): string
    {
        return Str::lower(self::display($name));
    }
}
