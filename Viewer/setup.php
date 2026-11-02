<?php
// setup.php - Automatically downloads the tracking script
$url = "https://cdn.jsdelivr.net/npm/rrweb@1.1.3/dist/rrweb.min.js";
$file = __DIR__ . '/app-core.js';

$ch = curl_init($url);
$fp = fopen($file, 'w+');
curl_setopt($ch, CURLOPT_FILE, $fp);
curl_setopt($ch, CURLOPT_TIMEOUT, 50);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
fclose($fp);

if($statusCode == 200 && filesize($file) > 0) {
    echo "<h2 style='color: green; font-family: sans-serif;'>✅ Success!</h2>";
    echo "<p style='font-family: sans-serif;'>The file <b>app-core.js</b> has been downloaded and saved to your server.</p>";
    echo "<p style='font-family: sans-serif;'>You can now safely delete this setup.php file and refresh your PDF link.</p>";
} else {
    echo "<h2 style='color: red; font-family: sans-serif;'>❌ Error</h2>";
    echo "<p style='font-family: sans-serif;'>Could not download the file. Please ensure your folder has write permissions.</p>";
}
?>