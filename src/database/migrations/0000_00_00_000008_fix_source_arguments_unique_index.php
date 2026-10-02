<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One argument per name.
 *
 * The unique index included what the argument points at, so the same name could
 * point at two different things at once and the resolved value was whichever row
 * came last. A name is a key: `(source_id, name)` is the invariant.
 */
return new class extends Migration {
    private const STALE = 'src_args_unique';

    private const WANTED = 'source_arguments_source_id_name_unique';

    public function up(): void
    {
        if (!Schema::hasTable('source_arguments')) {
            return;
        }

        if (!$this->hasIndex(self::WANTED)) {
            $this->pruneArgumentsPointingElsewhere();

            // Created before the old one is dropped on purpose: InnoDB uses the
            // old unique as the index behind the `source_id` foreign key and
            // refuses to drop the only index that can serve it. This one starts
            // with `source_id` too, so it can take over.
            Schema::table('source_arguments', function (Blueprint $table) {
                $table->unique(['source_id', 'name'], self::WANTED);
            });
        }

        $this->dropIndexIfPresent(self::STALE);
    }

    public function down(): void
    {
        if (!Schema::hasTable('source_arguments') || !$this->hasIndex(self::WANTED)) {
            return;
        }

        if (!$this->hasIndex(self::STALE)) {
            Schema::table('source_arguments', function (Blueprint $table) {
                $table->unique(['source_id', 'argumentable_type', 'argumentable_id', 'name'], self::STALE);
            });
        }

        $this->dropIndexIfPresent(self::WANTED);
    }

    private function dropIndexIfPresent(string $index): void
    {
        if (!$this->hasIndex($index)) {
            return;
        }

        Schema::table('source_arguments', function (Blueprint $table) use ($index) {
            $table->dropUnique($index);
        });
    }

    /**
     * The newest row for a name is the one that was being resolved.
     */
    private function pruneArgumentsPointingElsewhere(): void
    {
        $duplicates = DB::table('source_arguments')
            ->selectRaw('source_id, name, MAX(id) as keep_id')
            ->groupBy('source_id', 'name')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('source_arguments')
                ->where('source_id', $duplicate->source_id)
                ->where('name', $duplicate->name)
                ->where('id', '<', $duplicate->keep_id)
                ->delete();
        }
    }

    private function hasIndex(string $index): bool
    {
        $builder = Schema::getFacadeRoot();

        if (method_exists($builder, 'hasIndex')) {
            return $builder->hasIndex('source_arguments', $index);
        }

        $connection = Schema::getConnection();

        return match ($connection->getDriverName()) {
            'mysql', 'mariadb' => (bool) $connection->selectOne(
                'show index from `source_arguments` where key_name = ?', [$index]
            ),
            'pgsql' => (bool) $connection->selectOne(
                'select 1 from pg_indexes where tablename = ? and indexname = ?', ['source_arguments', $index]
            ),
            'sqlite' => (bool) $connection->selectOne(
                "select 1 from sqlite_master where type = 'index' and name = ?", [$index]
            ),
            'sqlsrv' => (bool) $connection->selectOne(
                'select 1 from sys.indexes where name = ? and object_id = object_id(?)', [$index, 'source_arguments']
            ),
            default => false,
        };
    }
};
