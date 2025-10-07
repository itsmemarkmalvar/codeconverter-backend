<?php

namespace App\Services\Execution;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Exception;

/**
 * Code Execution Service
 * 
 * This service handles the execution of JavaScript and C# code
 * using local compilers and runtime environments for thesis research.
 */
class CodeExecutionService
{
    private string $tempDir;
    private array $executionMetrics = [];

    public function __construct()
    {
        $this->tempDir = sys_get_temp_dir() . '/code-converter-' . uniqid();
        $this->createTempDirectory();
    }

    /**
     * Execute JavaScript code
     */
    public function executeJavaScript(string $code): array
    {
        try {
            $startTime = microtime(true);
            $startMemory = memory_get_usage();

            // Create temporary JavaScript file
            $jsFile = $this->tempDir . '/script.js';
            file_put_contents($jsFile, $code);

            // Execute using Node.js
            $process = new Process(['node', $jsFile]);
            $process->setTimeout(30); // 30 seconds timeout
            $process->run();

            $endTime = microtime(true);
            $endMemory = memory_get_usage();

            $output = $process->getOutput();
            $errorOutput = $process->getErrorOutput();
            $exitCode = $process->getExitCode();

            $success = $exitCode === 0 && empty($errorOutput);

            return [
                'success' => $success,
                'language' => 'javascript',
                'output' => $output,
                'error' => $errorOutput,
                'exitCode' => $exitCode,
                'executionTime' => ($endTime - $startTime) * 1000, // Convert to milliseconds
                'memoryUsage' => $endMemory - $startMemory,
                'metrics' => $this->calculateExecutionMetrics($startTime, $startMemory, $success)
            ];

        } catch (ProcessFailedException $e) {
            return [
                'success' => false,
                'language' => 'javascript',
                'output' => '',
                'error' => $e->getMessage(),
                'exitCode' => -1,
                'executionTime' => 0,
                'memoryUsage' => 0,
                'metrics' => $this->calculateExecutionMetrics($startTime ?? microtime(true), $startMemory ?? memory_get_usage(), false)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'language' => 'javascript',
                'output' => '',
                'error' => 'Execution failed: ' . $e->getMessage(),
                'exitCode' => -1,
                'executionTime' => 0,
                'memoryUsage' => 0,
                'metrics' => $this->calculateExecutionMetrics($startTime ?? microtime(true), $startMemory ?? memory_get_usage(), false)
            ];
        }
    }

    /**
     * Execute C# code
     */
    public function executeCSharp(string $code): array
    {
        try {
            $startTime = microtime(true);
            $startMemory = memory_get_usage();

            // Create temporary C# file
            $csFile = $this->tempDir . '/Program.cs';
            $executableName = $this->tempDir . '/Program';

            // Wrap code in a basic class structure if needed
            $wrappedCode = $this->wrapCSharpCode($code);
            file_put_contents($csFile, $wrappedCode);

            // Compile C# code
            $compileProcess = new Process(['dotnet', 'new', 'console', '--force', '--output', $this->tempDir]);
            $compileProcess->run();

            if ($compileProcess->getExitCode() !== 0) {
                throw new Exception('Failed to create C# project: ' . $compileProcess->getErrorOutput());
            }

            // Replace the generated Program.cs with our code
            file_put_contents($this->tempDir . '/Program.cs', $wrappedCode);

            // Build the project
            $buildProcess = new Process(['dotnet', 'build', $this->tempDir]);
            $buildProcess->run();

            if ($buildProcess->getExitCode() !== 0) {
                return [
                    'success' => false,
                    'language' => 'csharp',
                    'output' => '',
                    'error' => 'Compilation failed: ' . $buildProcess->getErrorOutput(),
                    'exitCode' => $buildProcess->getExitCode(),
                    'executionTime' => 0,
                    'memoryUsage' => 0,
                    'metrics' => $this->calculateExecutionMetrics($startTime, $startMemory, false)
                ];
            }

            // Execute the compiled program
            $executeProcess = new Process(['dotnet', 'run', '--project', $this->tempDir]);
            $executeProcess->setTimeout(30); // 30 seconds timeout
            $executeProcess->run();

            $endTime = microtime(true);
            $endMemory = memory_get_usage();

            $output = $executeProcess->getOutput();
            $errorOutput = $executeProcess->getErrorOutput();
            $exitCode = $executeProcess->getExitCode();

            $success = $exitCode === 0 && empty($errorOutput);

            return [
                'success' => $success,
                'language' => 'csharp',
                'output' => $output,
                'error' => $errorOutput,
                'exitCode' => $exitCode,
                'executionTime' => ($endTime - $startTime) * 1000, // Convert to milliseconds
                'memoryUsage' => $endMemory - $startMemory,
                'metrics' => $this->calculateExecutionMetrics($startTime, $startMemory, $success)
            ];

        } catch (ProcessFailedException $e) {
            return [
                'success' => false,
                'language' => 'csharp',
                'output' => '',
                'error' => $e->getMessage(),
                'exitCode' => -1,
                'executionTime' => 0,
                'memoryUsage' => 0,
                'metrics' => $this->calculateExecutionMetrics($startTime ?? microtime(true), $startMemory ?? memory_get_usage(), false)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'language' => 'csharp',
                'output' => '',
                'error' => 'Execution failed: ' . $e->getMessage(),
                'exitCode' => -1,
                'executionTime' => 0,
                'memoryUsage' => 0,
                'metrics' => $this->calculateExecutionMetrics($startTime ?? microtime(true), $startMemory ?? memory_get_usage(), false)
            ];
        }
    }

