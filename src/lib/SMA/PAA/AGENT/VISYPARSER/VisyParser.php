<?php
namespace SMA\PAA\AGENT\VISYPARSER;

use SMA\PAA\RESULTPOSTER\IResultPoster;
use SMA\PAA\AGENT\ApiConfig;
use SMA\PAA\AINO\AinoClient;

use Exception;
use SimpleXMLElement;
use DateTime;
use DateTimeZone;

class VisyParser
{
    private $inputXML;
    private $resultPoster;
    private $aino;

    public function __construct(IResultPoster $resultPoster, string $inputXML, $aino = null)
    {
        $this->resultPoster = $resultPoster;
        $this->inputXML = $inputXML;
        $this->aino = $aino;
    }

    public function execute(ApiConfig $apiConfig)
    {
        if ($this->inputXML === "") {
            throw new Exception("No input XML defined");
        }
        $rawResults = $this->fetchResults();
        $parsedResults = $this->parseResults($rawResults);
        $this->postResults($apiConfig, $parsedResults);
    }

    public function fetchResults(): SimpleXMLElement
    {
        libxml_use_internal_errors(true);

        $res = simplexml_load_file($this->inputXML);

        if (!$res) {
            libxml_clear_errors();
            libxml_use_internal_errors(false);
            throw new Exception("Cannot load input xml: ".$this->inputXML);
        } else {
            libxml_use_internal_errors(false);
            return $res;
        }
    }

    public function parseResults(SimpleXMLElement $rawResults): array
    {
        $parsedResults = [];

        if (isset($rawResults->date)) {
            if (isset($rawResults->date->year) &&
                isset($rawResults->date->month) &&
                isset($rawResults->date->day) &&
                isset($rawResults->date->hour) &&
                isset($rawResults->date->minute) &&
                isset($rawResults->date->second)) {
                    $datetime = new DateTime();
                    # Visy time is local time
                    $datetime->setTimezone(new DateTimeZone("Europe/Helsinki"));
                    $datetime->setDate(
                        (int)$rawResults->date->year,
                        (int)$rawResults->date->month,
                        (int)$rawResults->date->day
                    );
                    $datetime->setTime(
                        (int)$rawResults->date->hour,
                        (int)$rawResults->date->minute,
                        (int)$rawResults->date->second
                    );
                    $parsedResults["time"] = $datetime->format("Y-m-d\TH:i:sO");
            }
        }

        $parsedResults["external_id"] = 0;
        if (isset($rawResults->eventnumber)) {
            $parsedResults["external_id"] = (int)$rawResults->eventnumber;
        }

        if (isset($rawResults->checkpoint)) {
            if (isset($rawResults->checkpoint->tag)) {
                $parsedResults["checkpoint"] = (string)$rawResults->checkpoint->tag;
            }

            if (isset($rawResults->checkpoint->direction)) {
                if ((string)$rawResults->checkpoint->direction === "IN") {
                    $parsedResults["direction"] = "In";
                } elseif ((string)$rawResults->checkpoint->direction === "OUT") {
                    $parsedResults["direction"] = "Out";
                }
            }
        }

        $parsedResults["front_license_plates"] = [];
        if (isset($rawResults->frontlicenseplates)) {
            if (isset($rawResults->frontlicenseplates->licenseplate)) {
                foreach ($rawResults->frontlicenseplates->licenseplate as $licensePlate) {
                    $res = [];
                    if (isset($licensePlate->formatted)) {
                        $res["number"] = (string)$licensePlate->formatted;
                    }

                    if (isset($licensePlate->nationality)) {
                        $res["nationality"] = (string)$licensePlate->nationality;
                    }

                    $parsedResults["front_license_plates"][] = $res;
                }
            }
        }
        $parsedResults["rear_license_plates"] = [];
        if (isset($rawResults->rearlicenseplates)) {
            if (isset($rawResults->frontlicenseplates->licenseplate)) {
                foreach ($rawResults->rearlicenseplates->licenseplate as $licensePlate) {
                    $res = [];
                    if (isset($licensePlate->formatted)) {
                        $res["number"] = (string)$licensePlate->formatted;
                    }

                    if (isset($licensePlate->nationality)) {
                        $res["nationality"] = (string)$licensePlate->nationality;
                    }

                    $parsedResults["rear_license_plates"][] = $res;
                }
            }
        }
        $parsedResults["containers"] = [];
        if (isset($rawResults->containers)) {
            if (isset($rawResults->containers->container)) {
                foreach ($rawResults->containers->container as $container) {
                    $res = [];
                    if (isset($container->identification)) {
                        $res["identification"] = (string)$container->identification;
                    }

                    $parsedResults["containers"][] = $res;
                }
            }
        }

        return $parsedResults;
    }

    public function postResults(ApiConfig $apiConfig, array $result)
    {
        $ainoTimestamp = gmdate("Y-m-d\TH:i:s\Z");
        $ainoFlowId = $this->resultPoster->resultChecksum($apiConfig, $result);
        try {
            $this->resultPoster->postResult($apiConfig, $result);
            if (isset($this->aino)) {
                $this->aino->succeeded(
                    $ainoTimestamp,
                    "Visy agent succeeded",
                    "Post",
                    "logistics-timestamp",
                    ["external_id" => $result["external_id"]],
                    [],
                    $ainoFlowId
                );
            }
        } catch (\Exception $e) {
            error_log($e->getMessage());
            error_log($e->getTraceAsString());
            if (isset($this->aino)) {
                $this->aino->failure(
                    $ainoTimestamp,
                    "Visy agent failed",
                    "Post",
                    "logistics-timestamp",
                    ["external_id" => $result["external_id"]],
                    [],
                    $ainoFlowId
                );
            }
        }
    }
}
