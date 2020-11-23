<?php
namespace SMA\PAA\AGENT;

require_once __DIR__ . "/../../lib/init.php";

use SMA\PAA\CURL\CurlRequest;
use SMA\PAA\RESULTPOSTER\ResultPoster;
use SMA\PAA\AGENT\VISYPARSER\VisyParser;
use SMA\PAA\AGENT\ApiConfig;
use SMA\PAA\AINO\AinoClient;
use Exception;

$dir = "/var/www/src/public/uploads/";
$targetFile = $dir . "visy-" . gmdate("Y-m-d\TH:i:s\Z") . "-" . uniqid() . ".xml";
$lastProcessed = $dir . "last.xml";
$lastFailed = $dir . "last-failed.xml";

$xml = file_get_contents('php://input');

$ainoKey = getenv("AINO_API_KEY");
$ainoTimestamp = gmdate("Y-m-d\TH:i:s\Z");
$aino = null;
if ($ainoKey !== false) {
    $aino = new AinoClient($ainoKey, "Visy service", "Visy");
}

if ($xml) {
    file_put_contents($targetFile, $xml);
    echo "File was successfully uploaded...\n";
    echo "Next parsing result...\n";

    $apiKey = getenv("API_KEY");
    $apiUrl = getenv("API_URL");

    if (isset($aino)) {
        $aino->succeeded(
            $ainoTimestamp,
            "Visy agent succeeded",
            "Fetch",
            "logistics-timestamp",
            [],
            ["file" => basename($targetFile)]
        );
    }

    $apiParameters = [
        "time",
        "external_id",
        "checkpoint",
        "direction",
        "front_license_plates",
        "rear_license_plates",
        "containers"
    ];

    $apiConfig = new ApiConfig($apiKey, $apiUrl, $apiParameters);

    $ainoForAgent = null;
    if ($ainoKey) {
        $toApplication = parse_url($apiUrl, PHP_URL_HOST);
        $ainoForAgent = new AinoClient($ainoKey, "Visy", $toApplication);
    }
    $agent = new VisyParser(
        new ResultPoster(new CurlRequest()),
        $targetFile,
        $ainoForAgent
    );

    try {
        $agent->execute($apiConfig);
        file_put_contents($lastProcessed, $xml);
        file_put_contents($lastProcessed . "-time.txt", date("c"));
        unlink($targetFile);
        if (isset($aino)) {
            $aino->succeeded(
                $ainoTimestamp,
                "Visy agent succeeded",
                "Parse",
                "logistics-timestamp",
                [],
                ["file" => basename($targetFile)]
            );
        }
        echo "All done.\n";
    } catch (Exception $e) {
        echo "There was error prosessing this data:\n " . $xml;
        file_put_contents($lastFailed, $xml);
        file_put_contents($lastFailed . "-time.txt", date("c"));
        error_log($e->getTraceAsString());
        error_log("FAILED XML: " . $xml);
        if (isset($aino)) {
            $aino->failure(
                $ainoTimestamp,
                "Visy agent failed",
                "Parse",
                "logistics-timestamp",
                [],
                ["file" => basename($targetFile)]
            );
        }
        echo "Failed.\n";
    }
} else {
    echo "No data posted!\n";

    if (isset($aino)) {
        $aino->failure(
            $ainoTimestamp,
            "Visy agent failed",
            "Fetch",
            "logistics-timestamp",
            [],
            []
        );
    }
}
