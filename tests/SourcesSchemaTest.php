<?php

namespace EduLazaro\Larasources\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SourcesSchemaTest extends TestCase
{
    public function test_the_sources_table_has_the_signature_column_persist_writes(): void
    {
        $this->assertTrue(Schema::hasColumn('sources', 'signature'));
    }

    public function test_the_signature_column_is_added_to_installations_created_without_it(): void
    {
        Schema::dropIfExists('sources');

        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->morphs('sourceable');
            $table->string('name');
            $table->string('origin')->nullable();
            $table->json('arguments')->nullable();
            $table->string('variant')->nullable();
            $table->json('attributes');
            $table->timestamps();
        });

        $this->assertFalse(Schema::hasColumn('sources', 'signature'));

        $migration = require __DIR__ . '/../src/database/migrations/0000_00_00_000003_add_signature_to_sources_table.php';
        $migration->up();

        $this->assertTrue(Schema::hasColumn('sources', 'signature'));

        // Running it twice must not fail.
        $migration->up();

        $this->assertTrue(Schema::hasColumn('sources', 'signature'));
    }

    public function test_the_sources_table_has_the_status_column_persist_writes(): void
    {
        $this->assertTrue(Schema::hasColumn('sources', 'status'));
    }

    public function test_the_status_column_is_added_to_installations_created_without_it(): void
    {
        $this->createLegacySourcesTable();

        $this->assertFalse(Schema::hasColumn('sources', 'status'));

        $migration = require __DIR__ . '/../src/database/migrations/0000_00_00_000004_add_status_to_sources_table.php';
        $migration->up();

        $this->assertTrue(Schema::hasColumn('sources', 'status'));

        // Running it twice must not fail.
        $migration->up();

        $this->assertTrue(Schema::hasColumn('sources', 'status'));
    }

    public function test_rows_written_before_the_status_column_count_as_saved(): void
    {
        $this->createLegacySourcesTable();

        $this->insertSource(['signature' => 'a']);

        $migration = require __DIR__ . '/../src/database/migrations/0000_00_00_000004_add_status_to_sources_table.php';
        $migration->up();

        $this->assertSame('saved', DB::table('sources')->value('status'));
    }

    public function test_the_unique_index_is_moved_onto_the_key_persist_writes(): void
    {
        $this->createLegacySourcesTable(staleUniqueIndex: true);

        // The stale index allows these two, and `persist()` can only reach one.
        $this->insertSource(['signature' => 'a', 'variant' => 'sale']);
        $this->insertSource(['signature' => 'b', 'variant' => 'sale']);

        $this->assertSame(2, DB::table('sources')->count());

        $migration = require __DIR__ . '/../src/database/migrations/0000_00_00_000005_fix_sources_unique_index.php';
        $migration->up();

        $this->assertSame(1, DB::table('sources')->count());
        $this->assertSame('b', DB::table('sources')->value('signature'));
        $this->assertFalse($this->hasIndex('sources_sourceable_type_sourceable_id_name_signature_unique'));
        $this->assertTrue($this->hasIndex('sources_sourceable_type_sourceable_id_name_variant_unique'));

        // Running it twice must not fail.
        $migration->up();

        $this->assertTrue($this->hasIndex('sources_sourceable_type_sourceable_id_name_variant_unique'));
    }

    public function test_the_index_migration_leaves_a_correct_installation_alone(): void
    {
        $this->insertSource(['signature' => 'a', 'variant' => 'sale']);

        $migration = require __DIR__ . '/../src/database/migrations/0000_00_00_000005_fix_sources_unique_index.php';
        $migration->up();

        $this->assertSame(1, DB::table('sources')->count());
        $this->assertTrue($this->hasIndex('sources_sourceable_type_sourceable_id_name_variant_unique'));
    }

    private function createLegacySourcesTable(bool $staleUniqueIndex = false): void
    {
        Schema::dropIfExists('sources');

        Schema::create('sources', function (Blueprint $table) use ($staleUniqueIndex) {
            $table->id();
            $table->morphs('sourceable');
            $table->string('name');
            $table->string('origin')->nullable();
            $table->string('signature')->nullable();
            $table->json('arguments')->nullable();
            $table->string('variant')->nullable();
            $table->json('attributes');
            $table->timestamps();

            if ($staleUniqueIndex) {
                $table->unique(['sourceable_type', 'sourceable_id', 'name', 'signature']);
            }
        });
    }

    private function insertSource(array $attributes = []): void
    {
        DB::table('sources')->insert(array_merge([
            'sourceable_type' => 'city',
            'sourceable_id' => 1,
            'name' => 'weather',
            'origin' => 'fake_weather',
            'variant' => null,
            'attributes' => json_encode(['temperature' => 18.0]),
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    private function hasIndex(string $index): bool
    {
        return (bool) DB::selectOne(
            "select 1 from sqlite_master where type = 'index' and name = ?", [$index]
        );
    }
}
