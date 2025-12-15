<?php

namespace App\Http\Controllers\Api;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Customers\CustomerPayment;
use App\Models\Customers\CustomerReturn;
use App\Models\Customers\Sale;
use App\Models\Items\Item;
use App\Models\Suppliers\Purchase;
use App\Models\Suppliers\PurchaseReturn;
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

            $data = $this->getWarehouseManagerData();
        } else {
            return ApiResponse::error('Unauthorized access', 403);
        }

        return ApiResponse::index('Data', $data);
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

    private function getWarehouseManagerData(): array
    {
        $data = [
            'invoices_to_prepare' => 0,
            'returns_to_receive' => 0,
            'purchases_to_receive' => 0,
            'purchase_returns' => 0,
        ];

        $employee = RoleHelper::getWarehouseEmployee();
        if (! $employee) {
            return $data;
        }
        $warehouseIds = $employee->warehouses()->pluck('warehouses.id');
        if ($warehouseIds->isEmpty()) {
            return $data;
        }
        
        return [
            'invoices_to_prepare' => $this->salesToPrepare($warehouseIds),
            'returns_to_receive' => $this->returnToReceive($warehouseIds),
            'purchases_to_receive' => $this->purchaseToReceive($warehouseIds),
            'purchase_returns' => $this->purchaseToReturns($warehouseIds),
        ];
    }

    private function salesToPrepare($warehouseIds): int
    { 
        return Sale::approved()->byWaiting()->whereIn('warehouse_id', $warehouseIds)->count();
    }

    private function returnToReceive($warehouseIds): int
    {
        return CustomerReturn::approved()->notReceived()->whereIn('warehouse_id', $warehouseIds)->count();
    }

    private function purchaseToReceive($warehouseIds): int
    {
        return Purchase::byWaiting()->whereIn('warehouse_id', $warehouseIds)->count();
    }

    private function purchaseToReturns($warehouseIds): int
    {
        return PurchaseReturn::byWaiting()->whereIn('warehouse_id', $warehouseIds)->count();
    }
}
