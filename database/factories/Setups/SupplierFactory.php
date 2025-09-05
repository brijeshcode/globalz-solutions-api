<?php

namespace Database\Factories\Setups;

use App\Models\Setups\Country;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Supplier;
use App\Models\Setups\SupplierPaymentTerm;
use App\Models\Setups\SupplierType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Setups\Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Supplier::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $companyNames = [
            'Global Manufacturing Ltd',
            'Premium Suppliers Inc',
            'International Trading Co',
            'Elite Industrial Supply',
            'Advanced Materials Corp',
            'Quality Parts & Components',
            'Professional Equipment Ltd',
            'Industrial Solutions Group',
            'Precision Manufacturing Co',
            'Strategic Supply Partners',
            'Innovation Industries Ltd',
            'Reliable Components Inc',
            'Expert Manufacturing Group',
            'Dynamic Supply Solutions',
            'Superior Materials Ltd',
            'Efficient Production Co',
            'Modern Industrial Supply',
            'Trusted Partners Ltd',
            'Excellence Manufacturing',
            'Progressive Supply Chain',
        ];

        $shipFromLocations = [
            'Shanghai, China',
            'Hong Kong',
            'Dubai, UAE',
            'Mumbai, India',
            'Istanbul, Turkey',
            'Milan, Italy',
            'Hamburg, Germany',
            'Rotterdam, Netherlands',
            'Singapore',
            'Bangkok, Thailand',
            'Guangzhou, China',
            'Seoul, South Korea',
            'Tokyo, Japan',
            'Local Warehouse',
            'Regional Distribution Center',
        ];

        $bankInfoExamples = [
            'Bank: HSBC International, Account: 1234567890, SWIFT: HBUKGB4B',
            'Bank: Standard Chartered, Account: 9876543210, SWIFT: SCBLHKHH',
            'Bank: Citibank N.A., Account: 5555666677, SWIFT: CITIUS33',
            'Bank: Deutsche Bank AG, Account: 1111222233, SWIFT: DEUTDEFF',
            'Bank: Industrial Bank, Account: 4444555566, SWIFT: IBKAHKHH',
            'Bank: Bank of China, Account: 7777888899, SWIFT: BKCHCNBJ',
            'Wire Transfer Details Available Upon Request',
            'Multiple Payment Options Available',
        ];

        $attachmentExamples = [
            'contracts/supplier_agreement_2024.pdf',
            'certificates/iso_9001_certificate.pdf',
            'documents/company_registration.pdf',
            'quality/quality_assurance_docs.pdf',
            'insurance/liability_insurance.pdf',
            'compliance/export_license.pdf',
        ];

        // Generate code starting from 1000 or use existing pattern
        $code = $this->faker->unique()->numberBetween(1000, 9999);

        return [
            // Main Info Tab
            'code' => (string) $code,
            'name' => $this->faker->unique()->randomElement($companyNames),
            'supplier_type_id' => SupplierType::factory(),
            'country_id' => Country::factory(),
            'opening_balance' => $this->faker->randomFloat(2, -50000, 100000),

            // Contact Info Tab
            'address' => $this->faker->address,
            'phone' => $this->faker->optional(0.8)->phoneNumber,
            'mobile' => $this->faker->optional(0.7)->phoneNumber,
            'url' => $this->faker->optional(0.6)->url,
            'email' => $this->faker->optional(0.9)->companyEmail,
            'contact_person' => $this->faker->optional(0.8)->name,
            'contact_person_email' => $this->faker->optional(0.7)->email,
            'contact_person_mobile' => $this->faker->optional(0.6)->phoneNumber,

            // Purchase Info Tab
            'payment_term_id' => SupplierPaymentTerm::factory(),
            'ship_from' => $this->faker->optional(0.8)->randomElement($shipFromLocations),
            'bank_info' => $this->faker->optional(0.7)->randomElement($bankInfoExamples),
            'discount_percentage' => $this->faker->optional(0.4)->randomFloat(2, 0, 15), // 40% chance, 0-15%
            'currency_id' => Currency::factory(),

            // Other Tab
            'notes' => $this->faker->optional(0.6)->paragraph,
            // 'attachments' => $this->faker->optional(0.5)->randomElements($attachmentExamples, $this->faker->numberBetween(1, 3)),

            // System Fields
            'is_active' => $this->faker->boolean(85), // 85% chance of being active
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    /**
     * Indicate that the supplier is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the supplier is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a supplier with a specific code.
     */
    public function withCode(string $code): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => $code,
        ]);
    }

    /**
     * Create a supplier with a specific name.
     */
    public function withName(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
        ]);
    }

    /**
     * Create a supplier with a specific opening balance.
     */
    public function withBalance(float $balance): static
    {
        return $this->state(fn (array $attributes) => [
            'opening_balance' => $balance,
        ]);
    }

    /**
     * Create a supplier with positive opening balance.
     */
    public function withPositiveBalance(): static
    {
        return $this->state(fn (array $attributes) => [
            'opening_balance' => $this->faker->randomFloat(2, 1000, 50000),
        ]);
    }

    /**
     * Create a supplier with negative opening balance (debt).
     */
    public function withDebt(): static
    {
        return $this->state(fn (array $attributes) => [
            'opening_balance' => $this->faker->randomFloat(2, -30000, -100),
        ]);
    }

    /**
     * Create a supplier with zero balance.
     */
    public function withZeroBalance(): static
    {
        return $this->state(fn (array $attributes) => [
            'opening_balance' => 0,
        ]);
    }

    /**
     * Create a supplier with discount.
     */
    public function withDiscount(float $percentage = null): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_percentage' => $percentage ?? $this->faker->randomFloat(2, 1, 10),
        ]);
    }

    /**
     * Create a supplier without discount.
     */
    public function withoutDiscount(): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_percentage' => null,
        ]);
    }

    /**
     * Create a supplier with complete contact information.
     */
    public function withCompleteContact(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone' => $this->faker->phoneNumber,
            'mobile' => $this->faker->phoneNumber,
            'email' => $this->faker->companyEmail,
            'contact_person' => $this->faker->name,
            'contact_person_email' => $this->faker->email,
            'contact_person_mobile' => $this->faker->phoneNumber,
            'url' => $this->faker->url,
        ]);
    }

    /**
     * Create a supplier with minimal contact information.
     */
    public function withMinimalContact(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone' => null,
            'mobile' => null,
            'url' => null,
            'email' => $this->faker->companyEmail, // Keep email as it's important
            'contact_person' => null,
            'contact_person_email' => null,
            'contact_person_mobile' => null,
        ]);
    }

    /**
     * Create a supplier with attachments.
     */
    public function withAttachments(?array $attachments = null): static
    {
        $defaultAttachments = [
            'contracts/supplier_agreement_2024.pdf',
            'certificates/iso_certification.pdf',
            'documents/company_profile.pdf',
        ];

        return $this->state(fn (array $attributes) => [
            'attachments' => $attachments ?? $defaultAttachments,
        ]);
    }

    /**
     * Create a supplier without attachments.
     */
    public function withoutAttachments(): static
    {
        return $this->state(fn (array $attributes) => [
            'attachments' => null,
        ]);
    }

    /**
     * Create a supplier for a specific country.
     */
    public function fromCountry(Country $country): static
    {
        return $this->state(fn (array $attributes) => [
            'country_id' => $country->id,
        ]);
    }

    /**
     * Create a supplier of a specific type.
     */
    public function ofType(SupplierType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'supplier_type_id' => $type->id,
        ]);
    }

    /**
     * Create a supplier with specific payment terms.
     */
    public function withPaymentTerm(SupplierPaymentTerm $paymentTerm): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_term_id' => $paymentTerm->id,
        ]);
    }

    /**
     * Create a supplier with specific currency.
     */
    public function withCurrency(Currency $currency): static
    {
        return $this->state(fn (array $attributes) => [
            'currency_id' => $currency->id,
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

    /**
     * Create a supplier for import scenario (existing code pattern).
     */
    public function forImport(string $originalCode): static
    {
        // Remove first 3 digits: 4010057 → 0057
        $newCode = strlen($originalCode) > 3 ? substr($originalCode, 3) : $originalCode;
        
        return $this->state(fn (array $attributes) => [
            'code' => $newCode,
        ]);
    }
}