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
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('organization_id')->nullable()->after('password');
            $table->enum('hierarchy_role', ['MASTER', 'GO', 'GR', 'STORE_MANAGER'])->default('STORE_MANAGER')->after('organization_id');
            
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->index(['organization_id', 'hierarchy_role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn(['organization_id', 'hierarchy_role']);
        });
    }
};