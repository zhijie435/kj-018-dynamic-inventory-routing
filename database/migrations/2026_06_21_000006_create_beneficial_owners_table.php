<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficial_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('id_type')->default('id_card');
            $table->string('id_number');
            $table->decimal('ownership_percent', 5, 2)->default(0);
            $table->string('nationality')->nullable();
            $table->boolean('is_pep')->default(false);
            $table->string('verification_status')->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficial_owners');
    }
};
