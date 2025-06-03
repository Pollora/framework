<?php
// Script de test pour déboguer les routes WordPress

function testRoute($url, $expectedText = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $content = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $result = [
        'url' => $url,
        'status' => $httpCode,
        'error' => $error,
        'content_length' => strlen($content),
        'contains_expected' => $expectedText ? strpos($content, $expectedText) !== false : null,
        'title' => null,
        'is_404' => false,
        'route_matched' => null,
        'is_wordpress_route' => false
    ];
    
    // Extract title
    if (preg_match('/<title>(.*?)<\/title>/i', $content, $matches)) {
        $result['title'] = html_entity_decode($matches[1]);
    }
    
    // Check if it's our custom route response
    if (preg_match('/<h1>(ROUTE .* MATCHED)<\/h1>/i', $content, $matches)) {
        $result['route_matched'] = $matches[1];
        $result['is_wordpress_route'] = true;
    }
    
    // Check if it's a 404
    $result['is_404'] = $httpCode === 404 || strpos($content, 'Page non trouvée') !== false;
    
    return $result;
}

// Test routes with corrected URLs
$routes = [
    'https://pollen.ddev.site/' => 'front page',
    'https://pollen.ddev.site/realisations/campus-vert' => 'realisations',
    'https://pollen.ddev.site/blog' => 'blog archive',
    'https://pollen.ddev.site/contact' => 'contact',
    'https://pollen.ddev.site/agence' => 'page',
    'https://pollen.ddev.site/blog/author/amphibee' => 'author',
    'https://pollen.ddev.site/blog/category/actus' => 'category (real)',
    'https://pollen.ddev.site/blog/gutenberg-vs-elementor' => 'single post (real)',
    'https://pollen.ddev.site/category/seo' => 'category (fake)',
    'https://pollen.ddev.site/nonexistent-page-test' => '404'
];

echo "=== ROUTE TESTING ===\n\n";

foreach ($routes as $url => $description) {
    echo "Testing: $description\n";
    echo "URL: $url\n";
    
    $result = testRoute($url);
    
    echo "Status: {$result['status']}\n";
    
    if ($result['is_wordpress_route']) {
        echo "✅ WordPress Route: {$result['route_matched']}\n";
    } else {
        echo "❌ Standard/Fallback Route\n";
        echo "Title: {$result['title']}\n";
    }
    
    echo "Content Length: {$result['content_length']} bytes\n";
    echo "Is 404: " . ($result['is_404'] ? 'YES' : 'NO') . "\n";
    
    if ($result['error']) {
        echo "Error: {$result['error']}\n";
    }
    
    echo "\n" . str_repeat('-', 50) . "\n\n";
}