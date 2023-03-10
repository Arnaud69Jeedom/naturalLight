<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInita67f346bed05228146640799a64a769a
{
    public static $prefixLengthsPsr4 = array (
        'S' => 
        array (
            'SolarData\\Tests\\' => 16,
            'SolarData\\' => 10,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'SolarData\\Tests\\' => 
        array (
            0 => __DIR__ . '/..' . '/abbadon1334/sun-position-spa-php/tests',
        ),
        'SolarData\\' => 
        array (
            0 => __DIR__ . '/..' . '/abbadon1334/sun-position-spa-php/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInita67f346bed05228146640799a64a769a::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInita67f346bed05228146640799a64a769a::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInita67f346bed05228146640799a64a769a::$classMap;

        }, null, ClassLoader::class);
    }
}
