<?php

namespace Rakib\ServerLens\Http\Controllers;

use Rakib\ServerLens\Services\ServerObservabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ServerObservabilityService $observability,
    ) {}

    public function index(): View
    {
        $snapshot = $this->observability->snapshot();

        return view('server-lens::dashboard', compact('snapshot'));
    }

    public function poll(): JsonResponse
    {
        return response()->json($this->observability->snapshot());
    }
}
