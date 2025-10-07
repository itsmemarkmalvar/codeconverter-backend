<?php

namespace App\Services\RDP;

/**
 * C# to JavaScript AST Mapper
 * 
 * Maps C# AST nodes to equivalent JavaScript constructs
 * for the thesis research on RDP effectiveness.
 */
class CSharpToJavaScriptMapper
{
    /**
     * Map C# AST to JavaScript code
     */
    public function mapASTToJavaScript($ast): string
    {
        if (!$ast || !isset($ast['type'])) {
            return '';
        }

        switch ($ast['type']) {
            case 'CompilationUnit':
                return $this->mapCompilationUnit($ast);
            case 'ClassDeclaration':
                return $this->mapClassDeclaration($ast);
            case 'MethodDeclaration':
                return $this->mapMethodDeclaration($ast);
            case 'VariableDeclaration':
                return $this->mapVariableDeclaration($ast);
            case 'ExpressionStatement':
                return $this->mapExpressionStatement($ast);
            case 'InvocationExpression':
                return $this->mapInvocationExpression($ast);
            case 'IdentifierName':
                return $this->mapIdentifierName($ast);
            case 'LiteralExpression':
                return $this->mapLiteralExpression($ast);
            case 'BinaryExpression':
                return $this->mapBinaryExpression($ast);
            case 'ReturnStatement':
                return $this->mapReturnStatement($ast);
            case 'Block':
                return $this->mapBlock($ast);
            default:
                return $this->mapGenericNode($ast);
        }
    }

    /**
     * Map CompilationUnit node
     */
    private function mapCompilationUnit($ast): string
    {
        $javascriptCode = "";

        if (isset($ast['members']) && is_array($ast['members'])) {
            foreach ($ast['members'] as $member) {
                $mapped = $this->mapASTToJavaScript($member);
                if ($mapped) {
                    $javascriptCode .= $mapped . "\n";
                }
            }
        }

        return $javascriptCode;
    }

    /**
     * Map ClassDeclaration node
     */
    private function mapClassDeclaration($ast): string
    {
        $name = $ast['identifier']['value'] ?? 'Class';
        $members = $ast['members'] ?? [];

        $javascriptCode = "// Class: " . $name . "\n";

        foreach ($members as $member) {
            $mapped = $this->mapASTToJavaScript($member);
            if ($mapped) {
                $javascriptCode .= $mapped . "\n";
            }
        }

        return $javascriptCode;
    }

    /**
     * Map MethodDeclaration node
     */
    private function mapMethodDeclaration($ast): string
    {
        $name = $ast['identifier']['value'] ?? 'method';
        $parameters = $ast['parameterList']['parameters'] ?? [];
        $body = $ast['body'] ?? null;

        $javascriptCode = "function " . $name . "(";
        
        // Map parameters
        $paramList = [];
        foreach ($parameters as $param) {
            if (isset($param['identifier']['value'])) {
                $paramList[] = $param['identifier']['value'];
            }
        }
        $javascriptCode .= implode(', ', $paramList) . ")\n";
        $javascriptCode .= "{\n";

        // Map method body
        if ($body && isset($body['statements'])) {
            foreach ($body['statements'] as $statement) {
                $mapped = $this->mapASTToJavaScript($statement);
                if ($mapped) {
                    $javascriptCode .= "    " . $mapped . "\n";
                }
            }
        }

        $javascriptCode .= "}\n";

        return $javascriptCode;
    }

    /**
     * Map VariableDeclaration node
     */
    private function mapVariableDeclaration($ast): string
    {
        $declarators = $ast['declarators'] ?? [];
        $javascriptCode = [];

        foreach ($declarators as $declarator) {
            $name = $declarator['identifier']['value'] ?? 'variable';
            $initializer = $declarator['initializer'] ?? null;

            if ($initializer) {
                $value = $this->mapASTToJavaScript($initializer);
                $javascriptCode[] = "let " . $name . " = " . $value . ";";
            } else {
                $javascriptCode[] = "let " . $name . ";";
            }
        }

        return implode("\n    ", $javascriptCode);
    }

    /**
     * Map ExpressionStatement node
     */
    private function mapExpressionStatement($ast): string
    {
        if (isset($ast['expression'])) {
            return $this->mapASTToJavaScript($ast['expression']) . ";";
        }
        return "";
    }

    /**
     * Map InvocationExpression node
     */
    private function mapInvocationExpression($ast): string
    {
        $expression = $ast['expression'] ?? null;
        $arguments = $ast['argumentList']['arguments'] ?? [];

        if (!$expression) {
            return "";
        }

        $functionName = $this->mapASTToJavaScript($expression);
        
        // Map Console.WriteLine to console.log
        if ($functionName === 'Console.WriteLine') {
            $functionName = 'console.log';
        }

        $argList = [];
        foreach ($arguments as $arg) {
            $argList[] = $this->mapASTToJavaScript($arg);
        }

        return $functionName . "(" . implode(', ', $argList) . ")";
    }

    /**
     * Map IdentifierName node
     */
    private function mapIdentifierName($ast): string
    {
        return $ast['value'] ?? '';
    }

    /**
     * Map LiteralExpression node
     */
    private function mapLiteralExpression($ast): string
    {
        $value = $ast['value'] ?? '';
        
        if (is_string($value)) {
            return '"' . addslashes($value) . '"';
        }
        
        return (string)$value;
    }

    /**
     * Map BinaryExpression node
     */
    private function mapBinaryExpression($ast): string
    {
        $left = $this->mapASTToJavaScript($ast['left'] ?? null);
        $operator = $ast['operator'] ?? '';
        $right = $this->mapASTToJavaScript($ast['right'] ?? null);

        // Map C# operators to JavaScript equivalents
        $operatorMap = [
            '+' => '+',
            '-' => '-',
            '*' => '*',
            '/' => '/',
            '==' => '==',
            '!=' => '!=',
            '&&' => '&&',
            '||' => '||'
        ];

        $jsOperator = $operatorMap[$operator] ?? $operator;

        return $left . " " . $jsOperator . " " . $right;
    }

    /**
     * Map ReturnStatement node
     */
    private function mapReturnStatement($ast): string
    {
        if (isset($ast['expression'])) {
            return "return " . $this->mapASTToJavaScript($ast['expression']) . ";";
        }
        return "return;";
    }

    /**
     * Map Block node
     */
    private function mapBlock($ast): string
    {
        $statements = $ast['statements'] ?? [];
        $javascriptCode = [];

        foreach ($statements as $statement) {
            $mapped = $this->mapASTToJavaScript($statement);
            if ($mapped) {
                $javascriptCode[] = $mapped;
            }
        }

        return implode("\n    ", $javascriptCode);
    }

    /**
     * Map generic node (fallback)
     */
    private function mapGenericNode($ast): string
    {
        // For thesis demonstration, provide basic mapping
        if (isset($ast['type'])) {
            return "// Mapped " . $ast['type'] . " node";
        }
        return "";
    }
}
