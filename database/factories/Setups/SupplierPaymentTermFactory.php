<?php

namespace Database\Factories\Setups;

use App\Models\Setups\SupplierPaymentTerm;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Setups\SupplierPaymentTerm>
 */
class SupplierPaymentTermFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SupplierPaymentTerm::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $paymentTerms = [
            'Net 30',
            'Net 60',
            'Net 90',
            'Due on Receipt',
            '2/10 Net 30',
            '1/15 Net 45',
            'Cash on Delivery',
            'Payment in Advance',
            'Net 15',
            'Net 45',
            '3/10 Net 30',
            'Monthly Terms',
            'Quarterly Terms',
            'Credit Terms',
            'Immediate Payment',
        ];

        $descriptions = [
            'Standard payment terms for suppliers',
            'Extended payment period for bulk orders',
            'Quick payment terms for immediate settlement',
            'Flexible payment arrangement',
            'Early payment discount available',
            'Special terms for long-term partners',
            'Cash payment required upon delivery',
            'Advance payment terms for custom orders',
            'Short-term payment arrangement',
            'Standard credit terms',
        ];

        $types = ['net', 'due_on_receipt', 'cash_on_delivery', 'advance', 'credit'];
        $selectedType = $this->faker->randomElement($types);
        
        // Generate realistic days based on type
        $days = match($selectedType) {
            'due_on_receipt' => 0,
            'cash_on_delivery' => 0,
            'advance' => $this->faker->optional(0.7)->numberBetween(-30, -1), // Negative for advance
            default => $this->faker->randomElement([15, 30, 45, 60, 90])
        };

        // Generate discount terms (common for Net terms)
        $hasDiscount = $selectedType === 'net' && $this->faker->boolean(30); // 30% chance
        $discountPercentage = $hasDiscount ? $this->faker->randomElement([1, 2, 3, 5]) : null;
        $discountDays = $hasDiscount ? $this->faker->randomElement([10, 15, 20]) : null;

        return [
            'name' => $this->faker->unique()->randomElement($paymentTerms),
            'description' => $this->faker->optional(0.8)->randomElement($descriptions),
            'days' => $days,
            'type' => $selectedType,
            'discount_percentage' => $discountPercentage,
            'discount_days' => $discountDays,
            'is_active' => $this->faker->boolean(85), // 85% chance of being active
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    /**
     * Indicate that the payment term is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the payment term is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a net payment term with specific days.
     */
    public function net(int $days = 30): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'net',
            'days' => $days,
            'name' => "Net {$days}",
        ]);
    }

    /**
     * Create a payment term with early payment discount.
     */
    public function withDiscount(float $percentage = 2, int $discountDays = 10, int $netDays = 30): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'net',
            'days' => $netDays,
            'discount_percentage' => $percentage,
            'discount_days' => $discountDays,
            'name' => "{$percentage}/{$discountDays} Net {$netDays}",
        ]);
    }

    /**
     * Create a due on receipt payment term.
     */
    public function dueOnReceipt(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'due_on_receipt',
            'days' => 0,
            'name' => 'Due on Receipt',
            'discount_percentage' => null,
            'discount_days' => null,
        ]);
    }

    /**
     * Create a cash on delivery payment term.
     */
    public function cod(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'cash_on_delivery',
            'days' => 0,
            'name' => 'Cash on Delivery',
            'discount_percentage' => null,
            'discount_days' => null,
        ]);
    }

    /**
     * Create an advance payment term.
     */
    public function advance(int $days = 30): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'advance',
            'days' => -$days, // Negative for advance
            'name' => "Payment in Advance ({$days} days)",
            'discount_percentage' => null,
            'discount_days' => null,
        ]);
    }

    /**
     * Indicate that the payment term has no description.
     */
    public function withoutDescription(): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => null,
        ]);
    }

    /**
     * Set a specific name for the payment term.
     */
    public function withName(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
        ]);
    }

    /**
     * Set created_by and updated_by to the same user.
     */
    public function updatedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }
}