<?php

require 'vendor/autoload.php';

use App\Services\Conversion\CodeConversionService;

$service = new CodeConversionService();

echo "=== CONVERSION ACCURACY ANALYSIS ===\n\n";

// Test cases for accuracy validation
$testCases = [
    [
        'name' => 'Simple Variable Declaration',
        'javascript' => 'let name = "John";',
        'expected_csharp' => 'string name = "John";'
    ],
    [
        'name' => 'Function Declaration',
        'javascript' => 'function greet(name) { return "Hello " + name; }',
        'expected_csharp' => 'public static string greet(string name) { return "Hello " + name; }'
    ],
    [
        'name' => 'Console Output',
        'javascript' => 'console.log("Hello World");',
        'expected_csharp' => 'Console.WriteLine("Hello World");'
    ],
    [
        'name' => 'Conditional Statement',
        'javascript' => 'if (age >= 18) { console.log("Adult"); }',
        'expected_csharp' => 'if (age >= 18) { Console.WriteLine("Adult"); }'
    ],
    [
        'name' => 'Loop Structure',
        'javascript' => 'for (let i = 0; i < 10; i++) { console.log(i); }',
        'expected_csharp' => 'for (int i = 0; i < 10; i++) { Console.WriteLine(i); }'
    ]
];

$totalTests = count($testCases);
$passedTests = 0;
$accuracyIssues = [];

foreach ($testCases as $index => $testCase) {
    echo "Test " . ($index + 1) . ": " . $testCase['name'] . "\n";
    echo "----------------------------------------\n";
    
    echo "Input JavaScript:\n";
    echo $testCase['javascript'] . "\n\n";
    
    $result = $service->convertJavaScriptToCSharp($testCase['javascript']);
    
    echo "Generated C#:\n";
    echo $result['converted_code'] . "\n\n";
    
    echo "Expected C#:\n";
    echo $testCase['expected_csharp'] . "\n\n";
    
    // Analyze accuracy
    $generatedCode = $result['converted_code'];
    $expectedCode = $testCase['expected_csharp'];
    
    $accuracyScore = calculateAccuracy($generatedCode, $expectedCode);
    
    echo "Accuracy Analysis:\n";
    echo "- Syntax Accuracy: " . ($result['syntax_accuracy'] ?? 0) . "%\n";
    echo "- Semantic Preservation: " . ($result['semantic_preservation'] ?? 0) . "%\n";
    echo "- Code Structure Match: " . $accuracyScore . "%\n";
    
    if ($accuracyScore >= 80) {
        echo "✅ PASSED\n";
        $passedTests++;
    } else {
        echo "❌ FAILED - Accuracy Issues Detected\n";
        $accuracyIssues[] = [
            'test' => $testCase['name'],
            'score' => $accuracyScore,
            'issues' => identifyIssues($generatedCode, $expectedCode)
        ];
    }
    
    echo "\n" . str_repeat("=", 50) . "\n\n";
}

// Overall accuracy report
$overallAccuracy = ($passedTests / $totalTests) * 100;

echo "=== OVERALL ACCURACY REPORT ===\n";
echo "Total Tests: $totalTests\n";
echo "Passed Tests: $passedTests\n";
echo "Failed Tests: " . ($totalTests - $passedTests) . "\n";
echo "Overall Accuracy: " . round($overallAccuracy, 2) . "%\n\n";

if (!empty($accuracyIssues)) {
    echo "=== ACCURACY ISSUES IDENTIFIED ===\n";
    foreach ($accuracyIssues as $issue) {
        echo "Test: " . $issue['test'] . "\n";
        echo "Accuracy Score: " . $issue['score'] . "%\n";
        echo "Issues: " . implode(', ', $issue['issues']) . "\n\n";
    }
}

// RDP Effectiveness Analysis
echo "=== RDP EFFECTIVENESS ANALYSIS ===\n";
$rdpMetrics = [
    'Average Parsing Time' => 0,
    'Average AST Nodes' => 0,
    'Average Tokens Processed' => 0,
    'Average Memory Usage' => 0,
    'Error Recovery Rate' => 0
];

$totalParsingTime = 0;
$totalASTNodes = 0;
$totalTokens = 0;
$totalMemory = 0;
$errorRecoveryCount = 0;

foreach ($testCases as $testCase) {
    $result = $service->convertJavaScriptToCSharp($testCase['javascript']);
    $totalParsingTime += $result['rdp_parsing_time_ms'] ?? 0;
    $totalASTNodes += $result['ast_nodes'] ?? 0;
    $totalTokens += $result['tokens_processed'] ?? 0;
    $totalMemory += $result['memory_usage_kb'] ?? 0;
    if (!empty($result['errors'])) {
        $errorRecoveryCount++;
    }
}

$rdpMetrics['Average Parsing Time'] = round($totalParsingTime / $totalTests, 2) . 'ms';
$rdpMetrics['Average AST Nodes'] = round($totalASTNodes / $totalTests, 2);
$rdpMetrics['Average Tokens Processed'] = round($totalTokens / $totalTests, 2);
$rdpMetrics['Average Memory Usage'] = round($totalMemory / $totalTests, 2) . 'KB';
$rdpMetrics['Error Recovery Rate'] = round(($errorRecoveryCount / $totalTests) * 100, 2) . '%';

foreach ($rdpMetrics as $metric => $value) {
    echo "$metric: $value\n";
}

function calculateAccuracy($generated, $expected) {
    // Simple accuracy calculation based on key elements
    $generated = strtolower(trim($generated));
    $expected = strtolower(trim($expected));
    
    $score = 0;
    $totalChecks = 0;
    
    // Check for key C# elements
    $keyElements = [
        'using system' => 20,
        'public class' => 20,
        'public static' => 15,
        'console.writeline' => 15,
        'string' => 10,
        'int' => 10,
        'if' => 5,
        'for' => 5
    ];
    
    foreach ($keyElements as $element => $points) {
        $totalChecks += $points;
        if (strpos($generated, $element) !== false) {
            $score += $points;
        }
    }
    
    return $totalChecks > 0 ? round(($score / $totalChecks) * 100, 2) : 0;
}

function identifyIssues($generated, $expected) {
    $issues = [];
    
    if (strpos($generated, 'using System') === false) {
        $issues[] = 'Missing using System directive';
    }
    
    if (strpos($generated, 'public class Program') === false) {
        $issues[] = 'Missing proper class structure';
    }
    
    if (strpos($generated, 'public static void Main') === false) {
        $issues[] = 'Missing Main method';
    }
    
    if (strpos($generated, 'Console.WriteLine') === false && strpos($expected, 'Console.WriteLine') !== false) {
        $issues[] = 'Incorrect console output method';
    }
    
    if (strpos($generated, 'string') === false && strpos($expected, 'string') !== false) {
        $issues[] = 'Missing proper type declarations';
    }
    
    return $issues;
}
