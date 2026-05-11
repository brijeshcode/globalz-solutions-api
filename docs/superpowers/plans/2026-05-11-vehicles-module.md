# Vehicles Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Vehicles module — Gas Stations, Cars, Car Refills, and Gas Station Payments — to track delivery car movement, mileage, and fuel costs.

**Architecture:** Four entities under `app/**/Setups/Vehicle/` namespace. Gas station balance is stored on `gas_stations.balance` and updated via Eloquent model events on create/delete/restore; update adjustments are applied in the controller. `km_driven` on car refills is calculated at write time (current odometer − previous odometer for the same car) and stored.

**Tech Stack:** Laravel 11, Eloquent, Pest, `App\Helpers\SettingsHelper` (Setting counter), `ApiResponse`, `HasPagination`, `Authorable`, `Searchable`, `Sortable`, `SoftDeletes` traits.

---

## File Map

**Rename (existing artisan-generated files — camelCase → PascalCase):**
- `app/Models/Setups/Vehicle/gasStation.php` → `GasStation.php`
- `app/Http/Controllers/Api/Setups/Vehicle/gasStationsController.php` → `GasStationsController.php`
- `app/Http/Resources/Api/Setups/Vehicle/gasStationResource.php` → `GasStationResource.php`
- `app/Http/Requests/Api/Setups/Vehicle/gasStationsStoreRequest.php` → `GasStationsStoreRequest.php`
- `app/Http/Requests/Api/Setups/Vehicle/gasStationsUpdateRequest.php` → `GasStationsUpdateRequest.php`
- `database/factories/Setups/Vehicle/gasStationFactory.php` → `GasStationFactory.php`
- `tests/Feature/Setups/Vehicle/gasStationTest.php` → `GasStationTest.php`

**New files:**
- `database/migrations/2026_05_11_120000_create_cars_table.php`
- `database/migrations/2026_05_11_120001_create_car_refills_table.php`
- `database/migrations/2026_05_11_120002_create_gas_station_payments_table.php`
- `app/Models/Setups/Vehicle/GasStation.php`
- `app/Models/Setups/Vehicle/Car.php`
- `app/Models/Setups/Vehicle/CarRefill.php`
- `app/Models/Setups/Vehicle/GasStationPayment.php`
- `app/Http/Controllers/Api/Setups/Vehicle/GasStationsController.php`
- `app/Http/Controllers/Api/Setups/Vehicle/CarsController.php`
- `app/Http/Controllers/Api/Setups/Vehicle/CarRefillsController.php`
- `app/Http/Controllers/Api/Setups/Vehicle/GasStationPaymentsController.php`
- `app/Http/Requests/Api/Setups/Vehicle/GasStationsStoreRequest.php`
- `app/Http/Requests/Api/Setups/Vehicle/GasStationsUpdateRequest.php`
- `app/Http/Requests/Api/Setups/Vehicle/CarsStoreRequest.php`
- `app/Http/Requests/Api/Setups/Vehicle/CarsUpdateRequest.php`
- `app/Http/Requests/Api/Setups/Vehicle/CarRefillsStoreRequest.php`
- `app/Http/Requests/Api/Setups/Vehicle/CarRefillsUpdateRequest.php`
- `app/Http/Requests/Api/Setups/Vehicle/GasStationPaymentsStoreRequest.php`
- `app/Http/Requests/Api/Setups/Vehicle/GasStationPaymentsUpdateRequest.php`
- `app/Http/Resources/Api/Setups/Vehicle/GasStationResource.php`
- `app/Http/Resources/Api/Setups/Vehicle/CarResource.php`
- `app/Http/Resources/Api/Setups/Vehicle/CarRefillResource.php`
- `app/Http/Resources/Api/Setups/Vehicle/GasStationPaymentResource.php`
- `database/factories/Setups/Vehicle/GasStationFactory.php`
- `database/factories/Setups/Vehicle/CarFactory.php`
- `database/factories/Setups/Vehicle/CarRefillFactory.php`
- `database/factories/Setups/Vehicle/GasStationPaymentFactory.php`
- `tests/Feature/Setups/Vehicle/GasStationTest.php`
- `tests/Feature/Setups/Vehicle/CarTest.php`
- `tests/Feature/Setups/Vehicle/CarRefillTest.php`
- `tests/Feature/Setups/Vehicle/GasStationPaymentTest.php`

**Modified:**
- `routes/api.php` — add vehicles route group
- `app/Console/Commands/Tenants/PruneOrphanedSettings.php` — add `car_refills` and `gas_station_payments` to COUNTER_GROUPS

---

## Task 1: Rename Artisan-Generated Files to PascalCase

The artisan-generated files use camelCase class names which violates PSR-4. Delete the old files and create PascalCase replacements.

**Files:**
- Delete: all 7 camelCase files listed in the rename section above
- Create: `app/Models/Setups/Vehicle/GasStation.php` (empty stub — filled in Task 3)
- Create: `app/Http/Controllers/Api/Setups/Vehicle/GasStationsController.php` (empty stub)
- Create: `app/Http/Resources/Api/Setups/Vehicle/GasStationResource.php` (empty stub)
- Create: `app/Http/Requests/Api/Setups/Vehicle/GasStationsStoreRequest.php` (empty stub)
- Create: `app/Http/Requests/Api/Setups/Vehicle/GasStationsUpdateRequest.php` (empty stub)
- Create: `database/factories/Setups/Vehicle/GasStationFactory.php` (empty stub)
- Create: `tests/Feature/Setups/Vehicle/GasStationTest.php` (empty stub)

- [ ] **Step 1: Delete the 7 camelCase files**

```bash
rm app/Models/Setups/Vehicle/gasStation.php
rm app/Http/Controllers/Api/Setups/Vehicle/gasStationsController.php
rm app/Http/Resources/Api/Setups/Vehicle/gasStationResource.php
rm app/Http/Requests/Api/Setups/Vehicle/gasStationsStoreRequest.php
rm app/Http/Requests/Api/Setups/Vehicle/gasStationsUpdateRequest.php
rm database/factories/Setups/Vehicle/gasStationFactory.php
rm tests/Feature/Setups/Vehicle/gasStationTest.php
```

- [ ] **Step 2: Create PascalCase stub for GasStation model**

Create `app/Models/Setups/Vehicle/GasStation.php`:
```php
<?php

namespace App\Models\Setups\Vehicle;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Authorable;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Database\Factories\Setups\Vehicle\GasStationFactory;

class GasStation extends Model
{
    use HasFactory, SoftDeletes, Authorable, Searchable, Sortable;

    protected static function newFactory(): GasStationFactory
    {
        return GasStationFactory::new();
    }
}
```

- [ ] **Step 3: Create PascalCase stub for GasStationsController**

Create `app/Http/Controllers/Api/Setups/Vehicle/GasStationsController.php`:
```php
<?php

namespace App\Http\Controllers\Api\Setups\Vehicle;

use App\Http\Controllers\Controller;

class GasStationsController extends Controller
{
}
```

- [ ] **Step 4: Create PascalCase stubs for requests, resource, factory, test**

Create `app/Http/Requests/Api/Setups/Vehicle/GasStationsStoreRequest.php`:
```php
<?php

namespace App\Http\Requests\Api\Setups\Vehicle;

use Illuminate\Foundation\Http\FormRequest;

class GasStationsStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return []; }
}
```

Create `app/Http/Requests/Api/Setups/Vehicle/GasStationsUpdateRequest.php`:
```php
<?php

namespace App\Http\Requests\Api\Setups\Vehicle;

use Illuminate\Foundation\Http\FormRequest;

class GasStationsUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return []; }
}
```

Create `app/Http/Resources/Api/Setups/Vehicle/GasStationResource.php`:
```php
<?php

namespace App\Http\Resources\Api\Setups\Vehicle;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GasStationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }
}
```

Create `database/factories/Setups/Vehicle/GasStationFactory.php`:
```php
<?php

namespace Database\Factories\Setups\Vehicle;

use App\Models\Setups\Vehicle\GasStation;
use Illuminate\Database\Eloquent\Factories\Factory;

class GasStationFactory extends Factory
{
    protected $model = GasStation::class;

    public function definition(): array
    {
        return [];
    }
}
```

Create `tests/Feature/Setups/Vehicle/GasStationTest.php`:
```php
<?php

use App\Models\User;

uses()->group('api', 'setup', 'vehicles', 'vehicles.gas-stations');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});
```

- [ ] **Step 5: Verify PHP can find all new stubs**

```bash
php artisan config:clear
php artisan route:clear
```

Expected: no errors.

- [ ] **Step 6: Commit**

```bash
git add app/Models/Setups/Vehicle/ app/Http/Controllers/Api/Setups/Vehicle/ app/Http/Requests/Api/Setups/Vehicle/ app/Http/Resources/Api/Setups/Vehicle/ database/factories/Setups/Vehicle/ tests/Feature/Setups/Vehicle/
git commit -m "refactor: rename vehicles module files to PascalCase"
```

---

## Task 2: Database Migrations

**Files:**
- Already exists: `database/migrations/2026_05_11_115453_create_gas_stations_table.php`
- Create: `database/migrations/2026_05_11_120000_create_cars_table.php`
- Create: `database/migrations/2026_05_11_120001_create_car_refills_table.php`
- Create: `database/migrations/2026_05_11_120002_create_gas_station_payments_table.php`

- [ ] **Step 1: Create cars migration**

