<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entidades administradas en la plataforma (clubes y gimnasios). Cada una es independiente:
 * sus propios socios, cuotas, pagos, configuración y sitio web.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 60)->unique();
            $table->string('type', 20)->default('club');
            $table->string('domain')->nullable()->unique();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_super_admin'));
        Schema::dropIfExists('organizations');
    }
};
