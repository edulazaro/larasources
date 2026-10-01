<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the origin last said about the data in each row.
 *
 * A record only exists because an origin took the data, so the column holds
 * `saved` or `processing`, never `failed`. Rows written before the column
 * existed were all confirmed writes, hence the backfill.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('sources') || Schema::hasColumn('sources', 'status')) {
            return;
        }

        Schema::table('sources', function (Blueprint $table) {
            $table->string('status', 20)->default('saved')->after('signature');
        });

        DB::table('sources')->whereNull('status')->update(['status' => 'saved']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('sources') || !Schema::hasColumn('sources', 'status')) {
            return;
        }

        Schema::table('sources', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
