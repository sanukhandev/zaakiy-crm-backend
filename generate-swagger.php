<?php

require 'vendor/autoload.php';

use OpenAPI\Annotations as OA;

$paths = [
    __DIR__ . '/app',
];

$openapi = \OpenAPI\Generator::scan($paths);

$docsPath = __DIR__ . '/storage/api-docs';
if (!is_dir($docsPath)) {
    mkdir($docsPath, 0755, true);
}

file_put_contents(
    $docsPath . '/api-docs.json',
    json_encode(json_decode($openapi->toJson()), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo "✓ Swagger documentation generated at: storage/api-docs/api-docs.json\n";
