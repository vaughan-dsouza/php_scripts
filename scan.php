<?php

require 'vendor/autoload.php';

use League\Csv\Reader;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// CLI usage: php scan.php links.csv
if (!isset($argv[1])) {
    die("Usage: php scan.php <csv-file>\n");
}

$csvFile = $argv[1];

// Read CSV
$csv = Reader::createFromPath($csvFile, 'r');

// Create HTTP client
$client = new Client([
    'timeout' => 5,
    'allow_redirects' => true,
    'verify' => false, // <--- add this
    'headers' => [
        'User-Agent' => 'LinkChecker/1.0 (+https://example.com)'
    ],
]);


$startTime = microtime(true);
$resultFile = __DIR__ . "/broken_links.txt";
file_put_contents($resultFile, ""); // clear previous results

foreach ($csv as $row) {

    $url = trim($row[0] ?? '');

    if ($url === '') {
        continue;
    }

    // Validate
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        echo "INVALID: $url\n";
        file_put_contents($resultFile, "INVALID: $url\n", FILE_APPEND);
        continue;
    }

    try {
        // Try HEAD (fast)
        $response = $client->head($url);
        $status = $response->getStatusCode();
    } catch (RequestException $e) {
        // fallback GET
        try {
            $response = $client->get($url);
            $status = $response->getStatusCode();
        } catch (RequestException $e) {
            echo "FAILED: $url\n";
            file_put_contents($resultFile, "FAILED: $url\n", FILE_APPEND);
            continue;
        }
    }

    if ($status >= 400) {
        echo "BAD ($status): $url\n";
        file_put_contents($resultFile, "BAD ($status): $url\n", FILE_APPEND);
    } else {
        echo "OK ($status): $url\n";
    }
}

$time = round(microtime(true) - $startTime, 2);
echo "\n\n Completed in {$time}s\n";
echo " Broken links saved to: $resultFile\n";
