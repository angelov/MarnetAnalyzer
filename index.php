<?php

include 'vendor/autoload.php';

use MarnetAnalyzer\Analyzer;
use MarnetAnalyzer\Exception\MarnetException;

try {

    $m = new Analyzer();

    $m->setSaveAsJson(true)->setStorage(__DIR__."/results/");
    $m->setPause(0);

    //$m->setLetters(["X"]);

    $m->welcome();

    $m->fetchDomains();

    //$m->saveDomainsAsJson(__DIR__."/results/domains.json"); // optional

    $m->analyze();

    print "\n";

    $m->printDetails();

    print "\n";

} catch (MarnetException $e) {
    print "\033[0;31mError:\033[0m ";
    die($e->getMessage() ."\n");
}
