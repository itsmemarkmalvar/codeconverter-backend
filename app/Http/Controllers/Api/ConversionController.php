<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Conversion\CodeConversionService;
use App\Services\Execution\CodeExecutionService;
use App\Models\Conversion;
use App\Models\Execution;
use App\Models\ErrorLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Exception;

/**
 * Conversion Controller
 * 
 * Handles API requests for code conversion and execution
 * using the Recursive Descent Parsing (RDP) algorithm.
 */
class ConversionController extends Controller
{
    private CodeConversionService $conversionService;
    private CodeExecutionService $executionService;

    public function __construct(
        CodeConversionService $conversionService,
        CodeExecutionService $executionService
    ) {
        $this->conversionService = $conversionService;
        $this->executionService = $executionService;
    }

    /**
     * Convert JavaScript to C#
     */
    public function convertJavaScriptToCSharp(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => 'required|string|max:10000',
                'save_conversion' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $code = $request->input('code');
            $saveConversion = $request->input('save_conversion', false);

            // Perform conversion
            $result = $this->conversionService->convertJavaScriptToCSharp($code);

            // Save conversion to database if requested
            if ($saveConversion && Auth::check()) {
                $this->saveConversion($result, Auth::id());
            }

            // Log errors if any
            if (!empty($result['errors'])) {
                $this->logErrors($result['errors'], 'javascript-to-csharp', Auth::id());
            }

            return response()->json($result);

        } catch (Exception $e) {
            $this->logError($e, 'javascript-to-csharp', Auth::id());
            
            return response()->json([
                'success' => false,
                'error' => 'Conversion failed: ' . $e->getMessage(),
                'errors' => [
                    [
                        'type' => 'system_error',
                        'message' => $e->getMessage(),
                        'line' => 1,
                        'column' => 1,
                        'severity' => 'error'
                    ]
                ]
            ], 500);
        }
    }

    /**
     * Convert C# to JavaScript
     */
    public function convertCSharpToJavaScript(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => 'required|string|max:10000',
                'save_conversion' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $code = $request->input('code');
            $saveConversion = $request->input('save_conversion', false);

            // Perform conversion
            $result = $this->conversionService->convertCSharpToJavaScript($code);

            // Save conversion to database if requested
            if ($saveConversion && Auth::check()) {
                $this->saveConversion($result, Auth::id());
            }

            // Log errors if any
            if (!empty($result['errors'])) {
                $this->logErrors($result['errors'], 'csharp-to-javascript', Auth::id());
            }

            return response()->json($result);

        } catch (Exception $e) {
            $this->logError($e, 'csharp-to-javascript', Auth::id());
            
            return response()->json([
                'success' => false,
                'error' => 'Conversion failed: ' . $e->getMessage(),
                'errors' => [
                    [
                        'type' => 'system_error',
                        'message' => $e->getMessage(),
                        'line' => 1,
                        'column' => 1,
                        'severity' => 'error'
                    ]
                ]
            ], 500);
        }
    }

    /**
     * Execute JavaScript code
     */
    public function executeJavaScript(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => 'required|string|max:10000',
                'save_execution' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $code = $request->input('code');
            $saveExecution = $request->input('save_execution', false);

            // Execute JavaScript code
            $result = $this->executionService->executeJavaScript($code);

            // Save execution to database if requested
            if ($saveExecution && Auth::check()) {
                $this->saveExecution($result, Auth::id());
            }

            return response()->json($result);

        } catch (Exception $e) {
            $this->logError($e, 'javascript-execution', Auth::id());
            
            return response()->json([
                'success' => false,
                'error' => 'Execution failed: ' . $e->getMessage(),
                'language' => 'javascript',
                'output' => '',
                'error' => $e->getMessage(),
                'exitCode' => -1,
                'executionTime' => 0,
                'memoryUsage' => 0
            ], 500);
        }
    }

    /**
     * Execute C# code
     */
    public function executeCSharp(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => 'required|string|max:10000',
                'save_execution' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $code = $request->input('code');
            $saveExecution = $request->input('save_execution', false);

            // Execute C# code
            $result = $this->executionService->executeCSharp($code);

            // Save execution to database if requested
            if ($saveExecution && Auth::check()) {
                $this->saveExecution($result, Auth::id());
            }

            return response()->json($result);

        } catch (Exception $e) {
            $this->logError($e, 'csharp-execution', Auth::id());
            
            return response()->json([
                'success' => false,
                'error' => 'Execution failed: ' . $e->getMessage(),
                'language' => 'csharp',
                'output' => '',
                'error' => $e->getMessage(),
                'exitCode' => -1,
                'executionTime' => 0,
                'memoryUsage' => 0
            ], 500);
        }
    }

    /**
     * Compile JavaScript code (syntax check)
     */
    public function compileJavaScript(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => 'required|string|max:10000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $code = $request->input('code');

            // Compile JavaScript code
            $result = $this->executionService->compileJavaScript($code);

            return response()->json($result);

        } catch (Exception $e) {
            $this->logError($e, 'javascript-compilation', Auth::id());
            
            return response()->json([
                'success' => false,
                'error' => 'Compilation failed: ' . $e->getMessage(),
                'language' => 'javascript',
                'output' => '',
                'error' => $e->getMessage(),
                'exitCode' => -1,
                'compilationTime' => 0,
                'memoryUsage' => 0
            ], 500);
        }
    }

    /**
     * Compile C# code
     */
    public function compileCSharp(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => 'required|string|max:10000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $code = $request->input('code');

            // Compile C# code
            $result = $this->executionService->compileCSharp($code);

            return response()->json($result);

        } catch (Exception $e) {
            $this->logError($e, 'csharp-compilation', Auth::id());
            
            return response()->json([
                'success' => false,
                'error' => 'Compilation failed: ' . $e->getMessage(),
                'language' => 'csharp',
                'output' => '',
                'error' => $e->getMessage(),
                'exitCode' => -1,
                'compilationTime' => 0,
                'memoryUsage' => 0
            ], 500);
        }
    }

    /**
     * Get conversion history for authenticated user
     */
    public function getConversionHistory(Request $request): JsonResponse
    {
        try {
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Authentication required'
                ], 401);
            }

            $conversions = Conversion::where('user_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            return response()->json([
                'success' => true,
                'conversions' => $conversions
            ]);

        } catch (Exception $e) {
            $this->logError($e, 'get-conversion-history', Auth::id());
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve conversion history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get execution history for authenticated user
     */
    public function getExecutionHistory(Request $request): JsonResponse
    {
        try {
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Authentication required'
                ], 401);
            }

            $executions = Execution::where('user_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            return response()->json([
                'success' => true,
                'executions' => $executions
            ]);

        } catch (Exception $e) {
            $this->logError($e, 'get-execution-history', Auth::id());
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve execution history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get RDP performance metrics
     */
    public function getRDPMetrics(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'start_date' => 'date',
                'end_date' => 'date|after:start_date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $startDate = $request->input('start_date', now()->subDays(30));
            $endDate = $request->input('end_date', now());

            $query = Conversion::whereBetween('created_at', [$startDate, $endDate]);

            if (Auth::check()) {
                $query->where('user_id', Auth::id());
            }

            $conversions = $query->get();

            $metrics = $this->calculateRDPMetrics($conversions);

            return response()->json([
                'success' => true,
                'metrics' => $metrics,
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]
            ]);

        } catch (Exception $e) {
            $this->logError($e, 'get-rdp-metrics', Auth::id());
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve RDP metrics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save conversion to database
     */
    private function saveConversion(array $result, int $userId): void
    {
        try {
            Conversion::create([
                'user_id' => $userId,
                'source_language' => $result['sourceLanguage'] ?? 'unknown',
                'target_language' => $result['targetLanguage'] ?? 'unknown',
                'conversion_direction' => $result['conversionDirection'] ?? 'unknown',
                'source_code' => $result['sourceCode'] ?? '',
                'target_code' => $result['targetCode'] ?? '',
                'success' => $result['success'] ?? false,
                'conversion_time' => $result['conversionMetrics']['conversion_time'] ?? 0,
                'memory_usage' => $result['conversionMetrics']['memory_usage'] ?? 0,
                'source_ast_nodes' => $result['conversionMetrics']['source_ast_nodes'] ?? 0,
                'source_tokens_processed' => $result['conversionMetrics']['source_tokens_processed'] ?? 0,
                'source_syntax_accuracy' => $result['conversionMetrics']['source_syntax_accuracy'] ?? 0,
                'source_semantic_preservation' => $result['conversionMetrics']['source_semantic_preservation'] ?? 0,
                'conversion_success_rate' => $result['conversionMetrics']['conversion_success_rate'] ?? 0,
                'error_count' => count($result['errors'] ?? []),
                'warning_count' => count($result['warnings'] ?? [])
            ]);
        } catch (Exception $e) {
            // Log error but don't fail the request
            $this->logError($e, 'save-conversion', $userId);
        }
    }

    /**
     * Save execution to database
     */
    private function saveExecution(array $result, int $userId): void
    {
        try {
            Execution::create([
                'user_id' => $userId,
                'language' => $result['language'] ?? 'unknown',
                'code' => $request->input('code') ?? '',
                'output' => $result['output'] ?? '',
                'error_output' => $result['error'] ?? '',
                'exit_code' => $result['exitCode'] ?? -1,
                'success' => $result['success'] ?? false,
                'execution_time' => $result['executionTime'] ?? 0,
                'memory_usage' => $result['memoryUsage'] ?? 0
            ]);
        } catch (Exception $e) {
            // Log error but don't fail the request
            $this->logError($e, 'save-execution', $userId);
        }
    }

    /**
     * Log errors to database
     */
    private function logErrors(array $errors, string $context, ?int $userId): void
    {
        foreach ($errors as $error) {
            try {
                ErrorLog::create([
                    'user_id' => $userId,
                    'context' => $context,
                    'error_type' => $error['type'] ?? 'unknown',
                    'error_message' => $error['message'] ?? 'Unknown error',
                    'line_number' => $error['line'] ?? 0,
                    'column_number' => $error['column'] ?? 0,
                    'severity' => $error['severity'] ?? 'error',
                    'stack_trace' => null
                ]);
            } catch (Exception $e) {
                // Log error but don't fail the request
                error_log('Failed to log error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Log single error to database
     */
    private function logError(Exception $e, string $context, ?int $userId): void
    {
        try {
            ErrorLog::create([
                'user_id' => $userId,
                'context' => $context,
                'error_type' => 'system_error',
                'error_message' => $e->getMessage(),
                'line_number' => $e->getLine(),
                'column_number' => 0,
                'severity' => 'error',
                'stack_trace' => $e->getTraceAsString()
            ]);
        } catch (Exception $logException) {
            // Log error but don't fail the request
            error_log('Failed to log error: ' . $logException->getMessage());
        }
    }

    /**
     * Calculate RDP performance metrics
     */
    private function calculateRDPMetrics($conversions): array
    {
        if ($conversions->isEmpty()) {
            return [
                'total_conversions' => 0,
                'successful_conversions' => 0,
                'success_rate' => 0,
                'average_conversion_time' => 0,
                'average_memory_usage' => 0,
                'average_syntax_accuracy' => 0,
                'average_semantic_preservation' => 0,
                'total_errors' => 0,
                'total_warnings' => 0
            ];
        }

        $totalConversions = $conversions->count();
        $successfulConversions = $conversions->where('success', true)->count();
        $successRate = ($successfulConversions / $totalConversions) * 100;

        return [
            'total_conversions' => $totalConversions,
            'successful_conversions' => $successfulConversions,
            'success_rate' => round($successRate, 2),
            'average_conversion_time' => round($conversions->avg('conversion_time'), 2),
            'average_memory_usage' => round($conversions->avg('memory_usage'), 2),
            'average_syntax_accuracy' => round($conversions->avg('source_syntax_accuracy'), 2),
            'average_semantic_preservation' => round($conversions->avg('source_semantic_preservation'), 2),
            'total_errors' => $conversions->sum('error_count'),
            'total_warnings' => $conversions->sum('warning_count')
        ];
    }
}