    /**
     * Compile JavaScript code (syntax check)
     */
    public function compileJavaScript(string $code): array
    {
        try {
            $startTime = microtime(true);
            $startMemory = memory_get_usage();

            // Create temporary JavaScript file
            $jsFile = $this->tempDir . '/syntax-check.js';
            file_put_contents($jsFile, $code);

            // Use Node.js to check syntax
            $process = new Process(['node', '--check', $jsFile]);
            $process->setTimeout(10); // 10 seconds timeout
            $process->run();

            $endTime = microtime(true);
            $endMemory = memory_get_usage();

            $errorOutput = $process->getErrorOutput();
            $exitCode = $process->getExitCode();

            $success = $exitCode === 0;

            return [
                'success' => $success,
                'language' => 'javascript',
                'output' => $success ? 'Syntax is valid' : '',
                'error' => $errorOutput,
                'exitCode' => $exitCode,
                'compilationTime' => ($endTime - $startTime) * 1000, // Convert to milliseconds
                'memoryUsage' => $endMemory - $startMemory,
                'metrics' => $this->calculateCompilationMetrics($startTime, $startMemory, $success)
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'language' => 'javascript',
                'output' => '',
                'error' => 'Compilation failed: ' . $e->getMessage(),
                'exitCode' => -1,
                'compilationTime' => 0,
                'memoryUsage' => 0,
                'metrics' => $this->calculateCompilationMetrics($startTime ?? microtime(true), $startMemory ?? memory_get_usage(), false)
            ];
        }
    }

    /**
     * Compile C# code
     */
    public function compileCSharp(string $code): array
    {
        try {
            $startTime = microtime(true);
            $startMemory = memory_get_usage();

            // Create temporary C# file
            $csFile = $this->tempDir . '/compile-check.cs';
            $wrappedCode = $this->wrapCSharpCode($code);
            file_put_contents($csFile, $wrappedCode);

            // Create a temporary project
            $projectDir = $this->tempDir . '/compile-project';
            $createProcess = new Process(['dotnet', 'new', 'console', '--force', '--output', $projectDir]);
            $createProcess->run();

            if ($createProcess->getExitCode() !== 0) {
                throw new Exception('Failed to create C# project: ' . $createProcess->getErrorOutput());
            }

            // Replace the generated Program.cs with our code
            file_put_contents($projectDir . '/Program.cs', $wrappedCode);

            // Build the project
            $buildProcess = new Process(['dotnet', 'build', $projectDir]);
            $buildProcess->run();

            $endTime = microtime(true);
            $endMemory = memory_get_usage();

            $output = $buildProcess->getOutput();
            $errorOutput = $buildProcess->getErrorOutput();
            $exitCode = $buildProcess->getExitCode();

            $success = $exitCode === 0;

            return [
                'success' => $success,
                'language' => 'csharp',
                'output' => $success ? 'Compilation successful' : $output,
                'error' => $errorOutput,
                'exitCode' => $exitCode,
                'compilationTime' => ($endTime - $startTime) * 1000, // Convert to milliseconds
                'memoryUsage' => $endMemory - $startMemory,
                'metrics' => $this->calculateCompilationMetrics($startTime, $startMemory, $success)
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'language' => 'csharp',
                'output' => '',
                'error' => 'Compilation failed: ' . $e->getMessage(),
                'exitCode' => -1,
                'compilationTime' => 0,
                'memoryUsage' => 0,
                'metrics' => $this->calculateCompilationMetrics($startTime ?? microtime(true), $startMemory ?? memory_get_usage(), false)
            ];
        }
    }

