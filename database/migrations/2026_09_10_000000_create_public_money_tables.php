<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_money_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('adapter');
            $table->text('base_url');
            $table->text('discovery_url');
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('check_interval_minutes')->default(1440);
            $table->timestampTz('last_checked_at')->nullable();
            $table->timestampTz('last_success_at')->nullable();
            $table->timestampTz('last_error_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampsTz();
        });

        Schema::create('public_money_import_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_id')->constrained('public_money_sources')->cascadeOnDelete();
            $table->string('status')->default('running');
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->unsignedInteger('discovered_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->text('error_summary')->nullable();
            $table->json('log')->nullable();
            $table->timestampsTz();
            $table->index(['source_id', 'started_at']);
        });

        Schema::create('public_money_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_id')->constrained('public_money_sources')->cascadeOnDelete();
            $table->foreignId('import_run_id')->nullable()->constrained('public_money_import_runs')->nullOnDelete();
            $table->string('title');
            $table->string('document_type');
            $table->text('source_url');
            $table->text('resolved_url');
            $table->string('mime_type')->nullable();
            $table->date('source_published_on')->nullable();
            $table->timestampTz('retrieved_at');
            $table->string('content_hash', 64);
            $table->string('parser_version')->default('1');
            $table->longText('archive_body')->nullable();
            $table->string('archive_encoding')->nullable();
            $table->text('archive_url')->nullable();
            $table->unsignedBigInteger('archive_size')->default(0);
            $table->string('extraction_status')->default('retrieved');
            $table->json('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['source_id', 'content_hash']);
        });

        Schema::create('public_money_procurements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_id')->constrained('public_money_sources')->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('public_money_documents')->nullOnDelete();
            $table->string('external_key');
            $table->string('source_record_id')->nullable();
            $table->string('procurement_id')->nullable();
            $table->string('lot_id')->nullable();
            $table->string('stage');
            $table->string('original_stage')->nullable();
            $table->string('status_normalized')->nullable();
            $table->string('original_status')->nullable();
            $table->text('title');
            $table->text('description')->nullable();
            $table->text('authority');
            $table->text('supplier')->nullable();
            $table->decimal('amount', 30, 4)->nullable();
            $table->text('amount_text')->nullable();
            $table->string('currency', 12)->nullable();
            $table->date('event_on')->nullable();
            $table->date('publication_on')->nullable();
            $table->date('standstill_end_on')->nullable();
            $table->date('contract_on')->nullable();
            $table->date('implementation_on')->nullable();
            $table->text('source_url');
            $table->json('evidence')->nullable();
            $table->string('review_status')->default('pending');
            $table->string('publication_status')->default('draft');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->string('fingerprint', 64);
            $table->timestampsTz();
            $table->unique(['source_id', 'external_key']);
            $table->index(['review_status', 'publication_status', 'event_on']);
            $table->index(['stage', 'currency']);
        });

        Schema::create('public_money_financial_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_id')->constrained('public_money_sources')->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('public_money_documents')->nullOnDelete();
            $table->string('external_key');
            $table->string('fiscal_period');
            $table->date('reporting_period_start')->nullable();
            $table->date('reporting_period_end')->nullable();
            $table->string('measure_type');
            $table->text('authority')->nullable();
            $table->text('category');
            $table->decimal('amount', 30, 4);
            $table->text('amount_text');
            $table->string('currency', 12);
            $table->string('original_unit');
            $table->decimal('scale', 20, 4)->default(1);
            $table->string('accounting_scope')->nullable();
            $table->string('source_version')->nullable();
            $table->string('page_reference')->nullable();
            $table->string('table_reference')->nullable();
            $table->string('row_reference')->nullable();
            $table->text('source_url');
            $table->boolean('is_total')->default(false);
            $table->string('overlap_group')->nullable();
            $table->json('evidence')->nullable();
            $table->string('review_status')->default('pending');
            $table->string('publication_status')->default('draft');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->string('fingerprint', 64);
            $table->timestampsTz();
            $table->unique(['source_id', 'external_key']);
            $table->index(['measure_type', 'fiscal_period', 'currency'], 'pm_fin_measure_period_currency');
            $table->index(['review_status', 'publication_status']);
        });

        Schema::create('public_money_review_actions', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('action');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('public_money_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title_ar');
            $table->string('title_en')->nullable();
            $table->longText('body_ar');
            $table->longText('body_en')->nullable();
            $table->json('evidence_links')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_money_reports');
        Schema::dropIfExists('public_money_review_actions');
        Schema::dropIfExists('public_money_financial_observations');
        Schema::dropIfExists('public_money_procurements');
        Schema::dropIfExists('public_money_documents');
        Schema::dropIfExists('public_money_import_runs');
        Schema::dropIfExists('public_money_sources');
    }
};
