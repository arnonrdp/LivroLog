<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * @OA\Get(
     *     path="/health",
     *     operationId="healthCheck",
     *     tags={"Health"},
     *     summary="Health check endpoint",
     *     description="Returns the health status of the API and its dependencies",
     *     @OA\Response(
     *         response=200,
     *         description="Service is healthy",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="healthy", description="Overall health status"),
     *             @OA\Property(property="timestamp", type="string", format="date-time", example="2024-01-10T12:00:00Z", description="Current timestamp"),
     *             @OA\Property(property="environment", type="string", example="production", description="Current environment"),
     *             @OA\Property(property="version", type="string", example="1.0.0", description="Application version"),
     *             @OA\Property(
     *                 property="services",
     *                 type="object",
     *                 description="Health status of individual services",
     *                 @OA\Property(property="database", type="boolean", example=true, description="Database connection status"),
     *                 @OA\Property(property="cache", type="boolean", example=true, description="Cache connection status")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=503,
     *         description="Service unavailable",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="unhealthy"),
     *             @OA\Property(property="timestamp", type="string", format="date-time"),
     *             @OA\Property(property="environment", type="string"),
     *             @OA\Property(property="version", type="string"),
     *             @OA\Property(property="services", type="object")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $services = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
        ];

        $isHealthy = !in_array(false, $services, true);
        
        $response = [
            'status' => $isHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'environment' => config('app.env'),
            'version' => config('app.version', '1.0.0'),
            'services' => $services,
        ];

        return response()->json($response, $isHealthy ? 200 : 503);
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkCache(): bool
    {
        try {
            Cache::store()->put('health_check', true, 10);
            return Cache::store()->get('health_check') === true;
        } catch (\Exception $e) {
            return false;
        }
    }
}