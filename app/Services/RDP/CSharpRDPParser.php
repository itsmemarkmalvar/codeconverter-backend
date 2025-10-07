<?php

namespace App\Services\RDP;

use Exception;

/**
 * C# Recursive Descent Parser (RDP)
 * 
 * This class implements a pure Recursive Descent Parsing algorithm
 * for C# syntax analysis, focusing on demonstrating RDP effectiveness
 * for thesis research purposes.
 */
class CSharpRDPParser
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
     * Parse C# code using RDP algorithm
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
            $ast = $this->parseCompilationUnit();
            
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
     * Tokenize C# code into tokens
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
                if ($char === '"' || $char === "'" || $char === '@') {
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
                if (preg_match('/[a-zA-Z_]/', $char)) {
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
        
        // Handle verbatim strings (@"...")
        if ($quote === '@' && $i < strlen($line) && $line[$i] === '"') {
            $value .= '"';
            $i++;
            while ($i < strlen($line)) {
                if ($line[$i] === '"' && $i + 1 < strlen($line) && $line[$i + 1] === '"') {
                    $value .= '""';
                    $i += 2;
                } elseif ($line[$i] === '"') {
                    $value .= '"';
                    break;
                } else {
                    $value .= $line[$i];
                    $i++;
                }
            }
        } else {
            // Regular string
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
        
        while ($i < strlen($line) && (is_numeric($line[$i]) || $line[$i] === '.' || $line[$i] === 'f' || $line[$i] === 'd' || $line[$i] === 'm' || $line[$i] === 'l')) {
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
        
        while ($i < strlen($line) && preg_match('/[a-zA-Z0-9_]/', $line[$i])) {
            $value .= $line[$i];
            $i++;
        }
        
        // Check if it's a keyword
        $keywords = [
            'using', 'namespace', 'class', 'struct', 'interface', 'enum', 'delegate',
            'public', 'private', 'protected', 'internal', 'static', 'readonly', 'const',
            'virtual', 'override', 'abstract', 'sealed', 'partial', 'async', 'await',
            'var', 'int', 'string', 'bool', 'double', 'float', 'decimal', 'char', 'byte',
            'short', 'long', 'uint', 'ushort', 'ulong', 'sbyte',
            'if', 'else', 'while', 'for', 'foreach', 'do', 'switch', 'case', 'default',
            'break', 'continue', 'return', 'throw', 'try', 'catch', 'finally',
            'new', 'this', 'base', 'null', 'true', 'false', 'void',
            'in', 'out', 'ref', 'params', 'where', 'select', 'from', 'group', 'orderby',
            'get', 'set', 'add', 'remove', 'event', 'operator', 'implicit', 'explicit'
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
            '%=' => 'MODULO_ASSIGN',
            '&=' => 'AND_ASSIGN',
            '|=' => 'OR_ASSIGN',
            '^=' => 'XOR_ASSIGN',
            '<<=' => 'LEFT_SHIFT_ASSIGN',
            '>>=' => 'RIGHT_SHIFT_ASSIGN',
            '??' => 'NULL_COALESCE',
            '??=' => 'NULL_COALESCE_ASSIGN',
            '=>' => 'LAMBDA',
            '...' => 'SPREAD',
            '<<' => 'LEFT_SHIFT',
            '>>' => 'RIGHT_SHIFT',
            '::' => 'NAMESPACE_ALIAS'
        ];
        
        // Check for three-character operators
        if ($start + 2 < strlen($line)) {
            $threeChar = $char . $line[$start + 1] . $line[$start + 2];
            if (isset($operators[$threeChar])) {
                return new Token($operators[$threeChar], $threeChar, $lineNumber, $column, $start, $start + 3);
            }
        }
        
        // Check for two-character operators
        if ($nextChar) {
            $twoChar = $char . $nextChar;
            if (isset($operators[$twoChar])) {
                return new Token($operators[$twoChar], $twoChar, $lineNumber, $column, $start, $start + 2);
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
            '}' => 'RBRACE',
            '@' => 'AT'
        ];
        
        if (isset($singleOperators[$char])) {
            return new Token($singleOperators[$char], $char, $lineNumber, $column, $start, $start + 1);
        }
        
        return null;
    }

    /**
     * RDP: Parse compilation unit (top-level)
     */
    private function parseCompilationUnit(): ASTNode
    {
        $this->astNodes++;
        $members = [];
        
        // Parse using directives
        while ($this->match('USING')) {
            $members[] = $this->parseUsingDirective();
        }
        
        // Parse namespace declarations
        while ($this->currentToken && $this->currentToken->type !== 'EOF') {
            if ($this->match('NAMESPACE')) {
                $members[] = $this->parseNamespaceDeclaration();
            } else {
                $members[] = $this->parseTypeDeclaration();
            }
        }
        
        return new ASTNode('CompilationUnit', [
            'members' => $members
        ], $this->currentToken?->line ?? 1);
    }

    /**
     * RDP: Parse using directive
     */
    private function parseUsingDirective(): ASTNode
    {
        $this->astNodes++;
        $this->consume('USING', "Expected 'using'");
        
        $name = $this->parseQualifiedName();
        $this->consume('SEMICOLON', "Expected ';' after using directive");
        
        return new ASTNode('UsingDirective', [
            'name' => $name
        ], $this->currentToken->line);
    }

    /**
     * RDP: Parse namespace declaration
     */
    private function parseNamespaceDeclaration(): ASTNode
    {
        $this->astNodes++;
        $this->consume('NAMESPACE', "Expected 'namespace'");
        
        $name = $this->parseQualifiedName();
        $this->consume('LBRACE', "Expected '{' after namespace name");
        
        $members = [];
        while (!$this->match('RBRACE') && $this->currentToken->type !== 'EOF') {
            $members[] = $this->parseTypeDeclaration();
        }
        
        $this->consume('RBRACE', "Expected '}' after namespace body");
        
        return new ASTNode('NamespaceDeclaration', [
            'name' => $name,
            'members' => $members
        ], $this->currentToken->line);
    }

    /**
     * RDP: Parse type declaration
     */
    private function parseTypeDeclaration(): ASTNode
    {
        $this->astNodes++;
        
        $modifiers = $this->parseModifiers();
        
        if ($this->match('CLASS')) {
            return $this->parseClassDeclaration($modifiers);
        } elseif ($this->match('STRUCT')) {
            return $this->parseStructDeclaration($modifiers);
        } elseif ($this->match('INTERFACE')) {
            return $this->parseInterfaceDeclaration($modifiers);
        } elseif ($this->match('ENUM')) {
            return $this->parseEnumDeclaration($modifiers);
        } else {
            throw new Exception("Expected type declaration, got {$this->currentToken->type}");
        }
    }

    /**
     * RDP: Parse class declaration
     */
    private function parseClassDeclaration(array $modifiers): ASTNode
    {
        $this->astNodes++;
        $this->consume('CLASS', "Expected 'class'");
        
        $identifier = $this->parseIdentifier();
        $typeParameters = $this->parseTypeParameters();
        $baseTypes = $this->parseBaseTypes();
        
        $this->consume('LBRACE', "Expected '{' after class name");
        
        $members = [];
        while (!$this->match('RBRACE') && $this->currentToken->type !== 'EOF') {
            $members[] = $this->parseClassMember();
        }
        
        $this->consume('RBRACE', "Expected '}' after class body");
        
        return new ASTNode('ClassDeclaration', [
            'modifiers' => $modifiers,
            'identifier' => $identifier,
            'typeParameters' => $typeParameters,
            'baseTypes' => $baseTypes,
            'members' => $members
        ], $this->currentToken->line);
    }

    /**
     * RDP: Parse struct declaration
     */
    private function parseStructDeclaration(array $modifiers): ASTNode
    {
        $this->astNodes++;
        $this->consume('STRUCT', "Expected 'struct'");
        
        $identifier = $this->parseIdentifier();
        $typeParameters = $this->parseTypeParameters();
        $interfaces = $this->parseInterfaceList();
        
        $this->consume('LBRACE', "Expected '{' after struct name");
        
        $members = [];
        while (!$this->match('RBRACE') && $this->currentToken->type !== 'EOF') {
            $members[] = $this->parseStructMember();
        }
        
        $this->consume('RBRACE', "Expected '}' after struct body");
        
        return new ASTNode('StructDeclaration', [
            'modifiers' => $modifiers,
            'identifier' => $identifier,
            'typeParameters' => $typeParameters,
            'interfaces' => $interfaces,
            'members' => $members
        ], $this->currentToken->line);
    }

    /**
     * RDP: Parse interface declaration
     */
    private function parseInterfaceDeclaration(array $modifiers): ASTNode
    {
        $this->astNodes++;
        $this->consume('INTERFACE', "Expected 'interface'");
        
        $identifier = $this->parseIdentifier();
        $typeParameters = $this->parseTypeParameters();
        $baseTypes = $this->parseBaseTypes();
        
        $this->consume('LBRACE', "Expected '{' after interface name");
        
        $members = [];
        while (!$this->match('RBRACE') && $this->currentToken->type !== 'EOF') {
            $members[] = $this->parseInterfaceMember();
        }
        
        $this->consume('RBRACE', "Expected '}' after interface body");
        
        return new ASTNode('InterfaceDeclaration', [
            'modifiers' => $modifiers,
            'identifier' => $identifier,
            'typeParameters' => $typeParameters,
            'baseTypes' => $baseTypes,
            'members' => $members
        ], $this->currentToken->line);
    }

    /**
     * RDP: Parse enum declaration
     */
    private function parseEnumDeclaration(array $modifiers): ASTNode
    {
        $this->astNodes++;
        $this->consume('ENUM', "Expected 'enum'");
        
        $identifier = $this->parseIdentifier();
        $baseType = null;
        
        if ($this->match('COLON')) {
            $this->advance();
            $baseType = $this->parseType();
        }
        
        $this->consume('LBRACE', "Expected '{' after enum name");
        
        $members = [];
        while (!$this->match('RBRACE') && $this->currentToken->type !== 'EOF') {
            $members[] = $this->parseEnumMember();
        }
        
        $this->consume('RBRACE', "Expected '}' after enum body");
        
        return new ASTNode('EnumDeclaration', [
            'modifiers' => $modifiers,
            'identifier' => $identifier,
            'baseType' => $baseType,
            'members' => $members
        ], $this->currentToken->line);
    }

    /**
     * RDP: Parse modifiers
     */
    private function parseModifiers(): array
    {
        $modifiers = [];
        
        $modifierKeywords = [
            'PUBLIC', 'PRIVATE', 'PROTECTED', 'INTERNAL', 'STATIC', 'READONLY', 'CONST',
            'VIRTUAL', 'OVERRIDE', 'ABSTRACT', 'SEALED', 'PARTIAL', 'ASYNC'
        ];
        
        while ($this->currentToken && in_array($this->currentToken->type, $modifierKeywords)) {
            $modifiers[] = $this->currentToken->value;
            $this->advance();
        }
        
        return $modifiers;
    }

    /**
     * RDP: Parse qualified name
     */
    private function parseQualifiedName(): ASTNode
    {
        $this->astNodes++;
        $names = [];
        
        $names[] = $this->parseIdentifier();
        
        while ($this->match('DOT')) {
            $this->advance();
            $names[] = $this->parseIdentifier();
        }
        
        return new ASTNode('QualifiedName', [
            'names' => $names
        ], $this->currentToken->line);
    }

    /**
     * RDP: Parse type parameters
     */
    private function parseTypeParameters(): array
    {
        if (!$this->match('LESS')) {
            return [];
        }
        
        $this->consume('LESS', "Expected '<'");
        $parameters = [];
        
        do {
            $parameters[] = $this->parseTypeParameter();
        } while ($this->match('COMMA') && $this->advance());
        
        $this->consume('GREATER', "Expected '>'");
        
        return $parameters;
    }

    /**
     * RDP: Parse type parameter
     */
    private function parseTypeParameter(): ASTNode
    {
        $this->astNodes++;
        $identifier = $this->parseIdentifier();
        $constraints = [];
        
        if ($this->match('WHERE')) {
            $this->advance();
            $constraints[] = $this->parseTypeParameterConstraint();
        }
        
        return new ASTNode('TypeParameter', [
            'identifier' => $identifier,
            'constraints' => $constraints
        ], $this->currentToken->line);
    }

    /**
     * RDP: Parse type parameter constraint
     */
    private function parseTypeParameterConstraint(): ASTNode
    {
        $this->astNodes++;
        $identifier = $this->parseIdentifier();
        $this->consume('COLON', "Expected ':'");
        
        $constraint = $this->parseType();
        
        return new ASTNode('TypeParameterConstraint', [
            'identifier' => $identifier,
            'constraint' => $constraint
        ], $this->currentToken->line);
    }

    /**
     * RDP: Parse base types
     */
    private function parseBaseTypes(): array
    {
        if (!$this->match('COLON')) {
            return [];
        }
        
        $this->consume('COLON', "Expected ':'");
        $baseTypes = [];
        
        do {
            $baseTypes[] = $this->parseType();
        } while ($this->match('COMMA') && $this->advance());
        
        return $baseTypes;
    }

    /**
     * RDP: Parse interface list
     */
    private function parseInterfaceList(): array
    {
        if (!$this->match('COLON')) {
            return [];
        }
        
        $this->consume('COLON', "Expected ':'");
        $interfaces = [];
        
        do {
            $interfaces[] = $this->parseType();
        } while ($this->match('COMMA') && $this->advance());
        
        return $interfaces;
    }

    /**
     * RDP: Parse type
     */
    private function parseType(): ASTNode
    {
        $this->astNodes++;
        
        if ($this->match('IDENTIFIER')) {
            $name = $this->parseQualifiedName();
            $typeArguments = $this->parseTypeArguments();
            
            return new ASTNode('NamedType', [
                'name' => $name,
                'typeArguments' => $typeArguments
            ], $this->currentToken->line);
        }
        
        throw new Exception("Expected type, got {$this->currentToken->type}");
    }

    /**
     * RDP: Parse type arguments
     */
    private function parseTypeArguments(): array
    {
        if (!$this->match('LESS')) {
            return [];
        }
        
        $this->consume('LESS', "Expected '<'");
        $arguments = [];
        
        do {
            $arguments[] = $this->parseType();
        } while ($this->match('COMMA') && $this->advance());
        
        $this->consume('GREATER', "Expected '>'");
        
        return $arguments;
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
     * RDP: Parse class member
     */
    private function parseClassMember(): ASTNode
    {
        $this->astNodes++;
        
        $modifiers = $this->parseModifiers();
        
        if ($this->match('CONSTRUCTOR') || $this->match('IDENTIFIER')) {
            return $this->parseConstructorDeclaration($modifiers);
        } elseif ($this->match('GET') || $this->match('SET')) {
            return $this->parsePropertyDeclaration($modifiers);
        } elseif ($this->match('EVENT')) {
            return $this->parseEventDeclaration($modifiers);
        } else {
            return $this->parseMethodDeclaration($modifiers);
        }
    }

    /**
     * RDP: Parse constructor declaration
     */
    private function parseConstructorDeclaration(array $modifiers): ASTNode
    {
        $this->astNodes++;
        $identifier = $this->parseIdentifier();
        $parameters = $this->parseParameterList();
        $initializer = $this->parseConstructorInitializer();
        $body = $this->parseBlockStatement();
        
        return new ASTNode('ConstructorDeclaration', [
            'modifiers' => $modifiers,
            'identifier' => $identifier,
            'parameters' => $parameters,
            'initializer' => $initializer,
            'body' => $body
        ], $this->currentToken->line);
    }

    /**
     * RDP: Parse method declaration
     */
    private function parseMethodDeclaration(array $modifiers): ASTNode
    {
        $this->astNodes++;
        $returnType = $this->parseType();
        $identifier = $this->parseIdentifier();
        $typeParameters = $this->parseTypeParameters();
        $parameters = $this->parseParameterList();
        $constraints = $this->parseTypeParameterConstraints();
        $body = $this->parseBlockStatement();
        
        return new ASTNode('MethodDeclaration', [
            'modifiers' => $modifiers,
            'returnType' => $returnType,
            'identifier' => $identifier,
            'typeParameters' => $typeParameters,
            'parameters' => $parameters,
            'constraints' => $constraints,
            'body' => $body
        ], $this->currentToken->line);
    }

    /**
     * RDP: Parse property declaration
     */
    private function parsePropertyDeclaration(array $modifiers): ASTNode
    {
        $this->astNodes++;
        $type = $this->parseType();
        $identifier = $this->parseIdentifier();
        
        $accessors = [];
        $this->consume('LBRACE', "Expected '{'");
        
        while (!$this->match('RBRACE') && $this->currentToken->type !== 'EOF') {
            $accessors[] = $this->parseAccessorDeclaration();
        }
        
        $this->consume('RBRACE', "Expected '}'");
        
        return new ASTNode('PropertyDeclaration', [
            'modifiers' => $modifiers,
            'type' => $type,
            'identifier' => $identifier,
            'accessors' => $accessors
        ], $this->currentToken->line);
    }

    /**
     * RDP: Parse accessor declaration
     */
    private function parseAccessorDeclaration(): ASTNode
    {
        $this->astNodes++;
        $modifiers = $this->parseModifiers();
        
        if ($this->match('GET')) {
            $this->consume('GET', "Expected 'get'");
            $body = $this->parseAccessorBody();
            
            return new ASTNode('GetAccessorDeclaration', [
                'modifiers' => $modifiers,
                'body' => $body
            ], $this->currentToken->line);
        } elseif ($this->match('SET')) {
            $this->consume('SET', "Expected 'set'");
            $body = $this->parseAccessorBody();
            
            return new ASTNode('SetAccessorDeclaration', [
                'modifiers' => $modifiers,
                'body' => $body
            ], $this->currentToken->line);
        }
        
        throw new Exception("Expected 'get' or 'set' accessor");
    }

    /**
     * RDP: Parse accessor body
     */
    private function parseAccessorBody(): ASTNode
    {
        $this->astNodes++;
        
        if ($this->match('SEMICOLON')) {
            $this->advance();
            return new ASTNode('AccessorBody', ['statements' => []], $this->currentToken->line);
        } else {
            return $this->parseBlockStatement();
        }
    }

    /**
     * RDP: Parse event declaration
     */
    private function parseEventDeclaration(array $modifiers): ASTNode
    {
        $this->astNodes++;
        $this->consume('EVENT', "Expected 'event'");
        
        $type = $this->parseType();
        $identifier = $this->parseIdentifier();
        
        if ($this->match('LBRACE')) {
            // Event with accessors
            $accessors = [];
            $this->consume('LBRACE', "Expected '{'");
            
            while (!$this->match('RBRACE') && $this->currentToken->type !== 'EOF') {
                $accessors[] = $this->parseEventAccessorDeclaration();
            }
            
            $this->consume('RBRACE', "Expected '}'");
            
            return new ASTNode('EventDeclaration', [
                'modifiers' => $modifiers,
                'type' => $type,
                'identifier' => $identifier,
                'accessors' => $accessors
            ], $this->currentToken->line);
        } else {
            // Field-like event
            $this->consume('SEMICOLON', "Expected ';'");
            
            return new ASTNode('EventFieldDeclaration', [
                'modifiers' => $modifiers,
                'type' => $type,
                'identifier' => $identifier
            ], $this->currentToken->line);
        }
    }

    /**
     * RDP: Parse event accessor declaration
     */
    private function parseEventAccessorDeclaration(): ASTNode
    {
        $this->astNodes++;
        $modifiers = $this->parseModifiers();
        
        if ($this->match('ADD')) {
            $this->consume('ADD', "Expected 'add'");
            $body = $this->parseBlockStatement();
            
            return new ASTNode('AddAccessorDeclaration', [
                'modifiers' => $modifiers,
                'body' => $body
            ], $this->currentToken->line);
        } elseif ($this->match('REMOVE')) {
            $this->consume('REMOVE', "Expected 'remove'");
            $body = $this->parseBlockStatement();
            
            return new ASTNode('RemoveAccessorDeclaration', [
                'modifiers' => $modifiers,
                'body' => $body
            ], $this->currentToken->line);
        }
        
        throw new Exception("Expected 'add' or 'remove' accessor");
    }

    /**
     * RDP: Parse struct member
     */
    private function parseStructMember(): ASTNode
    {
        $this->astNodes++;
        
        $modifiers = $this->parseModifiers();
        
        if ($this->match('CONSTRUCTOR') || $this->match('IDENTIFIER')) {
            return $this->parseConstructorDeclaration($modifiers);
        } else {
            return $this->parseMethodDeclaration($modifiers);
        }
    }

    /**
     * RDP: Parse interface member
     */
    private function parseInterfaceMember(): ASTNode
    {
        $this->astNodes++;
        
        if ($this->match('EVENT')) {
            $modifiers = $this->parseModifiers();
            return $this->parseEventDeclaration($modifiers);
        } else {
            return $this->parseMethodDeclaration([]);
        }
    }

    /**
     * RDP: Parse enum member
     */
    private function parseEnumMember(): ASTNode
    {
        $this->astNodes++;
        $identifier = $this->parseIdentifier();
        $value = null;
        
        if ($this->match('ASSIGN')) {
            $this->advance();
            $value = $this->parseExpression();
        }
        
        return new ASTNode('EnumMemberDeclaration', [
            'identifier' => $identifier,
            'value' => $value
        ], $this->currentToken->line);
    }

    /**
     * RDP: Parse parameter list
     */
    private function parseParameterList(): array
    {
        $this->consume('LPAREN', "Expected '('");
        $parameters = [];
        
        if (!$this->match('RPAREN')) {
            do {
                $parameters[] = $this->parseParameter();
            } while ($this->match('COMMA') && $this->advance());
        }
        
        $this->consume('RPAREN', "Expected ')'");
        
        return $parameters;
    }

    /**
     * RDP: Parse parameter
     */
    private function parseParameter(): ASTNode
    {
        $this->astNodes++;
        $modifiers = $this->parseParameterModifiers();
        $type = $this->parseType();
        $identifier = $this->parseIdentifier();
        $defaultValue = null;
        
        if ($this->match('ASSIGN')) {
            $this->advance();
            $defaultValue = $this->parseExpression();
        }
        
        return new ASTNode('Parameter', [
            'modifiers' => $modifiers,
            'type' => $type,
            'identifier' => $identifier,
            'defaultValue' => $defaultValue
        ], $this->currentToken->line);
    }

    /**
     * RDP: Parse parameter modifiers
     */
    private function parseParameterModifiers(): array
    {
        $modifiers = [];
        
        while ($this->match('REF') || $this->match('OUT') || $this->match('PARAMS')) {
            $modifiers[] = $this->currentToken->value;
            $this->advance();
        }
        
        return $modifiers;
    }

    /**
     * RDP: Parse constructor initializer
     */
    private function parseConstructorInitializer(): ?ASTNode
    {
        if (!$this->match('COLON')) {
            return null;
        }
        
        $this->consume('COLON', "Expected ':'");
        
        if ($this->match('BASE')) {
            $this->consume('BASE', "Expected 'base'");
        } elseif ($this->match('THIS')) {
            $this->consume('THIS', "Expected 'this'");
        } else {
            throw new Exception("Expected 'base' or 'this'");
        }
        
        $arguments = $this->parseArgumentList();
        
        return new ASTNode('ConstructorInitializer', [
            'arguments' => $arguments
        ], $this->currentToken->line);
    }

    /**
     * RDP: Parse type parameter constraints
     */
    private function parseTypeParameterConstraints(): array
    {
        $constraints = [];
        
        while ($this->match('WHERE')) {
            $this->advance();
            $constraints[] = $this->parseTypeParameterConstraint();
        }
        
        return $constraints;
    }

    /**
     * RDP: Parse argument list
     */
    private function parseArgumentList(): array
    {
        $this->consume('LPAREN', "Expected '('");
        $arguments = [];
        
        if (!$this->match('RPAREN')) {
            do {
                $arguments[] = $this->parseArgument();
            } while ($this->match('COMMA') && $this->advance());
        }
        
        $this->consume('RPAREN', "Expected ')'");
        
        return $arguments;
    }

    /**
     * RDP: Parse argument
     */
    private function parseArgument(): ASTNode
    {
        $this->astNodes++;
        $expression = $this->parseExpression();
        
        return new ASTNode('Argument', [
            'expression' => $expression
        ], $this->currentToken->line);
    }

    /**
     * RDP: Parse block statement
     */
    private function parseBlockStatement(): ASTNode
    {
        $this->astNodes++;
        $this->consume('LBRACE', "Expected '{'");
        
        $statements = [];
        while (!$this->match('RBRACE') && $this->currentToken->type !== 'EOF') {
            $statement = $this->parseStatement();
            if ($statement) {
                $statements[] = $statement;
            }
        }
        
        $this->consume('RBRACE', "Expected '}'");
        
        return new ASTNode('BlockStatement', [
            'statements' => $statements
        ], $this->currentToken->line);
    }

    /**
     * RDP: Parse statement
     */
    private function parseStatement(): ?ASTNode
    {
        $this->astNodes++;
        
        switch ($this->currentToken->type) {
            case 'VAR':
                return $this->parseVariableDeclaration();
            case 'IF':
                return $this->parseIfStatement();
            case 'WHILE':
                return $this->parseWhileStatement();
            case 'FOR':
                return $this->parseForStatement();
            case 'FOREACH':
                return $this->parseForeachStatement();
            case 'DO':
                return $this->parseDoStatement();
            case 'SWITCH':
                return $this->parseSwitchStatement();
            case 'RETURN':
                return $this->parseReturnStatement();
            case 'THROW':
                return $this->parseThrowStatement();
            case 'TRY':
                return $this->parseTryStatement();
            case 'BREAK':
                return $this->parseBreakStatement();
            case 'CONTINUE':
                return $this->parseContinueStatement();
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
        $this->consume('VAR', "Expected 'var'");
        
        $identifier = $this->parseIdentifier();
        $type = null;
        $initializer = null;
        
        if ($this->match('ASSIGN')) {
            $this->advance();
            $initializer = $this->parseExpression();
        }
        
        $this->consume('SEMICOLON', "Expected ';' after variable declaration");
        
        return new ASTNode('VariableDeclaration', [
            'identifier' => $identifier,
            'type' => $type,
            'initializer' => $initializer
        ], $this->currentToken->line);
    }

    /**
     * RDP: Parse if statement
     */
    private function parseIfStatement(): ASTNode
    {
        $this->astNodes++;
        $this->consume('IF', "Expected 'if'");
        $this->consume('LPAREN', "Expected '(' after 'if'");
        
        $condition = $this->parseExpression();
        $this->consume('RPAREN', "Expected ')' after if condition");
        
        $consequent = $this->parseStatement();
        $alternate = null;
        
        if ($this->match('ELSE')) {
            $this->advance();
            $alternate = $this->parseStatement();
        }
        
        return new ASTNode('IfStatement', [
            'condition' => $condition,
            'consequent' => $consequent,
            'alternate' => $alternate
        ], $this->currentToken->line);
    }

    /**
     * RDP: Parse while statement
     */
    private function parseWhileStatement(): ASTNode
    {
        $this->astNodes++;
        $this->consume('WHILE', "Expected 'while'");
        $this->consume('LPAREN', "Expected '(' after 'while'");
        
        $condition = $this->parseExpression();
        $this->consume('RPAREN', "Expected ')' after while condition");
        
        $body = $this->parseStatement();
        
        return new ASTNode('WhileStatement', [
            'condition' => $condition,
            'body' => $body
        ], $this->currentToken->line);
    }

    /**
     * RDP: Parse for statement
     */
    private function parseForStatement(): ASTNode
    {
        $this->astNodes++;
        $this->consume('FOR', "Expected 'for'");
        $this->consume('LPAREN', "Expected '(' after 'for'");
        
        $initializer = null;
        $condition = null;
        $increment = null;
        
        // Parse initializer
        if (!$this->match('SEMICOLON')) {
            $initializer = $this->parseExpression();
        }
        $this->consume('SEMICOLON', "Expected ';' after for loop initializer");
        
        // Parse condition
        if (!$this->match('SEMICOLON')) {
            $condition = $this->parseExpression();
        }
        $this->consume('SEMICOLON', "Expected ';' after for loop condition");
        
        // Parse increment
        if (!$this->match('RPAREN')) {
            $increment = $this->parseExpression();
        }
        $this->consume('RPAREN', "Expected ')' after for loop increment");
        
        $body = $this->parseStatement();
        
        return new ASTNode('ForStatement', [
            'initializer' => $initializer,
            'condition' => $condition,
            'increment' => $increment,
            'body' => $body
        ], $this->currentToken->line);
    }

    /**
     * RDP: Parse foreach statement
     */
    private function parseForeachStatement(): ASTNode
    {
        $this->astNodes++;
        $this->consume('FOREACH', "Expected 'foreach'");
        $this->consume('LPAREN', "Expected '(' after 'foreach'");
        
        $type = $this->parseType();
        $identifier = $this->parseIdentifier();
        $this->consume('IN', "Expected 'in'");
        $expression = $this->parseExpression();
        
        $this->consume('RPAREN', "Expected ')' after foreach");
        $body = $this->parseStatement();
        
        return new ASTNode('ForeachStatement', [
            'type' => $type,
            'identifier' => $identifier,
            'expression' => $expression,
            'body' => $body
        ], $this->currentToken->line);
    }

    /**
     * RDP: Parse do statement
     */
    private function parseDoStatement(): ASTNode
    {
        $this->astNodes++;
        $this->consume('DO', "Expected 'do'");
        
        $body = $this->parseStatement();
        $this->consume('WHILE', "Expected 'while'");
        $this->consume('LPAREN', "Expected '(' after 'while'");
        
        $condition = $this->parseExpression();
        $this->consume('RPAREN', "Expected ')' after while condition");
        $this->consume('SEMICOLON', "Expected ';' after do-while");
        
        return new ASTNode('DoStatement', [
            'body' => $body,
            'condition' => $condition
        ], $this->currentToken->line);
    }

    /**
     * RDP: Parse switch statement
     */
    private function parseSwitchStatement(): ASTNode
    {
        $this->astNodes++;
        $this->consume('SWITCH', "Expected 'switch'");
        $this->consume('LPAREN', "Expected '(' after 'switch'");
        
        $expression = $this->parseExpression();
        $this->consume('RPAREN', "Expected ')' after switch expression");
        $this->consume('LBRACE', "Expected '{' after switch");
        
        $cases = [];
        while (!$this->match('RBRACE') && $this->currentToken->type !== 'EOF') {
            $cases[] = $this->parseSwitchCase();
        }
        
        $this->consume('RBRACE', "Expected '}' after switch");
        
        return new ASTNode('SwitchStatement', [
            'expression' => $expression,
            'cases' => $cases
        ], $this->currentToken->line);
    }

    /**
     * RDP: Parse switch case
     */
    private function parseSwitchCase(): ASTNode
    {
        $this->astNodes++;
        
        if ($this->match('CASE')) {
            $this->consume('CASE', "Expected 'case'");
            $value = $this->parseExpression();
            $this->consume('COLON', "Expected ':' after case value");
            
            $statements = [];
            while (!$this->match('CASE') && !$this->match('DEFAULT') && 
                   !$this->match('RBRACE') && $this->currentToken->type !== 'EOF') {
                $statements[] = $this->parseStatement();
            }
            
            return new ASTNode('SwitchCase', [
                'value' => $value,
                'statements' => $statements
            ], $this->currentToken->line);
        } elseif ($this->match('DEFAULT')) {
            $this->consume('DEFAULT', "Expected 'default'");
            $this->consume('COLON', "Expected ':' after default");
            
            $statements = [];
            while (!$this->match('CASE') && !$this->match('DEFAULT') && 
                   !$this->match('RBRACE') && $this->currentToken->type !== 'EOF') {
                $statements[] = $this->parseStatement();
            }
            
            return new ASTNode('SwitchDefault', [
                'statements' => $statements
            ], $this->currentToken->line);
        }
        
        throw new Exception("Expected 'case' or 'default'");
    }

    /**
     * RDP: Parse return statement
     */
    private function parseReturnStatement(): ASTNode
    {
        $this->astNodes++;
        $this->consume('RETURN', "Expected 'return'");
        
        $expression = null;
        if (!$this->match('SEMICOLON') && $this->currentToken->type !== 'EOF') {
            $expression = $this->parseExpression();
        }
        
        $this->consume('SEMICOLON', "Expected ';' after return statement");
        
        return new ASTNode('ReturnStatement', [
            'expression' => $expression
        ], $this->currentToken->line);
    }

    /**
     * RDP: Parse throw statement
     */
    private function parseThrowStatement(): ASTNode
    {
        $this->astNodes++;
        $this->consume('THROW', "Expected 'throw'");
        
        $expression = null;
        if (!$this->match('SEMICOLON') && $this->currentToken->type !== 'EOF') {
            $expression = $this->parseExpression();
        }
        
        $this->consume('SEMICOLON', "Expected ';' after throw statement");
        
        return new ASTNode('ThrowStatement', [
            'expression' => $expression
        ], $this->currentToken->line);
    }

    /**
     * RDP: Parse try statement
     */
    private function parseTryStatement(): ASTNode
    {
        $this->astNodes++;
        $this->consume('TRY', "Expected 'try'");
        
        $block = $this->parseBlockStatement();
        $catches = [];
        $finally = null;
        
        while ($this->match('CATCH')) {
            $catches[] = $this->parseCatchClause();
        }
        
        if ($this->match('FINALLY')) {
            $this->advance();
            $finally = $this->parseBlockStatement();
        }
        
        return new ASTNode('TryStatement', [
            'block' => $block,
            'catches' => $catches,
            'finally' => $finally
        ], $this->currentToken->line);
    }

    /**
     * RDP: Parse catch clause
     */
    private function parseCatchClause(): ASTNode
    {
        $this->astNodes++;
        $this->consume('CATCH', "Expected 'catch'");
        
        $type = null;
        $identifier = null;
        
        if ($this->match('LPAREN')) {
            $this->consume('LPAREN', "Expected '('");
            $type = $this->parseType();
            $identifier = $this->parseIdentifier();
            $this->consume('RPAREN', "Expected ')'");
        }
        
        $body = $this->parseBlockStatement();
        
        return new ASTNode('CatchClause', [
            'type' => $type,
            'identifier' => $identifier,
            'body' => $body
        ], $this->currentToken->line);
    }

    /**
     * RDP: Parse break statement
     */
    private function parseBreakStatement(): ASTNode
    {
        $this->astNodes++;
        $this->consume('BREAK', "Expected 'break'");
        $this->consume('SEMICOLON', "Expected ';' after break statement");
        
        return new ASTNode('BreakStatement', [], $this->currentToken->line);
    }

    /**
     * RDP: Parse continue statement
     */
    private function parseContinueStatement(): ASTNode
    {
        $this->astNodes++;
        $this->consume('CONTINUE', "Expected 'continue'");
        $this->consume('SEMICOLON', "Expected ';' after continue statement");
        
        return new ASTNode('ContinueStatement', [], $this->currentToken->line);
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
        ], $this->currentToken->line);
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
        $left = $this->parseConditionalExpression();
        
        if ($this->isAssignmentOperator($this->currentToken->type)) {
            $operator = $this->currentToken->type;
            $this->advance();
            $right = $this->parseAssignmentExpression();
            
            return new ASTNode('AssignmentExpression', [
                'operator' => $operator,
                'left' => $left,
                'right' => $right
            ], $this->currentToken->line);
        }
        
        return $left;
    }

    /**
     * RDP: Parse conditional expression
     */
    private function parseConditionalExpression(): ASTNode
    {
        $this->astNodes++;
        $test = $this->parseLogicalOrExpression();
        
        if ($this->match('QUESTION')) {
            $this->advance();
            $consequent = $this->parseExpression();
            $this->consume('COLON', "Expected ':' in conditional expression");
            $alternate = $this->parseConditionalExpression();
            
            return new ASTNode('ConditionalExpression', [
                'test' => $test,
                'consequent' => $consequent,
                'alternate' => $alternate
            ], $this->currentToken->line);
        }
        
        return $test;
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
                'operator' => $operator,
                'left' => $left,
                'right' => $right
            ], $this->currentToken->line);
        }
        
        return $left;
    }

    /**
     * RDP: Parse logical AND expression
     */
    private function parseLogicalAndExpression(): ASTNode
    {
        $this->astNodes++;
        $left = $this->parseBitwiseOrExpression();
        
        while ($this->match('LOGICAL_AND')) {
            $operator = $this->currentToken->type;
            $this->advance();
            $right = $this->parseBitwiseOrExpression();
            
            $left = new ASTNode('LogicalExpression', [
                'operator' => $operator,
                'left' => $left,
                'right' => $right
            ], $this->currentToken->line);
        }
        
        return $left;
    }

    /**
     * RDP: Parse bitwise OR expression
     */
    private function parseBitwiseOrExpression(): ASTNode
    {
        $this->astNodes++;
        $left = $this->parseBitwiseXorExpression();
        
        while ($this->match('BITWISE_OR')) {
            $operator = $this->currentToken->type;
            $this->advance();
            $right = $this->parseBitwiseXorExpression();
            
            $left = new ASTNode('BinaryExpression', [
                'operator' => $operator,
                'left' => $left,
                'right' => $right
            ], $this->currentToken->line);
        }
        
        return $left;
    }

    /**
     * RDP: Parse bitwise XOR expression
     */
    private function parseBitwiseXorExpression(): ASTNode
    {
        $this->astNodes++;
        $left = $this->parseBitwiseAndExpression();
        
        while ($this->match('BITWISE_XOR')) {
            $operator = $this->currentToken->type;
            $this->advance();
            $right = $this->parseBitwiseAndExpression();
            
            $left = new ASTNode('BinaryExpression', [
                'operator' => $operator,
                'left' => $left,
                'right' => $right
            ], $this->currentToken->line);
        }
        
        return $left;
    }

    /**
     * RDP: Parse bitwise AND expression
     */
    private function parseBitwiseAndExpression(): ASTNode
    {
        $this->astNodes++;
        $left = $this->parseEqualityExpression();
        
        while ($this->match('BITWISE_AND')) {
            $operator = $this->currentToken->type;
            $this->advance();
            $right = $this->parseEqualityExpression();
            
            $left = new ASTNode('BinaryExpression', [
                'operator' => $operator,
                'left' => $left,
                'right' => $right
            ], $this->currentToken->line);
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
        
        while ($this->match('EQUAL') || $this->match('NOT_EQUAL')) {
            $operator = $this->currentToken->type;
            $this->advance();
            $right = $this->parseRelationalExpression();
            
            $left = new ASTNode('BinaryExpression', [
                'operator' => $operator,
                'left' => $left,
                'right' => $right
            ], $this->currentToken->line);
        }
        
        return $left;
    }

    /**
     * RDP: Parse relational expression
     */
    private function parseRelationalExpression(): ASTNode
    {
        $this->astNodes++;
        $left = $this->parseShiftExpression();
        
        while ($this->match('LESS') || $this->match('GREATER') || 
               $this->match('LESS_EQUAL') || $this->match('GREATER_EQUAL')) {
            $operator = $this->currentToken->type;
            $this->advance();
            $right = $this->parseShiftExpression();
            
            $left = new ASTNode('BinaryExpression', [
                'operator' => $operator,
                'left' => $left,
                'right' => $right
            ], $this->currentToken->line);
        }
        
        return $left;
    }

    /**
     * RDP: Parse shift expression
     */
    private function parseShiftExpression(): ASTNode
    {
        $this->astNodes++;
        $left = $this->parseAdditiveExpression();
        
        while ($this->match('LEFT_SHIFT') || $this->match('RIGHT_SHIFT')) {
            $operator = $this->currentToken->type;
            $this->advance();
            $right = $this->parseAdditiveExpression();
            
            $left = new ASTNode('BinaryExpression', [
                'operator' => $operator,
                'left' => $left,
                'right' => $right
            ], $this->currentToken->line);
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
                'operator' => $operator,
                'left' => $left,
                'right' => $right
            ], $this->currentToken->line);
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
                'operator' => $operator,
                'left' => $left,
                'right' => $right
            ], $this->currentToken->line);
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
            $this->match('INCREMENT') || $this->match('DECREMENT') || $this->match('BITWISE_NOT')) {
            $operator = $this->currentToken->type;
            $this->advance();
            $operand = $this->parseUnaryExpression();
            
            return new ASTNode('UnaryExpression', [
                'operator' => $operator,
                'operand' => $operand
            ], $this->currentToken->line);
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
                ], $this->currentToken->line);
                $this->advance();
                return $node;
                
            case 'NUMBER':
                $node = new ASTNode('Literal', [
                    'value' => (float) $this->currentToken->value
                ], $this->currentToken->line);
                $this->advance();
                return $node;
                
            case 'STRING':
                $node = new ASTNode('Literal', [
                    'value' => $this->currentToken->value
                ], $this->currentToken->line);
                $this->advance();
                return $node;
                
            case 'TRUE':
                $node = new ASTNode('Literal', ['value' => true], $this->currentToken->line);
                $this->advance();
                return $node;
                
            case 'FALSE':
                $node = new ASTNode('Literal', ['value' => false], $this->currentToken->line);
                $this->advance();
                return $node;
                
            case 'NULL':
                $node = new ASTNode('Literal', ['value' => null], $this->currentToken->line);
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
        ], $this->currentToken->line);
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
        ], $this->currentToken->line);
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
            'key' => $key,
            'value' => $value
        ], $this->currentToken->line);
    }

    /**
     * RDP: Check if token is assignment operator
     */
    private function isAssignmentOperator(string $type): bool
    {
        return in_array($type, [
            'ASSIGN', 'PLUS_ASSIGN', 'MINUS_ASSIGN', 
            'MULTIPLY_ASSIGN', 'DIVIDE_ASSIGN', 'MODULO_ASSIGN',
            'AND_ASSIGN', 'OR_ASSIGN', 'XOR_ASSIGN',
            'LEFT_SHIFT_ASSIGN', 'RIGHT_SHIFT_ASSIGN', 'NULL_COALESCE_ASSIGN'
        ]);
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
}
