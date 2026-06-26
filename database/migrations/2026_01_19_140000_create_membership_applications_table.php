<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('membership_applications')) {
            return;
        }

        Schema::create('membership_applications', function (Blueprint $table) {
            $table->id();

            $table->string('full_name');
            $table->string('mother_name')->nullable();
            $table->date('birth_date')->nullable()->index();
            $table->string('registry_number')->nullable();
            $table->string('registration_place')->nullable();
            $table->string('phone');
            $table->string('emergency_phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('profession')->nullable();
            $table->string('marital_status')->nullable()->index(); // married | single
            $table->unsignedInteger('children_count')->nullable();
            $table->string('blood_type')->nullable();

            $table->json('volunteer_areas')->nullable();
            $table->string('volunteer_other')->nullable();
            $table->boolean('has_volunteer_experience')->nullable();
            $table->text('volunteer_experience_details')->nullable();

            $table->string('id_document_path')->nullable();
            $table->string('signature_name')->nullable();

            $table->string('status')->default('pending')->index(); // pending | approved | rejected
            $table->text('admin_notes')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_applications');
    }
};
