<?php

namespace Database\Seeders;

use App\Models\FormField;
use Illuminate\Database\Seeder;

/**
 * The rows behind Settings -> Form fields.
 *
 * Split out of DatabaseSeeder so it can be run on a live server. These rows are
 * configuration, not sample data — without them the Form fields panel renders empty and
 * the mobile app has no field definitions to honour — but DatabaseSeeder also creates
 * demo developers, brokers, properties and placeholder media, which must never be run
 * against production. Extracting them is what makes `db:seed --class=FormFieldSeeder`
 * a safe deploy step.
 *
 * Every write is updateOrCreate keyed on (form, field_key), so re-running is idempotent
 * and, importantly, never resets an `enabled` toggle an admin has already changed —
 * only the label/required/sort metadata is refreshed, and a missing row is added.
 *
 * Deliberately does NOT touch `settings` (accent colour, app name). Those are the
 * admin's to set, and re-seeding them would silently revert branding on every deploy.
 */
class FormFieldSeeder extends Seeder
{
    /** [field_key, label, enabled, required, is_core] */
    private const BROKER = [
        ['full_name', 'Full Name', true, true, true],
        ['mobile', 'Mobile Number', true, true, true],
        ['email', 'Email', true, true, true],
        ['company_name', 'Company Name', true, false, false],
        ['rera_number', 'RERA Number', true, true, true],
        ['gst_number', 'GST Number', true, false, false],
        ['years_of_experience', 'Years of Experience', false, false, false],
    ];

    private const PROPERTY = [
        ['project_name', 'Project Name', true, true, true],
        ['price_range', 'Price Range', true, true, true],
        ['location', 'Location', true, true, true],
        ['amenities', 'Amenities', true, false, false],
        ['cp_commission', 'CP Commission %', true, true, false],
        ['construction_progress', 'Construction Progress', false, false, false],
    ];

    public function run(): void
    {
        $this->seed(FormField::FORM_BROKER, self::BROKER);
        $this->seed(FormField::FORM_PROPERTY, self::PROPERTY);
    }

    private function seed(string $form, array $rows): void
    {
        foreach ($rows as $i => [$key, $label, $enabled, $required, $core]) {
            $existing = FormField::where('form', $form)->where('field_key', $key)->first();

            FormField::updateOrCreate(
                ['form' => $form, 'field_key' => $key],
                [
                    'label' => $label,
                    'required' => $required,
                    'is_core' => $core,
                    'sort_order' => $i,
                    // Keep whatever the admin last chose; only a brand-new row takes the
                    // default. Re-seeding must not silently switch a field back on.
                    'enabled' => $existing?->enabled ?? $enabled,
                ]
            );
        }
    }
}
