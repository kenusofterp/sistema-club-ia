<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mensajes del profesor/administración a un grupo de alumnos (por ejemplo, a los que deben):
 * se envían como notificación push y quedan visibles en el portal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 120);
            $table->text('body');
            $table->string('context', 150)->nullable();
            $table->timestamps();
        });

        Schema::create('member_message_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->unique(['member_message_id', 'member_id']);
            $table->index(['member_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_message_recipients');
        Schema::dropIfExists('member_messages');
    }
};
