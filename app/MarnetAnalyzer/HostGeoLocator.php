<?php

/**
 * Stores an IP Locator object
 *
 * @package MarnetAnalyzer
 * @author Dejan Angelov <angelovdejan92@gmail.com>
 * @link https://github.com/angelov/MarnetAnalyzer
 */

namespace MarnetAnalyzer;

use GeoIp2\Database\Reader;

abstract class HostGeoLocator {

    private static $_instance;
    private static $_database;

    public static function instance() {

        if (!isset(self::$_instance)) {

            if (!isset(self::$_database)) {
                self::$_database = __DIR__."/../../countries.mmdb";
            }

            self::$_instance = new Reader(self::$_database);
            //echo "\ncreated locator instance\n";

        }

        return self::$_instance;

    }

    public static function setDatabase($database) {
        self::$_database = $database;
    }

}