# Vehicles Module Design

**Date:** 2026-05-11  
**Status:** Approved

## Overview

A module to track delivery car movement, mileage, fuel consumption, and gas station balances. Covers four entities: Gas Stations, Cars, Car Refills, and Gas Station Payments.

---

## Database Schema

### `gas_stations` (migration already exists)
| Column | Type | Notes |
|---|---|---|
| id | bigint | PK |
| name | string(200) | required |
| balance | decimal(15,4) | default 0 — debt owed to station |
| address | text | |
| note | text | nullable |
| created_by | foreignId → users | nullable |
| updated_by | foreignId → users | nullable |
| timestamps, softDeletes | | |

### `cars` (new migration)
| Column | Type | Notes |
|---|---|---|
| id | bigint | PK |
| name | string(200) | required |
| plate_number | string(50) | nullable |
| year | smallint | nullable |
| color | string(50) | nullable |
| make | string(100) | nullable |
| model | string(100) | nullable |
| note | text | nullable |
| is_active | boolean | default true |
| created_by | foreignId → users | nullable |
| updated_by | foreignId → users | nullable |
| timestamps, softDeletes | | |

### `car_refills` (new migration)
| Column | Type | Notes |
|---|---|---|
| id | bigint | PK |
| date | datetime | required |
| code | string(50) | unique, auto-generated, prefix KM (e.g. KM1000) |
| car_id | foreignId → cars | required |
| gas_station_id | foreignId → gas_stations | required |
| driver_id | foreignId → employees | required |
| odometer | decimal(10,2) | required |
| km_driven | decimal(10,2) | stored, auto-calculated on save |
| amount | decimal(15,4) | required |
| invoices_count | integer | nullable, manually entered |
| note | text | nullable |
| created_by | foreignId → users | nullable |
| updated_by | foreignId → users | nullable |
| timestamps, softDeletes | | |

### `gas_station_payments` (new migration)
| Column | Type | Notes |
|---|---|---|
| id | bigint | PK |
| date | datetime | required |
| code | string(50) | unique, auto-generated, prefix GS (e.g. GS100) |
| gas_station_id | foreignId → gas_stations | required |
| account_id | foreignId → accounts | required |
| amount | decimal(15,4) | required |
| note | text | nullable |
| created_by | foreignId → users | nullable |
| updated_by | foreignId → users | nullable |
| timestamps, softDeletes | | |

---

## Balance & Calculation Logic

### Gas Station Balance (stored on `gas_stations.balance`)
- **Debt system**: balance represents what is owed to the gas station
- Refill created → `balance += amount`
- Refill soft-deleted → `balance -= amount`
- Refill restored → `balance += amount`
- Payment created → `balance -= amount`
- Payment soft-deleted → `balance += amount`
- Payment restored → `balance -= amount`
- Implemented via Eloquent model events (`created`, `deleted`, `restored`) on `CarRefill` and `GasStationPayment` models

### KM Driven (stored on `car_refills.km_driven`)
- Calculated on create and update: find the most recent previous refill for the same car (ordered by date DESC, id DESC) → `km_driven = current_odometer - previous_odometer`
- If no previous refill exists → `km_driven = 0`
- If a refill's odometer is updated → also recalculate `km_driven` for the immediately following refill of the same car

### KM Cost (not stored — API response only)
- `km_cost = amount / km_driven` (null if `km_driven = 0`)

### Running Balance per Row (not stored — calculated when listing)
- When listing transactions for a gas station, running balance is computed as cumulative sum ordered by date, id

---

## API Endpoints

All routes under prefix `/api/setups/vehicles/`, authenticated.

### Gas Stations `/setups/vehicles/gas-stations`
| Method | URI | Action |
|---|---|---|
| GET | / | index — list with balance, filter by name |
| POST | / | store |
| GET | /{gasStation} | show |
| PUT | /{gasStation} | update |
| DELETE | /{gasStation} | destroy (soft delete) |
| GET | /trashed | trashed |
| PATCH | /{id}/restore | restore |
| DELETE | /{id}/force-delete | force delete |
| GET | /{id}/transactions | combined refills + payments with running balance |

