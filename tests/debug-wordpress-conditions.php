<?php
// Script pour tester les conditions WordPress

function testWordPressConditions($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $content = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Look for WordPress condition debug info
    $debugInfo = [];
    
    // Check if content contains specific patterns
    $patterns = [
        'is_singular' => '/is_singular\s*\(\s*[\'"]?realisations[\'"]?\s*\)/',
        'is_single' => '/is_single\s*\(\s*\)/',
        'is_page' => '/is_page\s*\(\s*\)/',
        'is_category' => '/is_category\s*\(\s*\)/',
        'is_author' => '/is_author\s*\(\s*\)/',
        'is_home' => '/is_home\s*\(\s*\)/',
        'is_front_page' => '/is_front_page\s*\(\s*\)/',
        'is_404' => '/is_404\s*\(\s*\)/',
    ];
    
    foreach ($patterns as $condition => $pattern) {
        $debugInfo[$condition] = preg_match($pattern, $content) ? 'found' : 'not found';
    }
    
    return [
        'url' => $url,
        'status' => $httpCode,
        'debug_info' => $debugInfo,
        'is_404' => strpos($content, 'Page non trouvée') !== false
    ];
}

// Test specific problematic URLs
$problematicUrls = [
    'https://pollen.ddev.site/realisation/campus-vert',
    'https://pollen.ddev.site/un-articles-avec-la-meme-categorie', 
    'https://pollen.ddev.site/category/seo'
];

echo "=== WORDPRESS CONDITIONS DEBUG ===\n\n";

foreach ($problematicUrls as $url) {
    echo "Testing: $url\n";
    $result = testWordPressConditions($url);
    echo "Status: {$result['status']}\n";
    echo "Is 404: " . ($result['is_404'] ? 'YES' : 'NO') . "\n";
    echo "Debug info: " . json_encode($result['debug_info'], JSON_PRETTY_PRINT) . "\n";
    echo "\n" . str_repeat('-', 50) . "\n\n";
}