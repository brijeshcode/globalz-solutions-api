<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Setups\Users\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant;
use App\Models\User;
use App\Traits\HasPagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class TenantUserController extends Controller
{
    use HasPagination;

    /**
     * List all users for a specific tenant.
     */
    public function index(Request $request, Tenant $tenant)
    {
        try {
            $users = $tenant->execute(function () use ($request) {
                $query = User::query();

                if ($request->has('is_active')) {
                    $query->where('is_active', $request->boolean('is_active'));
                }

                if ($request->has('role')) {
                    $query->where('role', $request->role);
                }

                if ($request->has('search')) {
                    $search = $request->search;
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%");
                    });
                }

                return $this->applyPagination($query, $request);
            });

            return ApiResponse::paginated('Users retrieved successfully', $users, UserResource::class);

        } catch (\Exception $e) {
            Log::error('Failed to fetch users for tenant', [
                'tenant_id'   => $tenant->id,
                'tenant_name' => $tenant->name,
                'error'       => $e->getMessage(),
                'fetched_by'  => auth()->user()?->email ?? 'System',
            ]);

            return ApiResponse::serverError('Failed to fetch users: ' . $e->getMessage());
        }
    }

    /**
     * Create or update a user for a specific tenant.
     */
    public function store(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|max:255',
            'password'  => 'nullable|string|min:6',
            'role'      => ['required', 'string', Rule::in(User::getRoles())],
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $result = $tenant->execute(function () use ($validated) {
                $user = User::where('email', $validated['email'])->first();

                if ($user) {
                    $updateData = [
                        'name'       => $validated['name'],
                        'role'       => $validated['role'],
                        'updated_by' => 1,
                    ];

                    if (isset($validated['is_active'])) {
                        $updateData['is_active'] = $validated['is_active'];
                    }

                    if (!empty($validated['password'])) {
                        $updateData['password'] = bcrypt($validated['password']);
                    }

                    $user->update($updateData);

                    return ['user' => $user->fresh(), 'action' => 'updated'];
                }

                if (empty($validated['password'])) {
                    throw new \Exception('Password is required when creating a new user.');
                }

                $user = User::create([
                    'name'       => $validated['name'],
                    'email'      => $validated['email'],
                    'password'   => bcrypt($validated['password']),
                    'role'       => $validated['role'],
                    'is_active'  => $validated['is_active'] ?? true,
                    'created_by' => 1,
                    'updated_by' => 1,
                ]);

                return ['user' => $user, 'action' => 'created'];
            });

            $user    = $result['user'];
            $action  = $result['action'];
            $message = $action === 'created'
                ? 'User created successfully for tenant'
                : 'User updated successfully for tenant';

            return ApiResponse::send($message, $action === 'created' ? 201 : 200, [
                'tenant_id'   => $tenant->id,
                'tenant_name' => $tenant->name,
                'action'      => $action,
                'user'        => [
                    'id'         => $user->id,
                    'name'       => $user->name,
                    'email'      => $user->email,
                    'role'       => $user->role,
                    'is_active'  => $user->is_active,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
            ]);

        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to create/update user: ' . $e->getMessage());
        }
    }
}
