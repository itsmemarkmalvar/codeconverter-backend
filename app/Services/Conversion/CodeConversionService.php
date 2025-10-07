<?php

namespace App\Services\Conversion;

use App\Services\RDP\JavaScriptRDPParser;
use App\Services\RDP\CSharpRDPParser;
use Exception;

/**
 * Code Conversion Service
 * 
 * This service handles the conversion between JavaScript and C# code
 * using the Recursive Descent Parsing (RDP) algorithm for thesis research.
 */
class CodeConversionService
{
    private JavaScriptRDPParser $jsParser;
    private CSharpRDPParser $csParser;
    private array $conversionMetrics = [];

    public function __construct()
    {
        $this->jsParser = new JavaScriptRDPParser();
        $this->csParser = new CSharpRDPParser();
    }

    /**
     * Convert JavaScript code to C#
     */
    public function convertJavaScriptToCSharp(string $javascriptCode): array
    {
        try {
            $startTime = microtime(true);
            $startMemory = memory_get_usage();

            // Step 1: Parse JavaScript code using RDP
            $jsParseResult = $this->jsParser->parse($javascriptCode);
            
            if (!$jsParseResult['success']) {
                return [
                    'success' => false,
                    'error' => 'JavaScript parsing failed',
                    'errors' => $jsParseResult['errors'],
                    'metrics' => $this->calculateConversionMetrics($startTime, $startMemory, $jsParseResult)
                ];
            }

            // Step 2: Convert AST from JavaScript to C#
            $conversionResult = $this->convertJavaScriptASTToCSharp($jsParseResult['ast']);
            
            if (!$conversionResult['success']) {
                return [
                    'success' => false,
                    'error' => 'AST conversion failed',
                    'errors' => $conversionResult['errors'],
                    'metrics' => $this->calculateConversionMetrics($startTime, $startMemory, $jsParseResult)
                ];
            }

            // Step 3: Generate C# code from converted AST
            $csharpCode = $this->generateCSharpCode($conversionResult['ast']);

            // Step 4: Validate generated C# code
            $csParseResult = $this->csParser->parse($csharpCode);
            
            $endTime = microtime(true);
            $endMemory = memory_get_usage();

            return [
                'success' => true,
                'sourceCode' => $javascriptCode,
                'targetCode' => $csharpCode,
                'sourceLanguage' => 'javascript',
                'targetLanguage' => 'csharp',
                'conversionDirection' => 'javascript-to-csharp',
                'sourceAST' => $jsParseResult['ast'],
                'targetAST' => $conversionResult['ast'],
                'sourceMetrics' => $jsParseResult['metrics'],
                'targetMetrics' => $csParseResult['metrics'],
                'conversionMetrics' => $this->calculateConversionMetrics($startTime, $startMemory, $jsParseResult),
                'warnings' => array_merge($jsParseResult['warnings'] ?? [], $conversionResult['warnings'] ?? []),
                'errors' => array_merge($jsParseResult['errors'] ?? [], $conversionResult['errors'] ?? [])
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Conversion failed: ' . $e->getMessage(),
                'errors' => [
                    [
                        'type' => 'conversion_error',
                        'message' => $e->getMessage(),
                        'line' => 1,
                        'column' => 1,
                        'severity' => 'error'
                    ]
                ],
                'metrics' => $this->calculateConversionMetrics($startTime ?? microtime(true), $startMemory ?? memory_get_usage(), [])
            ];
        }
    }

