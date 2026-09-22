<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dissolve the legacy `company` settings group:
 *   - identity (name/tax_number/address/website/email/phone) -> `company_details`
 *   - print branding (logo/stamp + show/size toggles)        -> `invoice`
 * and rename the `tenant_details` group -> `company_details` (so end users never
 * see the multi-tenant wording).
 *
 * Rows are moved by UPDATEing group_name in place wherever possible so the
 * polymorphic Document attachments on logo/stamp survive the move.
 */
return new class extends Migration
{
    private const INVOICE_KEYS = [
        'logo', 'stamp', 'show_logo', 'show_stamp',
        'logo_width', 'logo_height', 'stamp_width', 'stamp_height',
    ];

    private const IDENTITY_NEW_KEYS = ['tax_number', 'address', 'website'];

    // company key => company_details key (reuse existing branding keys)
    private const IDENTITY_REUSED_KEYS = [
        'name'  => 'company_name',
        'email' => 'contact_email',
        'phone' => 'contact_phone',
    ];

    public function up(): void
    {
        // Wrap every write so a failure part-way rolls the whole thing back
        // instead of leaving the group half-migrated.
        DB::transaction(function () {
            // 1. Rename tenant_details -> company_details first so it exists as a target.
            DB::table('settings')->where('group_name', 'tenant_details')
                ->update(['group_name' => 'company_details']);

            // 2. Print branding company -> invoice (in place, preserves documents).
            foreach (self::INVOICE_KEYS as $key) {
                if (! $this->exists('invoice', $key)) {
                    DB::table('settings')->where('group_name', 'company')->where('key_name', $key)
                        ->update(['group_name' => 'invoice']);
                }
            }

            // 3. New identity keys company -> company_details (in place).
            foreach (self::IDENTITY_NEW_KEYS as $key) {
                if (! $this->exists('company_details', $key)) {
                    DB::table('settings')->where('group_name', 'company')->where('key_name', $key)
                        ->update(['group_name' => 'company_details']);
                }
            }

            // 4. Reused identity keys: rename in place if the target is absent,
            //    otherwise copy the value only when the target is still empty.
            foreach (self::IDENTITY_REUSED_KEYS as $from => $to) {
                $source = DB::table('settings')->where('group_name', 'company')->where('key_name', $from)->first();
                if (! $source) {
                    continue;
                }

                $target = DB::table('settings')->where('group_name', 'company_details')->where('key_name', $to)->first();
                if (! $target) {
                    DB::table('settings')->where('id', $source->id)
                        ->update(['group_name' => 'company_details', 'key_name' => $to]);
                    continue;
                }

                if ($target->value === null || $target->value === '') {
                    DB::table('settings')->where('id', $target->id)->update(['value' => $source->value]);
                }
            }

            // 5. Drop whatever is left in the retired company group.
            DB::table('settings')->where('group_name', 'company')->delete();
        });

        // Cache isn't transactional — clear it only after the commit succeeds.
        Setting::clearCache();
    }

    public function down(): void
    {
        DB::transaction(function () {
            // Best-effort reverse: move rows back to the company group.
            foreach (self::INVOICE_KEYS as $key) {
                DB::table('settings')->where('group_name', 'invoice')->where('key_name', $key)
                    ->update(['group_name' => 'company']);
            }

            foreach (self::IDENTITY_NEW_KEYS as $key) {
                DB::table('settings')->where('group_name', 'company_details')->where('key_name', $key)
                    ->update(['group_name' => 'company']);
            }

            DB::table('settings')->where('group_name', 'company_details')
                ->update(['group_name' => 'tenant_details']);
        });

        Setting::clearCache();
    }

    private function exists(string $group, string $key): bool
    {
        return DB::table('settings')->where('group_name', $group)->where('key_name', $key)->exists();
    }
};
