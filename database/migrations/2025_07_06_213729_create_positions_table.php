<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('user_id');
            $table->enum('level', ['go', 'gr', 'store_manager']);
            $table->uuid('organization_unit_id');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users_ddd')->onDelete('cascade');
            $table->foreign('organization_unit_id')->references('id')->on('organization_units')->onDelete('cascade');
            
            $table->unique(['user_id', 'organization_id', 'organization_unit_id']);
            $table->index(['organization_id', 'level']);
            $table->index(['user_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};