    /**
     * Convert C# code to JavaScript
     */
    public function convertCSharpToJavaScript(string $csharpCode): array
    {
        try {
            $startTime = microtime(true);
            $startMemory = memory_get_usage();

            // Step 1: Parse C# code using RDP
            $csParseResult = $this->csParser->parse($csharpCode);
            
            if (!$csParseResult['success']) {
                return [
                    'success' => false,
                    'error' => 'C# parsing failed',
                    'errors' => $csParseResult['errors'],
                    'metrics' => $this->calculateConversionMetrics($startTime, $startMemory, $csParseResult)
                ];
            }

            // Step 2: Convert AST from C# to JavaScript
            $conversionResult = $this->convertCSharpASTToJavaScript($csParseResult['ast']);
            
            if (!$conversionResult['success']) {
                return [
                    'success' => false,
                    'error' => 'AST conversion failed',
                    'errors' => $conversionResult['errors'],
                    'metrics' => $this->calculateConversionMetrics($startTime, $startMemory, $csParseResult)
                ];
            }

            // Step 3: Generate JavaScript code from converted AST
            $javascriptCode = $this->generateJavaScriptCode($conversionResult['ast']);

            // Step 4: Validate generated JavaScript code
            $jsParseResult = $this->jsParser->parse($javascriptCode);
            
            $endTime = microtime(true);
            $endMemory = memory_get_usage();

            return [
                'success' => true,
                'sourceCode' => $csharpCode,
                'targetCode' => $javascriptCode,
                'sourceLanguage' => 'csharp',
                'targetLanguage' => 'javascript',
                'conversionDirection' => 'csharp-to-javascript',
                'sourceAST' => $csParseResult['ast'],
                'targetAST' => $conversionResult['ast'],
                'sourceMetrics' => $csParseResult['metrics'],
                'targetMetrics' => $jsParseResult['metrics'],
                'conversionMetrics' => $this->calculateConversionMetrics($startTime, $startMemory, $csParseResult),
                'warnings' => array_merge($csParseResult['warnings'] ?? [], $conversionResult['warnings'] ?? []),
                'errors' => array_merge($csParseResult['errors'] ?? [], $conversionResult['errors'] ?? [])
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Conversion failed: ' . $e->getMessage(),
                'errors' => [
                    [
                        'type' => 'conversion_error',
                        'message' => $e->getMessage(),
                        'line' => 1,
                        'column' => 1,
                        'severity' => 'error'
                    ]
                ],
                'metrics' => $this->calculateConversionMetrics($startTime ?? microtime(true), $startMemory ?? memory_get_usage(), [])
            ];
        }
    }

    /**
     * Convert JavaScript AST to C# AST
     */
    private function convertJavaScriptASTToCSharp($jsAST): array
    {
        try {
            $convertedAST = $this->convertASTNode($jsAST, 'javascript', 'csharp');
            
            return [
                'success' => true,
                'ast' => $convertedAST,
                'warnings' => [],
                'errors' => []
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'ast' => null,
                'warnings' => [],
                'errors' => [
                    [
                        'type' => 'ast_conversion_error',
                        'message' => $e->getMessage(),
                        'line' => $jsAST->line ?? 1,
                        'column' => 1,
                        'severity' => 'error'
                    ]
                ]
            ];
        }
    }

    /**
     * Convert C# AST to JavaScript AST
     */
    private function convertCSharpASTToJavaScript($csAST): array
    {
        try {
            $convertedAST = $this->convertASTNode($csAST, 'csharp', 'javascript');
            
            return [
                'success' => true,
                'ast' => $convertedAST,
                'warnings' => [],
                'errors' => []
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'ast' => null,
                'warnings' => [],
                'errors' => [
                    [
                        'type' => 'ast_conversion_error',
                        'message' => $e->getMessage(),
                        'line' => $csAST->line ?? 1,
                        'column' => 1,
                        'severity' => 'error'
                    ]
                ]
            ];
        }
    }

    /**
     * Convert AST node from source language to target language
     */
    private function convertASTNode($node, string $sourceLang, string $targetLang)
    {
        if (!$node) {
            return null;
        }

        $nodeType = $node->type;
        $children = $node->children;
        $line = $node->line;

        // Handle different node types based on conversion direction
        if ($sourceLang === 'javascript' && $targetLang === 'csharp') {
            return $this->convertJSNodeToCSNode($nodeType, $children, $line);
        } elseif ($sourceLang === 'csharp' && $targetLang === 'javascript') {
            return $this->convertCSNodeToJSNode($nodeType, $children, $line);
        }

        throw new Exception("Unsupported conversion direction: {$sourceLang} to {$targetLang}");
    }

