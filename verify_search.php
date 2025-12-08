<?php
// Mock DB Connection for testing (or use actual if possible, but let's try to query the modified search.php logic directly via CLI if adaptable, or just curl it)
// Since search.php relies on web server context (query string), we'll simulate a request.

$url = "http://localhost/crypto_intelligence/public/api/search.php?q=PEPE";

echo "Testing Search for 'PEPE'...\n";
$response = file_get_contents($url);
echo "Response: " . $response . "\n";

$data = json_decode($response, true);
if (!empty($data) && is_array($data) && $data[0]['symbol'] === 'SENTUSDT') {
    echo "SUCCESS: Found SENTUSDT!\n";
} else {
    echo "FAILED: Did not find SENTUSDT.\n";
}

echo "\nTesting Search for 'XYZ123' (Invalid)...\n";
$url2 = "http://localhost/crypto_intelligence/public/api/search.php?q=XYZ123";
$response2 = file_get_contents($url2);
echo "Response: " . $response2 . "\n";
?>
