<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class HealthController extends Controller
{
    /**
     * @OA\Get(
     *     path="/health",
     *     operationId="healthCheck",
     *     tags={"Health"},
     *     summary="Health check endpoint",
     *     description="Returns the health status of the API and its dependencies",
     *
     *     @OA\Response(
     *         response=200,
     *         description="Service is healthy",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="healthy", description="Overall health status"),
     *             @OA\Property(property="timestamp", type="string", format="date-time", example="2024-01-10T12:00:00Z", description="Current timestamp"),
     *             @OA\Property(property="environment", type="string", example="production", description="Current environment"),
     *             @OA\Property(property="version", type="string", example="1.0.0", description="Application version"),
     *             @OA\Property(
     *                 property="services",
     *                 type="object",
     *                 description="Health status of individual services",
     *                 @OA\Property(property="database", type="boolean", example=true, description="Database connection status"),
     *                 @OA\Property(property="cache", type="boolean", example=true, description="Cache connection status"),
     *                 @OA\Property(property="google_books_api", type="boolean", example=true, description="Google Books API connectivity"),
     *                 @OA\Property(property="storage", type="boolean", example=true, description="File storage write/read capabilities")
     *             ),
     *             @OA\Property(
     *                 property="uptime",
     *                 type="object",
     *                 description="Application uptime information",
     *                 @OA\Property(property="seconds", type="integer", example=3600, description="Uptime in seconds"),
     *                 @OA\Property(property="human", type="string", example="1h", description="Human readable uptime")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=503,
     *         description="Service unavailable",
     *
     *         @OA\JsonContent(
     *
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
            'google_books_api' => $this->checkGoogleBooksApi(),
            'storage' => $this->checkStorage(),
        ];

        $isHealthy = ! in_array(false, $services, true);

        $response = [
            'status' => $isHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'environment' => config('app.env'),
            'version' => config('app.version', '1.0.0'),
            'uptime' => $this->getUptime(),
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

    private function checkGoogleBooksApi(): bool
    {
        try {
            $apiKey = config('services.google.books_api_key');
            
            if (empty($apiKey)) {
                return false;
            }

            $response = Http::timeout(5)->get('https://www.googleapis.com/books/v1/volumes', [
                'q' => 'isbn:9780134685991',
                'key' => $apiKey,
            ]);

            return $response->successful() && $response->json('totalItems') !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkStorage(): bool
    {
        try {
            $testFile = storage_path('logs/health_check_test.txt');
            
            file_put_contents($testFile, 'health_check_' . time());
            
            if (file_exists($testFile)) {
                unlink($testFile);
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getUptime(): array
    {
        $startTime = cache()->remember('app_start_time', 3600, function () {
            return now();
        });
        
        $uptimeSeconds = now()->diffInSeconds($startTime);
        
        return [
            'seconds' => $uptimeSeconds,
            'human' => $this->formatUptime($uptimeSeconds),
        ];
    }

    private function formatUptime(int $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        $parts = [];
        
        if ($days > 0) {
            $parts[] = "{$days}d";
        }
        if ($hours > 0) {
            $parts[] = "{$hours}h";
        }
        if ($minutes > 0) {
            $parts[] = "{$minutes}m";
        }
        if ($seconds > 0 || empty($parts)) {
            $parts[] = "{$seconds}s";
        }

        return implode(' ', $parts);
    }
}