    /**
     * Convert JavaScript node to C# node
     */
    private function convertJSNodeToCSNode(string $nodeType, array $children, int $line)
    {
        switch ($nodeType) {
            case 'Program':
                return new \App\Services\RDP\ASTNode('CompilationUnit', [
                    'members' => array_map(function($child) {
                        return $this->convertASTNode($child, 'javascript', 'csharp');
                    }, $children)
                ], $line);

            case 'FunctionDeclaration':
                return new \App\Services\RDP\ASTNode('MethodDeclaration', [
                    'modifiers' => ['public', 'static'],
                    'returnType' => new \App\Services\RDP\ASTNode('NamedType', [
                        'name' => new \App\Services\RDP\ASTNode('QualifiedName', [
                            'names' => [new \App\Services\RDP\ASTNode('Identifier', ['name' => 'void'], $line)]
                        ], $line)
                    ], $line),
                    'identifier' => $children['id'],
                    'typeParameters' => [],
                    'parameters' => $children['params'] ?? [],
                    'constraints' => [],
                    'body' => $children['body']
                ], $line);

            case 'VariableDeclaration':
                $declarations = $children['declarations'] ?? [];
                if (empty($declarations)) {
                    return null;
                }
                
                $firstDecl = $declarations[0];
                return new \App\Services\RDP\ASTNode('VariableDeclaration', [
                    'identifier' => $firstDecl->children['id'],
                    'type' => null, // var keyword equivalent
                    'initializer' => $firstDecl->children['init']
                ], $line);

            case 'IfStatement':
                return new \App\Services\RDP\ASTNode('IfStatement', [
                    'condition' => $children['test'],
                    'consequent' => $children['consequent'],
                    'alternate' => $children['alternate']
                ], $line);

            case 'WhileStatement':
                return new \App\Services\RDP\ASTNode('WhileStatement', [
                    'condition' => $children['test'],
                    'body' => $children['body']
                ], $line);

            case 'ForStatement':
                return new \App\Services\RDP\ASTNode('ForStatement', [
                    'initializer' => $children['init'],
                    'condition' => $children['test'],
                    'increment' => $children['update'],
                    'body' => $children['body']
                ], $line);

            case 'ReturnStatement':
                return new \App\Services\RDP\ASTNode('ReturnStatement', [
                    'expression' => $children['argument']
                ], $line);

            case 'BlockStatement':
                return new \App\Services\RDP\ASTNode('BlockStatement', [
                    'statements' => array_map(function($child) {
                        return $this->convertASTNode($child, 'javascript', 'csharp');
                    }, $children['body'] ?? [])
                ], $line);

            case 'ExpressionStatement':
                return new \App\Services\RDP\ASTNode('ExpressionStatement', [
                    'expression' => $this->convertASTNode($children['expression'], 'javascript', 'csharp')
                ], $line);

            case 'BinaryExpression':
                return new \App\Services\RDP\ASTNode('BinaryExpression', [
                    'operator' => $this->convertJSOperatorToCSOperator($children['operator']),
                    'left' => $this->convertASTNode($children['left'], 'javascript', 'csharp'),
                    'right' => $this->convertASTNode($children['right'], 'javascript', 'csharp')
                ], $line);

            case 'UnaryExpression':
                return new \App\Services\RDP\ASTNode('UnaryExpression', [
                    'operator' => $this->convertJSOperatorToCSOperator($children['operator']),
                    'operand' => $this->convertASTNode($children['argument'], 'javascript', 'csharp')
                ], $line);

            case 'Identifier':
                return new \App\Services\RDP\ASTNode('Identifier', [
                    'name' => $children['name']
                ], $line);

            case 'Literal':
                return new \App\Services\RDP\ASTNode('Literal', [
                    'value' => $children['value']
                ], $line);

            case 'AssignmentExpression':
                return new \App\Services\RDP\ASTNode('AssignmentExpression', [
                    'operator' => $this->convertJSOperatorToCSOperator($children['operator']),
                    'left' => $this->convertASTNode($children['left'], 'javascript', 'csharp'),
                    'right' => $this->convertASTNode($children['right'], 'javascript', 'csharp')
                ], $line);

            default:
                // For unsupported node types, return a basic conversion
                return new \App\Services\RDP\ASTNode($nodeType, $children, $line);
        }
    }