### Cars `/setups/vehicles/cars`
| Method | URI | Action |
|---|---|---|
| GET | / | index — filters: name, plate_number, is_active |
| POST | / | store |
| GET | /{car} | show |
| PUT | /{car} | update |
| DELETE | /{car} | destroy (soft delete) |
| GET | /trashed | trashed |
| PATCH | /{id}/restore | restore |
| DELETE | /{id}/force-delete | force delete |

### Car Refills `/setups/vehicles/car-refills`
| Method | URI | Action |
|---|---|---|
| GET | / | index — filters: date range, gas_station_id, car_id, driver_id |
| POST | / | store — auto-calculates km_driven, updates gas station balance |
| GET | /{carRefill} | show |
| PUT | /{carRefill} | update — recalculates km_driven, adjusts gas station balance |
| DELETE | /{carRefill} | destroy (soft delete) — reverses gas station balance |
| GET | /trashed | trashed |
| PATCH | /{id}/restore | restore — re-applies gas station balance |
| DELETE | /{id}/force-delete | force delete |

### Gas Station Payments `/setups/vehicles/gas-station-payments`
| Method | URI | Action |
|---|---|---|
| GET | / | index — filters: date range, gas_station_id, account_id |
| POST | / | store — updates gas station balance |
| GET | /{payment} | show |
| PUT | /{payment} | update — adjusts gas station balance |
| DELETE | /{payment} | destroy (soft delete) — reverses gas station balance |
| GET | /trashed | trashed |
| PATCH | /{id}/restore | restore — re-applies gas station balance |
| DELETE | /{id}/force-delete | force delete |

---

## Code Structure

All existing `Cars` folder files will be moved/renamed to `Vehicles`.

### Controllers (`App\Http\Controllers\Api\Setups\Vehicles\`)
- `GasStationsController` (rename from gasStationsController)
- `CarsController`
- `CarRefillsController`
- `GasStationPaymentsController`

### Models (`App\Models\Setups\Vehicles\`)
- `GasStation` — traits: SoftDeletes, Authorable, Searchable, Sortable
- `Car` — traits: SoftDeletes, Authorable, Searchable, Sortable, HasBooleanFilters
- `CarRefill` — traits: SoftDeletes, Authorable, Searchable, Sortable; model events for balance + km_driven
- `GasStationPayment` — traits: SoftDeletes, Authorable, Searchable, Sortable; model events for balance

### Form Requests (`App\Http\Requests\Api\Setups\Vehicles\`)
- `GasStationsStoreRequest` / `GasStationsUpdateRequest`
- `CarsStoreRequest` / `CarsUpdateRequest`
- `CarRefillsStoreRequest` / `CarRefillsUpdateRequest`
- `GasStationPaymentsStoreRequest` / `GasStationPaymentsUpdateRequest`

### Resources (`App\Http\Resources\Api\Setups\Vehicles\`)
- `GasStationResource` — id, name, balance, address, note, created_by, updated_by
- `CarResource` — id, name, plate_number, year, color, make, model, note, is_active
- `CarRefillResource` — all fields + km_cost (calculated), car name, gas station name, driver name
- `GasStationPaymentResource` — all fields + gas station name, account name

### Migrations (new)
- `create_cars_table`
- `create_car_refills_table`
- `create_gas_station_payments_table`

### Tests (`tests\Feature\Setups\Vehicles\`)
- `GasStationTest`
- `CarTest`
- `CarRefillTest`
- `GasStationPaymentTest`

### Factories (`database\factories\Setups\Vehicles\`)
- `GasStationFactory`, `CarFactory`, `CarRefillFactory`, `GasStationPaymentFactory`

---

## Update Logic for Balance on Edit

When a car refill or gas station payment is **updated** (amount changed):
- Calculate the difference: `new_amount - old_amount`
- Apply the delta to `gas_stations.balance`

When a car refill's odometer is updated:
- Recalculate `km_driven` for the current refill (current - previous)
- Recalculate `km_driven` for the next refill for that car (next_odometer - current)
