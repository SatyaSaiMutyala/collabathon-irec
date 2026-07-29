<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-side staff roles (Super Admin, Manager, and any custom role a Super Admin
 * creates). `is_system` marks the one reserved "Super Admin" row — it bypasses every
 * permission check (see AuthServiceProvider) and cannot be edited/deleted from the UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
