<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `persist()` writes one row per model, source and variant, and the table is
 * created with a unique index over exactly that. Installations created earlier
 * carry a unique index over `signature` instead of `variant`, which cannot
 * enforce that invariant and rejects a legitimate insert whenever two variants
 * happen to build the same payload.
 */
return new class extends Migration {
    private const STALE = 'sources_sourceable_type_sourceable_id_name_signature_unique';

    private const WANTED = 'sources_sourceable_type_sourceable_id_name_variant_unique';

    public function up(): void
    {
        if (!Schema::hasTable('sources')) {
            return;
        }

        if ($this->hasIndex(self::STALE)) {
            Schema::table('sources', function (Blueprint $table) {
                $table->dropUnique(self::STALE);
            });
        }

        if ($this->hasIndex(self::WANTED)) {
            return;
        }

        $this->pruneUnreachableRows();

        Schema::table('sources', function (Blueprint $table) {
            $table->unique(['sourceable_type', 'sourceable_id', 'name', 'variant']);
        });
    }

    /**
     * The index this replaced was the defect, so it is not brought back.
     */
    public function down(): void
    {
        if (!Schema::hasTable('sources') || !$this->hasIndex(self::WANTED)) {
            return;
        }

        Schema::table('sources', function (Blueprint $table) {
            $table->dropUnique(self::WANTED);
        });
    }

    /**
     * Rows that no longer have a way in.
     *
     * Without the right unique index a model, source and variant could end up
     * with several rows, and `persist()` only ever reaches one of them. The
     * newest is the one everything has been reading, so the older ones go.
     */
    private function pruneUnreachableRows(): void
    {
        $duplicates = DB::table('sources')
            ->selectRaw('sourceable_type, sourceable_id, name, variant, MAX(id) as keep_id')
            ->groupBy('sourceable_type', 'sourceable_id', 'name', 'variant')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('sources')
                ->where('sourceable_type', $duplicate->sourceable_type)
                ->where('sourceable_id', $duplicate->sourceable_id)
                ->where('name', $duplicate->name)
                ->when(
                    $duplicate->variant === null,
                    fn ($query) => $query->whereNull('variant'),
                    fn ($query) => $query->where('variant', $duplicate->variant),
                )
                ->where('id', '<', $duplicate->keep_id)
                ->delete();
        }
    }

    /**
     * `Schema::hasIndex()` only exists from Laravel 11, and this package
     * supports older ones. Asking the driver avoids a failed statement, which
     * on Postgres would poison the migration's transaction.
     */
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
