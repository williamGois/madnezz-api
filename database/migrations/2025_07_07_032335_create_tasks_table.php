<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->enum('status', ['TODO', 'IN_PROGRESS', 'IN_REVIEW', 'BLOCKED', 'DONE', 'CANCELLED'])
                  ->default('TODO');
            $table->enum('priority', ['LOW', 'MEDIUM', 'HIGH', 'URGENT'])
                  ->default('MEDIUM');
            
            $table->foreignId('created_by')->constrained('users');
            $table->uuid('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->uuid('organization_unit_id')->nullable();
            $table->foreign('organization_unit_id')->references('id')->on('organization_units');
            $table->foreignId('parent_task_id')->nullable()->constrained('tasks');
            
            $table->timestamp('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['organization_id', 'status']);
            $table->index(['organization_unit_id', 'status']);
            $table->index(['created_by']);
            $table->index(['status']);
            $table->index(['due_date']);
            $table->index(['parent_task_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};