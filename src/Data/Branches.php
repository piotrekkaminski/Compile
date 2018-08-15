<?php
namespace M2t\Data;

class Branches
{
    public static $branches = [
        "2.2.5",
        "2.1.13",
        "Head",
    ];

    public static function branches()
    {
        return self::$branches;
    }
}