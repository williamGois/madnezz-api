<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->enum('type', ['administrative', 'financial', 'marketing', 'operations', 'trade', 'macro']);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            
            $table->unique(['organization_id', 'type']);
            $table->index(['organization_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};