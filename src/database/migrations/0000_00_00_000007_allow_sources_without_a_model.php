<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a row exist before the thing it describes.
 *
 * A source is identified by its model or, when there is none yet, by the id the
 * service gave the resource. That is the flow where a payload is fetched first
 * and the model is derived from it, and then attached with `attachTo()`.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('sources')) {
            return;
        }

        Schema::table('sources', function (Blueprint $table) {
            $table->string('sourceable_type')->nullable()->change();
            $table->unsignedBigInteger('sourceable_id')->nullable()->change();
        });

        if (!$this->hasIndex('sources_name_variant_external_id_index')) {
            Schema::table('sources', function (Blueprint $table) {
                $table->index(['name', 'variant', 'external_id']);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('sources')) {
            return;
        }

        if ($this->hasIndex('sources_name_variant_external_id_index')) {
            Schema::table('sources', function (Blueprint $table) {
                $table->dropIndex(['name', 'variant', 'external_id']);
            });
        }

        // Rows without a model cannot stay once the columns are required again.
        Schema::getConnection()->table('sources')->whereNull('sourceable_id')->delete();

        Schema::table('sources', function (Blueprint $table) {
            $table->string('sourceable_type')->nullable(false)->change();
            $table->unsignedBigInteger('sourceable_id')->nullable(false)->change();
        });
    }

    private function hasIndex(string $index): bool
    {
        $builder = Schema::getFacadeRoot();

        if (method_exists($builder, 'hasIndex')) {
            return $builder->hasIndex('sources', $index);
        }

        $connection = Schema::getConnection();

        return match ($connection->getDriverName()) {
            'mysql', 'mariadb' => (bool) $connection->selectOne(
                'show index from `sources` where key_name = ?', [$index]
            ),
            'pgsql' => (bool) $connection->selectOne(
                'select 1 from pg_indexes where tablename = ? and indexname = ?', ['sources', $index]
            ),
            'sqlite' => (bool) $connection->selectOne(
                "select 1 from sqlite_master where type = 'index' and name = ?", [$index]
            ),
            'sqlsrv' => (bool) $connection->selectOne(
                'select 1 from sys.indexes where name = ? and object_id = object_id(?)', [$index, 'sources']
            ),
            default => false,
        };
    }
};
