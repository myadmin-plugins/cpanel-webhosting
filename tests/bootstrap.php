<?php

// Autoload via Composer if available, otherwise manually load classes
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../../../autoload.php')) {
    require_once __DIR__ . '/../../../autoload.php';
}

// Ensure xmlapi class is loaded (it is in the global namespace)
if (!class_exists('xmlapi')) {
    require_once __DIR__ . '/../src/xmlapi.php';
}