Create `database/migrations/2026_05_11_120000_create_cars_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('plate_number', 50)->nullable();
            $table->smallInteger('year')->nullable()->unsigned();
            $table->string('color', 50)->nullable();
            $table->string('make', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cars');
    }
};
```

- [ ] **Step 2: Create car_refills migration**

Create `database/migrations/2026_05_11_120001_create_car_refills_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('car_refills', function (Blueprint $table) {
            $table->id();
            $table->datetime('date');
            $table->string('code', 50)->unique()->index();

            $table->foreignId('car_id')->constrained('cars')->onDelete('restrict');
            $table->foreignId('gas_station_id')->constrained('gas_stations')->onDelete('restrict');
            $table->foreignId('driver_id')->constrained('employees')->onDelete('restrict');

            $table->decimal('odometer', 10, 2);
            $table->decimal('km_driven', 10, 2)->default(0);
            $table->decimal('amount', 15, 4);
            $table->integer('invoices_count')->nullable();
            $table->text('note')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['date', 'car_id']);
            $table->index(['date', 'gas_station_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_refills');
    }
};
```

- [ ] **Step 3: Create gas_station_payments migration**

Create `database/migrations/2026_05_11_120002_create_gas_station_payments_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gas_station_payments', function (Blueprint $table) {
            $table->id();
            $table->datetime('date');
            $table->string('code', 50)->unique()->index();

            $table->foreignId('gas_station_id')->constrained('gas_stations')->onDelete('restrict');
            $table->foreignId('account_id')->constrained('accounts')->onDelete('restrict');

            $table->decimal('amount', 15, 4);
            $table->text('note')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['date', 'gas_station_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gas_station_payments');
    }
};
```

- [ ] **Step 4: Run all migrations**

```bash
php artisan migrate
```

Expected output includes:
```
2026_05_11_115453_create_gas_stations_table .......... 16ms DONE
2026_05_11_120000_create_cars_table .................. Xms DONE
2026_05_11_120001_create_car_refills_table ........... Xms DONE
2026_05_11_120002_create_gas_station_payments_table .. Xms DONE
```

- [ ] **Step 5: Commit**

```bash
git add database/migrations/
git commit -m "feat: add vehicles module migrations (cars, car_refills, gas_station_payments)"
```

---

## Task 3: Gas Station — Model, Factory, Resource, Requests, Controller, Routes, Tests

**Files:**
- Modify: `app/Models/Setups/Vehicle/GasStation.php`
- Modify: `database/factories/Setups/Vehicle/GasStationFactory.php`
- Modify: `app/Http/Resources/Api/Setups/Vehicle/GasStationResource.php`
- Modify: `app/Http/Requests/Api/Setups/Vehicle/GasStationsStoreRequest.php`
- Modify: `app/Http/Requests/Api/Setups/Vehicle/GasStationsUpdateRequest.php`
- Modify: `app/Http/Controllers/Api/Setups/Vehicle/GasStationsController.php`
- Modify: `routes/api.php`
- Modify: `tests/Feature/Setups/Vehicle/GasStationTest.php`

- [ ] **Step 1: Write the failing gas station tests**

Replace `tests/Feature/Setups/Vehicle/GasStationTest.php`:
```php
<?php

use App\Models\Setups\Vehicle\GasStation;
use App\Models\User;

uses()->group('api', 'setup', 'vehicles', 'vehicles.gas-stations');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

describe('Gas Stations API', function () {
    it('can list gas stations', function () {
        GasStation::factory()->count(3)->create();

        $response = $this->getJson(route('setups.vehicles.gas-stations.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => ['id', 'name', 'balance', 'address', 'note', 'created_by', 'updated_by', 'created_at', 'updated_at']
                ],
                'pagination'
            ]);
    });

    it('can create a gas station', function () {
        $data = ['name' => 'Total Bleibel', 'address' => 'Bleibel, Lebanon', 'note' => 'Main station'];

        $response = $this->postJson(route('setups.vehicles.gas-stations.store'), $data);

        $response->assertCreated()
            ->assertJson(['data' => ['name' => 'Total Bleibel', 'balance' => 0]]);

        $this->assertDatabaseHas('gas_stations', ['name' => 'Total Bleibel']);
    });

    it('can show a gas station', function () {
        $station = GasStation::factory()->create();

        $response = $this->getJson(route('setups.vehicles.gas-stations.show', $station));

        $response->assertOk()->assertJson(['data' => ['id' => $station->id]]);
    });

    it('can update a gas station', function () {
        $station = GasStation::factory()->create();

        $response = $this->putJson(route('setups.vehicles.gas-stations.update', $station), [
            'name' => 'Updated Station',
            'address' => 'New Address',
        ]);

        $response->assertOk()->assertJson(['data' => ['name' => 'Updated Station']]);
        $this->assertDatabaseHas('gas_stations', ['id' => $station->id, 'name' => 'Updated Station']);
    });

    it('can delete a gas station', function () {
        $station = GasStation::factory()->create();

        $response = $this->deleteJson(route('setups.vehicles.gas-stations.destroy', $station));

        $response->assertNoContent();
        $this->assertSoftDeleted('gas_stations', ['id' => $station->id]);
    });

    it('validates required fields', function () {
        $response = $this->postJson(route('setups.vehicles.gas-stations.store'), []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['name', 'address']);
    });

    it('can list trashed gas stations', function () {
        $station = GasStation::factory()->create();
        $station->delete();

        $response = $this->getJson(route('setups.vehicles.gas-stations.trashed'));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    });

    it('can restore a gas station', function () {
        $station = GasStation::factory()->create();
        $station->delete();

        $response = $this->patchJson(route('setups.vehicles.gas-stations.restore', $station->id));

        $response->assertOk();
        $this->assertDatabaseHas('gas_stations', ['id' => $station->id, 'deleted_at' => null]);
    });

    it('can force delete a gas station', function () {
        $station = GasStation::factory()->create();
        $station->delete();

        $response = $this->deleteJson(route('setups.vehicles.gas-stations.force-delete', $station->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('gas_stations', ['id' => $station->id]);
    });

    it('can search gas stations', function () {
        GasStation::factory()->create(['name' => 'Total Bleibel']);
        GasStation::factory()->create(['name' => 'Hypco Hazmieh']);

        $response = $this->getJson(route('setups.vehicles.gas-stations.index', ['search' => 'Total']));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.name'))->toBe('Total Bleibel');
    });
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Setups/Vehicle/GasStationTest.php
```

Expected: FAIL — routes not registered yet.

- [ ] **Step 3: Implement GasStation model**

Replace `app/Models/Setups/Vehicle/GasStation.php`:
```php
<?php

namespace App\Models\Setups\Vehicle;

use App\Traits\Authorable;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Database\Factories\Setups\Vehicle\GasStationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GasStation extends Model
{
    use HasFactory, SoftDeletes, Authorable, Searchable, Sortable;

    protected $fillable = ['name', 'balance', 'address', 'note'];

    protected $searchable = ['name', 'address', 'note'];

    protected $casts = [
        'balance' => 'decimal:4',
    ];

    protected static function newFactory(): GasStationFactory
    {
        return GasStationFactory::new();
    }

    public function refills()
    {
        return $this->hasMany(CarRefill::class, 'gas_station_id');
    }

    public function payments()
    {
        return $this->hasMany(GasStationPayment::class, 'gas_station_id');
    }
}
```

- [ ] **Step 4: Implement GasStationFactory**

Replace `database/factories/Setups/Vehicle/GasStationFactory.php`:
```php
<?php

namespace Database\Factories\Setups\Vehicle;

use App\Models\Setups\Vehicle\GasStation;
use Illuminate\Database\Eloquent\Factories\Factory;

class GasStationFactory extends Factory
{
    protected $model = GasStation::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company() . ' Gas Station',
            'balance' => 0,
            'address' => $this->faker->address(),
            'note' => $this->faker->optional()->sentence(),
        ];
    }

    public function withBalance(float $balance): static
    {
        return $this->state(['balance' => $balance]);
    }
}
```

- [ ] **Step 5: Implement GasStationResource**

Replace `app/Http/Resources/Api/Setups/Vehicle/GasStationResource.php`:
```php
<?php

namespace App\Http\Resources\Api\Setups\Vehicle;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GasStationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'balance' => $this->balance,
            'address' => $this->address,
            'note' => $this->note,
            'created_by' => [
                'id' => $this->createdBy?->id,
                'name' => $this->createdBy?->name,
            ],
            'updated_by' => [
                'id' => $this->updatedBy?->id,
                'name' => $this->updatedBy?->name,
            ],
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
```

- [ ] **Step 6: Implement GasStationsStoreRequest and GasStationsUpdateRequest**

Replace `app/Http/Requests/Api/Setups/Vehicle/GasStationsStoreRequest.php`:
```php
<?php

namespace App\Http\Requests\Api\Setups\Vehicle;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;

class GasStationsStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleHelper::canSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'name'    => 'required|string|max:200|unique:gas_stations,name',
            'address' => 'required|string',
            'note'    => 'nullable|string',
        ];
    }
}
```

Replace `app/Http/Requests/Api/Setups/Vehicle/GasStationsUpdateRequest.php`:
```php
<?php

namespace App\Http\Requests\Api\Setups\Vehicle;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;

class GasStationsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleHelper::canSuperAdmin();
    }

    public function rules(): array
    {
        $id = $this->route('gasStation')?->id;

        return [
            'name'    => "required|string|max:200|unique:gas_stations,name,{$id}",
            'address' => 'required|string',
            'note'    => 'nullable|string',
        ];
    }
}
```

