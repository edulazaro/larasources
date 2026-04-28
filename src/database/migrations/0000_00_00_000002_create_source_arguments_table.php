<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('source_arguments')) return;
        Schema::create('source_arguments', function (Blueprint $table) {

            $table->id();

            $table->string('name');
            $table->foreignId('source_id')->constrained('sources')->onDelete('cascade');

            $table->morphs('argumentable'); 
               
            $table->timestamps();
        
            $table->unique(['source_id', 'argumentable_type', 'argumentable_id', 'name'], 'src_args_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_arguments');
    }
};
