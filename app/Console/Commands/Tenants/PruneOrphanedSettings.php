<?php

namespace App\Console\Commands\Tenants;

use App\Models\Setting;
use Illuminate\Console\Command;

class PruneOrphanedSettings extends Command
{
    protected $signature = 'settings:prune-orphaned {--delete : Delete the orphaned settings}';

    protected $description = 'Find (and optionally delete) settings in the DB that are no longer defined in any controller';

    /**
     * Groups that only ever store a single `code_counter` key for transaction numbering.
     */
    private const COUNTER_GROUPS = [
        'sales',
        'customers',
        'purchases',
        'purchase_returns',
        'customer_payments',
        'customer_returns',
        'customer_credit_debit_notes',
        'supplier_payments',
        'supplier_credit_debit_notes',
        'items',
        'item_adjusts',
        'item_transfers',
        'employees',
        'salaries',
        'advanceLoans',
        'allowances',
        'commission_targets',
        'expense_transactions',
        'income_transactions',
        'account_transfers',
        'account_adjusts',
        'suppliers',
    ];

    /**
     * Groups whose keys are set dynamically at runtime — skipped entirely to avoid false positives.
     */
    private const DYNAMIC_GROUPS = ['company', 'tenant_details'];

    /**
     * All explicitly known settings: group_name => [key_names].
     * Update this list whenever settings keys are added or renamed.
     */
    private const KNOWN = [
        'item_catalog' => [
            'inv_show_qrcode',
            'inv_external_link',
            'inv_internal_link',
            'inv_active_link',
            'inv_label',
            'inv_file',
            'inx_show_qrcode',
            'inx_external_link',
            'inx_internal_link',
            'inx_active_link',
            'inx_label',
            'inx_file',
        ],
        'invoice' => [
            'prefix_tax',
            'prefix_tax_free',
            'footer_notes',
            'show_bank_details',
            'due_date_type',
            'note_1',
            'show_note_1',
            'note_2',
            'show_note_2',
            'show_local_currency_tax',
            'show_local_currency_total',
            'default_invoice_currency_id',
            'inv_show_google_map_qrcode',
            'inx_show_google_map_qrcode',
            'show_catalog_qrcode',
        ],
        'sale_settings' => [
            'block_new_sale',
            'block_new_sale_order',
            'block_return_sale_received',
        ],
        'employee_settings' => [
            'disable_payment_date_change',
            'disable_payment_order_date_change',
        ],
        'currency' => [
            'local_currency',
            'system_currency_mode',
        ],
        'general' => [
            'default_currency',
        ],
        'backup' => [
            'storage_drivers',
            's3_key',
            's3_secret',
            's3_region',
            's3_bucket',
            'ftp_host',
            'ftp_user',
            'ftp_password',
            'ftp_port',
            'ftp_root',
            'dropbox_token',
            'frequency_hours',
            'preferred_hour',
            'skip_if_unchanged',
            'retention_type',
            'retention_value',
        ],
        'mirror' => [
            'enabled',
            'db_type',
            'host',
            'port',
            'database',
            'username',
            'password',
            'last_mirrored_at',
            'store_limit',
            'display_limit',
        ],
        'app' => [
            'cache_versions',
        ],
    ];

    public function handle(): int
    {
        $shouldDelete = $this->option('delete');

        $orphans = Setting::all()->filter(function ($setting) {
            if (in_array($setting->group_name, self::DYNAMIC_GROUPS, true)) {
                return false;
            }

            if (in_array($setting->group_name, self::COUNTER_GROUPS, true)) {
                return $setting->key_name !== 'code_counter';
            }

            $knownKeys = self::KNOWN[$setting->group_name] ?? null;

            return $knownKeys === null || ! in_array($setting->key_name, $knownKeys, true);
        });

        if ($orphans->isEmpty()) {
            $this->info('No orphaned settings found.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Group', 'Key', 'Value'],
            $orphans->map(fn($s) => [$s->id, $s->group_name, $s->key_name, $s->value])->values()
        );

        if (! $shouldDelete) {
            $this->line('');
            $this->warn('Run with --delete to remove these settings.');
            return self::SUCCESS;
        }

        if (! $this->confirm("Delete {$orphans->count()} orphaned setting(s)?")) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        foreach ($orphans as $setting) {
            Setting::remove($setting->group_name, $setting->key_name);
        }

        $this->info("Deleted {$orphans->count()} orphaned setting(s).");

        return self::SUCCESS;
    }
}
