<?php

use App\Models\Setups\Expenses\ExpenseCategory;
use App\Models\User;

uses()->group('api', 'setup', 'setup.expenses', 'expense-categories');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
    
    // Helper method for base expense category data
    $this->getBaseExpenseCategoryData = function ($overrides = []) {
        return array_merge([
            'name' => 'Office Supplies',
            'description' => 'General office supplies and stationery',
            'is_active' => true,
        ], $overrides);
    };
});

describe('Expense Categories API', function () {
    it('can list expense categories', function () {
        ExpenseCategory::factory()->count(3)->create();

        $response = $this->getJson(route('setups.expenses.categories.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'is_active',
                        'is_root',
                        'has_children',
                        'parent_id',
                        'created_by',
                        'updated_by',
                        'created_at',
                        'updated_at',
                    ]
                ],
                'pagination'
            ]);

        expect($response->json('data'))->toHaveCount(3);
    });

    it('can create an expense category with minimum required fields', function () {
        $data = [
            'name' => 'Travel Expenses',
        ];

        $response = $this->postJson(route('setups.expenses.categories.store'), $data);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'is_active',
                    'is_root',
                    'has_children',
                ]
            ]);

        $this->assertDatabaseHas('expense_categories', [
            'name' => 'Travel Expenses',
            'is_active' => true,
            'parent_id' => null,
        ]);
    });

    it('can create an expense category with all fields', function () {
        $parentCategory = ExpenseCategory::factory()->create(['name' => 'Office Equipment']);

        $data = [
            'parent_id' => $parentCategory->id,
            'name' => 'Computer Hardware',
            'description' => 'Computers, laptops, and related hardware',
            'is_active' => true,
        ];

        $response = $this->postJson(route('setups.expenses.categories.store'), $data);

        $response->assertCreated()
            ->assertJson([
                'data' => [
                    'parent_id' => $parentCategory->id,
                    'name' => 'Computer Hardware',
                    'description' => 'Computers, laptops, and related hardware',
                    'is_active' => true,
                    'is_root' => false,
                ]
            ]);

        $this->assertDatabaseHas('expense_categories', [
            'parent_id' => $parentCategory->id,
            'name' => 'Computer Hardware',
            'description' => 'Computers, laptops, and related hardware',
        ]);
    });

    it('can show an expense category', function () {
        $category = ExpenseCategory::factory()->create([
            'name' => 'Marketing Expenses',
        ]);

        $response = $this->getJson(route('setups.expenses.categories.show', $category));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'is_root' => true,
                    'has_children' => false,
                ]
            ]);
    });

    it('can update an expense category', function () {
        $category = ExpenseCategory::factory()->create([
            'name' => 'Old Category Name',
            'description' => 'Old description',
        ]);

        $data = [
            'name' => 'Updated Category Name',
            'description' => 'Updated description',
            'is_active' => false,
        ];

        $response = $this->putJson(route('setups.expenses.categories.update', $category), $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $category->id,
                    'name' => 'Updated Category Name',
                    'description' => 'Updated description',
                    'is_active' => false,
                ]
            ]);

        $this->assertDatabaseHas('expense_categories', [
            'id' => $category->id,
            'name' => 'Updated Category Name',
            'description' => 'Updated description',
            'is_active' => false,
        ]);
    });

    it('can delete an expense category without children', function () {
        $category = ExpenseCategory::factory()->create();

        $response = $this->deleteJson(route('setups.expenses.categories.destroy', $category));

        $response->assertStatus(204);
        $this->assertSoftDeleted('expense_categories', ['id' => $category->id]);
    });

    it('cannot delete expense category with children', function () {
        $parentCategory = ExpenseCategory::factory()->create(['name' => 'Parent Category']);
        $childCategory = ExpenseCategory::factory()->create([
            'name' => 'Child Category',
            'parent_id' => $parentCategory->id
        ]);

        $response = $this->deleteJson(route('setups.expenses.categories.destroy', $parentCategory));

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete expense category that has children'
            ]);

        $this->assertDatabaseHas('expense_categories', [
            'id' => $parentCategory->id,
            'deleted_at' => null
        ]);
    });

    it('validates required fields when creating', function () {
        $response = $this->postJson(route('setups.expenses.categories.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates parent_id exists when provided', function () {
        $response = $this->postJson(route('setups.expenses.categories.store'), [
            'name' => 'Test Category',
            'parent_id' => 99999,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);
    });

    it('validates unique name within same parent category', function () {
        $parentCategory = ExpenseCategory::factory()->create();
        ExpenseCategory::factory()->create([
            'name' => 'Duplicate Name',
            'parent_id' => $parentCategory->id
        ]);

        $response = $this->postJson(route('setups.expenses.categories.store'), [
            'name' => 'Duplicate Name',
            'parent_id' => $parentCategory->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('allows same name in different parent categories', function () {
        $parentCategory1 = ExpenseCategory::factory()->create(['name' => 'Parent 1']);
        $parentCategory2 = ExpenseCategory::factory()->create(['name' => 'Parent 2']);
        
        ExpenseCategory::factory()->create([
            'name' => 'Same Name',
            'parent_id' => $parentCategory1->id
        ]);

        $response = $this->postJson(route('setups.expenses.categories.store'), [
            'name' => 'Same Name',
            'parent_id' => $parentCategory2->id,
        ]);

        $response->assertCreated();
    });

    it('allows same name for root categories and subcategories', function () {
        ExpenseCategory::factory()->create(['name' => 'Office Supplies', 'parent_id' => null]);
        $parentCategory = ExpenseCategory::factory()->create(['name' => 'Equipment']);

        $response = $this->postJson(route('setups.expenses.categories.store'), [
            'name' => 'Office Supplies',
            'parent_id' => $parentCategory->id,
        ]);

        $response->assertCreated();
    });

    it('prevents circular references when updating parent', function () {
        $parentCategory = ExpenseCategory::factory()->create(['name' => 'Parent']);
        $childCategory = ExpenseCategory::factory()->create([
            'name' => 'Child',
            'parent_id' => $parentCategory->id
        ]);

        $response = $this->putJson(route('setups.expenses.categories.update', $parentCategory), [
            'name' => 'Parent',
            'parent_id' => $childCategory->id, // Circular reference
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);
    });

    it('prevents category from being its own parent', function () {
        $category = ExpenseCategory::factory()->create();

        $response = $this->putJson(route('setups.expenses.categories.update', $category), [
            'name' => 'Test Category',
            'parent_id' => $category->id, // Self-parent
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);
    });

    it('can search expense categories by name', function () {
        ExpenseCategory::factory()->create(['name' => 'Searchable Category']);
        ExpenseCategory::factory()->create(['name' => 'Another Category']);

        $response = $this->getJson(route('setups.expenses.categories.index', ['search' => 'Searchable']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['name'])->toBe('Searchable Category');
    });

    it('can search expense categories by description', function () {
        ExpenseCategory::factory()->create([
            'name' => 'Category One',
            'description' => 'Searchable description content'
        ]);
        ExpenseCategory::factory()->create([
            'name' => 'Category Two',
            'description' => 'Different content'
        ]);

        $response = $this->getJson(route('setups.expenses.categories.index', ['search' => 'Searchable']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['description'])->toContain('Searchable');
    });

    it('can filter by active status', function () {
        ExpenseCategory::factory()->create(['is_active' => true]);
        ExpenseCategory::factory()->create(['is_active' => false]);

        $response = $this->getJson(route('setups.expenses.categories.index', ['is_active' => true]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['is_active'])->toBe(true);
    });

    it('can filter by parent category', function () {
        $parentCategory = ExpenseCategory::factory()->create(['name' => 'Parent']);
        $otherParent = ExpenseCategory::factory()->create(['name' => 'Other Parent']);
        
        ExpenseCategory::factory()->create([
            'name' => 'Child 1',
            'parent_id' => $parentCategory->id
        ]);
        ExpenseCategory::factory()->create([
            'name' => 'Child 2',
            'parent_id' => $parentCategory->id
        ]);
        ExpenseCategory::factory()->create([
            'name' => 'Other Child',
            'parent_id' => $otherParent->id
        ]);

        $response = $this->getJson(route('setups.expenses.categories.index', ['parent_id' => $parentCategory->id]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(2);
        foreach ($data as $category) {
            expect($category['parent_id'])->toBe($parentCategory->id);
        }
    });

    it('can filter root categories only', function () {
        ExpenseCategory::factory()->create(['name' => 'Root 1', 'parent_id' => null]);
        ExpenseCategory::factory()->create(['name' => 'Root 2', 'parent_id' => null]);
        $parent = ExpenseCategory::factory()->create(['name' => 'Parent', 'parent_id' => null]);
        ExpenseCategory::factory()->create([
            'name' => 'Child',
            'parent_id' => $parent->id
        ]);

        $response = $this->getJson(route('setups.expenses.categories.index', ['parent_id' => 'null']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(3);
        foreach ($data as $category) {
            expect($category['parent_id'])->toBeNull();
        }
    });

    it('can get tree structure with nested children', function () {
        $parent = ExpenseCategory::factory()->create(['name' => 'Office Equipment']);
        $child1 = ExpenseCategory::factory()->create([
            'name' => 'Computers',
            'parent_id' => $parent->id
        ]);
        $child2 = ExpenseCategory::factory()->create([
            'name' => 'Furniture',
            'parent_id' => $parent->id
        ]);
        $grandchild = ExpenseCategory::factory()->create([
            'name' => 'Laptops',
            'parent_id' => $child1->id
        ]);

        $response = $this->getJson(route('setups.expenses.categories.index', ['tree_structure' => true]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['name'])->toBe('Office Equipment');
        expect($data[0]['children_recursive'])->toHaveCount(2);
        
        $computerCategory = collect($data[0]['children_recursive'])->firstWhere('name', 'Computers');
        expect($computerCategory['children_recursive'])->toHaveCount(1);
        expect($computerCategory['children_recursive'][0]['name'])->toBe('Laptops');
    });

    it('can sort expense categories by name', function () {
        ExpenseCategory::factory()->create(['name' => 'Z Category']);
        ExpenseCategory::factory()->create(['name' => 'A Category']);

        $response = $this->getJson(route('setups.expenses.categories.index', [
            'sort_by' => 'name',
            'sort_direction' => 'asc'
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data[0]['name'])->toBe('A Category');
        expect($data[1]['name'])->toBe('Z Category');
    });

    it('can list trashed expense categories', function () {
        $category = ExpenseCategory::factory()->create(['name' => 'Trashed Category']);
        $category->delete();

        $response = $this->getJson(route('setups.expenses.categories.trashed'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'is_active',
                    ]
                ],
                'pagination'
            ]);

        expect($response->json('data'))->toHaveCount(1);
    });

    it('can restore a trashed expense category', function () {
        $category = ExpenseCategory::factory()->create();
        $category->delete();

        $response = $this->patchJson(route('setups.expenses.categories.restore', $category->id));

        $response->assertStatus(200);
        $this->assertDatabaseHas('expense_categories', [
            'id' => $category->id,
            'deleted_at' => null,
        ]);
    });

    it('can force delete a trashed expense category', function () {
        $category = ExpenseCategory::factory()->create();
        $category->delete();

        $response = $this->deleteJson(route('setups.expenses.categories.force-delete', $category->id));

        $response->assertStatus(200);
        $this->assertDatabaseMissing('expense_categories', ['id' => $category->id]);
    });

    it('validates maximum length for string fields', function () {
        $response = $this->postJson(route('setups.expenses.categories.store'), [
            'name' => str_repeat('a', 256), // Exceeds 255 character limit
            'description' => str_repeat('b', 501), // Exceeds 500 character limit
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'description']);
    });

    it('sets created_by and updated_by fields automatically', function () {
        $category = ExpenseCategory::factory()->create(['name' => 'Test Category']);

        expect($category->created_by)->toBe($this->user->id);
        expect($category->updated_by)->toBe($this->user->id);

        // Test update tracking
        $category->update(['name' => 'Updated Category']);
        expect($category->fresh()->updated_by)->toBe($this->user->id);
    });

    it('returns 404 for non-existent expense category', function () {
        $response = $this->getJson(route('setups.expenses.categories.show', 999));

        $response->assertNotFound();
    });

    it('can paginate expense categories', function () {
        ExpenseCategory::factory()->count(7)->create();

        $response = $this->getJson(route('setups.expenses.categories.index', ['per_page' => 3]));

        $response->assertOk();
        $data = $response->json('data');
        $pagination = $response->json('pagination');
        
        expect($data)->toHaveCount(3);
        expect($pagination['total'])->toBe(7);
        expect($pagination['per_page'])->toBe(3);
        expect($pagination['last_page'])->toBe(3);
    });
});

describe('Expense Category Hierarchical Features', function () {
    it('correctly identifies root categories', function () {
        $rootCategory = ExpenseCategory::factory()->create(['parent_id' => null]);
        $parent = ExpenseCategory::factory()->create();
        $childCategory = ExpenseCategory::factory()->create([
            'parent_id' => $parent->id
        ]);

        expect($rootCategory->is_root)->toBe(true);
        expect($childCategory->is_root)->toBe(false);
    });

    it('correctly identifies categories with children', function () {
        $parent = ExpenseCategory::factory()->create();
        $child = ExpenseCategory::factory()->create([
            'parent_id' => $parent->id
        ]);
        $childless = ExpenseCategory::factory()->create();

        expect($parent->has_children)->toBe(true);
        expect($child->has_children)->toBe(false);
        expect($childless->has_children)->toBe(false);
    });

    it('can get all ancestors of a category', function () {
        $grandparent = ExpenseCategory::factory()->create(['name' => 'Grandparent']);
        $parent = ExpenseCategory::factory()->create([
            'name' => 'Parent',
            'parent_id' => $grandparent->id
        ]);
        $child = ExpenseCategory::factory()->create([
            'name' => 'Child',
            'parent_id' => $parent->id
        ]);

        $ancestors = $child->getAllAncestors();

        expect($ancestors)->toHaveCount(2);
        expect($ancestors->first()->name)->toBe('Grandparent');
        expect($ancestors->last()->name)->toBe('Parent');
    });

    it('can get all descendants of a category', function () {
        $parent = ExpenseCategory::factory()->create(['name' => 'Parent']);
        $child1 = ExpenseCategory::factory()->create([
            'name' => 'Child 1',
            'parent_id' => $parent->id
        ]);
        $child2 = ExpenseCategory::factory()->create([
            'name' => 'Child 2',
            'parent_id' => $parent->id
        ]);
        $grandchild = ExpenseCategory::factory()->create([
            'name' => 'Grandchild',
            'parent_id' => $child1->id
        ]);

        $descendants = $parent->getAllDescendants();

        expect($descendants)->toHaveCount(3);
        $names = $descendants->pluck('name')->toArray();
        expect($names)->toContain('Child 1');
        expect($names)->toContain('Child 2');
        expect($names)->toContain('Grandchild');
    });

    it('validates deep circular references', function () {
        $grandparent = ExpenseCategory::factory()->create(['name' => 'Grandparent']);
        $parent = ExpenseCategory::factory()->create([
            'name' => 'Parent',
            'parent_id' => $grandparent->id
        ]);
        $child = ExpenseCategory::factory()->create([
            'name' => 'Child',
            'parent_id' => $parent->id
        ]);

        // Try to make grandparent a child of its grandchild (deep circular reference)
        $response = $this->putJson(route('setups.expenses.categories.update', $grandparent), [
            'name' => 'Grandparent',
            'parent_id' => $child->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);
    });

    it('can handle complex hierarchical queries', function () {
        // Create a complex hierarchy
        $office = ExpenseCategory::factory()->create(['name' => 'Office']);
        $supplies = ExpenseCategory::factory()->create([
            'name' => 'Supplies',
            'parent_id' => $office->id
        ]);
        $equipment = ExpenseCategory::factory()->create([
            'name' => 'Equipment',
            'parent_id' => $office->id
        ]);
        $stationery = ExpenseCategory::factory()->create([
            'name' => 'Stationery',
            'parent_id' => $supplies->id
        ]);
        $computers = ExpenseCategory::factory()->create([
            'name' => 'Computers',
            'parent_id' => $equipment->id
        ]);

        // Create another root category
        $travel = ExpenseCategory::factory()->create(['name' => 'Travel']);
        $transportation = ExpenseCategory::factory()->create([
            'name' => 'Transportation',
            'parent_id' => $travel->id
        ]);

        // Test filtering by specific parent
        $response = $this->getJson(route('setups.expenses.categories.index', ['parent_id' => $office->id]));
        $data = $response->json('data');
        expect($data)->toHaveCount(2); // Supplies and Equipment

        // Test root categories only
        $response = $this->getJson(route('setups.expenses.categories.index', ['parent_id' => 'null']));
        $data = $response->json('data');
        expect($data)->toHaveCount(2); // Office and Travel

        // Test tree structure
        $response = $this->getJson(route('setups.expenses.categories.index', ['tree_structure' => true]));
        $data = $response->json('data');
        expect($data)->toHaveCount(2); // Office and Travel with all nested children
    });

    it('prevents deletion of parent categories with active children', function () {
        $parent = ExpenseCategory::factory()->create(['name' => 'Parent', 'is_active' => true]);
        $activeChild = ExpenseCategory::factory()->create([
            'is_active' => true,
            'parent_id' => $parent->id
        ]);
        $inactiveChild = ExpenseCategory::factory()->create([
            'is_active' => false,
            'parent_id' => $parent->id
        ]);

        $response = $this->deleteJson(route('setups.expenses.categories.destroy', $parent));

        $response->assertStatus(422);
        $this->assertDatabaseHas('expense_categories', [
            'id' => $parent->id,
            'deleted_at' => null
        ]);
    });

    it('allows deletion of parent categories when all children are soft deleted', function () {
        $parent = ExpenseCategory::factory()->create(['name' => 'Parent']);
        $child1 = ExpenseCategory::factory()->create([
            'parent_id' => $parent->id
        ]);
        $child2 = ExpenseCategory::factory()->create([
            'parent_id' => $parent->id
        ]);

        // Soft delete all children
        $child1->delete();
        $child2->delete();

        $response = $this->deleteJson(route('setups.expenses.categories.destroy', $parent));

        $response->assertStatus(204);
        $this->assertSoftDeleted('expense_categories', ['id' => $parent->id]);
    });
});
