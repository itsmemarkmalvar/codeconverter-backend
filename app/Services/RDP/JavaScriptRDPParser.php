<?php

namespace App\Services\RDP;

use Exception;

/**
 * JavaScript Recursive Descent Parser (RDP)
 * 
 * This class implements a pure Recursive Descent Parsing algorithm
 * for JavaScript syntax analysis, focusing on demonstrating RDP effectiveness
 * for thesis research purposes.
 */
class JavaScriptRDPParser
{
    private array $tokens = [];
    private int $position = 0;
    private ?Token $currentToken = null;
    private array $errors = [];
    private array $warnings = [];
    private array $symbolTable = [];
    private array $scopes = [];
    private int $astNodes = 0;
    private int $tokensProcessed = 0;
    private int $errorRecoveryCount = 0;
    private float $startTime;
    private int $startMemory;

    public function __construct()
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage();
    }

    /**
     * Parse JavaScript code using RDP algorithm
     */
    public function parse(string $code): array
    {
        try {
            // Reset parser state
            $this->reset();
            
            // Tokenize the input code
            $this->tokens = $this->tokenize($code);
            $this->tokensProcessed = count($this->tokens);
            
            if (empty($this->tokens)) {
                throw new Exception('No tokens found in input code');
            }
            
            $this->position = 0;
            $this->currentToken = $this->tokens[0];
            
            // Parse the program using RDP
            $ast = $this->parseProgram();
            
            // Calculate metrics
            $metrics = $this->calculateMetrics();
            
            return [
                'success' => empty($this->errors),
                'ast' => $ast,
                'errors' => $this->errors,
                'warnings' => $this->warnings,
                'metrics' => $metrics,
                'symbolTable' => $this->symbolTable
            ];
            
        } catch (Exception $e) {
            $this->errors[] = [
                'type' => 'rdp_parsing',
                'message' => $e->getMessage(),
                'line' => $this->currentToken?->line ?? 1,
                'column' => $this->currentToken?->column ?? 1,
                'severity' => 'error'
            ];
            
            return [
                'success' => false,
                'ast' => null,
                'errors' => $this->errors,
                'warnings' => $this->warnings,
                'metrics' => $this->calculateMetrics(),
                'symbolTable' => $this->symbolTable
            ];
        }
    }

    /**
     * Tokenize JavaScript code into tokens
     */
    private function tokenize(string $code): array
    {
        $tokens = [];
        $lines = explode("\n", $code);
        $lineNumber = 1;
        
        foreach ($lines as $line) {
            $column = 1;
            $i = 0;
            
            while ($i < strlen($line)) {
                $char = $line[$i];
                
                // Skip whitespace
                if (preg_match('/\s/', $char)) {
                    $i++;
                    $column++;
                    continue;
                }
                
                // Handle comments
                if ($char === '/' && isset($line[$i + 1])) {
                    if ($line[$i + 1] === '/') {
                        // Single line comment - skip rest of line
                        break;
                    } elseif ($line[$i + 1] === '*') {
                        // Multi-line comment - find end
                        $i += 2;
                        $column += 2;
                        while ($i < strlen($line) - 1) {
                            if ($line[$i] === '*' && $line[$i + 1] === '/') {
                                $i += 2;
                                $column += 2;
                                break;
                            }
                            $i++;
                            $column++;
                        }
                        continue;
                    }
                }
                
                // Handle strings
                if ($char === '"' || $char === "'" || $char === '`') {
                    $stringToken = $this->parseString($line, $i, $char, $lineNumber, $column);
                    $tokens[] = $stringToken;
                    $i = $stringToken->end;
                    $column = $stringToken->column + ($stringToken->end - $stringToken->start);
                    continue;
                }
                
                // Handle numbers
                if (is_numeric($char)) {
                    $numberToken = $this->parseNumber($line, $i, $lineNumber, $column);
                    $tokens[] = $numberToken;
                    $i = $numberToken->end;
                    $column = $numberToken->column + ($numberToken->end - $numberToken->start);
                    continue;
                }
                
                // Handle identifiers and keywords
                if (preg_match('/[a-zA-Z_$]/', $char)) {
                    $identifierToken = $this->parseIdentifier($line, $i, $lineNumber, $column);
                    $tokens[] = $identifierToken;
                    $i = $identifierToken->end;
                    $column = $identifierToken->column + ($identifierToken->end - $identifierToken->start);
                    continue;
                }
                
                // Handle operators and punctuation
                $operatorToken = $this->parseOperator($line, $i, $lineNumber, $column);
                if ($operatorToken) {
                    $tokens[] = $operatorToken;
                    $i = $operatorToken->end;
                    $column = $operatorToken->column + ($operatorToken->end - $operatorToken->start);
                    continue;
                }
                
                // Unknown character
                $tokens[] = new Token('UNKNOWN', $char, $lineNumber, $column);
                $i++;
                $column++;
            }
            
            $lineNumber++;
        }
        
        // Add EOF token
        $tokens[] = new Token('EOF', '', $lineNumber, 1);
        
        return $tokens;
    }

    /**
     * Parse string literals
     */
    private function parseString(string $line, int $start, string $quote, int $lineNumber, int $column): Token
    {
        $value = $quote;
        $i = $start + 1;
        
        while ($i < strlen($line)) {
            if ($line[$i] === $quote) {
                $value .= $quote;
                break;
            } elseif ($line[$i] === '\\' && $i + 1 < strlen($line)) {
                $value .= $line[$i] . $line[$i + 1];
                $i += 2;
            } else {
                $value .= $line[$i];
                $i++;
            }
        }
        
        return new Token('STRING', $value, $lineNumber, $column, $start, $i + 1);
    }

    /**
     * Parse numeric literals
     */
    private function parseNumber(string $line, int $start, int $lineNumber, int $column): Token
    {
        $value = '';
        $i = $start;
        
        while ($i < strlen($line) && (is_numeric($line[$i]) || $line[$i] === '.')) {
            $value .= $line[$i];
            $i++;
        }
        
        return new Token('NUMBER', $value, $lineNumber, $column, $start, $i);
    }

    /**
     * Parse identifiers and keywords
     */
    private function parseIdentifier(string $line, int $start, int $lineNumber, int $column): Token
    {
        $value = '';
        $i = $start;
        
        while ($i < strlen($line) && preg_match('/[a-zA-Z0-9_$]/', $line[$i])) {
            $value .= $line[$i];
            $i++;
        }
        
        // Check if it's a keyword
        $keywords = [
            'var', 'let', 'const', 'function', 'class', 'if', 'else', 'while', 'for',
            'return', 'break', 'continue', 'try', 'catch', 'finally', 'throw',
            'new', 'this', 'super', 'import', 'export', 'default', 'async', 'await',
            'true', 'false', 'null', 'undefined', 'typeof', 'instanceof', 'in', 'of'
        ];
        
        $type = in_array($value, $keywords) ? strtoupper($value) : 'IDENTIFIER';
        
        return new Token($type, $value, $lineNumber, $column, $start, $i);
    }

    /**
     * Parse operators and punctuation
     */
    private function parseOperator(string $line, int $start, int $lineNumber, int $column): ?Token
    {
        $char = $line[$start];
        $nextChar = $start + 1 < strlen($line) ? $line[$start + 1] : null;
        
        $operators = [
            '===' => 'STRICT_EQUAL',
            '!==' => 'STRICT_NOT_EQUAL',
            '==' => 'EQUAL',
            '!=' => 'NOT_EQUAL',
            '<=' => 'LESS_EQUAL',
            '>=' => 'GREATER_EQUAL',
            '&&' => 'LOGICAL_AND',
            '||' => 'LOGICAL_OR',
            '++' => 'INCREMENT',
            '--' => 'DECREMENT',
            '+=' => 'PLUS_ASSIGN',
            '-=' => 'MINUS_ASSIGN',
            '*=' => 'MULTIPLY_ASSIGN',
            '/=' => 'DIVIDE_ASSIGN',
            '=>' => 'ARROW',
            '...' => 'SPREAD',
        ];
        
        // Check for two-character operators
        if ($nextChar) {
            $twoChar = $char . $nextChar;
            if (isset($operators[$twoChar])) {
                return new Token($operators[$twoChar], $twoChar, $lineNumber, $column, $start, $start + 2);
            }
        }
        
        // Check for three-character operators
        if ($start + 2 < strlen($line)) {
            $threeChar = $char . $line[$start + 1] . $line[$start + 2];
            if (isset($operators[$threeChar])) {
                return new Token($operators[$threeChar], $threeChar, $lineNumber, $column, $start, $start + 3);
            }
        }
        
        // Single character operators
        $singleOperators = [
            '+' => 'PLUS',
            '-' => 'MINUS',
            '*' => 'MULTIPLY',
            '/' => 'DIVIDE',
            '%' => 'MODULO',
            '=' => 'ASSIGN',
            '<' => 'LESS',
            '>' => 'GREATER',
            '!' => 'NOT',
            '&' => 'BITWISE_AND',
            '|' => 'BITWISE_OR',
            '^' => 'BITWISE_XOR',
            '~' => 'BITWISE_NOT',
            '?' => 'QUESTION',
            ':' => 'COLON',
            ';' => 'SEMICOLON',
            ',' => 'COMMA',
            '.' => 'DOT',
            '(' => 'LPAREN',
            ')' => 'RPAREN',
            '[' => 'LBRACKET',
            ']' => 'RBRACKET',
            '{' => 'LBRACE',
            '}' => 'RBRACE'
        ];
        
        if (isset($singleOperators[$char])) {
            return new Token($singleOperators[$char], $char, $lineNumber, $column, $start, $start + 1);
        }
        
        return null;
    }

    /**
     * RDP: Parse program (top-level)
     */
    private function parseProgram(): ASTNode
    {
        $this->astNodes++;
        $statements = [];
        
        while ($this->currentToken && $this->currentToken->type !== 'EOF') {
            $statement = $this->parseStatement();
            if ($statement) {
                $statements[] = $statement;
            }
        }
        
        return new ASTNode('Program', $statements, $this->currentToken?->line ?? 1, [], []);
    }

    /**
     * RDP: Parse statement
     */
    private function parseStatement(): ?ASTNode
    {
        $this->astNodes++;
        
        switch ($this->currentToken->type) {
            case 'VAR':
            case 'LET':
            case 'CONST':
                return $this->parseVariableDeclaration();
            case 'FUNCTION':
                return $this->parseFunctionDeclaration();
            case 'CLASS':
                return $this->parseClassDeclaration();
            case 'IF':
                return $this->parseIfStatement();
            case 'WHILE':
                return $this->parseWhileStatement();
            case 'FOR':
                return $this->parseForStatement();
            case 'RETURN':
                return $this->parseReturnStatement();
            case 'TRY':
                return $this->parseTryStatement();
            case 'LBRACE':
                return $this->parseBlockStatement();
            default:
                return $this->parseExpressionStatement();
        }
    }

    /**
     * RDP: Parse variable declaration
     */
    private function parseVariableDeclaration(): ASTNode
    {
        $this->astNodes++;
        $kind = $this->currentToken->type; // var, let, const
        $this->advance();
        
        $declarations = [];
        do {
            $id = $this->parseIdentifier();
            $init = null;
            
            if ($this->match('ASSIGN')) {
                $this->advance();
                $init = $this->parseExpression();
            }
            
            $declarations[] = new ASTNode('VariableDeclarator', [
                'id' => $id, 'init' => $init
            ], $this->currentToken->line, [], []);
            
        } while ($this->match('COMMA') && $this->advance());
        
        $this->consume('SEMICOLON', "Expected ';' after variable declaration");
        
        return new ASTNode('VariableDeclaration', [
            'kind' => $kind, 'declarations' => $declarations
        ], $this->currentToken->line, [], []);
    }

    /**
     * RDP: Parse function declaration
     */
    private function parseFunctionDeclaration(): ASTNode
    {
        $this->astNodes++;
        $this->consume('FUNCTION', "Expected 'function'");
        
        $id = $this->parseIdentifierForAST();
        $params = $this->parseFunctionParameters();
        $body = $this->parseBlockStatement();
        
        return new ASTNode('FunctionDeclaration', [
            'id' => $id, 'params' => $params,
            'body' => $body
        ], $this->currentToken->line, [], []);
    }

    /**
     * RDP: Parse class declaration
     */
    private function parseClassDeclaration(): ASTNode
    {
        $this->astNodes++;
        $this->consume('CLASS', "Expected 'class'");
        
        $id = $this->parseIdentifier();
        $superClass = null;
        
        if ($this->match('EXTENDS')) {
            $this->advance();
            $superClass = $this->parseIdentifier();
        }
        
        $this->consume('LBRACE', "Expected '{' after class name");
        
        $body = [];
        while (!$this->match('RBRACE') && $this->currentToken->type !== 'EOF') {
            $body[] = $this->parseClassMember();
        }
        
        $this->consume('RBRACE', "Expected '}' after class body");
        
        return new ASTNode('ClassDeclaration', [
            'id' => $id, 'superClass' => $superClass,
            'body' => $body
        ], $this->currentToken->line, []);
    }

    /**
     * RDP: Parse expression
     */
    private function parseExpression(): ASTNode
    {
        $this->astNodes++;
        return $this->parseAssignmentExpression();
    }

    /**
     * RDP: Parse assignment expression
     */
    private function parseAssignmentExpression(): ASTNode
    {
        $this->astNodes++;
        $left = $this->parseLogicalOrExpression();
        
        if ($this->isAssignmentOperator($this->currentToken->type)) {
            $operator = $this->currentToken->type;
            $this->advance();
            $right = $this->parseAssignmentExpression();
            
            return new ASTNode('AssignmentExpression', [
                'operator' => $operator, 'left' => $left,
                'right' => $right
            ], $this->currentToken->line, []);
        }
        
        return $left;
    }

    /**
     * RDP: Parse logical OR expression
     */
    private function parseLogicalOrExpression(): ASTNode
    {
        $this->astNodes++;
        $left = $this->parseLogicalAndExpression();
        
        while ($this->match('LOGICAL_OR')) {
            $operator = $this->currentToken->type;
            $this->advance();
            $right = $this->parseLogicalAndExpression();
            
            $left = new ASTNode('LogicalExpression', [
                'operator' => $operator, 'left' => $left,
                'right' => $right
            ], $this->currentToken->line, []);
        }
        
        return $left;
    }

    /**
     * RDP: Parse logical AND expression
     */
    private function parseLogicalAndExpression(): ASTNode
    {
        $this->astNodes++;
        $left = $this->parseEqualityExpression();
        
        while ($this->match('LOGICAL_AND')) {
            $operator = $this->currentToken->type;
            $this->advance();
            $right = $this->parseEqualityExpression();
            
            $left = new ASTNode('LogicalExpression', [
                'operator' => $operator, 'left' => $left,
                'right' => $right
            ], $this->currentToken->line, []);
        }
        
        return $left;
    }

    /**
     * RDP: Parse equality expression
     */
    private function parseEqualityExpression(): ASTNode
    {
        $this->astNodes++;
        $left = $this->parseRelationalExpression();
        
        while ($this->match('EQUAL') || $this->match('NOT_EQUAL') || 
               $this->match('STRICT_EQUAL') || $this->match('STRICT_NOT_EQUAL')) {
            $operator = $this->currentToken->type;
            $this->advance();
            $right = $this->parseRelationalExpression();
            
            $left = new ASTNode('BinaryExpression', [
                'operator' => $operator, 'left' => $left,
                'right' => $right
            ], $this->currentToken->line, []);
        }
        
        return $left;
    }

    /**
     * RDP: Parse relational expression
     */
    private function parseRelationalExpression(): ASTNode
    {
        $this->astNodes++;
        $left = $this->parseAdditiveExpression();
        
        while ($this->match('LESS') || $this->match('GREATER') || 
               $this->match('LESS_EQUAL') || $this->match('GREATER_EQUAL') ||
               $this->match('INSTANCEOF') || $this->match('IN')) {
            $operator = $this->currentToken->type;
            $this->advance();
            $right = $this->parseAdditiveExpression();
            
            $left = new ASTNode('BinaryExpression', [
                'operator' => $operator, 'left' => $left,
                'right' => $right
            ], $this->currentToken->line, []);
        }
        
        return $left;
    }

    /**
     * RDP: Parse additive expression
     */
    private function parseAdditiveExpression(): ASTNode
    {
        $this->astNodes++;
        $left = $this->parseMultiplicativeExpression();
        
        while ($this->match('PLUS') || $this->match('MINUS')) {
            $operator = $this->currentToken->type;
            $this->advance();
            $right = $this->parseMultiplicativeExpression();
            
            $left = new ASTNode('BinaryExpression', [
                'operator' => $operator, 'left' => $left,
                'right' => $right
            ], $this->currentToken->line, []);
        }
        
        return $left;
    }

    /**
     * RDP: Parse multiplicative expression
     */
    private function parseMultiplicativeExpression(): ASTNode
    {
        $this->astNodes++;
        $left = $this->parseUnaryExpression();
        
        while ($this->match('MULTIPLY') || $this->match('DIVIDE') || $this->match('MODULO')) {
            $operator = $this->currentToken->type;
            $this->advance();
            $right = $this->parseUnaryExpression();
            
            $left = new ASTNode('BinaryExpression', [
                'operator' => $operator, 'left' => $left,
                'right' => $right
            ], $this->currentToken->line, []);
        }
        
        return $left;
    }

    /**
     * RDP: Parse unary expression
     */
    private function parseUnaryExpression(): ASTNode
    {
        $this->astNodes++;
        
        if ($this->match('NOT') || $this->match('MINUS') || $this->match('PLUS') || 
            $this->match('INCREMENT') || $this->match('DECREMENT') || $this->match('TYPEOF')) {
            $operator = $this->currentToken->type;
            $this->advance();
            $argument = $this->parseUnaryExpression();
            
            return new ASTNode('UnaryExpression', [
                'operator' => $operator, 'argument' => $argument
            ], $this->currentToken->line, []);
        }
        
        return $this->parsePrimaryExpression();
    }

    /**
     * RDP: Parse primary expression
     */
    private function parsePrimaryExpression(): ASTNode
    {
        $this->astNodes++;
        
        switch ($this->currentToken->type) {
            case 'IDENTIFIER':
                $node = new ASTNode('Identifier', [
                    'name' => $this->currentToken->value
                ], $this->currentToken->line, [], []);
                $this->advance();
                return $node;
                
            case 'NUMBER':
                $node = new ASTNode('Literal', [
                    'value' => (float) $this->currentToken->value
                ], $this->currentToken->line, [], []);
                $this->advance();
                return $node;
                
            case 'STRING':
                $node = new ASTNode('Literal', [
                    'value' => $this->currentToken->value
                ], $this->currentToken->line, [], []);
                $this->advance();
                return $node;
                
            case 'TRUE':
                $node = new ASTNode('Literal', ['value' => true], $this->currentToken->line, [], []);
                $this->advance();
                return $node;
                
            case 'FALSE':
                $node = new ASTNode('Literal', ['value' => false], $this->currentToken->line, [], []);
                $this->advance();
                return $node;
                
            case 'NULL':
                $node = new ASTNode('Literal', ['value' => null], $this->currentToken->line, [], []);
                $this->advance();
                return $node;
                
            case 'LPAREN':
                $this->advance();
                $expression = $this->parseExpression();
                $this->consume('RPAREN', "Expected ')' after expression");
                return $expression;
                
            case 'LBRACKET':
                return $this->parseArrayExpression();
                
            case 'LBRACE':
                return $this->parseObjectExpression();
                
            default:
                throw new Exception("Unexpected token: {$this->currentToken->type}");
        }
    }

    /**
     * RDP: Parse array expression
     */
    private function parseArrayExpression(): ASTNode
    {
        $this->astNodes++;
        $this->consume('LBRACKET', "Expected '['");
        
        $elements = [];
        while (!$this->match('RBRACKET') && $this->currentToken->type !== 'EOF') {
            if ($this->match('COMMA')) {
                $elements[] = null; // Empty element
                $this->advance();
            } else {
                $elements[] = $this->parseExpression();
                if (!$this->match('RBRACKET')) {
                    $this->consume('COMMA', "Expected ',' or ']'");
                }
            }
        }
        
        $this->consume('RBRACKET', "Expected ']'");
        
        return new ASTNode('ArrayExpression', [
            'elements' => $elements
        ], $this->currentToken->line, []);
    }

    /**
     * RDP: Parse object expression
     */
    private function parseObjectExpression(): ASTNode
    {
        $this->astNodes++;
        $this->consume('LBRACE', "Expected '{'");
        
        $properties = [];
        while (!$this->match('RBRACE') && $this->currentToken->type !== 'EOF') {
            $key = $this->parseObjectProperty();
            $properties[] = $key;
            
            if (!$this->match('RBRACE')) {
                $this->consume('COMMA', "Expected ',' or '}'");
            }
        }
        
        $this->consume('RBRACE', "Expected '}'");
        
        return new ASTNode('ObjectExpression', [
            'properties' => $properties
        ], $this->currentToken->line, []);
    }

    /**
     * RDP: Parse object property
     */
    private function parseObjectProperty(): ASTNode
    {
        $this->astNodes++;
        
        $key = $this->parseExpression();
        $this->consume('COLON', "Expected ':'");
        $value = $this->parseExpression();
        
        return new ASTNode('Property', [
            'key' => $key, 'value' => $value
        ], $this->currentToken->line, []);
    }

    /**
     * RDP: Parse function parameters
     */
    private function parseFunctionParameters(): array
    {
        $this->consume('LPAREN', "Expected '('");
        
        $params = [];
        while (!$this->match('RPAREN') && $this->currentToken->type !== 'EOF') {
            $params[] = $this->parseIdentifierForAST();
            if (!$this->match('RPAREN')) {
                $this->consume('COMMA', "Expected ',' or ')'");
            }
        }
        
        $this->consume('RPAREN', "Expected ')'");
        
        return $params;
    }

    /**
     * RDP: Parse block statement
     */
    private function parseBlockStatement(): ASTNode
    {
        $this->astNodes++;
        $this->consume('LBRACE', "Expected '{'");
        
        $body = [];
        while (!$this->match('RBRACE') && $this->currentToken->type !== 'EOF') {
            $statement = $this->parseStatement();
            if ($statement) {
                $body[] = $statement;
            }
        }
        
        $this->consume('RBRACE', "Expected '}'");
        
        return new ASTNode('BlockStatement', [
            'body' => $body
        ], $this->currentToken->line, []);
    }

    /**
     * RDP: Parse if statement
     */
    private function parseIfStatement(): ASTNode
    {
        $this->astNodes++;
        $this->consume('IF', "Expected 'if'");
        $this->consume('LPAREN', "Expected '(' after 'if'");
        
        $test = $this->parseExpression();
        $this->consume('RPAREN', "Expected ')' after if condition");
        
        $consequent = $this->parseStatement();
        $alternate = null;
        
        if ($this->match('ELSE')) {
            $this->advance();
            $alternate = $this->parseStatement();
        }
        
        return new ASTNode('IfStatement', [
            'test' => $test, 'consequent' => $consequent,
            'alternate' => $alternate
        ], $this->currentToken->line, []);
    }

    /**
     * RDP: Parse while statement
     */
    private function parseWhileStatement(): ASTNode
    {
        $this->astNodes++;
        $this->consume('WHILE', "Expected 'while'");
        $this->consume('LPAREN', "Expected '(' after 'while'");
        
        $test = $this->parseExpression();
        $this->consume('RPAREN', "Expected ')' after while condition");
        
        $body = $this->parseStatement();
        
        return new ASTNode('WhileStatement', [
            'test' => $test, 'body' => $body
        ], $this->currentToken->line, []);
    }

    /**
     * RDP: Parse for statement
     */
    private function parseForStatement(): ASTNode
    {
        $this->astNodes++;
        $this->consume('FOR', "Expected 'for'");
        $this->consume('LPAREN', "Expected '(' after 'for'");
        
        $init = null;
        $test = null;
        $update = null;
        
        // Parse initialization
        if (!$this->match('SEMICOLON')) {
            $init = $this->parseExpression();
        }
        $this->consume('SEMICOLON', "Expected ';' after for loop initialization");
        
        // Parse test condition
        if (!$this->match('SEMICOLON')) {
            $test = $this->parseExpression();
        }
        $this->consume('SEMICOLON', "Expected ';' after for loop condition");
        
        // Parse update
        if (!$this->match('RPAREN')) {
            $update = $this->parseExpression();
        }
        $this->consume('RPAREN', "Expected ')' after for loop update");
        
        $body = $this->parseStatement();
        
        return new ASTNode('ForStatement', [
            'init' => $init, 'test' => $test,
            'update' => $update,
            'body' => $body
        ], $this->currentToken->line, []);
    }

    /**
     * RDP: Parse return statement
     */
    private function parseReturnStatement(): ASTNode
    {
        $this->astNodes++;
        $this->consume('RETURN', "Expected 'return'");
        
        $argument = null;
        if (!$this->match('SEMICOLON') && $this->currentToken->type !== 'EOF') {
            $argument = $this->parseExpression();
        }
        
        $this->consume('SEMICOLON', "Expected ';' after return statement");
        
        return new ASTNode('ReturnStatement', [
            'argument' => $argument
        ], $this->currentToken->line, []);
    }

    /**
     * RDP: Parse try statement
     */
    private function parseTryStatement(): ASTNode
    {
        $this->astNodes++;
        $this->consume('TRY', "Expected 'try'");
        
        $block = $this->parseBlockStatement();
        $handler = null;
        $finalizer = null;
        
        if ($this->match('CATCH')) {
            $this->advance();
            $this->consume('LPAREN', "Expected '(' after 'catch'");
            $param = $this->parseIdentifier();
            $this->consume('RPAREN', "Expected ')' after catch parameter");
            $handler = $this->parseBlockStatement();
        }
        
        if ($this->match('FINALLY')) {
            $this->advance();
            $finalizer = $this->parseBlockStatement();
        }
        
        return new ASTNode('TryStatement', [
            'block' => $block, 'handler' => $handler,
            'finalizer' => $finalizer
        ], $this->currentToken->line, []);
    }

    /**
     * RDP: Parse class member
     */
    private function parseClassMember(): ASTNode
    {
        $this->astNodes++;
        
        if ($this->match('CONSTRUCTOR')) {
            $this->advance();
            $params = $this->parseFunctionParameters();
            $body = $this->parseBlockStatement();
            
            return new ASTNode('MethodDefinition', [
                'key' => new ASTNode('Identifier', ['name' => 'constructor'], $this->currentToken->line, [], []),
                'value' => new ASTNode('FunctionExpression', [
                    'params' => $params, 'body' => $body
                ], $this->currentToken->line, []),
                'kind' => 'constructor'
            ], $this->currentToken->line);
        }
        
        // Parse method or property
        $key = $this->parseExpression();
        $value = null;
        $kind = 'method';
        
        if ($this->match('LPAREN')) {
            // Method
            $params = $this->parseFunctionParameters();
            $body = $this->parseBlockStatement();
            $value = new ASTNode('FunctionExpression', [
                'params' => $params, 'body' => $body
            ], $this->currentToken->line, []);
        } else {
            // Property
            $this->consume('ASSIGN', "Expected '=' for property");
            $value = $this->parseExpression();
            $kind = 'property';
        }
        
        return new ASTNode('MethodDefinition', [
            'key' => $key, 'value' => $value,
            'kind' => $kind
        ], $this->currentToken->line, []);
    }

    /**
     * RDP: Parse expression statement
     */
    private function parseExpressionStatement(): ASTNode
    {
        $this->astNodes++;
        $expression = $this->parseExpression();
        $this->consume('SEMICOLON', "Expected ';' after expression");
        
        return new ASTNode('ExpressionStatement', [
            'expression' => $expression
        ], $this->currentToken->line, []);
    }


    /**
     * RDP: Advance to next token
     */
    private function advance(): ?Token
    {
        if ($this->position < count($this->tokens) - 1) {
            $this->position++;
            $this->currentToken = $this->tokens[$this->position];
        } else {
            $this->currentToken = null;
        }
        
        return $this->currentToken;
    }

    /**
     * RDP: Check if current token matches expected type
     */
    private function match(string $type): bool
    {
        return $this->currentToken && $this->currentToken->type === $type;
    }

    /**
     * RDP: Consume token of expected type
     */
    private function consume(string $type, string $message): Token
    {
        if ($this->match($type)) {
            $token = $this->currentToken;
            $this->advance();
            return $token;
        }
        
        throw new Exception($message);
    }

    /**
     * RDP: Check if token is assignment operator
     */
    private function isAssignmentOperator(string $type): bool
    {
        return in_array($type, [
            'ASSIGN', 'PLUS_ASSIGN', 'MINUS_ASSIGN', 
            'MULTIPLY_ASSIGN', 'DIVIDE_ASSIGN'
        ]);
    }

    /**
     * RDP: Error recovery - synchronize parser
     */
    private function synchronize(): void
    {
        $this->errorRecoveryCount++;
        $this->advance();
        
        while ($this->currentToken && $this->currentToken->type !== 'EOF') {
            if ($this->currentToken->type === 'SEMICOLON') {
                $this->advance();
                return;
            }
            
            // Check for statement start tokens
            $statementStartTokens = [
                'VAR', 'LET', 'CONST', 'FUNCTION', 'CLASS', 'IF', 'WHILE', 'FOR',
                'RETURN', 'TRY', 'CATCH', 'FINALLY', 'LBRACE'
            ];
            
            if (in_array($this->currentToken->type, $statementStartTokens)) {
                return;
            }
            
            $this->advance();
        }
    }

    /**
     * Calculate RDP performance metrics
     */
    private function calculateMetrics(): array
    {
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        return [
            'parsing_time' => ($endTime - $this->startTime) * 1000, // Convert to milliseconds
            'memory_usage' => $endMemory - $this->startMemory,
            'ast_nodes' => $this->astNodes,
            'tokens_processed' => $this->tokensProcessed,
            'error_recovery_count' => $this->errorRecoveryCount,
            'syntax_accuracy' => $this->calculateSyntaxAccuracy(),
            'semantic_preservation' => $this->calculateSemanticPreservation()
        ];
    }

    /**
     * Calculate syntax accuracy percentage
     */
    private function calculateSyntaxAccuracy(): float
    {
        if ($this->tokensProcessed === 0) {
            return 0.0;
        }
        
        $errorCount = count($this->errors);
        $accuracy = (($this->tokensProcessed - $errorCount) / $this->tokensProcessed) * 100;
        
        return max(0, min(100, $accuracy));
    }

    /**
     * Calculate semantic preservation percentage
     */
    private function calculateSemanticPreservation(): float
    {
        // This is a simplified calculation
        // In a real implementation, you would analyze the AST for semantic correctness
        $errorCount = count($this->errors);
        $warningCount = count($this->warnings);
        
        if ($this->astNodes === 0) {
            return 0.0;
        }
        
        $semanticScore = 100 - ($errorCount * 10) - ($warningCount * 5);
        
        return max(0, min(100, $semanticScore));
    }

    /**
     * Reset parser state
     */
    private function reset(): void
    {
        $this->tokens = [];
        $this->position = 0;
        $this->currentToken = null;
        $this->errors = [];
        $this->warnings = [];
        $this->symbolTable = [];
        $this->scopes = [];
        $this->astNodes = 0;
        $this->tokensProcessed = 0;
        $this->errorRecoveryCount = 0;
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage();
    }

    /**
     * Parse identifier for AST construction
     */
    private function parseIdentifierForAST(): ASTNode
    {
        if ($this->currentToken->type !== 'IDENTIFIER') {
            throw new Exception("Expected identifier, got {$this->currentToken->type}");
        }
        
        $name = $this->currentToken->value;
        $line = $this->currentToken->line;
        $this->advance();
        
        return new ASTNode('Identifier', ['name' => $name], $line, []);
    }

}

/**
 * Token class for representing lexical tokens
 */
class Token
{
    public string $type;
    public string $value;
    public int $line;
    public int $column;
    public int $start;
    public int $end;

    public function __construct(string $type, string $value, int $line, int $column, int $start = 0, int $end = 0)
    {
        $this->type = $type;
        $this->value = $value;
        $this->line = $line;
        $this->column = $column;
        $this->start = $start;
        $this->end = $end;
    }
}

/**
 * AST Node class for representing Abstract Syntax Tree nodes
 */
class ASTNode
{
    public string $type;
    public array $children;
    public int $line;
    public array $metadata;

    public function __construct(string $type, array $children, int $line, array $metadata = [])
    {
        $this->type = $type;
        $this->children = $children;
        $this->line = $line;
        $this->metadata = $metadata;
    }
}
