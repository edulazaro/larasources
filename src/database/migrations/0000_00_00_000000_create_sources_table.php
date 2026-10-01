<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('sources')) return;
        Schema::create('sources', function (Blueprint $table) {

            $table->id();
            $table->nullableMorphs('sourceable');
            $table->string('name');
            $table->string('origin')->nullable();
            $table->string('signature')->nullable();
            $table->string('status', 20)->default('saved');
            $table->string('external_id')->nullable();
            $table->json('arguments')->nullable();
            $table->string('variant')->nullable();
            $table->json('attributes');
            $table->timestamps();

            $table->unique(['sourceable_type', 'sourceable_id', 'name', 'variant']);
            $table->index(['name', 'external_id']);
            $table->index(['name', 'variant', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
