<?php

echo "=== Analyzing L5-Swagger Configuration Issue ===\n\n";

// Read the current config
$configFile = __DIR__ . '/config/l5-swagger.php';
$config = require $configFile;

echo "Current configuration structure:\n";
echo "- Default: " . $config['default'] . "\n";
echo "- Documentations:\n";
foreach ($config['documentations'] as $name => $docConfig) {
    echo "  - $name:\n";
    echo "    - Has 'routes': " . (isset($docConfig['routes']) ? 'YES' : 'NO') . "\n";
    if (isset($docConfig['routes'])) {
        echo "    - Routes: " . json_encode($docConfig['routes']) . "\n";
    }
}

echo "\n- Defaults:\n";
echo "  - Has 'routes': " . (isset($config['defaults']['routes']) ? 'YES' : 'NO') . "\n";
if (isset($config['defaults']['routes'])) {
    echo "  - Routes keys: " . json_encode(array_keys($config['defaults']['routes'])) . "\n";
}

echo "\n=== Solution ===\n";
echo "The issue is that the 'documentations.default.routes' section only contains 'api',\n";
echo "but the 'docs' route is defined in 'defaults.routes'.\n\n";

echo "The ConfigFactory should merge these, but the route registration in routes.php\n";
echo "appears to be using the raw config instead of the merged config.\n\n";

echo "To fix this, we need to add the 'docs' route to the documentation config.\n";