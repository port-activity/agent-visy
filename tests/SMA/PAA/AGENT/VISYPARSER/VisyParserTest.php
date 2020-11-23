<?php

namespace SMA\PAA\AGENT\VISYPARSER;

use PHPUnit\Framework\TestCase;

use SMA\PAA\FAKERESULTPOSTER\FakeResultPoster;
use SMA\PAA\AGENT\ApiConfig;

final class VisyParserTest extends TestCase
{
    public function testConstructorAllGood(): void
    {
        require_once(__DIR__ . "/../../FAKERESULTPOSTER/FakeResultPoster.php");
        $visyParser = new VisyParser(new FakeResultPoster(), "foo.xml");
        $this->assertEquals(isset($visyParser), true);
    }

    public function testExecuteAllGood(): void
    {
        $resultPoster = new FakeResultPoster();
        $visyParser = new VisyParser($resultPoster, __DIR__ . "/ValidXMLIn.xml");

        $visyParser->execute(new ApiConfig("apikey", "http://url", []));
        $this->assertEquals(
            json_decode(file_get_contents(__DIR__ . "/ValidPosterData.json"), true),
            $resultPoster->results
        );
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage No input XML defined
     */
    public function testExecuteNoInputXML(): void
    {
        $visyParser = new VisyParser(new FakeResultPoster(), "");
        $visyParser->execute(new ApiConfig("apikey", "http://url", []));
    }

    public function testFetchResultsAllGood(): void
    {
        $visyParser = new VisyParser(new FakeResultPoster(), __DIR__ . "/ValidXMLIn.xml");

        $xml = simplexml_load_file(__DIR__ . "/ValidXMLIn.xml");
        $this->assertEquals(
            json_encode($xml, JSON_PRETTY_PRINT),
            json_encode($visyParser->fetchResults(), JSON_PRETTY_PRINT)
        );
    }


    public function testFetchResultsAllGoodWithOtherValidXml(): void
    {
        $visyParser = new VisyParser(new FakeResultPoster(), __DIR__ . "/visy-2019-11-26T01:22:16Z-5ddc7e4823bae.xml");

        $json = json_encode(json_decode(
            file_get_contents(__DIR__ . "/visy-2019-11-26T01:22:16Z-5ddc7e4823bae-result.json")
        ), JSON_PRETTY_PRINT);

        $this->assertEquals($json, json_encode($visyParser->fetchResults(), JSON_PRETTY_PRINT));
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Cannot load input xml
     */
    public function testFetchResultsXMLFileDoesNotExist(): void
    {
        $visyParser = new VisyParser(new FakeResultPoster(), __DIR__ . "/DoesNotExist.xml");
        $visyParser->fetchResults();
    }

    public function testParseResultsValidData(): void
    {
        $visyParser = new VisyParser(new FakeResultPoster(), "");
        $fetchedData = simplexml_load_file(__DIR__ . "/ValidXMLIn.xml");
        $parsedData = $visyParser->parseResults($fetchedData);
        $this->assertEquals($parsedData, json_decode(file_get_contents(__DIR__."/ValidParsedData.json"), true));
    }

    public function testParseResultsValidDataSummerTime(): void
    {
        $visyParser = new VisyParser(new FakeResultPoster(), "");
        $fetchedData = simplexml_load_file(__DIR__ . "/ValidXMLInSummerTime.xml");
        $parsedData = $visyParser->parseResults($fetchedData);
        $this->assertEquals(
            $parsedData,
            json_decode(file_get_contents(__DIR__."/ValidParsedDataSummerTime.json"), true)
        );
    }

    public function testPostResults(): void
    {
        $resultPoster = new FakeResultPoster();
        $visyParser = new VisyParser($resultPoster, "");
        $parsedData = json_decode(file_get_contents(__DIR__ . "/ValidParsedData.json"), true);
        $visyParser->postResults(new ApiConfig("apikey", "http://url", []), $parsedData);
        $this->assertEquals(
            $resultPoster->results,
            json_decode(file_get_contents(__DIR__ . "/ValidPosterData.json"), true)
        );
    }
    public function testPostResultsAnotherXml(): void
    {
        $resultPoster = new FakeResultPoster();
        $visyParser = new VisyParser($resultPoster, "");
        $parsedData = json_decode(
            file_get_contents(__DIR__ . "/visy-2019-11-26T01:22:16Z-5ddc7e4823bae-result.json"),
            true
        );
        $visyParser->postResults(new ApiConfig("apikey", "http://url", []), $parsedData);
        $this->assertEquals(
            $resultPoster->results,
            json_decode(file_get_contents(__DIR__ . "/visy-2019-11-26T01:22:16Z-5ddc7e4823bae-post-result.json"), true)
        );
    }
}
