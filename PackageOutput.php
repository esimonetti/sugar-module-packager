<?php

// Enrico Simonetti
// enricosimonetti.com
//
// 2018-01-25 on Sugar 7.9.3.0
//
// Tool that helps package Sugar installable modules

namespace SugarModulePackager;

class PackageOutput
{
    public static function message($out = '')
    {
        if (!empty($out)) {
            echo $out . PHP_EOL;
        }
    }

    public static function createFile($filename = '', $content = '')
    {
        if (!empty($filename)) {
            file_put_contents($filename, $content);
        }
    }

    public static function copyFile($src, $dst)
    {
        if (!empty($src) && !empty($dst)) {
            copy($src, $dst);
        }
    }
}
