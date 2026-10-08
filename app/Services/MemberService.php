<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\FeeType;
use App\Enums\MemberStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Activity;
use App\Models\Member;
use App\Models\MemberCategory;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Notifications\WelcomeMemberNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class MemberService
{
    public function __construct(private FeeService $fees) {}

    /** Alta de socio desde la administración (queda activo) o desde la web (queda pendiente). */
    public function create(array $data, bool $activate = true): Member
    {
        $this->assertCategoryAcceptsAge($data['member_category_id'], Carbon::parse($data['birth_date']));

        return DB::transaction(function () use ($data, $activate) {
            $member = Member::create([...$data, 'status' => MemberStatus::Pending]);

            if ($activate) {
                $this->approve($member);
            }

            return $member->refresh();
        });
    }

    /**
     * Membresía en la entidad actual para una persona ya registrada en el sistema (por ejemplo, alumno de un
     * profesor que da clases en esta entidad). Queda activa, en la primera categoría que admite su edad,
     * y no se le cobra derecho de ingreso.
     */
    public function joinOrganization(Person $person): Member
    {
        $existing = Member::where('person_id', $person->id)->first();
        if ($existing) {
            return $existing;
        }

        $age = (int) $person->birth_date->diffInYears(now());
        $category = MemberCategory::active()->get()->first(fn (MemberCategory $c) => $c->acceptsAge($age));
        if (! $category) {
            throw new BusinessRuleException("No hay una categoría de socio para {$age} años en esta entidad. Cargá a {$person->fullName()} desde Socios.");
        }

        return DB::transaction(fn () => Member::create([
            ...$person->personalData(),
            'person_id' => $person->id,
            'user_id' => $person->user_id,
            'member_category_id' => $category->id,
            'status' => MemberStatus::Active,
            'member_number' => $this->nextMemberNumber(),
            'admission_date' => today(),
        ]));
    }

    public function update(Member $member, array $data): Member
    {
        $this->assertCategoryAcceptsAge($data['member_category_id'], Carbon::parse($data['birth_date']));

        $member->update($data);

        return $member;
    }

    /** Aprueba una solicitud: asigna número de socio, fecha de ingreso y cobra el derecho de ingreso. */
    public function approve(Member $member): Member
    {
        if (! in_array($member->status, [MemberStatus::Pending, MemberStatus::Rejected], true)) {
            throw new BusinessRuleException('Solo se pueden aprobar solicitudes pendientes.');
        }

        DB::transaction(function () use ($member) {
            $member->forceFill([
                'status' => MemberStatus::Active,
                'member_number' => $member->member_number ?: $this->nextMemberNumber(),
                'admission_date' => $member->admission_date ?: today(),
            ])->save();

            $admissionFee = (string) $member->category->admission_fee;
            if (bccomp($admissionFee, '0', 2) > 0) {
                $this->fees->createCharge(
                    $member,
                    FeeType::Admission,
                    'Derecho de ingreso - '.$member->category->name,
                    $admissionFee,
                    today()->addDays(10),
                );
            }

            activity('members')->performedOn($member)->event('approved')->log('Socio aprobado');
        });

        if ($member->email && ! $member->user_id) {
            $this->enablePortalAccess($member);
        } elseif ($member->user && $this->accountComesFromOtherMembership($member)) {
            // La persona ya tenía cuenta por otra entidad: se le avisa que ahora también accede a esta.
            $member->user->notify(new WelcomeMemberNotification(null, $member->organization_id));
        }

        return $member;
    }

    public function reject(Member $member, ?string $reason = null): void
    {
        if ($member->status !== MemberStatus::Pending) {
            throw new BusinessRuleException('Solo se pueden rechazar solicitudes pendientes.');
        }

        $member->update(['status' => MemberStatus::Rejected, 'notes' => trim(($member->notes ?? '')."\nRechazo: ".$reason)]);
    }

    public function suspend(Member $member, string $reason): void
    {
        if ($member->status !== MemberStatus::Active) {
            throw new BusinessRuleException('Solo se pueden suspender socios activos.');
        }

        $member->update(['status' => MemberStatus::Suspended]);
        activity('members')->performedOn($member)->event('suspended')->withProperties(['reason' => $reason])->log('Socio suspendido');
    }

    public function reactivate(Member $member): void
    {
        if (! in_array($member->status, [MemberStatus::Suspended, MemberStatus::Inactive], true)) {
            throw new BusinessRuleException('El socio no está suspendido ni dado de baja.');
        }

        $member->update(['status' => MemberStatus::Active, 'leave_date' => null, 'leave_reason' => null]);
        activity('members')->performedOn($member)->event('reactivated')->log('Socio reactivado');
    }

    /** Baja del socio: finaliza inscripciones, cancela reservas futuras y bloquea el acceso al portal. */
    public function deactivate(Member $member, string $reason): void
    {
        if ($member->status === MemberStatus::Inactive) {
            throw new BusinessRuleException('El socio ya está dado de baja.');
        }

        DB::transaction(function () use ($member, $reason) {
            $activities = $member->enrollments()->where('status', EnrollmentStatus::Active)->with('activity')->get()->pluck('activity');
            $member->enrollments()->where('status', EnrollmentStatus::Active)->update([
                'status' => EnrollmentStatus::Ended,
                'end_date' => today(),
            ]);
            $activities->filter()->each(fn (Activity $activity) => app(LevelLessonService::class)->syncEnrollment($member, $activity, false));

            $member->reservations()
                ->where('status', ReservationStatus::Confirmed)
                ->whereDate('date', '>=', today())
                ->update(['status' => ReservationStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => 'Baja del socio']);

            $member->update(['status' => MemberStatus::Inactive, 'leave_date' => today(), 'leave_reason' => $reason]);

            $member->user?->update(['is_active' => false]);
        });
    }

    /** Crea el usuario del portal y envía el enlace para definir la contraseña. */
    public function enablePortalAccess(Member $member): User
    {
        if (! $member->email) {
            throw new BusinessRuleException('El socio necesita un correo electrónico para acceder al portal.');
        }

        if ($member->user) {
            $member->user->update(['is_active' => true]);

            return $member->user;
        }

        // Una persona, una cuenta: si ya es socia de otra entidad (mismo correo o documento), se vincula esa cuenta.
        $existing = $this->findExistingAccount($member);

        if ($existing) {
            if ($existing->trashed()) {
                throw new BusinessRuleException('La cuenta asociada a '.$member->email.' fue eliminada. Restaurala desde Usuarios.');
            }

            $member->update(['user_id' => $existing->id]);
            $existing->notify(new WelcomeMemberNotification(null, $member->organization_id));

            return $existing;
        }

        $user = DB::transaction(function () use ($member) {
            $user = User::create([
                'name' => $member->fullName(),
                'email' => $member->email,
                'phone' => $member->phone,
                'password' => Str::password(32),
                'is_active' => true,
            ]);
            $member->update(['user_id' => $user->id]);

            return $user;
        });

        $token = Password::broker()->createToken($user);
        $user->notify(new WelcomeMemberNotification($token, $member->organization_id));

        return $user;
    }

    private function accountComesFromOtherMembership(Member $member): bool
    {
        return Member::acrossOrganizations()
            ->where('person_id', $member->person_id)
            ->whereKeyNot($member->id)
            ->where('user_id', $member->user_id)
            ->exists();
    }

    private function findExistingAccount(Member $member): ?User
    {
        if ($member->person?->user) {
            return $member->person->user;
        }

        $byEmail = User::withTrashed()->where('email', $member->email)->first();
        if ($byEmail) {
            return $byEmail;
        }

        return Member::acrossOrganizations()
            ->where('document_type', $member->document_type)
            ->where('document_number', $member->document_number)
            ->whereKeyNot($member->id)
            ->whereNotNull('user_id')
            ->first()?->user;
    }

    /** Número de socio correlativo dentro de la entidad. Usa un lock transaccional para evitar duplicados concurrentes. */
    public function nextMemberNumber(): string
    {
        return DB::transaction(function () {
            DB::select('SELECT pg_advisory_xact_lock(?, ?)', [hexdec(substr(md5('member_number'), 0, 7)), (int) Organization::currentId()]);

            $last = (int) Member::withTrashed()
                ->whereNotNull('member_number')
                ->selectRaw("MAX(CAST(NULLIF(regexp_replace(member_number, '\\D', '', 'g'), '') AS BIGINT)) as n")
                ->value('n');

            return setting('club.member_number_prefix', '').str_pad((string) ($last + 1), 5, '0', STR_PAD_LEFT);
        });
    }

    private function assertCategoryAcceptsAge(int|string $categoryId, Carbon $birthDate): void
    {
        $category = MemberCategory::findOrFail($categoryId);
        $age = (int) $birthDate->diffInYears(now());

        if (! $category->acceptsAge($age)) {
            throw new BusinessRuleException("La categoría {$category->name} es para {$category->ageRangeLabel()} y el socio tiene {$age} años.");
        }
    }
}
