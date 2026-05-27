<?php

declare(strict_types=1);

$sku = trim((string)($_GET['sku'] ?? 'OBT-0001'));

header('Content-Type: image/svg+xml');

echo '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="120">';
echo '<rect width="100%" height="100%" fill="white" />';

$x = 20;

for ($i = 0; $i < strlen($sku); $i++) {
    $height = 40 + (($i % 3) * 15);
    echo '<rect x="' . $x . '" y="20" width="4" height="' . $height . '" fill="black" />';
    $x += 8;
}

echo '<text x="20" y="100" font-size="18" font-family="monospace">' . htmlspecialchars($sku) . '</text>';
echo '</svg>';
