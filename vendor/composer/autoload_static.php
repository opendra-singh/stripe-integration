<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit568cdf1e980c5265fee14a951ffdba01
{
    public static $prefixLengthsPsr4 = array (
        'S' => 
        array (
            'Stripe\\' => 7,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Stripe\\' => 
        array (
            0 => __DIR__ . '/..' . '/stripe/stripe-php/lib',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit568cdf1e980c5265fee14a951ffdba01::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit568cdf1e980c5265fee14a951ffdba01::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit568cdf1e980c5265fee14a951ffdba01::$classMap;

        }, null, ClassLoader::class);
    }
}
