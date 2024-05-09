<?php
@ob_start(); // Start output buffering

require __DIR__ . '/../../../../../wp-load.php';

use RM_PagBank\Helpers\Params;

header('Content-Type: image/svg+xml');
$allowedMethods = ['cc', 'pix', 'boleto'];
$iconColor = Params::getConfig('icons_color', 'gray');
$method = sanitize_text_field($_GET['method'] ?? '');
$method = in_array($_GET['method'], $allowedMethods) ? $_GET['method'] : 'cc';

if (extension_loaded('DOM') === false) {
    echo file_get_contents(__DIR__ . '/' . $method . '.svg');
    $output = @ob_get_clean(); // Get the buffer content and clean it
    echo $output !== false ? trim($output): ''; // Output the cleaned buffer content
    exit;
}

$doc = new DOMDocument();
$doc->load(__DIR__ . '/' . $method . '.svg');

$paths = $doc->getElementsByTagName('path');

foreach ($paths as $path) {
    // Get the current style attribute
    $style = $path->getAttribute('style');

    // Check if the style attribute contains a fill rule
    if (strpos($style, 'fill:') !== false) {
        // Replace the fill color in the style attribute
        $newStyle = preg_replace('/fill: #[0-9a-fA-F]+/', 'fill: ' . $iconColor, $style);
    } else {
        // Add the fill rule to the style attribute
        $newStyle = $style . '; fill: ' . $iconColor;
    }

    $path->setAttribute('style', $newStyle);
}

echo $doc->saveXML();

$output = @ob_get_clean(); // Get the buffer content and clean it

echo $output !== false ? trim($output): ''; // Output the cleaned buffer content
