<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('membership_applications')) {
            return;
        }

        Schema::table('membership_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('membership_applications', 'registry_number')) {
                $table->string('registry_number')->nullable();
            }

            if (! Schema::hasColumn('membership_applications', 'registration_place')) {
                $table->string('registration_place')->nullable();
            }

            if (! Schema::hasColumn('membership_applications', 'volunteer_areas')) {
                $table->json('volunteer_areas')->nullable();
            }

            if (! Schema::hasColumn('membership_applications', 'volunteer_other')) {
                $table->string('volunteer_other')->nullable();
            }

            if (! Schema::hasColumn('membership_applications', 'has_volunteer_experience')) {
                $table->boolean('has_volunteer_experience')->nullable();
            }

            if (! Schema::hasColumn('membership_applications', 'volunteer_experience_details')) {
                $table->text('volunteer_experience_details')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('membership_applications')) {
            return;
        }

        Schema::table('membership_applications', function (Blueprint $table) {
            $columns = [
                'registry_number',
                'registration_place',
                'volunteer_areas',
                'volunteer_other',
                'has_volunteer_experience',
                'volunteer_experience_details',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('membership_applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
