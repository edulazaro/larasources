<?php

namespace EduLazaro\Larasources\Tests;

use Illuminate\Database\Schema\Blueprint;
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
}
