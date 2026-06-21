<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('uscc', 32)->index();
            $table->string('legal_rep');
            $table->string('registered_capital')->nullable();
            $table->date('establish_date')->nullable();
            $table->string('address')->nullable();
            $table->text('scope')->nullable();
            $table->string('industry')->nullable();
            $table->string('region')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
