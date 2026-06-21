<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('score')->default(0);
            $table->string('level')->default('low');
            $table->boolean('pep_hit')->default(false);
            $table->boolean('sanctions_hit')->default(false);
            $table->boolean('adverse_media')->default(false);
            $table->boolean('shell_company')->default(false);
            $table->json('factors')->nullable();
            $table->timestamp('screened_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_assessments');
    }
};