    /**
     * Convert C# node to JavaScript node
     */
    private function convertCSNodeToJSNode(string $nodeType, array $children, int $line)
    {
        switch ($nodeType) {
            case 'CompilationUnit':
                return new \App\Services\RDP\ASTNode('Program', [
                    'body' => array_map(function($child) {
                        return $this->convertASTNode($child, 'csharp', 'javascript');
                    }, $children['members'] ?? [])
                ], $line);

            case 'MethodDeclaration':
                return new \App\Services\RDP\ASTNode('FunctionDeclaration', [
                    'id' => $children['identifier'],
                    'params' => $children['parameters'] ?? [],
                    'body' => $children['body']
                ], $line);

            case 'VariableDeclaration':
                return new \App\Services\RDP\ASTNode('VariableDeclaration', [
                    'kind' => 'var',
                    'declarations' => [
                        new \App\Services\RDP\ASTNode('VariableDeclarator', [
                            'id' => $children['identifier'],
                            'init' => $children['initializer']
                        ], $line)
                    ]
                ], $line);

            case 'IfStatement':
                return new \App\Services\RDP\ASTNode('IfStatement', [
                    'test' => $children['condition'],
                    'consequent' => $children['consequent'],
                    'alternate' => $children['alternate']
                ], $line);

            case 'WhileStatement':
                return new \App\Services\RDP\ASTNode('WhileStatement', [
                    'test' => $children['condition'],
                    'body' => $children['body']
                ], $line);

            case 'ForStatement':
                return new \App\Services\RDP\ASTNode('ForStatement', [
                    'init' => $children['initializer'],
                    'test' => $children['condition'],
                    'update' => $children['increment'],
                    'body' => $children['body']
                ], $line);

            case 'ReturnStatement':
                return new \App\Services\RDP\ASTNode('ReturnStatement', [
                    'argument' => $children['expression']
                ], $line);

            case 'BlockStatement':
                return new \App\Services\RDP\ASTNode('BlockStatement', [
                    'body' => array_map(function($child) {
                        return $this->convertASTNode($child, 'csharp', 'javascript');
                    }, $children['statements'] ?? [])
                ], $line);

            case 'ExpressionStatement':
                return new \App\Services\RDP\ASTNode('ExpressionStatement', [
                    'expression' => $this->convertASTNode($children['expression'], 'csharp', 'javascript')
                ], $line);

            case 'BinaryExpression':
                return new \App\Services\RDP\ASTNode('BinaryExpression', [
                    'operator' => $this->convertCSOperatorToJSOperator($children['operator']),
                    'left' => $this->convertASTNode($children['left'], 'csharp', 'javascript'),
                    'right' => $this->convertASTNode($children['right'], 'csharp', 'javascript')
                ], $line);

            case 'UnaryExpression':
                return new \App\Services\RDP\ASTNode('UnaryExpression', [
                    'operator' => $this->convertCSOperatorToJSOperator($children['operator']),
                    'argument' => $this->convertASTNode($children['operand'], 'csharp', 'javascript')
                ], $line);

            case 'Identifier':
                return new \App\Services\RDP\ASTNode('Identifier', [
                    'name' => $children['name']
                ], $line);

            case 'Literal':
                return new \App\Services\RDP\ASTNode('Literal', [
                    'value' => $children['value']
                ], $line);

            case 'AssignmentExpression':
                return new \App\Services\RDP\ASTNode('AssignmentExpression', [
                    'operator' => $this->convertCSOperatorToJSOperator($children['operator']),
                    'left' => $this->convertASTNode($children['left'], 'csharp', 'javascript'),
                    'right' => $this->convertASTNode($children['right'], 'csharp', 'javascript')
                ], $line);

            default:
                // For unsupported node types, return a basic conversion
                return new \App\Services\RDP\ASTNode($nodeType, $children, $line);
        }
    }

    /**
     * Convert JavaScript operator to C# operator
     */
    private function convertJSOperatorToCSOperator(string $jsOperator): string
    {
        $operatorMap = [
            '===' => '==',
            '!==' => '!=',
            '==' => '==',
            '!=' => '!=',
            '&&' => '&&',
            '||' => '||',
            '++' => '++',
            '--' => '--',
            '+=' => '+=',
            '-=' => '-=',
            '*=' => '*=',
            '/=' => '/=',
            '=' => '=',
            '+' => '+',
            '-' => '-',
            '*' => '*',
            '/' => '/',
            '%' => '%',
            '<' => '<',
            '>' => '>',
            '<=' => '<=',
            '>=' => '>=',
            '!' => '!'
        ];

        return $operatorMap[$jsOperator] ?? $jsOperator;
    }

