<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the service calls the resource a row stands for.
 *
 * Optional, and only written when an origin reports it. It is what makes the
 * next write an update instead of a create, and what lets an id coming from
 * the outside find the local model it belongs to.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('sources') || Schema::hasColumn('sources', 'external_id')) {
            return;
        }

        Schema::table('sources', function (Blueprint $table) {
            $table->string('external_id')->nullable()->after('status');
            $table->index(['name', 'external_id']);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('sources') || !Schema::hasColumn('sources', 'external_id')) {
            return;
        }

        Schema::table('sources', function (Blueprint $table) {
            $table->dropIndex(['name', 'external_id']);
            $table->dropColumn('external_id');
        });
    }
};
