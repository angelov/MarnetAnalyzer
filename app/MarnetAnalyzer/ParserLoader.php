<?php

/**
 * Fetches the source code of an url and loads a parser with it
 *
 * @package MarnetAnalyzer
 * @author Dejan Angelov <angelovdejan92@gmail.com>
 * @link https://github.com/angelov/MarnetAnalyzer
 */

namespace MarnetAnalyzer;

use Guzzle\Http\Client;
use Guzzle\Http\Exception\CurlException;
use MarnetAnalyzer\Exception\NoConnectionException;
use Sunra\PhpSimple\HtmlDomParser as Parser;

class ParserLoader {

    public static function fetch($url) {

        $client = new Client($url);

        try {
            $response = $client->get()->send();
        } catch (CurlException $e) {
            throw new NoConnectionException("Check your connection.");
        }

        $content = (string) $response;

        return Parser::str_get_html($content);

    }

}