- [ ] **Step 7: Implement GasStationsController**

Replace `app/Http/Controllers/Api/Setups/Vehicle/GasStationsController.php`:
```php
<?php

namespace App\Http\Controllers\Api\Setups\Vehicle;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\Vehicle\GasStationsStoreRequest;
use App\Http\Requests\Api\Setups\Vehicle\GasStationsUpdateRequest;
use App\Http\Resources\Api\Setups\Vehicle\GasStationResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\Vehicle\GasStation;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GasStationsController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = GasStation::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        $stations = $this->applyPagination($query, $request);

        return ApiResponse::paginated('Gas stations retrieved successfully', $stations, GasStationResource::class);
    }

    public function store(GasStationsStoreRequest $request): JsonResponse
    {
        $station = GasStation::create($request->validated());
        $station->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store('Gas station created successfully', new GasStationResource($station));
    }

    public function show(GasStation $gasStation): JsonResponse
    {
        $gasStation->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show('Gas station retrieved successfully', new GasStationResource($gasStation));
    }

    public function update(GasStationsUpdateRequest $request, GasStation $gasStation): JsonResponse
    {
        $gasStation->update($request->validated());
        $gasStation->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update('Gas station updated successfully', new GasStationResource($gasStation));
    }

    public function destroy(GasStation $gasStation): JsonResponse
    {
        if (!RoleHelper::canSuperAdmin()) {
            return ApiResponse::forbidden('You are not authorized');
        }

        $gasStation->delete();

        return ApiResponse::delete('Gas station deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = GasStation::onlyTrashed()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        $stations = $this->applyPagination($query, $request);

        return ApiResponse::paginated('Trashed gas stations retrieved successfully', $stations, GasStationResource::class);
    }

    public function restore(int $id): JsonResponse
    {
        $station = GasStation::onlyTrashed()->findOrFail($id);
        $station->restore();
        $station->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update('Gas station restored successfully', new GasStationResource($station));
    }

    public function forceDelete(int $id): JsonResponse
    {
        $station = GasStation::onlyTrashed()->findOrFail($id);
        $station->forceDelete();

        return ApiResponse::delete('Gas station permanently deleted successfully');
    }
}
```

- [ ] **Step 8: Register gas stations routes in routes/api.php**

In `routes/api.php`, add the import at the top (with other use statements):
```php
use App\Http\Controllers\Api\Setups\Vehicle\GasStationsController;
```

Inside the `Route::prefix('setups')` group, add:
```php
        // Vehicles
        Route::prefix('vehicles')->name('vehicles.')->group(function () {

            // Gas Stations
            Route::controller(GasStationsController::class)->prefix('gas-stations')->name('gas-stations.')->group(function () {
                Route::get('trashed', 'trashed')->name('trashed');
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('{gasStation}', 'show')->name('show');
                Route::put('{gasStation}', 'update')->name('update');
                Route::delete('{gasStation}', 'destroy')->name('destroy');
                Route::patch('{id}/restore', 'restore')->name('restore');
                Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
            });

        });
```

- [ ] **Step 9: Run gas station tests**

```bash
php artisan test tests/Feature/Setups/Vehicle/GasStationTest.php
```

Expected: all tests PASS.

- [ ] **Step 10: Commit**

```bash
git add app/Models/Setups/Vehicle/GasStation.php database/factories/Setups/Vehicle/GasStationFactory.php app/Http/Resources/Api/Setups/Vehicle/GasStationResource.php app/Http/Requests/Api/Setups/Vehicle/GasStations*.php app/Http/Controllers/Api/Setups/Vehicle/GasStationsController.php routes/api.php tests/Feature/Setups/Vehicle/GasStationTest.php
git commit -m "feat: implement gas stations CRUD"
```

---

## Task 4: Car — Model, Factory, Resource, Requests, Controller, Routes, Tests

**Files:**
- Create: `app/Models/Setups/Vehicle/Car.php`
- Create: `database/factories/Setups/Vehicle/CarFactory.php`
- Create: `app/Http/Resources/Api/Setups/Vehicle/CarResource.php`
- Create: `app/Http/Requests/Api/Setups/Vehicle/CarsStoreRequest.php`
- Create: `app/Http/Requests/Api/Setups/Vehicle/CarsUpdateRequest.php`
- Create: `app/Http/Controllers/Api/Setups/Vehicle/CarsController.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/Setups/Vehicle/CarTest.php`

- [ ] **Step 1: Write failing Car tests**

Create `tests/Feature/Setups/Vehicle/CarTest.php`:
```php
<?php

use App\Models\Setups\Vehicle\Car;
use App\Models\User;

uses()->group('api', 'setup', 'vehicles', 'vehicles.cars');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

describe('Cars API', function () {
    it('can list cars', function () {
        Car::factory()->count(3)->create();

        $response = $this->getJson(route('setups.vehicles.cars.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => ['id', 'name', 'plate_number', 'year', 'color', 'make', 'model', 'is_active', 'created_by', 'updated_by']
                ],
                'pagination'
            ]);
    });

    it('can create a car with name only', function () {
        $response = $this->postJson(route('setups.vehicles.cars.store'), ['name' => 'Toyota Hiace']);

        $response->assertCreated()
            ->assertJson(['data' => ['name' => 'Toyota Hiace', 'is_active' => true]]);

        $this->assertDatabaseHas('cars', ['name' => 'Toyota Hiace']);
    });

    it('can create a car with all fields', function () {
        $data = [
            'name'         => 'Toyota Hiace',
            'plate_number' => 'LEB-1234',
            'year'         => 2020,
            'color'        => 'White',
            'make'         => 'Toyota',
            'model'        => 'Hiace',
            'note'         => 'Delivery van',
            'is_active'    => true,
        ];

        $response = $this->postJson(route('setups.vehicles.cars.store'), $data);

        $response->assertCreated()->assertJson(['data' => ['plate_number' => 'LEB-1234', 'year' => 2020]]);
    });

    it('can show a car', function () {
        $car = Car::factory()->create();

        $response = $this->getJson(route('setups.vehicles.cars.show', $car));

        $response->assertOk()->assertJson(['data' => ['id' => $car->id]]);
    });

    it('can update a car', function () {
        $car = Car::factory()->create();

        $response = $this->putJson(route('setups.vehicles.cars.update', $car), ['name' => 'Updated Car', 'is_active' => false]);

        $response->assertOk()->assertJson(['data' => ['name' => 'Updated Car', 'is_active' => false]]);
        $this->assertDatabaseHas('cars', ['id' => $car->id, 'name' => 'Updated Car']);
    });

    it('can delete a car', function () {
        $car = Car::factory()->create();

        $response = $this->deleteJson(route('setups.vehicles.cars.destroy', $car));

        $response->assertNoContent();
        $this->assertSoftDeleted('cars', ['id' => $car->id]);
    });

    it('validates name is required', function () {
        $response = $this->postJson(route('setups.vehicles.cars.store'), []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['name']);
    });

    it('can filter by is_active', function () {
        Car::factory()->create(['is_active' => true]);
        Car::factory()->create(['is_active' => false]);

        $response = $this->getJson(route('setups.vehicles.cars.index', ['is_active' => true]));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    });

    it('can search cars by name', function () {
        Car::factory()->create(['name' => 'Toyota Hiace']);
        Car::factory()->create(['name' => 'Ford Transit']);

        $response = $this->getJson(route('setups.vehicles.cars.index', ['search' => 'Toyota']));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    });

    it('can list trashed cars', function () {
        $car = Car::factory()->create();
        $car->delete();

        $response = $this->getJson(route('setups.vehicles.cars.trashed'));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    });

    it('can restore a car', function () {
        $car = Car::factory()->create();
        $car->delete();

        $response = $this->patchJson(route('setups.vehicles.cars.restore', $car->id));

        $response->assertOk();
        $this->assertDatabaseHas('cars', ['id' => $car->id, 'deleted_at' => null]);
    });

    it('can force delete a car', function () {
        $car = Car::factory()->create();
        $car->delete();

        $response = $this->deleteJson(route('setups.vehicles.cars.force-delete', $car->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('cars', ['id' => $car->id]);
    });
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Setups/Vehicle/CarTest.php
```

Expected: FAIL — model/routes not defined yet.

- [ ] **Step 3: Implement Car model**

Create `app/Models/Setups/Vehicle/Car.php`:
```php
<?php

namespace App\Models\Setups\Vehicle;

use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Database\Factories\Setups\Vehicle\CarFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Car extends Model
{
    use HasFactory, SoftDeletes, Authorable, Searchable, Sortable, HasBooleanFilters;

    protected $fillable = ['name', 'plate_number', 'year', 'color', 'make', 'model', 'note', 'is_active'];

    protected $searchable = ['name', 'plate_number', 'make', 'model', 'color', 'note'];

    protected $casts = [
        'is_active' => 'boolean',
        'year'      => 'integer',
    ];

    protected static function newFactory(): CarFactory
    {
        return CarFactory::new();
    }

    public function refills()
    {
        return $this->hasMany(CarRefill::class, 'car_id');
    }
}
```

- [ ] **Step 4: Implement CarFactory**

