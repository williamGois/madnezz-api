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
        Schema::table('users_ddd', function (Blueprint $table) {
            $table->string('hierarchy_role')->default('STORE_MANAGER');
            $table->uuid('organization_id')->nullable();
            $table->uuid('store_id')->nullable();
            $table->string('phone')->nullable();
            $table->json('permissions')->nullable();
            $table->json('context_data')->nullable(); // For MASTER context switching
            
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('set null');
            
            $table->index(['hierarchy_role']);
            $table->index(['organization_id']);
            $table->index(['store_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users_ddd', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['store_id']);
            $table->dropIndex(['hierarchy_role']);
            $table->dropIndex(['organization_id']);
            $table->dropIndex(['store_id']);
            
            $table->dropColumn([
                'hierarchy_role',
                'organization_id',
                'store_id',
                'phone',
                'permissions',
                'context_data'
            ]);
        });
    }
};
