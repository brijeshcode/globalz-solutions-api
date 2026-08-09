<?php

namespace App\Models\Pivots;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot record for the employee_warehouses many-to-many relation.
 *
 * @property bool $is_primary
 */
class EmployeeWarehouse extends Pivot
{
}
