<?php
// Test de résolution des routes

// Ajouter le script de test pour vérifier les conditions WordPress
$testConditionsUrl = 'https://pollen.ddev.site/pollora-test-conditions';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $testConditionsUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$content = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "=== ROUTE RESOLUTION TEST ===\n";
echo "URL: $testConditionsUrl\n";
echo "Status: $httpCode\n";
echo "Content: " . substr($content, 0, 500) . "\n";

if ($httpCode === 404) {
    echo "\nThe test route is not yet created. Let's analyze existing routes.\n";
    
    // Test the debug route
    $debugUrl = 'https://pollen.ddev.site/pollora-debug';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $debugUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $debugContent = curl_exec($ch);
    curl_close($ch);
    
    $debugData = json_decode($debugContent, true);
    echo "\nDEBUG INFO:\n";
    echo "Router class: {$debugData['router_class']}\n";
    echo "Total routes: {$debugData['total_routes']}\n";
    echo "WordPress routes: {$debugData['wp_routes']}\n";
    echo "Standard routes: {$debugData['standard_routes']}\n";
}