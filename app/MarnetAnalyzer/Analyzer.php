<?php

/**
 * MarnetAnalyzer - Analyze the domains available in the Macedonian Academic Research Network's registrar
 *
 * @package MarnetAnalyzer
 * @author Dejan Angelov <angelovdejan92@gmail.com>
 * @link https://github.com/angelov/MarnetAnalyzer
 */

namespace MarnetAnalyzer;

use MarnetAnalyzer\Exception\FilesystemErrorException;
use MarnetAnalyzer\Exception\MarnetException;
use MarnetAnalyzer\Exception\NoConnectionException;
use Purl\Url;
use Sunra\PhpSimple\HtmlDomParser as Parser;

class Analyzer {

    private $_letters = ["NUM", "A", "B", "C", "D", "E", "F", "G", "H", "I", "J",
                           "K", "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U",
                           "V", "X", "Y", "W", "Z"];
    private $_url;
    private $_domains = [];
    private $_count = 0;
    private $_pause = 5;
    private $_saveAsJson = true;
    private $_storage = "/results/";
    private $_analyzeId;


    private $_types = [         // map: "type" => "num domains", not sorted
                ".mk"      => 0,
                ".gov.mk"  => 0,
                ".edu.mk"  => 0,
                ".org.mk"  => 0,
                ".com.mk"  => 0,
                ".net.mk"  => 0,
                ".inf.mk"  => 0,
                ".name.mk" => 0,
                ".mil.mk"  => 0
            ];
    private $_registrants         =    [];   // map: "registrant" => "num of registered domains", not sorted
    private $_registeredPerMonths =   [  ];  // map: "year" => ["month" => "day"]
    private $_registeredPerYears  =  [    ];
    private $_nameservers         = [      ];
    private $_statusCodes         =    [];    // merry christmas!

    private $_countries = [];
    private $_continents = [];
    private $_details = [];
    private $_notAvailable = 0;

    public function __construct($base_url = "http://reg.marnet.net.mk/registar.php") {
        $this->_url = new Url($base_url);
        $this->_analyzeId = date("d.m-H:i:s");
    }

    /**
     * Shows the welcome message
     */
    public function welcome() {
        print "\n\033[01;33m==========================";
        print "\n= MARnet Domain Analyzer =";
        print "\n==========================\n\n\033[0m";
    }

    /**
     * Parses the registrar and loads the domains list
     */
    public function fetchDomains() {

        foreach ($this->_letters as $letter) {

            $this->_url->query->set("bukva", $letter);

            print "Fetching domains for letter: ". $letter ."\n";

            $fetched = false;

            $dom = null;
            while (!$fetched) {
                try {
                    $dom = ParserLoader::fetch($this->_url);
                    $fetched = true;
                } catch (NoConnectionException $e) {
                    // to do
                }
            }

            $num_pages = count($dom->find('td[align=center] a[href*=bukva]'));
            $linksCount = 0;

            for ($i = 0; $i < $num_pages; $i++) {

                $this->_url->query->set("del", $i);
                $dom = Parser::file_get_html($this->_url);

                $links = $dom->find('td[align=center] a[href*=dom]');

                foreach ($links as $link) {
                    $domain = new Url($link->href);
                    $this->_domains[] = $domain->getQuery()->get("dom");
                    $linksCount++;
                }

                sleep($this->_pause);

            }

            print "Fetched ". $linksCount ." domains from ". $num_pages ." pages.\n\n";
            $this->_count += $linksCount;

        }

        print "--------\n";
        print "Fetched ". $this->_count ." domains in 00h 00m 00s.";
        print "\n\n";

        if ($this->_saveAsJson) {
            $this->saveDomainsAsJson();
        }

    }

    /**
     * Load the domains list from an existing file
     *
     * @param string $file Full path to the file
     * @throws Exception\FilesystemErrorException
     */
    public function loadDomainsFromFile($file) {
        if (!file_exists($file)) {
            throw new FilesystemErrorException("The file \"". $file ."\" doesn't exist.");
        }

        $this->_domains = json_decode(file_get_contents($file), true);
    }

    public function countDomains() {
        return count($this->_domains);
    }

    /**
     * Save the domains list to a file
     *
     * @param string|null $dest Path to the destination file
     */
    public function saveDomainsAsJson($dest = null) {

        if (!isset($dest)) {
            $dest = $this->_storage ."domains.json";
        }

        file_put_contents($dest, json_encode($this->_domains));

    }

    /**
     * Load a single domain
     *
     * @param string $name The domain name
     * @return Domain
     */
    public static function getDomain($name) {
        return new Domain($name);
    }

