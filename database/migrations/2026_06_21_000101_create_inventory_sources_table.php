<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_sources', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('type', 32)->default('warehouse')->index();
            $table->string('country', 32)->nullable();
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('timezone', 64)->nullable();
            $table->decimal('priority', 5, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_sources');
    }
};
