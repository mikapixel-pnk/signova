<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Services\Inventory\InventoryStockReadService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockBalanceController extends Controller
{
    public function index(
        Request $request,
        InventoryStockReadService $service
    ): JsonResponse {
        return ApiResponse::success(
            $request,
            $service->balances()
        );
    }
}