Create `database/factories/Setups/Vehicle/CarFactory.php`:
```php
<?php

namespace Database\Factories\Setups\Vehicle;

use App\Models\Setups\Vehicle\Car;
use Illuminate\Database\Eloquent\Factories\Factory;

class CarFactory extends Factory
{
    protected $model = Car::class;

    public function definition(): array
    {
        return [
            'name'         => $this->faker->randomElement(['Toyota', 'Ford', 'Nissan']) . ' ' . $this->faker->word(),
            'plate_number' => strtoupper($this->faker->bothify('???-####')),
            'year'         => $this->faker->numberBetween(2010, 2024),
            'color'        => $this->faker->safeColorName(),
            'make'         => $this->faker->randomElement(['Toyota', 'Ford', 'Nissan', 'Mitsubishi']),
            'model'        => $this->faker->word(),
            'note'         => $this->faker->optional()->sentence(),
            'is_active'    => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
```

- [ ] **Step 5: Implement CarResource**

Create `app/Http/Resources/Api/Setups/Vehicle/CarResource.php`:
```php
<?php

namespace App\Http\Resources\Api\Setups\Vehicle;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'plate_number' => $this->plate_number,
            'year'         => $this->year,
            'color'        => $this->color,
            'make'         => $this->make,
            'model'        => $this->model,
            'note'         => $this->note,
            'is_active'    => $this->is_active,
            'created_by'   => ['id' => $this->createdBy?->id, 'name' => $this->createdBy?->name],
            'updated_by'   => ['id' => $this->updatedBy?->id, 'name' => $this->updatedBy?->name],
            'created_at'   => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'   => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
```

- [ ] **Step 6: Implement Cars form requests**

Create `app/Http/Requests/Api/Setups/Vehicle/CarsStoreRequest.php`:
```php
<?php

namespace App\Http\Requests\Api\Setups\Vehicle;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;

class CarsStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleHelper::canSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'name'         => 'required|string|max:200',
            'plate_number' => 'nullable|string|max:50',
            'year'         => 'nullable|integer|min:1990|max:' . (date('Y') + 1),
            'color'        => 'nullable|string|max:50',
            'make'         => 'nullable|string|max:100',
            'model'        => 'nullable|string|max:100',
            'note'         => 'nullable|string',
            'is_active'    => 'boolean',
        ];
    }
}
```

Create `app/Http/Requests/Api/Setups/Vehicle/CarsUpdateRequest.php`:
```php
<?php

namespace App\Http\Requests\Api\Setups\Vehicle;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;

class CarsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleHelper::canSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'name'         => 'required|string|max:200',
            'plate_number' => 'nullable|string|max:50',
            'year'         => 'nullable|integer|min:1990|max:' . (date('Y') + 1),
            'color'        => 'nullable|string|max:50',
            'make'         => 'nullable|string|max:100',
            'model'        => 'nullable|string|max:100',
            'note'         => 'nullable|string',
            'is_active'    => 'boolean',
        ];
    }
}
```

- [ ] **Step 7: Implement CarsController**

Create `app/Http/Controllers/Api/Setups/Vehicle/CarsController.php`:
```php
<?php

namespace App\Http\Controllers\Api\Setups\Vehicle;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\Vehicle\CarsStoreRequest;
use App\Http\Requests\Api\Setups\Vehicle\CarsUpdateRequest;
use App\Http\Resources\Api\Setups\Vehicle\CarResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\Vehicle\Car;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CarsController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = Car::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $cars = $this->applyPagination($query, $request);

        return ApiResponse::paginated('Cars retrieved successfully', $cars, CarResource::class);
    }

    public function store(CarsStoreRequest $request): JsonResponse
    {
        $car = Car::create($request->validated());
        $car->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store('Car created successfully', new CarResource($car));
    }

    public function show(Car $car): JsonResponse
    {
        $car->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show('Car retrieved successfully', new CarResource($car));
    }

    public function update(CarsUpdateRequest $request, Car $car): JsonResponse
    {
        $car->update($request->validated());
        $car->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update('Car updated successfully', new CarResource($car));
    }

    public function destroy(Car $car): JsonResponse
    {
        if (!RoleHelper::canSuperAdmin()) {
            return ApiResponse::forbidden('You are not authorized');
        }

        $car->delete();

        return ApiResponse::delete('Car deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = Car::onlyTrashed()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        $cars = $this->applyPagination($query, $request);

        return ApiResponse::paginated('Trashed cars retrieved successfully', $cars, CarResource::class);
    }

    public function restore(int $id): JsonResponse
    {
        $car = Car::onlyTrashed()->findOrFail($id);
        $car->restore();
        $car->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update('Car restored successfully', new CarResource($car));
    }

    public function forceDelete(int $id): JsonResponse
    {
        $car = Car::onlyTrashed()->findOrFail($id);
        $car->forceDelete();

        return ApiResponse::delete('Car permanently deleted successfully');
    }
}
```

- [ ] **Step 8: Add cars routes inside the vehicles group in routes/api.php**

Add the import:
```php
use App\Http\Controllers\Api\Setups\Vehicle\CarsController;
```

Inside the existing `vehicles` prefix group (after gas-stations block):
```php
            // Cars
            Route::controller(CarsController::class)->prefix('cars')->name('cars.')->group(function () {
                Route::get('trashed', 'trashed')->name('trashed');
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('{car}', 'show')->name('show');
                Route::put('{car}', 'update')->name('update');
                Route::delete('{car}', 'destroy')->name('destroy');
                Route::patch('{id}/restore', 'restore')->name('restore');
                Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
            });
```

- [ ] **Step 9: Run car tests**

```bash
php artisan test tests/Feature/Setups/Vehicle/CarTest.php
```

Expected: all tests PASS.

- [ ] **Step 10: Commit**

```bash
git add app/Models/Setups/Vehicle/Car.php database/factories/Setups/Vehicle/CarFactory.php app/Http/Resources/Api/Setups/Vehicle/CarResource.php app/Http/Requests/Api/Setups/Vehicle/Cars*.php app/Http/Controllers/Api/Setups/Vehicle/CarsController.php routes/api.php tests/Feature/Setups/Vehicle/CarTest.php
git commit -m "feat: implement cars CRUD"
```

---

## Task 5: CarRefill — Model (with km_driven + balance logic), Factory, Resource, Requests, Controller, Routes, Tests

**Files:**
- Create: `app/Models/Setups/Vehicle/CarRefill.php`
- Create: `database/factories/Setups/Vehicle/CarRefillFactory.php`
- Create: `app/Http/Resources/Api/Setups/Vehicle/CarRefillResource.php`
- Create: `app/Http/Requests/Api/Setups/Vehicle/CarRefillsStoreRequest.php`
- Create: `app/Http/Requests/Api/Setups/Vehicle/CarRefillsUpdateRequest.php`
- Create: `app/Http/Controllers/Api/Setups/Vehicle/CarRefillsController.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/Setups/Vehicle/CarRefillTest.php`

- [ ] **Step 1: Write failing CarRefill tests**

