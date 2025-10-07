<?php

namespace App\Services\RDP;

/**
 * JavaScript to C# AST Mapper
 * 
 * Maps JavaScript AST nodes to equivalent C# constructs
 * for the thesis research on RDP effectiveness.
 */
class JavaScriptToCSharpMapper
{
    /**
     * Map JavaScript AST to C# code
     */
    public function mapASTToCSharp($ast): string
    {
        if (!$ast || !isset($ast['type'])) {
            return '';
        }

        switch ($ast['type']) {
            case 'Program':
                return $this->mapProgram($ast);
            case 'FunctionDeclaration':
                return $this->mapFunctionDeclaration($ast);
            case 'VariableDeclaration':
                return $this->mapVariableDeclaration($ast);
            case 'ExpressionStatement':
                return $this->mapExpressionStatement($ast);
            case 'CallExpression':
                return $this->mapCallExpression($ast);
            case 'MemberExpression':
                return $this->mapMemberExpression($ast);
            case 'Identifier':
                return $this->mapIdentifier($ast);
            case 'Literal':
                return $this->mapLiteral($ast);
            case 'BinaryExpression':
                return $this->mapBinaryExpression($ast);
            case 'ReturnStatement':
                return $this->mapReturnStatement($ast);
            case 'BlockStatement':
                return $this->mapBlockStatement($ast);
            default:
                return $this->mapGenericNode($ast);
        }
    }

    /**
     * Map Program node
     */
    private function mapProgram($ast): string
    {
        $csharpCode = "using System;\n\n";
        $csharpCode .= "public class Program\n";
        $csharpCode .= "{\n";
        $csharpCode .= "    public static void Main(string[] args)\n";
        $csharpCode .= "    {\n";

        if (isset($ast['body']) && is_array($ast['body'])) {
            foreach ($ast['body'] as $statement) {
                $mapped = $this->mapASTToCSharp($statement);
                if ($mapped) {
                    $csharpCode .= "        " . $mapped . "\n";
                }
            }
        }

        $csharpCode .= "    }\n";
        $csharpCode .= "}\n";

        return $csharpCode;
    }

    /**
     * Map FunctionDeclaration node
     */
    private function mapFunctionDeclaration($ast): string
    {
        $name = $ast['name'] ?? 'Function';
        $params = $ast['params'] ?? [];
        $body = $ast['body'] ?? null;

        $csharpCode = "    public static void " . $name . "(";
        
        // Map parameters
        $paramList = [];
        foreach ($params as $param) {
            if (isset($param['name'])) {
                $paramList[] = "string " . $param['name'];
            }
        }
        $csharpCode .= implode(', ', $paramList) . ")\n";
        $csharpCode .= "    {\n";

        // Map function body
        if ($body && isset($body['body'])) {
            foreach ($body['body'] as $statement) {
                $mapped = $this->mapASTToCSharp($statement);
                if ($mapped) {
                    $csharpCode .= "        " . $mapped . "\n";
                }
            }
        }

        $csharpCode .= "    }\n";

        return $csharpCode;
    }

    /**
     * Map VariableDeclaration node
     */
    private function mapVariableDeclaration($ast): string
    {
        $declarations = $ast['declarations'] ?? [];
        $csharpCode = [];

        foreach ($declarations as $declaration) {
            $id = $declaration['id']['name'] ?? 'variable';
            $init = $declaration['init'] ?? null;

            if ($init) {
                $value = $this->mapASTToCSharp($init);
                $csharpCode[] = "string " . $id . " = " . $value . ";";
            } else {
                $csharpCode[] = "string " . $id . ";";
            }
        }

        return implode("\n        ", $csharpCode);
    }

    /**
     * Map ExpressionStatement node
     */
    private function mapExpressionStatement($ast): string
    {
        if (isset($ast['expression'])) {
            return $this->mapASTToCSharp($ast['expression']) . ";";
        }
        return "";
    }

    /**
     * Map CallExpression node
     */
    private function mapCallExpression($ast): string
    {
        $callee = $ast['callee'] ?? null;
        $arguments = $ast['arguments'] ?? [];

        if (!$callee) {
            return "";
        }

        $functionName = $this->mapASTToCSharp($callee);
        
        // Map console.log to Console.WriteLine
        if ($functionName === 'console.log') {
            $functionName = 'Console.WriteLine';
        }

        $argList = [];
        foreach ($arguments as $arg) {
            $argList[] = $this->mapASTToCSharp($arg);
        }

        return $functionName . "(" . implode(', ', $argList) . ")";
    }

    /**
     * Map MemberExpression node (e.g., console.log)
     */
    private function mapMemberExpression($ast): string
    {
        $object = $ast['object'] ?? null;
        $property = $ast['property'] ?? null;

        if (!$object || !$property) {
            return "";
        }

        $objectName = $this->mapASTToCSharp($object);
        $propertyName = $this->mapASTToCSharp($property);

        // Map console.log to Console.WriteLine
        if ($objectName === 'console' && $propertyName === 'log') {
            return 'Console.WriteLine';
        }

        return $objectName . "." . $propertyName;
    }

    /**
     * Map Identifier node
     */
    private function mapIdentifier($ast): string
    {
        return $ast['name'] ?? '';
    }

    /**
     * Map Literal node
     */
    private function mapLiteral($ast): string
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
        $left = $this->mapASTToCSharp($ast['left'] ?? null);
        $operator = $ast['operator'] ?? '';
        $right = $this->mapASTToCSharp($ast['right'] ?? null);

        // Map JavaScript operators to C# equivalents
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

        $csharpOperator = $operatorMap[$operator] ?? $operator;

        return $left . " " . $csharpOperator . " " . $right;
    }

    /**
     * Map ReturnStatement node
     */
    private function mapReturnStatement($ast): string
    {
        if (isset($ast['argument'])) {
            return "return " . $this->mapASTToCSharp($ast['argument']) . ";";
        }
        return "return;";
    }

    /**
     * Map BlockStatement node
     */
    private function mapBlockStatement($ast): string
    {
        $statements = $ast['body'] ?? [];
        $csharpCode = [];

        foreach ($statements as $statement) {
            $mapped = $this->mapASTToCSharp($statement);
            if ($mapped) {
                $csharpCode[] = $mapped;
            }
        }

        return implode("\n        ", $csharpCode);
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
