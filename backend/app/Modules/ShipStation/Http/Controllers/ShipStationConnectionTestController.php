<?php

namespace App\Modules\ShipStation\Http\Controllers;

use App\Modules\ShipStation\Services\ShipStationConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipStationConnectionTestController
{
    public function __invoke(Request $request, ShipStationConnectionService $connection): JsonResponse
    {
        if (! app()->environment('local', 'testing')) {
            abort(404);
        }

        $result = $connection->test();

        return response()->json([
            'success' => $result['success'],
            'connected' => $result['connected'],
            'authentication' => $result['authentication'] ?? null,
            'message' => $result['message'],
            'mode' => $result['mode'],
            'endpoint' => $result['endpoint'],
            'http_status' => $result['http_status'],
            'carriers' => $result['carriers'] ?? null,
            'warehouses' => $result['warehouses'] ?? null,
        ], $result['success'] ? 200 : 422);
    }
}
