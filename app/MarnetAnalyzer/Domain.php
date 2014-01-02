<?php

/**
 * Represents a single .mk domain
 *
 * @package MarnetAnalyzer
 * @author Dejan Angelov <angelovdejan92@gmail.com>
 * @link https://github.com/angelov/MarnetAnalyzer
 */

namespace MarnetAnalyzer;

use GeoIp2\Exception\GeoIp2Exception;
use MarnetAnalyzer\Exception\DomainNotFoundException;
use Purl\Url;
use Sunra\PhpSimple\HtmlDomParser as Parser;

use MarnetAnalyzer\ParserLoader;

class Domain implements \JsonSerializable {

    private $_name;
    private $_dateValid;
    private $_dateRegistered;
    private $_registrant;
    private $_mainTable;
    private $_htmlDom;
    private $_url;
    private $_type;
    private $_nameservers;
    private $_statusCode;
    private $_host;
    private $_location;

    /**
     * Fetches all the info for a domain
     *
     * @param string $name The domain
     * @throws Exception\DomainNotFoundException
     */
    public function __construct($name) {
        $this->_name = $name;
        $this->_url = new Url("http://reg.marnet.net.mk/registar.php");

        $this->_url->query->set("dom", $name);

        $dom = ParserLoader::fetch($this->_url);

        $this->_htmlDom = $dom;

        if (!$this->domainExists()) {
            throw new DomainNotFoundException("The domain ". $this->_name ." doesn't have a record.");
        }

        $this->_mainTable = $dom->find('table table table tr td');
        //file_put_contents("domains/".$name.".html", $dom);

    }

    /**
     * Checks if there's detailed info for an domain
     *
     * @return bool
     */
    private function domainExists() {
        $dom = $this->_htmlDom->find('table table tr td');

        // Поддомен со име nepostoechki.mk не е регистриран во .mk зоната.

        return (strlen($dom[1]->nodes[0]->{'_'}[4]) < 10);
    }

    public function getDomainAsUrl() {
        return new Url("http://" . $this->_name);
    }

    public function getUrl() {
        return $this->_url;
    }

    public function getDateRegistered() {

        if (isset($this->_dateRegistered)) {
            return $this->_dateRegistered;
        }

        $date_registered = Parser::str_get_html($this->_mainTable[3]);
        $date_registered = $date_registered->find('td');
        $date_registered = $date_registered[0]->nodes[0]->{'_'}[4];

        $this->_dateRegistered = $date_registered;

        return $this->_dateRegistered;

    }

    public function getYearRegistered() {

        $year_reg = $this->getDateRegistered();
        if (strlen($year_reg) > 10) { // Пред xx-xx-xxxx
            $year_reg = "< 2003";
        } else {
            $year_reg = new \DateTime($year_reg);
            $year_reg = $year_reg->format("Y");
        }

        return $year_reg;

    }

    public function getDateValid() {

        if (isset($this->_dateValid)) {
            return $this->_dateValid;
        }

        $date_valid = Parser::str_get_html($this->_mainTable[1]);
        $date_valid = $date_valid->find('td blockquote');
        $date_valid = $date_valid[0]->nodes[0]->{'_'}[4];

        $this->_dateValid = $date_valid;

        return $this->_dateValid;

    }

    public function getRegistrant() {

        if (isset($this->_registrant)) {
            return $this->_registrant;
        }

        $registrant = Parser::str_get_html($this->_mainTable[5]);
        $registrant = $registrant->find('td');
        $registrant = $registrant[0]->nodes[0]->{'_'}[4];

        $this->_registrant = $registrant;

        return $this->_registrant;

    }

    public function getDomainType() {

        if (isset($this->_type)) {
            return $this->_type;
        }

        $parts = explode(".", $this->_name);

        if (count($parts) == 2) {
            $this->_type = ".". $parts[1];
        } else {
            $this->_type = ".". $parts[1] .".". $parts[2];
        }

        return $this->_type;

    }

    public function getNameservers() {

        if (isset($this->_nameservers)) {
            return $this->_nameservers;
        }

        $i = 0;
        $ns = [];

        $items = $this->_htmlDom->find('table table table tr[align=center] td');

        foreach ($items as $item) {

            if ($i % 2 == 0) {
                $ns["name"] = $item->nodes[0]->{'_'}[4];
            } else {
                $ns["ip"] = $item->nodes[0]->{'_'}[4];
            }
            $i++;

            if ($i == 2) {
                $i = 0;
                $this->_nameservers[] = $ns;
                $ns = [];
            }
        }

        return $this->_nameservers;

    }

    public function getStatusCode() {

        if (isset($this->_statusCode)) {
            return $this->_statusCode;
        }

        $headers = @get_headers((string) $this->getDomainAsUrl());

        if (!$headers) {
            //echo "\n ". $this->_name ."\n";
            return null;
        }

        $this->_statusCode = explode(" ", $headers[0])[1][0] ."xx";

        return $this->_statusCode;

    }

    public function getHost() {
        if (isset($this->_host)) {
            return $this->_host;
        }

        return $this->_host = gethostbyname($this->_name);
    }

    private function isHostIpV4() {
        $host = $this->getHost();
        $parts = explode(".", $host);

        return (count($parts) == 4);
    }

    public function getLocation() {

        if (isset($this->_location)) {
            return $this->_location;
        }

        $location = [
            "country" => "Unknown",
            "continent" => "Unknown"
        ];

        if ($this->isHostIpV4()) {
            $locator = HostGeoLocator::instance();

            try {
                $location = $locator->country($this->getHost());
                $this->_location["country"]   = $location->country->name;
                $this->_location["continent"] = $location->continent->name;

            } catch (GeoIp2Exception $e) {
                $this->_location = $location;
            }
        } else {
            $this->_location["country"]   = "Unknown";
            $this->_location["continent"] = "Unknown";
        }

        return $this->_location;

    }

    public function jsonSerialize() {
        $domain = [
            "name" => $this->_name,
            "url" => (string) $this->getDomainAsUrl(),
            "date_valid" => $this->getDateValid(),
            "date_registered" => $this->getDateRegistered(),
            "registrant" => $this->getRegistrant(),
            "nameservers" => $this->getNameservers(),
            "status_code" => $this->getStatusCode()
        ];

        return $domain;
    }

}