    /**
     * Convert C# operator to JavaScript operator
     */
    private function convertCSOperatorToJSOperator(string $csOperator): string
    {
        $operatorMap = [
            '==' => '===',
            '!=' => '!==',
            '&&' => '&&',
            '||' => '||',
            '++' => '++',
            '--' => '--',
            '+=' => '+=',
            '-=' => '-=',
            '*=' => '*=',
            '/=' => '/=',
            '=' => '=',
            '+' => '+',
            '-' => '-',
            '*' => '*',
            '/' => '/',
            '%' => '%',
            '<' => '<',
            '>' => '>',
            '<=' => '<=',
            '>=' => '>=',
            '!' => '!'
        ];

        return $operatorMap[$csOperator] ?? $csOperator;
    }

    /**
     * Generate C# code from AST
     */
    private function generateCSharpCode($ast): string
    {
        if (!$ast) {
            return '';
        }

        return $this->generateCodeFromNode($ast, 'csharp');
    }

    /**
     * Generate JavaScript code from AST
     */
    private function generateJavaScriptCode($ast): string
    {
        if (!$ast) {
            return '';
        }

        return $this->generateCodeFromNode($ast, 'javascript');
    }

    /**
     * Generate code from AST node
     */
    private function generateCodeFromNode($node, string $language): string
    {
        if (!$node) {
            return '';
        }

        $nodeType = $node->type;
        $children = $node->children;
        $indent = '    '; // 4 spaces for indentation

        switch ($nodeType) {
            case 'CompilationUnit':
                $members = $children['members'] ?? [];
                $code = '';
                foreach ($members as $member) {
                    $code .= $this->generateCodeFromNode($member, $language) . "\n";
                }
                return trim($code);

            case 'Program':
                $body = $children['body'] ?? [];
                $code = '';
                foreach ($body as $statement) {
                    $code .= $this->generateCodeFromNode($statement, $language) . "\n";
                }
                return trim($code);

            case 'MethodDeclaration':
                $modifiers = implode(' ', $children['modifiers'] ?? []);
                $returnType = $this->generateTypeCode($children['returnType'], $language);
                $identifier = $this->generateCodeFromNode($children['identifier'], $language);
                $parameters = $this->generateParameterList($children['parameters'] ?? [], $language);
                $body = $this->generateCodeFromNode($children['body'], $language);
                
                return "{$modifiers} {$returnType} {$identifier}({$parameters})\n{$body}";

            case 'FunctionDeclaration':
                $identifier = $this->generateCodeFromNode($children['id'], $language);
                $parameters = $this->generateParameterList($children['params'] ?? [], $language);
                $body = $this->generateCodeFromNode($children['body'], $language);
                
                return "function {$identifier}({$parameters}) {$body}";

            case 'BlockStatement':
                $statements = $children['statements'] ?? $children['body'] ?? [];
                $code = "{\n";
                foreach ($statements as $statement) {
                    $statementCode = $this->generateCodeFromNode($statement, $language);
                    if ($statementCode) {
                        $code .= $indent . $statementCode . "\n";
                    }
                }
                $code .= "}";
                return $code;

            case 'VariableDeclaration':
                if ($language === 'csharp') {
                    $identifier = $this->generateCodeFromNode($children['identifier'], $language);
                    $initializer = $children['initializer'] ? ' = ' . $this->generateCodeFromNode($children['initializer'], $language) : '';
                    return "var {$identifier}{$initializer};";
                } else {
                    $kind = $children['kind'] ?? 'var';
                    $declarations = $children['declarations'] ?? [];
                    $declCode = '';
                    foreach ($declarations as $decl) {
                        $id = $this->generateCodeFromNode($decl->children['id'], $language);
                        $init = $decl->children['init'] ? ' = ' . $this->generateCodeFromNode($decl->children['init'], $language) : '';
                        $declCode .= "{$kind} {$id}{$init}";
                    }
                    return $declCode . ';';
                }

            case 'IfStatement':
                $condition = $this->generateCodeFromNode($children['condition'] ?? $children['test'], $language);
                $consequent = $this->generateCodeFromNode($children['consequent'], $language);
                $alternate = $children['alternate'] ? ' else ' . $this->generateCodeFromNode($children['alternate'], $language) : '';
                
                return "if ({$condition}) {$consequent}{$alternate}";

            case 'WhileStatement':
                $condition = $this->generateCodeFromNode($children['condition'] ?? $children['test'], $language);
                $body = $this->generateCodeFromNode($children['body'], $language);
                
                return "while ({$condition}) {$body}";

            case 'ForStatement':
                $init = $children['initializer'] ?? $children['init'];
                $condition = $children['condition'] ?? $children['test'];
                $increment = $children['increment'] ?? $children['update'];
                $body = $children['body'];
                
                $initCode = $init ? $this->generateCodeFromNode($init, $language) : '';
                $conditionCode = $condition ? $this->generateCodeFromNode($condition, $language) : '';
                $incrementCode = $increment ? $this->generateCodeFromNode($increment, $language) : '';
                $bodyCode = $this->generateCodeFromNode($body, $language);
                
                return "for ({$initCode}; {$conditionCode}; {$incrementCode}) {$bodyCode}";

            case 'ReturnStatement':
                $expression = $children['expression'] ?? $children['argument'];
                $exprCode = $expression ? ' ' . $this->generateCodeFromNode($expression, $language) : '';
                return "return{$exprCode};";

            case 'ExpressionStatement':
                $expression = $children['expression'];
                $exprCode = $this->generateCodeFromNode($expression, $language);
                return $exprCode . ';';

            case 'BinaryExpression':
                $left = $this->generateCodeFromNode($children['left'], $language);
                $operator = $children['operator'];
                $right = $this->generateCodeFromNode($children['right'], $language);
                
                return "({$left} {$operator} {$right})";

            case 'UnaryExpression':
                $operator = $children['operator'];
                $operand = $this->generateCodeFromNode($children['operand'] ?? $children['argument'], $language);
                
                return "{$operator}{$operand}";

            case 'AssignmentExpression':
                $left = $this->generateCodeFromNode($children['left'], $language);
                $operator = $children['operator'];
                $right = $this->generateCodeFromNode($children['right'], $language);
                
                return "{$left} {$operator} {$right}";

            case 'Identifier':
                return $children['name'];

            case 'Literal':
                $value = $children['value'];
                if (is_string($value)) {
                    return '"' . addslashes($value) . '"';
                } elseif (is_bool($value)) {
                    return $value ? 'true' : 'false';
                } elseif (is_null($value)) {
                    return 'null';
                } else {
                    return (string) $value;
                }

            default:
                return '';
        }
    }

