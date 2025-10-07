<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ConversionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| Code Conversion API Routes
|--------------------------------------------------------------------------
|
| These routes handle the core functionality of the JavaScript ↔ C# 
| Code Converter using Recursive Descent Parsing (RDP) algorithm.
|
*/

// Public conversion endpoints (no authentication required)
Route::prefix('conversion')->group(function () {
    // JavaScript to C# conversion
    Route::post('/javascript-to-csharp', [ConversionController::class, 'convertJavaScriptToCSharp']);
    
    // C# to JavaScript conversion
    Route::post('/csharp-to-javascript', [ConversionController::class, 'convertCSharpToJavaScript']);
    
    // Code execution endpoints
    Route::post('/execute/javascript', [ConversionController::class, 'executeJavaScript']);
    Route::post('/execute/csharp', [ConversionController::class, 'executeCSharp']);
    
    // Code compilation endpoints
    Route::post('/compile/javascript', [ConversionController::class, 'compileJavaScript']);
    Route::post('/compile/csharp', [ConversionController::class, 'compileCSharp']);
    
    // RDP performance metrics (public for research purposes)
    Route::get('/rdp-metrics', [ConversionController::class, 'getRDPMetrics']);
});

// Protected endpoints (authentication required)
Route::middleware('auth:sanctum')->prefix('conversion')->group(function () {
    // User-specific conversion history
    Route::get('/history', [ConversionController::class, 'getConversionHistory']);
    
    // User-specific execution history
    Route::get('/execution-history', [ConversionController::class, 'getExecutionHistory']);
    
    // User-specific RDP metrics
    Route::get('/user-rdp-metrics', [ConversionController::class, 'getRDPMetrics']);
});

/*
|--------------------------------------------------------------------------
| Research and Analytics API Routes
|--------------------------------------------------------------------------
|
| These routes provide data for thesis research and system evaluation.
|
*/

Route::prefix('research')->group(function () {
    // Public research data endpoints
    Route::get('/rdp-effectiveness', function () {
        return response()->json([
            'message' => 'RDP Effectiveness Research Data',
            'description' => 'This endpoint provides data for evaluating the effectiveness of Recursive Descent Parsing algorithm',
            'metrics' => [
                'syntactic_correctness' => 'Measures how accurately RDP parses JavaScript and C# syntax',
                'semantic_preservation' => 'Evaluates how well semantic meaning is preserved during conversion',
                'code_quality' => 'Assesses the quality and readability of converted code',
                'efficiency' => 'Measures parsing and conversion performance',
                'reliability' => 'Evaluates error handling and recovery capabilities'
            ]
        ]);
    });
    
    Route::get('/system-evaluation', function () {
        return response()->json([
            'message' => 'System Evaluation Data',
            'description' => 'This endpoint provides data for IT expert evaluation of the system',
            'criteria' => [
                'functionality' => 'Core conversion and execution features',
                'accuracy' => 'Correctness of code conversion results'
            ]
        ]);
    });
    
    Route::get('/educational-assessment', function () {
        return response()->json([
            'message' => 'Educational Assessment Data',
            'description' => 'This endpoint provides data for educational value assessment',
            'criteria' => [
                'usability' => 'Ease of use for computer science students and professors',
                'educational_value' => 'Learning benefits and pedagogical effectiveness'
            ]
        ]);
    });
});

/*
|--------------------------------------------------------------------------
| Health Check and System Status
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0',
        'rdp_parser' => [
            'javascript' => 'active',
            'csharp' => 'active'
        ],
        'execution_engines' => [
            'nodejs' => 'active',
            'dotnet' => 'active'
        ]
    ]);
});

Route::get('/status', function () {
    return response()->json([
        'system' => 'JavaScript ↔ C# Code Converter',
        'algorithm' => 'Recursive Descent Parsing (RDP)',
        'status' => 'operational',
        'features' => [
            'javascript_to_csharp_conversion' => 'available',
            'csharp_to_javascript_conversion' => 'available',
            'code_execution' => 'available',
            'syntax_validation' => 'available',
            'rdp_performance_metrics' => 'available'
        ],
        'research_focus' => [
            'rdp_effectiveness' => 'primary',
            'syntactic_correctness' => 'measured',
            'semantic_preservation' => 'measured',
            'code_quality' => 'assessed',
            'efficiency' => 'monitored',
            'reliability' => 'evaluated'
        ]
    ]);
});
