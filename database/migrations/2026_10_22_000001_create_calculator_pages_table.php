<?php

use Database\Seeders\CalculatorPageSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Calculators — the four public calculator pages (Mortgage, Eligibility, Rent vs Buy, Refinance),
 * editable from Admin › Calculators: page copy, enquiry-form copy and FAQs (faqs table, morph
 * `faqable`) in every language, plus per-calculator settings (e.g. the refinance rate). SEO title
 * and description live in the existing `metadata` rows (page_key = "<key>-calculator").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calculator_pages', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();      // mortgage | eligibility | rent-vs-buy | refinance
            $table->json('translations')->nullable(); // {lang: {title, heading, lead, form_eyebrow, form_title, form_text, form_points, faq_title}}
            $table->json('settings')->nullable();     // calculator-specific, e.g. refinance {rate, lenders[]}
            $table->unsignedInteger('order_index')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        // The pages read their copy from here, so the starting content (EN + AR) goes in now.
        (new CalculatorPageSeeder)->run();

        $permissions = collect(['view', 'edit'])
            ->map(fn ($action) => Permission::firstOrCreate(['name' => "calculators.{$action}", 'guard_name' => 'cms']));
        Role::where('name', 'superadmin')->where('guard_name', 'cms')->first()?->givePermissionTo($permissions);
    }

    public function down(): void
    {
        \App\Models\CmsKit\Faq::where('faqable_type', \App\Models\CalculatorPage::class)->delete();
        Schema::dropIfExists('calculator_pages');
        Permission::where('guard_name', 'cms')->where('name', 'like', 'calculators.%')->delete();
    }
};