Create `tests/Feature/Setups/Vehicle/CarRefillTest.php`:
```php
<?php

use App\Models\Employees\Employee;
use App\Models\Setups\Vehicle\Car;
use App\Models\Setups\Vehicle\CarRefill;
use App\Models\Setups\Vehicle\GasStation;
use App\Models\User;

uses()->group('api', 'setup', 'vehicles', 'vehicles.car-refills');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');

    $this->car     = Car::factory()->create();
    $this->station = GasStation::factory()->create(['balance' => 0]);
    $this->driver  = Employee::factory()->create();
});

describe('Car Refills API', function () {
    it('can create a car refill and updates gas station balance', function () {
        $data = [
            'date'           => '2025-12-25 10:00:00',
            'car_id'         => $this->car->id,
            'gas_station_id' => $this->station->id,
            'driver_id'      => $this->driver->id,
            'odometer'       => 1000,
            'amount'         => 50,
        ];

        $response = $this->postJson(route('setups.vehicles.car-refills.store'), $data);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'code', 'date', 'km_driven', 'km_cost', 'amount', 'car', 'gas_station', 'driver']]);

        $this->assertDatabaseHas('car_refills', [
            'car_id'         => $this->car->id,
            'gas_station_id' => $this->station->id,
            'odometer'       => 1000,
            'km_driven'      => 0,
        ]);

        $this->assertDatabaseHas('gas_stations', ['id' => $this->station->id, 'balance' => 50]);
    });

    it('auto-generates KM prefixed code', function () {
        $refill = CarRefill::factory()->create(['car_id' => $this->car->id, 'gas_station_id' => $this->station->id, 'driver_id' => $this->driver->id]);

        expect($refill->code)->toStartWith('KM');
    });

    it('calculates km_driven from previous odometer', function () {
        CarRefill::factory()->create([
            'car_id'         => $this->car->id,
            'gas_station_id' => $this->station->id,
            'driver_id'      => $this->driver->id,
            'odometer'       => 1000,
            'date'           => '2025-12-20 10:00:00',
        ]);

        $data = [
            'date'           => '2025-12-25 10:00:00',
            'car_id'         => $this->car->id,
            'gas_station_id' => $this->station->id,
            'driver_id'      => $this->driver->id,
            'odometer'       => 1302,
            'amount'         => 30,
        ];

        $response = $this->postJson(route('setups.vehicles.car-refills.store'), $data);

        $response->assertCreated()->assertJson(['data' => ['km_driven' => 302]]);
    });

    it('km_cost is calculated in response', function () {
        CarRefill::factory()->create([
            'car_id' => $this->car->id, 'gas_station_id' => $this->station->id,
            'driver_id' => $this->driver->id, 'odometer' => 1000, 'date' => '2025-12-20 10:00:00',
        ]);

        $response = $this->postJson(route('setups.vehicles.car-refills.store'), [
            'date' => '2025-12-25 10:00:00', 'car_id' => $this->car->id,
            'gas_station_id' => $this->station->id, 'driver_id' => $this->driver->id,
            'odometer' => 1100, 'amount' => 20,
        ]);

        $response->assertCreated();
        $kmCost = $response->json('data.km_cost');
        expect(round((float) $kmCost, 4))->toBe(round(20 / 100, 4));
    });

    it('soft delete reverses gas station balance', function () {
        $refill = CarRefill::factory()->create([
            'car_id' => $this->car->id, 'gas_station_id' => $this->station->id,
            'driver_id' => $this->driver->id, 'amount' => 50,
        ]);
        $this->station->update(['balance' => 50]);

        $response = $this->deleteJson(route('setups.vehicles.car-refills.destroy', $refill));

        $response->assertNoContent();
        $this->assertDatabaseHas('gas_stations', ['id' => $this->station->id, 'balance' => 0]);
    });

    it('restore re-applies gas station balance', function () {
        $refill = CarRefill::factory()->create([
            'car_id' => $this->car->id, 'gas_station_id' => $this->station->id,
            'driver_id' => $this->driver->id, 'amount' => 50,
        ]);
        $refill->delete();
        $this->station->update(['balance' => 0]);

        $response = $this->patchJson(route('setups.vehicles.car-refills.restore', $refill->id));

        $response->assertOk();
        $this->assertDatabaseHas('gas_stations', ['id' => $this->station->id, 'balance' => 50]);
    });

    it('can list car refills with filters', function () {
        CarRefill::factory()->count(2)->create(['car_id' => $this->car->id, 'gas_station_id' => $this->station->id, 'driver_id' => $this->driver->id]);

        $response = $this->getJson(route('setups.vehicles.car-refills.index', ['car_id' => $this->car->id]));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(2);
    });

    it('validates required fields', function () {
        $response = $this->postJson(route('setups.vehicles.car-refills.store'), []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['date', 'car_id', 'gas_station_id', 'driver_id', 'odometer', 'amount']);
    });

    it('can list trashed car refills', function () {
        $refill = CarRefill::factory()->create(['car_id' => $this->car->id, 'gas_station_id' => $this->station->id, 'driver_id' => $this->driver->id]);
        $refill->delete();

        $response = $this->getJson(route('setups.vehicles.car-refills.trashed'));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    });

    it('can force delete a car refill', function () {
        $refill = CarRefill::factory()->create(['car_id' => $this->car->id, 'gas_station_id' => $this->station->id, 'driver_id' => $this->driver->id]);
        $refill->delete();

        $response = $this->deleteJson(route('setups.vehicles.car-refills.force-delete', $refill->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('car_refills', ['id' => $refill->id]);
    });
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Setups/Vehicle/CarRefillTest.php
```

Expected: FAIL.

- [ ] **Step 3: Implement CarRefill model with km_driven + balance logic**

Create `app/Models/Setups/Vehicle/CarRefill.php`:
```php
<?php

namespace App\Models\Setups\Vehicle;

use App\Models\Employees\Employee;
use App\Models\Setting;
use App\Traits\Authorable;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Database\Factories\Setups\Vehicle\CarRefillFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarRefill extends Model
{
    use HasFactory, SoftDeletes, Authorable, Searchable, Sortable;

    protected $fillable = [
        'date', 'code', 'car_id', 'gas_station_id', 'driver_id',
        'odometer', 'km_driven', 'amount', 'invoices_count', 'note',
    ];

    protected $searchable = ['code', 'note'];

    protected $casts = [
        'date'           => 'datetime',
        'odometer'       => 'decimal:2',
        'km_driven'      => 'decimal:2',
        'amount'         => 'decimal:4',
        'invoices_count' => 'integer',
    ];

    protected static function newFactory(): CarRefillFactory
    {
        return CarRefillFactory::new();
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function car()
    {
        return $this->belongsTo(Car::class);
    }

    public function gasStation()
    {
        return $this->belongsTo(GasStation::class);
    }

    public function driver()
    {
        return $this->belongsTo(Employee::class, 'driver_id');
    }

    // ─── Code generation ──────────────────────────────────────────────────────

    public static function generateNextCode(): string
    {
        $number = Setting::getOrCreateCounter('car_refills', 'code_counter', 1000);
        return 'KM' . $number;
    }

    public static function reserveNextCode(): string
    {
        $number = Setting::incrementValue('car_refills', 'code_counter', 1, 1000);
        return 'KM' . ($number - 1);
    }

    // ─── KM Driven calculation ────────────────────────────────────────────────

    public function calculateKmDriven(): float
    {
        $previous = self::where('car_id', $this->car_id)
            ->where(function ($q) {
                $q->where('date', '<', $this->date)
                  ->orWhere(function ($q2) {
                      $q2->where('date', '=', $this->date)->where('id', '<', $this->id ?? PHP_INT_MAX);
                  });
            })
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->value('odometer');

        if ($previous === null) {
            return 0;
        }

        return max(0, $this->odometer - $previous);
    }

    public function recalculateNextRefill(): void
    {
        $next = self::where('car_id', $this->car_id)
            ->where(function ($q) {
                $q->where('date', '>', $this->date)
                  ->orWhere(function ($q2) {
                      $q2->where('date', '=', $this->date)->where('id', '>', $this->id);
                  });
            })
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc')
            ->first();

        if ($next) {
            $next->km_driven = $next->calculateKmDriven();
            $next->saveQuietly();
        }
    }

    // ─── Boot ─────────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (CarRefill $refill) {
            if (!$refill->code) {
                $refill->code = self::reserveNextCode();
            }
            $refill->km_driven = $refill->calculateKmDriven();
        });

        static::created(function (CarRefill $refill) {
            $refill->gasStation()->increment('balance', $refill->amount);
            $refill->recalculateNextRefill();
        });

        static::deleted(function (CarRefill $refill) {
            $refill->gasStation()->decrement('balance', $refill->amount);
            $refill->recalculateNextRefill();
        });

        static::restored(function (CarRefill $refill) {
            $refill->gasStation()->increment('balance', $refill->amount);
            $refill->recalculateNextRefill();
        });
    }
}
```

- [ ] **Step 4: Implement CarRefillFactory**

Create `database/factories/Setups/Vehicle/CarRefillFactory.php`:
```php
<?php

namespace Database\Factories\Setups\Vehicle;

use App\Models\Employees\Employee;
use App\Models\Setups\Vehicle\Car;
use App\Models\Setups\Vehicle\CarRefill;
use App\Models\Setups\Vehicle\GasStation;
use Illuminate\Database\Eloquent\Factories\Factory;

class CarRefillFactory extends Factory
{
    protected $model = CarRefill::class;

    public function definition(): array
    {
        return [
            'date'           => $this->faker->dateTimeBetween('-1 year', 'now'),
            'car_id'         => Car::factory(),
            'gas_station_id' => GasStation::factory(),
            'driver_id'      => Employee::factory(),
            'odometer'       => $this->faker->numberBetween(1000, 200000),
            'km_driven'      => 0,
            'amount'         => $this->faker->randomFloat(2, 10, 200),
            'invoices_count' => $this->faker->optional()->numberBetween(1, 50),
            'note'           => $this->faker->optional()->sentence(),
        ];
    }
}
```

- [ ] **Step 5: Implement CarRefillResource**

Create `app/Http/Resources/Api/Setups/Vehicle/CarRefillResource.php`:
```php
<?php

namespace App\Http\Resources\Api\Setups\Vehicle;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarRefillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $kmDriven = (float) $this->km_driven;
        $amount   = (float) $this->amount;
        $kmCost   = ($kmDriven > 0) ? round($amount / $kmDriven, 4) : null;

        return [
            'id'             => $this->id,
            'date'           => $this->date?->format('Y-m-d H:i:s'),
            'code'           => $this->code,
            'car_id'         => $this->car_id,
            'gas_station_id' => $this->gas_station_id,
            'driver_id'      => $this->driver_id,
            'odometer'       => $this->odometer,
            'km_driven'      => $this->km_driven,
            'amount'         => $this->amount,
            'km_cost'        => $kmCost,
            'invoices_count' => $this->invoices_count,
            'note'           => $this->note,
            'car' => $this->whenLoaded('car', fn() => [
                'id'   => $this->car->id,
                'name' => $this->car->name,
            ]),
            'gas_station' => $this->whenLoaded('gasStation', fn() => [
                'id'   => $this->gasStation->id,
                'name' => $this->gasStation->name,
            ]),
            'driver' => $this->whenLoaded('driver', fn() => [
                'id'   => $this->driver->id,
                'name' => $this->driver->name,
            ]),
            'created_by' => ['id' => $this->createdBy?->id, 'name' => $this->createdBy?->name],
            'updated_by' => ['id' => $this->updatedBy?->id, 'name' => $this->updatedBy?->name],
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
```

- [ ] **Step 6: Implement CarRefills form requests**

