<?php

require 'vendor/autoload.php';

use App\Services\RonSequenceDecoder;
use GuzzleHttp\Psr7\StreamWrapper;

// Capture the actual stream from the API using background curl
$outputFile = '/tmp/ron_test_stream_' . time() . '.bin';
$cmd = "curl -N -H 'Accept: application/json-seq' -X POST 'http://localhost:1980/queries/133/run' 2>/dev/null > $outputFile &";
shell_exec($cmd);

// Give curl time to connect and start receiving
sleep(3);

// Kill the background curl process
shell_exec('pkill -f "curl.*queries/133/run" 2>/dev/null');

// Wait a bit for file to be written
sleep(1);

// Read the captured data
$data = file_get_contents($outputFile);
if ($data === false) {
    echo "ERROR: Could not read output file\n";
    exit(1);
}

// Clean up
@unlink($outputFile);

echo "=== Captured Stream Data ===\n";
echo "Size: " . strlen($data) . " bytes\n";
echo "Hex preview: " . bin2hex(substr($data, 0, 50)) . "...\n\n";

// Create a memory stream from the captured data
$stream = \GuzzleHttp\Psr7\Utils::streamFor($data);

// Test the decoder
echo "=== Testing RonSequenceDecoder ===\n";
$decoder = new RonSequenceDecoder($stream);

$recordCount = 0;
try {
    while ($decoder->decode($value)) {
        $recordCount++;
        echo "Record $recordCount: " . json_encode($value) . "\n";
        if ($recordCount >= 5) {
            echo "... (stopped after 5 records)\n";
            break;
        }
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\nTotal records decoded: $recordCount\n";


