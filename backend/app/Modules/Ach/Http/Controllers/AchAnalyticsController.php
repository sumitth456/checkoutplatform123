<?php

namespace App\Modules\Ach\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ach\Services\AchAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AchAnalyticsController extends Controller
{
    public function __invoke(Request $request, AchAnalyticsService $analytics): JsonResponse
    {
        return response()->json([
            'data' => $analytics->metrics(
                from: $request->query('from'),
                to: $request->query('to'),
            ),
        ]);
    }
}
