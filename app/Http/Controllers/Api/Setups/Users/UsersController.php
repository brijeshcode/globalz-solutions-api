<?php

namespace App\Http\Controllers\Api\Setups\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\Users\UsersStoreRequest;
use App\Http\Requests\Api\Setups\Users\UsersUpdateRequest;
use App\Http\Resources\Api\Setups\Users\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Employees\Employee;
use App\Models\User;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UsersController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->with(['employee.department:id,name', 'createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('role')) {
            $query->where('role', $request->input('role'));
        }

        $users = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Users retrieved successfully',
            $users,
            UserResource::class
        );
    }

    public function store(UsersStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        
        // Hash the password
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        // Handle employee assignment
        $employeeId = $data['employee_id'] ?? null;
        unset($data['employee_id']);

        $user = User::create($data);

        // Assign employee if provided
        if ($employeeId) {
            $employee = Employee::find($employeeId);
            $employee->update(['user_id' => $user->id]);
        }

        $user->load(['employee.department:id,name', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'User created successfully',
            new UserResource($user)
        );
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['employee.department:id,name', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show(
            'User retrieved successfully',
            new UserResource($user)
        );
    }

    public function update(UsersUpdateRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();
        
        // Hash the password if provided
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        // Handle employee assignment changes
        $employeeId = $data['employee_id'] ?? null;
        unset($data['employee_id']);

        $user->update($data);

        // Handle employee assignment/unassignment
        if ($employeeId !== $user->employee?->id) {
            // Unassign current employee if exists
            if ($user->employee) {
                $user->employee->update(['user_id' => null]);
            }
            
            // Assign new employee if provided
            if ($employeeId) {
                $employee = Employee::find($employeeId);
                $employee->update(['user_id' => $user->id]);
            }
        }

        $user->load(['employee.department:id,name', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'User updated successfully',
            new UserResource($user)
        );
    }

    public function destroy(User $user): JsonResponse
    {
        // Unassign employee before deleting user
        if ($user->employee) {
            $user->employee->update(['user_id' => null]);
        }

        $user->delete();

        return ApiResponse::delete('User deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = User::onlyTrashed()
            ->with(['employee.department:id,name', 'createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('role')) {
            $query->where('role', $request->input('role'));
        }

        $users = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed users retrieved successfully',
            $users,
            UserResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $user->restore();
        $user->load(['employee.department:id,name', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'User restored successfully',
            new UserResource($user)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $user = User::onlyTrashed()->findOrFail($id);
        
        // Unassign employee before force deleting user
        if ($user->employee) {
            $user->employee->update(['user_id' => null]);
        }
        
        $user->forceDelete();

        return ApiResponse::delete('User permanently deleted successfully');
    }

    public function getUnassignedEmployees(Request $request): JsonResponse
    {
        $query = Employee::query()
            ->whereNull('user_id')
            ->where('is_active', true)
            ->select('id', 'name', 'code')
            ->orderBy('name');

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $employees = $query->get();

        return ApiResponse::send(
            'Unassigned employees retrieved successfully',
            200,
            $employees
        );
    }

    public function status(User $user) : JsonResponse {
        $user->is_active = !$user->is_active;
        $user->save();
        return ApiResponse::send(
            'User status updated',
            200,
            $user
        );
    }
}