    /**
     * Generate type code
     */
    private function generateTypeCode($typeNode, string $language): string
    {
        if (!$typeNode) {
            return $language === 'csharp' ? 'void' : '';
        }

        if ($typeNode->type === 'NamedType') {
            $name = $typeNode->children['name'];
            if ($name->type === 'QualifiedName') {
                $names = $name->children['names'];
                return implode('.', array_map(function($n) { return $n->children['name']; }, $names));
            }
        }

        return 'void';
    }

    /**
     * Generate parameter list
     */
    private function generateParameterList(array $parameters, string $language): string
    {
        $paramCodes = [];
        foreach ($parameters as $param) {
            if ($language === 'csharp') {
                $type = $this->generateTypeCode($param->children['type'] ?? null, $language);
                $identifier = $this->generateCodeFromNode($param->children['identifier'], $language);
                $paramCodes[] = "{$type} {$identifier}";
            } else {
                $paramCodes[] = $this->generateCodeFromNode($param, $language);
            }
        }
        return implode(', ', $paramCodes);
    }

    /**
     * Calculate conversion metrics
     */
    private function calculateConversionMetrics(float $startTime, int $startMemory, array $parseResult): array
    {
        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        return [
            'conversion_time' => ($endTime - $startTime) * 1000, // Convert to milliseconds
            'memory_usage' => $endMemory - $startMemory,
            'source_ast_nodes' => $parseResult['metrics']['ast_nodes'] ?? 0,
            'source_tokens_processed' => $parseResult['metrics']['tokens_processed'] ?? 0,
            'source_syntax_accuracy' => $parseResult['metrics']['syntax_accuracy'] ?? 0,
            'source_semantic_preservation' => $parseResult['metrics']['semantic_preservation'] ?? 0,
            'conversion_success_rate' => $parseResult['success'] ? 100 : 0
        ];
    }
}
