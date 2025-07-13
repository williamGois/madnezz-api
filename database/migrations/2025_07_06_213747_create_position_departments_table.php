<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('position_departments', function (Blueprint $table) {
            $table->uuid('position_id');
            $table->uuid('department_id');
            $table->timestamps();

            $table->foreign('position_id')->references('id')->on('positions')->onDelete('cascade');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('cascade');
            
            $table->primary(['position_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('position_departments');
    }
};