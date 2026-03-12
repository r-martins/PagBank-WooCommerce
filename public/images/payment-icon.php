<?php
@ob_start(); // Start output buffering

require __DIR__ . '/../../../../../wp-load.php';

use RM_PagBank\Helpers\Params;

header('Content-Type: image/svg+xml; charset=utf-8');

$allowedMethods = ['cc', 'pix', 'boleto'];
$iconColor = Params::getConfig('icons_color', 'gray');
$method = sanitize_text_field($_GET['method'] ?? '');
$method = in_array($method, $allowedMethods) ? $method : 'cc';
$svgPath = __DIR__ . '/' . $method . '.svg';

if (!file_exists($svgPath)) {
    exit;
}

if (extension_loaded('DOM') === false) {
    echo file_get_contents(__DIR__ . '/' . $method . '.svg');
    $output = @ob_get_clean(); // Get the buffer content and clean it
    echo $output !== false ? trim($output): ''; // Output the cleaned buffer content
    exit;
}

/*
 * Carrega SVG no DOM
 */
$doc = new DOMDocument();
$doc->preserveWhiteSpace = false;
$doc->formatOutput = false;
$doc->load($svgPath);

$paths = $doc->getElementsByTagName('path');

foreach ($paths as $path) {
    // Get the current style attribute
    $style = $path->getAttribute('style');

    // Check if the style attribute contains a fill rule
    if (strpos($style, 'fill:') !== false) {
        // Replace the fill color in the style attribute
        $newStyle = preg_replace('/fill:\s*#[0-9a-fA-F]+/', 'fill: ' . $iconColor, $style);
    } else {
        // Add the fill rule to the style attribute
        $newStyle = $style . '; fill: ' . $iconColor;
    }

    $path->setAttribute('style', $newStyle);
}

echo $doc->saveXML($doc->documentElement);

$output = @ob_get_clean(); // Get the buffer content and clean it

echo $output !== false ? trim($output): ''; // Output the cleaned buffer content