Create `app/Http/Requests/Api/Setups/Vehicle/CarRefillsStoreRequest.php`:
```php
<?php

namespace App\Http\Requests\Api\Setups\Vehicle;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;

class CarRefillsStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleHelper::canSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'date'           => 'required|date',
            'car_id'         => 'required|integer|exists:cars,id',
            'gas_station_id' => 'required|integer|exists:gas_stations,id',
            'driver_id'      => 'required|integer|exists:employees,id',
            'odometer'       => 'required|numeric|min:0',
            'amount'         => 'required|numeric|min:0',
            'invoices_count' => 'nullable|integer|min:0',
            'note'           => 'nullable|string',
        ];
    }
}
```

Create `app/Http/Requests/Api/Setups/Vehicle/CarRefillsUpdateRequest.php`:
```php
<?php

namespace App\Http\Requests\Api\Setups\Vehicle;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;

class CarRefillsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleHelper::canSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'date'           => 'required|date',
            'car_id'         => 'required|integer|exists:cars,id',
            'gas_station_id' => 'required|integer|exists:gas_stations,id',
            'driver_id'      => 'required|integer|exists:employees,id',
            'odometer'       => 'required|numeric|min:0',
            'amount'         => 'required|numeric|min:0',
            'invoices_count' => 'nullable|integer|min:0',
            'note'           => 'nullable|string',
        ];
    }
}
```

- [ ] **Step 7: Implement CarRefillsController**

Create `app/Http/Controllers/Api/Setups/Vehicle/CarRefillsController.php`:
```php
<?php

namespace App\Http\Controllers\Api\Setups\Vehicle;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\Vehicle\CarRefillsStoreRequest;
use App\Http\Requests\Api\Setups\Vehicle\CarRefillsUpdateRequest;
use App\Http\Resources\Api\Setups\Vehicle\CarRefillResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\Vehicle\CarRefill;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CarRefillsController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = CarRefill::query()
            ->with(['car', 'gasStation', 'driver', 'createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('car_id')) {
            $query->where('car_id', $request->car_id);
        }
        if ($request->has('gas_station_id')) {
            $query->where('gas_station_id', $request->gas_station_id);
        }
        if ($request->has('driver_id')) {
            $query->where('driver_id', $request->driver_id);
        }
        if ($request->has('from_date')) {
            $query->whereDate('date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('date', '<=', $request->to_date);
        }

        $refills = $this->applyPagination($query, $request);

        return ApiResponse::paginated('Car refills retrieved successfully', $refills, CarRefillResource::class);
    }

    public function store(CarRefillsStoreRequest $request): JsonResponse
    {
        $refill = CarRefill::create($request->validated());
        $refill->load(['car', 'gasStation', 'driver', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store('Car refill created successfully', new CarRefillResource($refill));
    }

    public function show(CarRefill $carRefill): JsonResponse
    {
        $carRefill->load(['car', 'gasStation', 'driver', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show('Car refill retrieved successfully', new CarRefillResource($carRefill));
    }

    public function update(CarRefillsUpdateRequest $request, CarRefill $carRefill): JsonResponse
    {
        $validated = $request->validated();

        $oldAmount         = (float) $carRefill->amount;
        $oldGasStationId   = $carRefill->gas_station_id;
        $odometerChanged   = isset($validated['odometer']) && (float) $validated['odometer'] !== (float) $carRefill->odometer;

        // Reverse old balance on old station
        if ($oldGasStationId !== ($validated['gas_station_id'] ?? $oldGasStationId)) {
            $carRefill->gasStation()->decrement('balance', $oldAmount);
        }

        $carRefill->fill($validated);

        if ($odometerChanged || isset($validated['car_id'])) {
            $carRefill->km_driven = $carRefill->calculateKmDriven();
        }

        $carRefill->save();

        // Adjust balance: apply new amount
        $newAmount       = (float) $carRefill->amount;
        $newGasStationId = $carRefill->gas_station_id;

        if ($oldGasStationId !== $newGasStationId) {
            $carRefill->gasStation()->increment('balance', $newAmount);
        } else {
            $diff = $newAmount - $oldAmount;
            if ($diff !== 0.0) {
                $carRefill->gasStation()->increment('balance', $diff);
            }
        }

        if ($odometerChanged) {
            $carRefill->recalculateNextRefill();
        }

        $carRefill->load(['car', 'gasStation', 'driver', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update('Car refill updated successfully', new CarRefillResource($carRefill));
    }

    public function destroy(CarRefill $carRefill): JsonResponse
    {
        if (!RoleHelper::canSuperAdmin()) {
            return ApiResponse::forbidden('You are not authorized');
        }

        $carRefill->delete();

        return ApiResponse::delete('Car refill deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = CarRefill::onlyTrashed()
            ->with(['car', 'gasStation', 'driver', 'createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        $refills = $this->applyPagination($query, $request);

        return ApiResponse::paginated('Trashed car refills retrieved successfully', $refills, CarRefillResource::class);
    }

    public function restore(int $id): JsonResponse
    {
        $refill = CarRefill::onlyTrashed()->findOrFail($id);
        $refill->restore();
        $refill->load(['car', 'gasStation', 'driver', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update('Car refill restored successfully', new CarRefillResource($refill));
    }

    public function forceDelete(int $id): JsonResponse
    {
        $refill = CarRefill::onlyTrashed()->findOrFail($id);
        $refill->forceDelete();

        return ApiResponse::delete('Car refill permanently deleted successfully');
    }
}
```

- [ ] **Step 8: Add car-refills routes inside vehicles group**

Add the import:
```php
use App\Http\Controllers\Api\Setups\Vehicle\CarRefillsController;
```

Inside the `vehicles` prefix group:
```php
            // Car Refills
            Route::controller(CarRefillsController::class)->prefix('car-refills')->name('car-refills.')->group(function () {
                Route::get('trashed', 'trashed')->name('trashed');
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('{carRefill}', 'show')->name('show');
                Route::put('{carRefill}', 'update')->name('update');
                Route::delete('{carRefill}', 'destroy')->name('destroy');
                Route::patch('{id}/restore', 'restore')->name('restore');
                Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
            });
```

- [ ] **Step 9: Run car refill tests**

```bash
php artisan test tests/Feature/Setups/Vehicle/CarRefillTest.php
```

Expected: all tests PASS.

- [ ] **Step 10: Commit**

```bash
git add app/Models/Setups/Vehicle/CarRefill.php database/factories/Setups/Vehicle/CarRefillFactory.php app/Http/Resources/Api/Setups/Vehicle/CarRefillResource.php app/Http/Requests/Api/Setups/Vehicle/CarRefills*.php app/Http/Controllers/Api/Setups/Vehicle/CarRefillsController.php routes/api.php tests/Feature/Setups/Vehicle/CarRefillTest.php
git commit -m "feat: implement car refills with km_driven auto-calculation and gas station balance"
```

---

## Task 6: GasStationPayment — Model (with balance logic), Factory, Resource, Requests, Controller, Routes, Tests

**Files:**
- Create: `app/Models/Setups/Vehicle/GasStationPayment.php`
- Create: `database/factories/Setups/Vehicle/GasStationPaymentFactory.php`
- Create: `app/Http/Resources/Api/Setups/Vehicle/GasStationPaymentResource.php`
- Create: `app/Http/Requests/Api/Setups/Vehicle/GasStationPaymentsStoreRequest.php`
- Create: `app/Http/Requests/Api/Setups/Vehicle/GasStationPaymentsUpdateRequest.php`
- Create: `app/Http/Controllers/Api/Setups/Vehicle/GasStationPaymentsController.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/Setups/Vehicle/GasStationPaymentTest.php`

- [ ] **Step 1: Write failing GasStationPayment tests**

