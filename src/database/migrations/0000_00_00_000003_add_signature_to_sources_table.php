<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `Source::persist()` has always written a `signature`, but installations
 * created before it was added to the table schema do not have the column.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('sources') || Schema::hasColumn('sources', 'signature')) {
            return;
        }

        Schema::table('sources', function (Blueprint $table) {
            $table->string('signature')->nullable()->after('origin');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('sources') || !Schema::hasColumn('sources', 'signature')) {
            return;
        }

        Schema::table('sources', function (Blueprint $table) {
            $table->dropColumn('signature');
        });
    }
};
