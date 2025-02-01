<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit6b6b3c8e2b3af3dff59647a3f830e015
{
    public static $files = array (
        '979dffec6fa5205cabd2c2cd1e9e6b3a' => __DIR__ . '/..' . '/algolia/algoliasearch-client-php/src/Http/Psr7/functions.php',
        '6783aef8c489bbc166eee2536fe605d5' => __DIR__ . '/..' . '/algolia/algoliasearch-client-php/src/functions.php',
    );

    public static $prefixLengthsPsr4 = array (
        'W' => 
        array (
            'Website_Ai_Assistant\\' => 21,
        ),
        'P' => 
        array (
            'Psr\\SimpleCache\\' => 16,
            'Psr\\Log\\' => 8,
            'Psr\\Http\\Message\\' => 17,
        ),
        'C' => 
        array (
            'Composer\\Installers\\' => 20,
        ),
        'A' => 
        array (
            'Algolia\\AlgoliaSearch\\' => 22,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Website_Ai_Assistant\\' => 
        array (
            0 => __DIR__ . '/../..' . '/includes',
        ),
        'Psr\\SimpleCache\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/simple-cache/src',
        ),
        'Psr\\Log\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/log/src',
        ),
        'Psr\\Http\\Message\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/http-message/src',
        ),
        'Composer\\Installers\\' => 
        array (
            0 => __DIR__ . '/..' . '/composer/installers/src/Composer/Installers',
        ),
        'Algolia\\AlgoliaSearch\\' => 
        array (
            0 => __DIR__ . '/..' . '/algolia/algoliasearch-client-php/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit6b6b3c8e2b3af3dff59647a3f830e015::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit6b6b3c8e2b3af3dff59647a3f830e015::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit6b6b3c8e2b3af3dff59647a3f830e015::$classMap;

        }, null, ClassLoader::class);
    }
}
