<?php

namespace Rakib\ServerLens\Http\Controllers;

use Rakib\ServerLens\Services\ServerObservabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ApiController extends Controller
{
    public function __construct(
        private readonly ServerObservabilityService $observability,
    ) {}

    public function snapshot(Request $request): JsonResponse
    {
        if (!$this->authorized($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json($this->observability->snapshot());
    }

    public function health(Request $request): JsonResponse
    {
        if (!$this->authorized($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $checks = $this->observability->healthOnly();

        $overall = collect($checks)->contains(fn ($c) => $c['status'] === 'critical')
            ? 'critical'
            : (collect($checks)->contains(fn ($c) => $c['status'] === 'warning') ? 'warning' : 'healthy');

        return response()->json([
            'status'       => $overall,
            'checks'       => $checks,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    public function metrics(Request $request): JsonResponse
    {
        if (!$this->authorized($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json([
            'metrics'      => $this->observability->metricsOnly(),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    private function authorized(Request $request): bool
    {
        if (!config('server-lens.api.enabled', false)) {
            return false;
        }

        $token = config('server-lens.api.token', '');

        if ($token === '') {
            return false;
        }

        return $request->bearerToken() === $token;
    }
}
