<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('name');
            $table->string('code');
            $table->enum('type', ['company', 'regional', 'store']);
            $table->uuid('parent_id')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'type']);
            $table->index(['parent_id']);
            $table->index(['active']);
        });

        // Add self-referencing foreign key in a separate statement
        Schema::table('organization_units', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('organization_units')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_units');
    }
};