    /**
     * Wrap C# code in a basic class structure
     */
    private function wrapCSharpCode(string $code): string
    {
        // Check if code already has a class structure
        if (strpos($code, 'class ') !== false || strpos($code, 'namespace ') !== false) {
            return $code;
        }

        // Wrap in a simple class structure
        return "using System;

class Program
{
    static void Main(string[] args)
    {
        " . $code . "
    }
}";
    }

    /**
     * Create temporary directory
     */
    private function createTempDirectory(): void
    {
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    /**
     * Clean up temporary files
     */
    public function cleanup(): void
    {
        if (is_dir($this->tempDir)) {
            $this->deleteDirectory($this->tempDir);
        }
    }

    /**
     * Recursively delete directory
     */
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    /**
     * Calculate execution metrics
     */
    private function calculateExecutionMetrics(float $startTime, int $startMemory, bool $success): array
    {
        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        return [
            'execution_time' => ($endTime - $startTime) * 1000, // Convert to milliseconds
            'memory_usage' => $endMemory - $startMemory,
            'success_rate' => $success ? 100 : 0,
            'performance_score' => $this->calculatePerformanceScore($endTime - $startTime, $endMemory - $startMemory, $success)
        ];
    }

    /**
     * Calculate compilation metrics
     */
    private function calculateCompilationMetrics(float $startTime, int $startMemory, bool $success): array
    {
        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        return [
            'compilation_time' => ($endTime - $startTime) * 1000, // Convert to milliseconds
            'memory_usage' => $endMemory - $startMemory,
            'success_rate' => $success ? 100 : 0,
            'efficiency_score' => $this->calculateEfficiencyScore($endTime - $startTime, $endMemory - $startMemory, $success)
        ];
    }

    /**
     * Calculate performance score
     */
    private function calculatePerformanceScore(float $executionTime, int $memoryUsage, bool $success): float
    {
        if (!$success) {
            return 0;
        }

        // Base score of 100, reduced by execution time and memory usage
        $score = 100;
        
        // Reduce score based on execution time (penalty for slow execution)
        if ($executionTime > 1.0) { // More than 1 second
            $score -= min(50, ($executionTime - 1.0) * 10);
        }
        
        // Reduce score based on memory usage (penalty for high memory usage)
        if ($memoryUsage > 1024 * 1024) { // More than 1MB
            $score -= min(30, ($memoryUsage - 1024 * 1024) / (1024 * 1024) * 5);
        }
        
        return max(0, $score);
    }

    /**
     * Calculate efficiency score
     */
    private function calculateEfficiencyScore(float $compilationTime, int $memoryUsage, bool $success): float
    {
        if (!$success) {
            return 0;
        }

        // Base score of 100, reduced by compilation time and memory usage
        $score = 100;
        
        // Reduce score based on compilation time (penalty for slow compilation)
        if ($compilationTime > 2.0) { // More than 2 seconds
            $score -= min(40, ($compilationTime - 2.0) * 5);
        }
        
        // Reduce score based on memory usage (penalty for high memory usage)
        if ($memoryUsage > 2 * 1024 * 1024) { // More than 2MB
            $score -= min(20, ($memoryUsage - 2 * 1024 * 1024) / (1024 * 1024) * 3);
        }
        
        return max(0, $score);
    }

    /**
     * Destructor to clean up temporary files
     */
    public function __destruct()
    {
        $this->cleanup();
    }
}
