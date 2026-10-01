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
            $table->morphs('sourceable');
            $table->string('name');
            $table->string('origin')->nullable();
            $table->string('signature')->nullable();
            $table->json('arguments')->nullable();
            $table->string('variant')->nullable();
            $table->json('attributes');
            $table->timestamps();

            $table->unique(['sourceable_type', 'sourceable_id', 'name', 'variant']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