Create `tests/Feature/Setups/Vehicle/GasStationPaymentTest.php`:
```php
<?php

use App\Models\Accounts\Account;
use App\Models\Setups\Vehicle\GasStation;
use App\Models\Setups\Vehicle\GasStationPayment;
use App\Models\User;

uses()->group('api', 'setup', 'vehicles', 'vehicles.gas-station-payments');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');

    $this->station = GasStation::factory()->create(['balance' => 200]);
    $this->account = Account::factory()->create();
});

describe('Gas Station Payments API', function () {
    it('can create a payment and reduces gas station balance', function () {
        $data = [
            'date'           => '2025-12-25 10:00:00',
            'gas_station_id' => $this->station->id,
            'account_id'     => $this->account->id,
            'amount'         => 100,
        ];

        $response = $this->postJson(route('setups.vehicles.gas-station-payments.store'), $data);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'code', 'date', 'amount', 'gas_station', 'account']]);

        $this->assertDatabaseHas('gas_station_payments', ['gas_station_id' => $this->station->id, 'amount' => 100]);
        $this->assertDatabaseHas('gas_stations', ['id' => $this->station->id, 'balance' => 100]);
    });

    it('auto-generates GS prefixed code', function () {
        $payment = GasStationPayment::factory()->create(['gas_station_id' => $this->station->id, 'account_id' => $this->account->id]);

        expect($payment->code)->toStartWith('GS');
    });

    it('soft delete restores gas station balance', function () {
        $payment = GasStationPayment::factory()->create([
            'gas_station_id' => $this->station->id,
            'account_id'     => $this->account->id,
            'amount'         => 100,
        ]);
        $this->station->update(['balance' => 100]);

        $response = $this->deleteJson(route('setups.vehicles.gas-station-payments.destroy', $payment));

        $response->assertNoContent();
        $this->assertDatabaseHas('gas_stations', ['id' => $this->station->id, 'balance' => 200]);
    });

    it('restore reduces gas station balance again', function () {
        $payment = GasStationPayment::factory()->create([
            'gas_station_id' => $this->station->id,
            'account_id'     => $this->account->id,
            'amount'         => 100,
        ]);
        $payment->delete();
        $this->station->update(['balance' => 200]);

        $response = $this->patchJson(route('setups.vehicles.gas-station-payments.restore', $payment->id));

        $response->assertOk();
        $this->assertDatabaseHas('gas_stations', ['id' => $this->station->id, 'balance' => 100]);
    });

    it('can list payments', function () {
        GasStationPayment::factory()->count(2)->create(['gas_station_id' => $this->station->id, 'account_id' => $this->account->id]);

        $response = $this->getJson(route('setups.vehicles.gas-station-payments.index'));

        $response->assertOk()->assertJsonStructure(['data' => ['*' => ['id', 'code', 'date', 'amount']], 'pagination']);
    });

    it('can filter payments by gas_station_id', function () {
        $otherStation = GasStation::factory()->create();
        GasStationPayment::factory()->count(2)->create(['gas_station_id' => $this->station->id, 'account_id' => $this->account->id]);
        GasStationPayment::factory()->create(['gas_station_id' => $otherStation->id, 'account_id' => $this->account->id]);

        $response = $this->getJson(route('setups.vehicles.gas-station-payments.index', ['gas_station_id' => $this->station->id]));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(2);
    });

    it('validates required fields', function () {
        $response = $this->postJson(route('setups.vehicles.gas-station-payments.store'), []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['date', 'gas_station_id', 'account_id', 'amount']);
    });

    it('can show a payment', function () {
        $payment = GasStationPayment::factory()->create(['gas_station_id' => $this->station->id, 'account_id' => $this->account->id]);

        $response = $this->getJson(route('setups.vehicles.gas-station-payments.show', $payment));

        $response->assertOk()->assertJson(['data' => ['id' => $payment->id]]);
    });

    it('can update payment amount and adjusts balance', function () {
        $payment = GasStationPayment::factory()->create([
            'gas_station_id' => $this->station->id, 'account_id' => $this->account->id, 'amount' => 100,
        ]);
        $this->station->update(['balance' => 100]);

        $response = $this->putJson(route('setups.vehicles.gas-station-payments.update', $payment), [
            'date'           => $payment->date->format('Y-m-d H:i:s'),
            'gas_station_id' => $this->station->id,
            'account_id'     => $this->account->id,
            'amount'         => 150,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('gas_stations', ['id' => $this->station->id, 'balance' => 50]);
    });

    it('can list trashed payments', function () {
        $payment = GasStationPayment::factory()->create(['gas_station_id' => $this->station->id, 'account_id' => $this->account->id]);
        $payment->delete();

        $response = $this->getJson(route('setups.vehicles.gas-station-payments.trashed'));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    });

    it('can force delete a payment', function () {
        $payment = GasStationPayment::factory()->create(['gas_station_id' => $this->station->id, 'account_id' => $this->account->id]);
        $payment->delete();

        $response = $this->deleteJson(route('setups.vehicles.gas-station-payments.force-delete', $payment->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('gas_station_payments', ['id' => $payment->id]);
    });
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Setups/Vehicle/GasStationPaymentTest.php
```

Expected: FAIL.

- [ ] **Step 3: Implement GasStationPayment model**

Create `app/Models/Setups/Vehicle/GasStationPayment.php`:
```php
<?php

namespace App\Models\Setups\Vehicle;

use App\Models\Accounts\Account;
use App\Models\Setting;
use App\Traits\Authorable;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Database\Factories\Setups\Vehicle\GasStationPaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GasStationPayment extends Model
{
    use HasFactory, SoftDeletes, Authorable, Searchable, Sortable;

    protected $fillable = ['date', 'code', 'gas_station_id', 'account_id', 'amount', 'note'];

    protected $searchable = ['code', 'note'];

    protected $casts = [
        'date'   => 'datetime',
        'amount' => 'decimal:4',
    ];

    protected static function newFactory(): GasStationPaymentFactory
    {
        return GasStationPaymentFactory::new();
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function gasStation()
    {
        return $this->belongsTo(GasStation::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    // ─── Code generation ──────────────────────────────────────────────────────

    public static function generateNextCode(): string
    {
        $number = Setting::getOrCreateCounter('gas_station_payments', 'code_counter', 100);
        return 'GS' . $number;
    }

    public static function reserveNextCode(): string
    {
        $number = Setting::incrementValue('gas_station_payments', 'code_counter', 1, 100);
        return 'GS' . ($number - 1);
    }

    // ─── Boot ─────────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (GasStationPayment $payment) {
            if (!$payment->code) {
                $payment->code = self::reserveNextCode();
            }
        });

        static::created(function (GasStationPayment $payment) {
            $payment->gasStation()->decrement('balance', $payment->amount);
        });

        static::deleted(function (GasStationPayment $payment) {
            $payment->gasStation()->increment('balance', $payment->amount);
        });

        static::restored(function (GasStationPayment $payment) {
            $payment->gasStation()->decrement('balance', $payment->amount);
        });
    }
}
```

- [ ] **Step 4: Implement GasStationPaymentFactory**

Create `database/factories/Setups/Vehicle/GasStationPaymentFactory.php`:
```php
<?php

namespace Database\Factories\Setups\Vehicle;

use App\Models\Accounts\Account;
use App\Models\Setups\Vehicle\GasStation;
use App\Models\Setups\Vehicle\GasStationPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

class GasStationPaymentFactory extends Factory
{
    protected $model = GasStationPayment::class;

    public function definition(): array
    {
        return [
            'date'           => $this->faker->dateTimeBetween('-1 year', 'now'),
            'gas_station_id' => GasStation::factory(),
            'account_id'     => Account::factory(),
            'amount'         => $this->faker->randomFloat(2, 50, 1000),
            'note'           => $this->faker->optional()->sentence(),
        ];
    }
}
```

- [ ] **Step 5: Implement GasStationPaymentResource**

Create `app/Http/Resources/Api/Setups/Vehicle/GasStationPaymentResource.php`:
```php
<?php

namespace App\Http\Resources\Api\Setups\Vehicle;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GasStationPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'date'           => $this->date?->format('Y-m-d H:i:s'),
            'code'           => $this->code,
            'gas_station_id' => $this->gas_station_id,
            'account_id'     => $this->account_id,
            'amount'         => $this->amount,
            'note'           => $this->note,
            'gas_station' => $this->whenLoaded('gasStation', fn() => [
                'id'   => $this->gasStation->id,
                'name' => $this->gasStation->name,
            ]),
            'account' => $this->whenLoaded('account', fn() => [
                'id'   => $this->account->id,
                'name' => $this->account->name,
            ]),
            'created_by' => ['id' => $this->createdBy?->id, 'name' => $this->createdBy?->name],
            'updated_by' => ['id' => $this->updatedBy?->id, 'name' => $this->updatedBy?->name],
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
```

- [ ] **Step 6: Implement GasStationPayments form requests**

Create `app/Http/Requests/Api/Setups/Vehicle/GasStationPaymentsStoreRequest.php`:
```php
<?php

namespace App\Http\Requests\Api\Setups\Vehicle;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;

class GasStationPaymentsStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleHelper::canSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'date'           => 'required|date',
            'gas_station_id' => 'required|integer|exists:gas_stations,id',
            'account_id'     => 'required|integer|exists:accounts,id',
            'amount'         => 'required|numeric|min:0',
            'note'           => 'nullable|string',
        ];
    }
}
```

Create `app/Http/Requests/Api/Setups/Vehicle/GasStationPaymentsUpdateRequest.php`:
```php
<?php

namespace App\Http\Requests\Api\Setups\Vehicle;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;

class GasStationPaymentsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleHelper::canSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'date'           => 'required|date',
            'gas_station_id' => 'required|integer|exists:gas_stations,id',
            'account_id'     => 'required|integer|exists:accounts,id',
            'amount'         => 'required|numeric|min:0',
            'note'           => 'nullable|string',
        ];
    }
}
```

- [ ] **Step 7: Implement GasStationPaymentsController**

