<?php

namespace App\Http\Controllers\Api\Reports\Finance;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Reports\Finance\ProfitReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MontlyProfitReportController extends Controller
{
    public function __construct(private ProfitReportService $profitReportService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $year = $request->get('year', now()->year);

        $reportData = $this->profitReportService->getMonthlyProfitData($year);

        return ApiResponse::send('Monthly profit report retrieved successfully', 200, $reportData);
    }
}
