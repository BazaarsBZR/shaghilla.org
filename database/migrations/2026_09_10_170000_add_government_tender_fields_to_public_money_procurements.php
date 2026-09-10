<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_money_procurements', function (Blueprint $table): void {
            $table->string('reference_number')->nullable();
            $table->string('procurement_type')->nullable();
            $table->string('sector')->nullable();
            $table->string('procurement_method')->nullable();
            $table->text('award_criteria')->nullable();
            $table->decimal('estimated_value_min', 24, 4)->nullable();
            $table->decimal('estimated_value_max', 24, 4)->nullable();
            $table->boolean('estimated_value_confidential')->default(false);
            $table->decimal('offer_guarantee_value', 24, 4)->nullable();
            $table->text('offer_guarantee_text')->nullable();
            $table->timestampTz('announcement_at')->nullable();
            $table->timestampTz('submission_deadline_at')->nullable();
            $table->timestampTz('clarification_deadline_at')->nullable();
            $table->timestampTz('administrative_opening_at')->nullable();
            $table->timestampTz('financial_opening_at')->nullable();
            $table->string('responsible_name')->nullable();
            $table->string('responsible_phone')->nullable();
            $table->string('responsible_email')->nullable();
            $table->text('submission_location')->nullable();
            $table->longText('eligibility_requirements')->nullable();
            $table->longText('required_documents')->nullable();
            $table->string('source_status')->nullable();
            $table->timestampTz('source_imported_at')->nullable();
            $table->timestampTz('last_verified_at')->nullable();
            $table->timestampTz('detail_verified_at')->nullable();
            $table->string('source_list_hash', 64)->nullable();
            $table->string('source_detail_hash', 64)->nullable();
            $table->json('tender_documents')->nullable();
            $table->json('procurement_stages')->nullable();

            $table->index(['stage', 'submission_deadline_at'], 'pm_tender_deadline_idx');
            $table->index(['source_id', 'detail_verified_at'], 'pm_tender_detail_idx');
        });
    }

    public function down(): void
    {
        Schema::table('public_money_procurements', function (Blueprint $table): void {
            $table->dropIndex('pm_tender_deadline_idx');
            $table->dropIndex('pm_tender_detail_idx');
            $table->dropColumn([
                'reference_number',
                'procurement_type',
                'sector',
                'procurement_method',
                'award_criteria',
                'estimated_value_min',
                'estimated_value_max',
                'estimated_value_confidential',
                'offer_guarantee_value',
                'offer_guarantee_text',
                'announcement_at',
                'submission_deadline_at',
                'clarification_deadline_at',
                'administrative_opening_at',
                'financial_opening_at',
                'responsible_name',
                'responsible_phone',
                'responsible_email',
                'submission_location',
                'eligibility_requirements',
                'required_documents',
                'source_status',
                'source_imported_at',
                'last_verified_at',
                'detail_verified_at',
                'source_list_hash',
                'source_detail_hash',
                'tender_documents',
                'procurement_stages',
            ]);
        });
    }
};
