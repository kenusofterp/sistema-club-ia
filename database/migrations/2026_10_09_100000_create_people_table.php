<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Identidad global de las personas: una sola base para todo el sistema.
 * Cada socio (members) es la membresía de una persona en una entidad; los datos personales
 * se copian en members y se mantienen sincronizados desde el modelo Member.
 */
return new class extends Migration
{
    private const PERSONAL = [
        'first_name', 'last_name', 'document_type', 'document_number', 'birth_date', 'gender', 'email', 'phone',
        'address', 'city', 'photo_path', 'emergency_contact_name', 'emergency_contact_phone', 'medical_notes',
    ];

    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('document_type', 20)->default('DNI');
            $table->string('document_number', 30);
            $table->date('birth_date');
            $table->string('gender', 1)->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone', 40)->nullable();
            $table->string('address')->nullable();
            $table->string('city', 80)->nullable();
            $table->string('photo_path')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 40)->nullable();
            $table->text('medical_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['document_type', 'document_number']);
            $table->index(['last_name', 'first_name']);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->foreignId('person_id')->nullable()->after('uuid')->constrained('people')->restrictOnDelete();
        });

        // Una persona por documento; los socios con el mismo documento en distintas entidades se unen.
        DB::table('members')->orderBy('id')->get()->each(function ($member) {
            $personId = DB::table('people')
                ->where('document_type', $member->document_type)
                ->where('document_number', $member->document_number)
                ->value('id');

            if (! $personId) {
                $data = collect((array) $member)->only(self::PERSONAL)->all();
                $personId = DB::table('people')->insertGetId([
                    ...$data,
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $member->user_id,
                    'created_at' => $member->created_at,
                    'updated_at' => $member->updated_at,
                ]);
            } elseif ($member->user_id) {
                DB::table('people')->where('id', $personId)->whereNull('user_id')->update(['user_id' => $member->user_id]);
            }

            DB::table('members')->where('id', $member->id)->update(['person_id' => $personId]);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->unique(['organization_id', 'person_id']);
        });
        DB::statement('ALTER TABLE members ALTER COLUMN person_id SET NOT NULL');
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'person_id']);
            $table->dropConstrainedForeignId('person_id');
        });
        Schema::dropIfExists('people');
    }
};
