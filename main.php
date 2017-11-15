<?php

use Keboola\Csv\CsvFile;
use What3words\Geocoder\Geocoder;

require "vendor/autoload.php";

try {
    $dataDir = getenv('KBC_DATADIR') === false ? '/data' : getenv('KBC_DATADIR');
    $configFile = $dataDir . DIRECTORY_SEPARATOR . 'config.json';
    $config = json_decode(file_get_contents($configFile), true);
    if (json_last_error() != JSON_ERROR_NONE) {
        throw new Exception("Config file error or not found");
    }
    if (empty($config['storage']['input']['tables'])
        || (count($config['storage']['input']['tables']) > 1)
    ) {
        throw new InvalidArgumentException("Exactly one table must be on input");
    }
    $sourceFileName = $dataDir . DIRECTORY_SEPARATOR . 'in' . DIRECTORY_SEPARATOR .
        'tables' . DIRECTORY_SEPARATOR . $config['storage']['input']['tables'][0]['destination'];
    if (empty($config['parameters']['#apiKey'])) {
        if (getenv('W3WTOKEN') !== false) {
            $config['parameters']['#apiKey'] = getenv('W3WTOKEN');
        } else {
            throw new InvalidArgumentException("api key is missing");
        }
    }
    $apiKey = $config['parameters']['#apiKey'];
    $destinationFileName = $dataDir . DIRECTORY_SEPARATOR . 'out' . DIRECTORY_SEPARATOR .
        'tables' . DIRECTORY_SEPARATOR . 'result.csv';
    if (empty($config['parameters']['direction'])
        || !in_array($config['parameters']['direction'], ['forward', 'reverse'])
    ) {
        throw new InvalidArgumentException('direction must be one of "forward", "reverse"');
    }
    $direction = $config['parameters']['direction'];
    if (empty($config['parameters']['lang'])) {
        $lang = 'en';
    } else {
        $lang = $config['parameters']['lang'];
    }

    $params = [
        'lang' => $lang,
        'format' => 'json',
        'display' => 'minimal'
    ];

    $geoCoder = new Geocoder(['key' => $apiKey]);
    $csvIn = new CsvFile($sourceFileName);
    $csvOut = new CsvFile($destinationFileName);
    if ($direction == 'forward') {
        if (count($csvIn->getHeader()) <> 1) {
            throw new InvalidArgumentException('Input table must contain one column with words');
        }
        $header[] = 'words';
        $header[] = 'lat';
        $header[] = 'lng';
        $header[] = 'message';
        $csvOut->writeRow($header);
        foreach ($csvIn as $index => $row) {
            if ($index === 0) {
                continue;
            }
            $payLoad = json_decode($geoCoder->forwardGeocode($row[0], $params), true);
            if (json_last_error() != JSON_ERROR_NONE) {
                throw new Exception("Failed to decode response: " . var_export($payLoad, true));
            }
            if (!is_array($payLoad['status'])) {
                throw new Exception($payLoad['message'] . ' s: ' . $payLoad['status'] . ' c:' . $payLoad['code']);
            }
            if (!empty($payLoad['status']['code'])) {
                $row[] = '';
                $row[] = '';
                $row[] = $payLoad['status']['message'];
            } else {
                $row[] = $payLoad['geometry']['lat'];
                $row[] = $payLoad['geometry']['lng'];
                $row[] = '';
            }
            $csvOut->writeRow($row);
        }
    } else {
        if (count($csvIn->getHeader()) <> 2) {
            throw new InvalidArgumentException(
                'Input table must contain two columns, first with latitude and second with longitude.'
            );
        }
        $header[] = 'lat';
        $header[] = 'lng';
        $header[] = 'words';
        $header[] = 'message';
        $csvOut->writeRow($header);
        foreach ($csvIn as $index => $row) {
            if ($index === 0) {
                continue;
            }
            $payLoad = json_decode($geoCoder->reverseGeocode(['lat' => $row[0], 'lng' => $row[1]], $params), true);
            if (json_last_error() != JSON_ERROR_NONE) {
                throw new Exception("Failed to decode response: " . var_export($payLoad, true));
            }
            if (!is_array($payLoad['status'])) {
                throw new Exception($payLoad['message'] . ' s: ' . $payLoad['status'] . ' c:' . $payLoad['code']);
            }
            if (!empty($payLoad['status']['code'])) {
                $row[] = '';
                $row[] = $payLoad['status']['message'];
            } else {
                $row[] = $payLoad['words'];
                $row[] = '';
            }
            $csvOut->writeRow($row);
        }
    }
} catch (InvalidArgumentException $e) {
    echo $e->getMessage();
    exit(1);
} catch (Exception $e) {
    echo $e->getMessage();
    exit(2);
}
