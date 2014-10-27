<?php
date_default_timezone_set("Asia/Irkutsk");
error_reporting(E_ALL);
ini_set("display_errors", 1);

header("Content-type: text/html; charset=utf-8");

define("ROOT_DIR", realpath("../"));
$loader = require '../vendor/autoload.php';

function get_last_retrieve_url_contents_content_type()
{
    return "Content-type: text/html; charset=windows-1251";
}

$type = isset($_GET["t"]) ? (integer)$_GET["t"] : 1;
$page = isset($_GET["p"]) ? (integer)$_GET["p"] : 1;

\DeltaDb\DbaStorage::setDefault(function() {
    $dbAdapter = new \DeltaDb\Adapter\MysqlPdoAdapter();
    $dbAdapter->connect('mysql:host=localhost;dbname=38studio', ["password" => "123"]);
    return $dbAdapter;
});

try {
    $client = new \Processor\Client();
    $raw = $client->getSolutionsList($type, $page);
    $parser = new \Processor\Parser();
    $solutionsLinks = $parser->parseSolutionsLinks($raw);
    $maxPages = $parser->parseSolutionsListPagination($parser->prepareHtml($raw));
    $nextPage = ($page + 1) <= $maxPages ? ($page + 1) : null;

    $storage = new \Processor\StorageMysql();

    foreach ($solutionsLinks as $linkId) {
        $solution = $storage->findOne(["linkid" => (integer) $linkId]);
        if ($solution) {
            continue;
        }
        $solutionRaw = $client->getSolution($linkId);
        if (!$solutionRaw) {
            throw new Exception("Error in get solution #{$linkId}");
        }
        $solution = $parser->parseSolution($solutionRaw);
        $solution->setLinkid($linkId);
        $solution->setGroup($type);
        $solution->setRaw($solutionRaw);
        $storage->save($solution);
    }
} catch (\Exception $e) {
    header( "Location: /parser.php?t={$type}&p={$page}");
    http_response_code(500);
    echo "<h1>Error</h1> \n";
    var_dump($e);
}

$view = new \Processor\View();
$view->setTemplateExtension("phtml");
$view->assign("currentPage", $page);
$view->assign("maxPages", $maxPages);
$view->assign("currentType", $type);
$view->assign("nextPage", $nextPage);
$solutionsCount = $storage->count(["group" => (integer) $type]);
$view->assign("solutionsCount", $solutionsCount);

if ($nextPage) {
    $html = $view->render("parser-work");
} else {
    $html = $view->render("parser-report");
}
echo $html;

