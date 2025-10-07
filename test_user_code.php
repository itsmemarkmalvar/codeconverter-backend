<?php

require 'vendor/autoload.php';

use App\Services\Conversion\CodeConversionService;

$service = new CodeConversionService();

echo "Testing JavaScript to C# conversion with user's code:\n";
echo "====================================================\n\n";

// User's JavaScript code
$javascriptCode = '// Simple JavaScript Program

// Ask the user for their name
let name = prompt("What is your name?");

// Display a greeting
alert("Hello, " + name + "! Welcome to JavaScript!");';

echo "Input JavaScript:\n";
echo $javascriptCode . "\n\n";

$result = $service->convertJavaScriptToCSharp($javascriptCode);

echo "Conversion Result:\n";
echo "Success: " . ($result['success'] ? 'true' : 'false') . "\n";
echo "Converted C# Code:\n";
echo $result['converted_code'] . "\n\n";

echo "RDP Metrics:\n";
echo "Parsing Time: " . ($result['rdp_parsing_time_ms'] ?? 0) . "ms\n";
echo "Conversion Time: " . ($result['conversion_time_ms'] ?? 0) . "ms\n";
echo "AST Nodes: " . ($result['ast_nodes'] ?? 0) . "\n";
echo "Tokens Processed: " . ($result['tokens_processed'] ?? 0) . "\n";
echo "Memory Usage: " . round(($result['memory_usage_kb'] ?? 0), 2) . "KB\n";
echo "Syntax Accuracy: " . ($result['syntax_accuracy'] ?? 0) . "%\n";
echo "Semantic Preservation: " . ($result['semantic_preservation'] ?? 0) . "%\n\n";

if (!empty($result['errors'])) {
    echo "Errors:\n";
    foreach ($result['errors'] as $error) {
        echo "- " . (is_array($error) ? json_encode($error) : $error) . "\n";
    }
}

if (!empty($result['warnings'])) {
    echo "Warnings:\n";
    foreach ($result['warnings'] as $warning) {
        echo "- " . (is_array($warning) ? json_encode($warning) : $warning) . "\n";
    }
}