Create `app/Http/Controllers/Api/Setups/Vehicle/GasStationPaymentsController.php`:
```php
<?php

namespace App\Http\Controllers\Api\Setups\Vehicle;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\Vehicle\GasStationPaymentsStoreRequest;
use App\Http\Requests\Api\Setups\Vehicle\GasStationPaymentsUpdateRequest;
use App\Http\Resources\Api\Setups\Vehicle\GasStationPaymentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\Vehicle\GasStationPayment;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GasStationPaymentsController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = GasStationPayment::query()
            ->with(['gasStation', 'account', 'createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('gas_station_id')) {
            $query->where('gas_station_id', $request->gas_station_id);
        }
        if ($request->has('account_id')) {
            $query->where('account_id', $request->account_id);
        }
        if ($request->has('from_date')) {
            $query->whereDate('date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('date', '<=', $request->to_date);
        }

        $payments = $this->applyPagination($query, $request);

        return ApiResponse::paginated('Gas station payments retrieved successfully', $payments, GasStationPaymentResource::class);
    }

    public function store(GasStationPaymentsStoreRequest $request): JsonResponse
    {
        $payment = GasStationPayment::create($request->validated());
        $payment->load(['gasStation', 'account', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store('Gas station payment created successfully', new GasStationPaymentResource($payment));
    }

    public function show(GasStationPayment $gasStationPayment): JsonResponse
    {
        $gasStationPayment->load(['gasStation', 'account', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show('Gas station payment retrieved successfully', new GasStationPaymentResource($gasStationPayment));
    }

    public function update(GasStationPaymentsUpdateRequest $request, GasStationPayment $gasStationPayment): JsonResponse
    {
        $validated      = $request->validated();
        $oldAmount      = (float) $gasStationPayment->amount;
        $oldStationId   = $gasStationPayment->gas_station_id;
        $newStationId   = $validated['gas_station_id'];

        if ($oldStationId !== $newStationId) {
            // Reverse on old station, apply on new station after save
            $gasStationPayment->gasStation()->increment('balance', $oldAmount);
        }

        $gasStationPayment->update($validated);

        $newAmount = (float) $gasStationPayment->amount;

        if ($oldStationId !== $newStationId) {
            $gasStationPayment->gasStation()->decrement('balance', $newAmount);
        } else {
            $diff = $newAmount - $oldAmount;
            if ($diff !== 0.0) {
                $gasStationPayment->gasStation()->decrement('balance', $diff);
            }
        }

        $gasStationPayment->load(['gasStation', 'account', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update('Gas station payment updated successfully', new GasStationPaymentResource($gasStationPayment));
    }

    public function destroy(GasStationPayment $gasStationPayment): JsonResponse
    {
        if (!RoleHelper::canSuperAdmin()) {
            return ApiResponse::forbidden('You are not authorized');
        }

        $gasStationPayment->delete();

        return ApiResponse::delete('Gas station payment deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = GasStationPayment::onlyTrashed()
            ->with(['gasStation', 'account', 'createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        $payments = $this->applyPagination($query, $request);

        return ApiResponse::paginated('Trashed gas station payments retrieved successfully', $payments, GasStationPaymentResource::class);
    }

    public function restore(int $id): JsonResponse
    {
        $payment = GasStationPayment::onlyTrashed()->findOrFail($id);
        $payment->restore();
        $payment->load(['gasStation', 'account', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update('Gas station payment restored successfully', new GasStationPaymentResource($payment));
    }

    public function forceDelete(int $id): JsonResponse
    {
        $payment = GasStationPayment::onlyTrashed()->findOrFail($id);
        $payment->forceDelete();

        return ApiResponse::delete('Gas station payment permanently deleted successfully');
    }
}
```

- [ ] **Step 8: Add gas-station-payments routes inside vehicles group**

Add the import:
```php
use App\Http\Controllers\Api\Setups\Vehicle\GasStationPaymentsController;
```

Inside the `vehicles` prefix group:
```php
            // Gas Station Payments
            Route::controller(GasStationPaymentsController::class)->prefix('gas-station-payments')->name('gas-station-payments.')->group(function () {
                Route::get('trashed', 'trashed')->name('trashed');
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('{gasStationPayment}', 'show')->name('show');
                Route::put('{gasStationPayment}', 'update')->name('update');
                Route::delete('{gasStationPayment}', 'destroy')->name('destroy');
                Route::patch('{id}/restore', 'restore')->name('restore');
                Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
            });
```

- [ ] **Step 9: Run gas station payment tests**

```bash
php artisan test tests/Feature/Setups/Vehicle/GasStationPaymentTest.php
```

Expected: all tests PASS.

- [ ] **Step 10: Commit**

```bash
git add app/Models/Setups/Vehicle/GasStationPayment.php database/factories/Setups/Vehicle/GasStationPaymentFactory.php app/Http/Resources/Api/Setups/Vehicle/GasStationPaymentResource.php app/Http/Requests/Api/Setups/Vehicle/GasStationPayments*.php app/Http/Controllers/Api/Setups/Vehicle/GasStationPaymentsController.php routes/api.php tests/Feature/Setups/Vehicle/GasStationPaymentTest.php
git commit -m "feat: implement gas station payments with balance tracking"
```

---

## Task 7: Gas Station Transactions Endpoint + PruneOrphanedSettings

**Files:**
- Modify: `app/Http/Controllers/Api/Setups/Vehicle/GasStationsController.php`
- Modify: `routes/api.php`
- Modify: `app/Console/Commands/Tenants/PruneOrphanedSettings.php`

- [ ] **Step 1: Write failing transactions test**

Append to `tests/Feature/Setups/Vehicle/GasStationTest.php` (inside the `describe` block):
```php
    it('can get combined transactions for a gas station with running balance', function () {
        $station = GasStation::factory()->create(['balance' => 0]);
        $car     = \App\Models\Setups\Vehicle\Car::factory()->create();
        $driver  = \App\Models\Employees\Employee::factory()->create();
        $account = \App\Models\Accounts\Account::factory()->create();

        // Create refill: balance should go +50
        \App\Models\Setups\Vehicle\CarRefill::factory()->create([
            'car_id' => $car->id, 'gas_station_id' => $station->id, 'driver_id' => $driver->id,
            'amount' => 50, 'date' => '2025-12-20 10:00:00',
        ]);

        // Create payment: balance should go -30
        \App\Models\Setups\Vehicle\GasStationPayment::factory()->create([
            'gas_station_id' => $station->id, 'account_id' => $account->id,
            'amount' => 30, 'date' => '2025-12-22 10:00:00',
        ]);

        $response = $this->getJson(route('setups.vehicles.gas-stations.transactions', $station->id));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => ['type', 'id', 'code', 'date', 'amount', 'balance']
                ]
            ]);

        $data = $response->json('data');
        expect($data)->toHaveCount(2);
        expect($data[0]['balance'])->toBe('50.0000');
        expect($data[1]['balance'])->toBe('20.0000');
    });
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/Setups/Vehicle/GasStationTest.php --filter="combined transactions"
```

Expected: FAIL — route not registered.

- [ ] **Step 3: Add transactions method to GasStationsController**

Add this method to `app/Http/Controllers/Api/Setups/Vehicle/GasStationsController.php`:
```php
    public function transactions(int $id): JsonResponse
    {
        $station = GasStation::findOrFail($id);

        $refills = \App\Models\Setups\Vehicle\CarRefill::where('gas_station_id', $id)
            ->with(['car:id,name', 'driver:id,name', 'createdBy:id,name'])
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->map(fn($r) => [
                'type'           => 'refill',
                'id'             => $r->id,
                'code'           => $r->code,
                'date'           => $r->date?->format('Y-m-d H:i:s'),
                'amount'         => $r->amount,
                'car'            => $r->car ? ['id' => $r->car->id, 'name' => $r->car->name] : null,
                'driver'         => $r->driver ? ['id' => $r->driver->id, 'name' => $r->driver->name] : null,
                'odometer'       => $r->odometer,
                'km_driven'      => $r->km_driven,
                'km_cost'        => ($r->km_driven > 0) ? round($r->amount / $r->km_driven, 4) : null,
                'invoices_count' => $r->invoices_count,
                'created_by'     => $r->createdBy?->name,
                'balance'        => null, // filled below
            ]);

        $payments = \App\Models\Setups\Vehicle\GasStationPayment::where('gas_station_id', $id)
            ->with(['account:id,name', 'createdBy:id,name'])
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->map(fn($p) => [
                'type'       => 'payment',
                'id'         => $p->id,
                'code'       => $p->code,
                'date'       => $p->date?->format('Y-m-d H:i:s'),
                'amount'     => $p->amount,
                'account'    => $p->account ? ['id' => $p->account->id, 'name' => $p->account->name] : null,
                'created_by' => $p->createdBy?->name,
                'balance'    => null,
            ]);

        $combined = $refills->concat($payments)
            ->sortBy([['date', 'asc'], ['id', 'asc']])
            ->values();

        $runningBalance = '0.0000';
        $result = $combined->map(function ($item) use (&$runningBalance) {
            if ($item['type'] === 'refill') {
                $runningBalance = bcadd($runningBalance, $item['amount'], 4);
            } else {
                $runningBalance = bcsub($runningBalance, $item['amount'], 4);
            }
            $item['balance'] = $runningBalance;
            return $item;
        });

        return ApiResponse::show('Gas station transactions retrieved successfully', $result);
    }
```

- [ ] **Step 4: Add transactions route inside gas-stations group in routes/api.php**

Inside the gas-stations route group (before the closing `}`):
```php
                Route::get('{id}/transactions', 'transactions')->name('transactions');
```

- [ ] **Step 5: Run transactions test**

```bash
php artisan test tests/Feature/Setups/Vehicle/GasStationTest.php
```

Expected: all tests PASS.

- [ ] **Step 6: Add code counter groups to PruneOrphanedSettings**

In `app/Console/Commands/Tenants/PruneOrphanedSettings.php`, add to the `COUNTER_GROUPS` array:
```php
        'car_refills',
        'gas_station_payments',
```

- [ ] **Step 7: Run full vehicle test suite**

```bash
php artisan test tests/Feature/Setups/Vehicle/
```

Expected: all tests PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Api/Setups/Vehicle/GasStationsController.php routes/api.php app/Console/Commands/Tenants/PruneOrphanedSettings.php tests/Feature/Setups/Vehicle/GasStationTest.php
git commit -m "feat: add gas station transactions endpoint and register code counter groups"
```