    /**
     * Fully analyze of the domains in the list
     */
    public function analyze() {

        $i=0;
        $count = $this->countDomains();

        $this->_details["total"] = $count;

        $start = new \DateTime("now");
        $this->_details["start"] = $start->format("d.m.Y H:i:s");

        foreach ($this->_domains as $d) {

            $fetched = false;
            $continue = false;

            $i++;
            $domain = null;

            while (!$fetched) {

                print "\033[0;37m[". date("d.m H:i:s") ."][".$i."/".$count."]\033[0m analyzing ". $d .": ";

                try {
                    $domain = new Domain($d);
                    $fetched = true;
                } catch (MarnetException $e) {

                    $name = get_class($e);

                    if ($name == "MarnetAnalyzer\Exception\DomainNotFoundException") {
                        print "\033[1;31mNo info. (". ++$this->_notAvailable .") \033[0m\n";
                        $fetched = true;
                        $continue = true;
                    }

                    if ($name == "MarnetAnalyzer\Exception\NoConnectionException") {
                        print "\033[1;31mNo connection. Will try again.\033[0m\n";
                        sleep(2);
                    }

                }

            }

            if ($continue) {
                continue;
            }

            $reg = $domain->getRegistrant();
            if (isset($this->_registrants[$reg])) {
                $this->_registrants[$reg]++;
            } else {
                $this->_registrants[$reg] = 1;
            }

            $type = $domain->getDomainType();
            $this->_types[$type]++;

            $reg = $domain->getDateRegistered();

            if (strlen($reg) > 10) { // Пред xx-xx-xxxx
                if (isset($this->_registeredPerMonths["< 2003"])) {
                    $this->_registeredPerMonths["< 2003"]++;
                } else {
                    $this->_registeredPerMonths["< 2003"] = 1;
                }
            } else {
                $reg = new \DateTime($reg);

                if (isset($this->_registeredPerMonths[$reg->format("Y")][$reg->format('m')])) {
                    $this->_registeredPerMonths[$reg->format("Y")][$reg->format('m')]++;
                } else {
                    $this->_registeredPerMonths[$reg->format("Y")][$reg->format('m')] = 1;
                }
            }

            $nservers = $domain->getNameservers();
            $added = [];

            foreach ($nservers as $ns) {

                $parts = explode(".", $ns["name"]);
                $top = implode(".", array_slice($parts, 1));

                if (!in_array($top, $added)) {
                    if (!isset($this->_nameservers[$top])) {
                        $this->_nameservers[$top] = 1;
                    } else {
                        $this->_nameservers[$top]++;
                    }
                }

                $added[] = $top;
            }

            $statusCode = $domain->getStatusCode();

            if (isset($statusCode)) {
                if (!isset($this->_statusCodes[$statusCode])) {
                    $this->_statusCodes[$statusCode] = 1;
                } else {
                    $this->_statusCodes[$statusCode]++;
                }
            }

            $location = $domain->getLocation();
            $country = $location["country"];
            $continent = $location["continent"];

            if (!isset($this->_countries[$country])) {
                $this->_countries[$country] = 1;
            } else {
                $this->_countries[$country]++;
            }

            if (!isset($this->_continents[$continent])) {
                $this->_continents[$continent] = 1;
            } else {
                $this->_continents[$continent]++;
            }

            print "\033[1;32mDone.\033[0m\n";
            sleep($this->_pause);

            //if ($i == 5000) break;
        }


        $this->_details["analyzed"] = $count - $this->_notAvailable;

        $end = new \DateTime("now");
        $this->_details["end"] = $end->format("d.m.Y H:i:s");

        if ($this->_saveAsJson) {
            $this->saveResults("main", $this->_details);
            $this->saveResults("types", $this->_types);
            $this->saveResults("registrants", $this->_registrants);
            $this->saveResults("registeredPerMonths", $this->_registeredPerMonths);
            $this->saveResults("nameservers", $this->_nameservers);
            $this->saveResults("statusCodes", $this->_statusCodes);
            $this->saveResults("countries", $this->_countries);
            $this->saveResults("continents", $this->_continents);
        }

    }

    /**
     * Save results to a file
     *
     * @param string $name Analyze's name
     * @param array $results Array with the results
     */
    private function saveResults($name, $results) {

        $target = $this->_storage . $this->_analyzeId . "/";

        if (!is_dir($target)) {
            mkdir($target);
        }

        file_put_contents($this->_storage . $this->_analyzeId ."/". $name . ".json", json_encode($results));
    }

    /**
     * Print the details after the analyze is finished
     */
    public function printDetails() {
        print "\033[0;37mStart:\033[0m\t\t". $this->_details["start"] ."\n";
        print "\033[0;37mEnd:\033[0m\t\t". $this->_details["end"] ."\n";
        print "\033[0;37mTotal domains:\033[0m\t". $this->_details["total"] ."\n";
        print "\033[0;37mAnalyzed:\033[0m\t". $this->_details["analyzed"] ."\n";
    }

    // getters and setters

    public function setLetters($letters) {
        $this->_letters = $letters;
        return $this;
    }

    public function setUrl($url) {
        $this->_url = new Url($url);
        return $this;
    }

    public function setPause($sec) {
        $this->_pause = $sec;
        return $this;
    }

    public function setSaveAsJson($save = true) {
        $this->_saveAsJson = $save;
        return $this;
    }

    public function setStorage($dest) {
        $this->_storage = $dest;
        return $this;
    }

    public function getDomains() {
        return $this->_domains;
    }

    public function getNotAvailable() {
        return $this->_notAvailable;
    }

    public function getDetails() {
        return $this->_details;
    }

    public function getRegistrants() {
        return $this->_registrants;
    }

    public function getRegisteredPerMonths() {
        ksort($this->_registeredPerMonths, SORT_NUMERIC);
        return $this->_registeredPerMonths;
    }

    public function getStatusCodes() {
        return $this->_statusCodes;
    }

    public function getTypes($sorted = false, $limit = null) {

        $count = count($this->_types);
        $limit = ($limit > $count) ? $count : $limit;

        if ($sorted) {
            arsort($this->_types, SORT_NUMERIC);
        }

        if (isset($limit)) {
            return array_slice($this->_types, 0, $limit);
        }

        return $this->_types;

    }

    public function getNameservers() {
        return $this->_nameservers;
    }

    public function getCountries() {
        return $this->_countries;
    }

    public function getContinents() {
        return $this->_continents;
    }

}