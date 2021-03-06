<?php
/*
 * To save time most of the application logic is just inside here.
 * However, my original intention was to just use this class to access
 * the wmata API
 *
 * All the line and station info is static and unchanging, so I thought
 * it doesn't really make sense to fetch it from the api everytime.
 * However, I also didn't have time to add a database, so this is
 * saved to, and read from a file as json. 
 */
class MetroData
{
    const METHOD_GET = "GET";
    const METHOD_PUT = "PUT";
    const METHOD_POST = "POST";

    const BASE_URL = "https://api.wmata.com";
    const LINES_FILE = __DIR__ . "/../../data/lines.json";

    const API_LINES_FILE = __DIR__ . "/../../data/api-lines.json";
    const API_STATION_FILE = __DIR__ . "/../../data/api-station%s.json";
    const API_TRAINS_FILE = __DIR__ . "/../../data/api-trains.json";

    private $lines = [];
    private $useWmata = true;

    private function readJsonFile($filePath)
    {
       $file = fopen($filePath, "r");
       if (!$file) return false;
       $fileText = fread($file, filesize($filePath));
       $data = json_decode($fileText, true);
       fclose($file);
       return $data;
    }

    public function getLines()
    {
        if (!$this->lines)
        {
            //this should really use a db instead, but this is quicker
            if ($this->loadLinesFromFile()) {
                return $this->lines;
            }

            if ($this->loadLinesFromApi()) {
                $this->saveLinesToFile();
            }
        }
        return $this->lines;
    }


    public function getStations()
    {
        $allStations = [];
        foreach ($this->getLines() as $l) {
            foreach($l['stations'] as $code => $s) {
                $allStations[$code] = $s;
            }
        }

        return $allStations;
    }

    public function getStation($code)
    {
        if ($this->useWmata) {
           $apiTrains = $this->callApi(self::METHOD_GET, "/StationPrediction.svc/json/GetPrediction/" . $code);
           if (!$apiTrains || !array_key_exists("Trains", $apiTrains)) {
               return [];
           }
        } else {
            $apiTrains = $this->readJsonFile(self::API_TRAINS_FILE);
        }

        return $apiTrains["Trains"];
    }

    private function loadLinesFromApi()
    {

        $apiLines = null;

        if ($this->useWmata) {
            $apiLines = $this->callApi(self::METHOD_GET, "/Rail.svc/json/jLines");
        } else {
            $apiLines = $this->readJsonFile(self::API_LINES_FILE);

            if (!$apiLines || !array_key_exists("Lines", $apiLines)) {
                $this->logger->error(sprintf("Unable to get lines: \n%s", json_encode($apiLines)));
                return false;
            }
        }


        foreach ($apiLines["Lines"] as $line)
        {
            if ($this->useWmata) {
                $apiStations = $this->callApi(
                    self::METHOD_GET,
                    "/Rail.svc/json/jStations",
                    ["LineCode" => $line['LineCode']]
                );
            } else {
                $path = sprintf(self::API_STATION_FILE, $line['LineCode']);
                $apiStations = $this->readJsonFile($path);
            }


            $stations = [];
            if ($apiStations && array_key_exists("Stations", $apiStations)) {
                foreach ($apiStations["Stations"] as $station)
                {
                    $stationLines = [
                        $station['LineCode1'],
                        $station['LineCode2'],
                        $station['LineCode3'],
                        $station['LineCode4'],
                    ];

                    $stationLines = array_filter($stationLines, function($s) {
                        return true == $s;
                    });

                    $stations[$station["Code"]] = [
                       'name' => $station["Name"],
                       'code' => $station["Code"],
                       'lines' => $stationLines,
                    ];
                }
            }

            $this->lines[$line['LineCode']] = [
                'name' => $line['DisplayName'],
                'code' => $line['LineCode'],
                'start_code' => $line['StartStationCode'],
                'end_code' => $line['EndStationCode'],
                'stations' => $stations,
            ];
        }

        return $this->lines;
    }
    
    private function saveLinesToFile()
    {
        $lineFile = fopen(self::LINES_FILE, "w");
        $fileText = json_encode($this->lines);
        fwrite($lineFile, $fileText);
        fclose($lineFile);
    }

    /*
     * This should be replaced by something like "loadLinesFromDb()"
     */
    private function loadLinesFromFile()
    {
        $lineFile = fopen(self::LINES_FILE, "r");
        if (!$lineFile) return false;
        $fileText = fread($lineFile, filesize(self::LINES_FILE));
        $this->lines = json_decode($fileText, true);
        fclose($lineFile);

        return $this->lines;
    }

    function __construct($apiKey, $logger, $useWmata)
    {
        $this->apiKey = $apiKey;
        $this->logger = $logger;
        $this->useWmata = $useWmata;
    }

    //some copy pasta from: https://www.weichieprojects.com/blog/curl-api-calls-with-php/
    private function callApi($method, $url, $data = null)
    {
        $url = self::BASE_URL . $url;
        $curl = curl_init();

        switch ($method){
            case self::METHOD_POST:
              curl_setopt($curl, CURLOPT_POST, 1);
              if ($data)
                 curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
              break;
           case self::METHOD_PUT:
              curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
              if ($data)
                 curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
              break;
           case self::METHOD_GET:
              if ($data)
                 $url = sprintf("%s?%s", $url, http_build_query($data));
              break;
           default:
              throw new Exception(sprintf("%s is not a valid method", $method));
        }

        // OPTIONS:
        $this->logger->info("ApiKey: " . $this->apiKey);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
           sprintf('api_key: %s', $this->apiKey),
           'Content-Type: application/json',
        ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

        // EXECUTE:
        $this->logger->info(sprintf("Calling: %s", $url));
        $result = curl_exec($curl);
        if(!$result) {
            $this->logger->error(sprintf("Unable to connect to %s", $url));
            return false;
        }
        curl_close($curl);

        return json_decode($result, true);
    }

}
