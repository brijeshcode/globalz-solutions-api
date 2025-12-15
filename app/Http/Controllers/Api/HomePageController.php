<?php

namespace App\Http\Controllers\Api;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Customers\CustomerPayment;
use App\Models\Customers\CustomerReturn;
use App\Models\Customers\Sale;
use App\Models\Items\Item;
use Illuminate\Http\Request;

class HomePageController extends Controller
{
    public function HomePage(Request $request)
    {
        $data = [];

        if (RoleHelper::canAdmin()) {
            $data = [
                'sales' => $this->saleToReview(),
                'returns' => $this->returnToReview(),
                'payments' => $this->paymentToReview(),
                'lowStocks' => $this->lowStock(),
            ];
        } elseif (RoleHelper::isSalesman()) {
            // Salesman can only see data for their assigned customers
            $data = [
                'sales' => $this->saleToReview(true),
                'returns' => $this->returnToReview(true),
                'payments' => $this->paymentToReview(true),
            ];
        } elseif (RoleHelper::isWarehouseManager()) {
            $data = [
                'sales' => $this->saleToReview(),
                'returns' => $this->returnToReview(),
            ];
        } else {
            return ApiResponse::error('Unauthorized access', 403);
        }

        return ApiResponse::index('logined', $data);
    }

    private function saleToReview($isSalesman = false)
    {
        $query = Sale::pending();

        if ($isSalesman) {
            $salesmanEmployee = RoleHelper::getSalesmanEmployee();
            if ($salesmanEmployee) {
                $query->whereHas('customer', function ($q) use ($salesmanEmployee) {
                    $q->where('salesperson_id', $salesmanEmployee->id);
                });
            }
        }

        return $query->count();
    }

    private function returnToReview($isSalesman = false)
    {
        $query = CustomerReturn::pending();

        if ($isSalesman) {
            $salesmanEmployee = RoleHelper::getSalesmanEmployee();
            if ($salesmanEmployee) {
                $query->whereHas('customer', function ($q) use ($salesmanEmployee) {
                    $q->where('salesperson_id', $salesmanEmployee->id);
                });
            }
        }

        return $query->count();
    }

    private function paymentToReview($isSalesman = false)
    {
        $query = CustomerPayment::pending();

        if ($isSalesman) {
            $salesmanEmployee = RoleHelper::getSalesmanEmployee();
            if ($salesmanEmployee) {
                $query->whereHas('customer', function ($q) use ($salesmanEmployee) {
                    $q->where('salesperson_id', $salesmanEmployee->id);
                });
            }
        }

        return $query->count();
    }

    private function lowStock()
    {
        return Item::lowStock()->count();
    }

}
