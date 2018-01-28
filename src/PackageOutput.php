<?php

// Enrico Simonetti
// enricosimonetti.com
//
// 2018-01-29 on Sugar 7.9.3.0
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

    public static function writeFile($filename = '', $content = '')
    {
        if (!empty($filename)) {
            file_put_contents($filename, $content);
        }
    }

    public static function readFile($filename = '')
    {
        if (!empty($filename) && file_exists($filename)) {
            return file_get_contents($filename);
        }
        return '';
    }

    public static function copyFile($src, $dst)
    {
        if (!empty($src) && !empty($dst)) {
            copy($src, $dst);
        }
    }

    public static function createDirectory($directory = '')
    {
        if (!empty($directory)) {
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
        }
    }
}
