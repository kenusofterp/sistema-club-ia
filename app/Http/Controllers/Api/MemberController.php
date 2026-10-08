<?php

namespace App\Http\Controllers\Api;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\FeeResource;
use App\Http\Resources\MemberResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\ReservationResource;
use App\Models\Activity;
use App\Models\Facility;
use App\Models\Member;
use App\Services\EnrollmentService;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/** Endpoints del socio autenticado ("/me"). */
class MemberController extends Controller
{
    private function member(Request $request): Member
    {
        $member = $request->user()->currentMember();
        abort_unless($member, 403, 'El usuario no está vinculado a un socio.');

        return $member;
    }

    public function me(Request $request): JsonResponse
    {
        $member = $this->member($request)->load('category');

        return response()->json([
            'data' => [
                'member' => new MemberResource($member),
                'balance' => $member->balance(),
                'overdue_fees' => $member->overdueFeesCount(),
                // Todas las entidades donde la persona es socia; se elige con el encabezado X-Entidad: <slug>.
                'memberships' => $request->user()->memberships()->with('organization:id,name,slug,type')->get()->map(fn ($m) => [
                    'organization' => $m->organization->only(['name', 'slug', 'type']),
                    'member_number' => $m->member_number,
                    'status' => $m->status->value,
                ]),
            ],
        ]);
    }

    public function fees(Request $request): AnonymousResourceCollection
    {
        $query = $this->member($request)->fees()->orderByDesc('due_date');

        if ($request->boolean('open')) {
            $query->open();
        }

        return FeeResource::collection($query->paginate(30));
    }

    public function payments(Request $request): AnonymousResourceCollection
    {
        return PaymentResource::collection($this->member($request)->payments()->with('fees')->latest('payment_date')->paginate(30));
    }

    public function subscriptions(Request $request): JsonResponse
    {
        $subscriptions = $this->member($request)->subscriptions()->with('plan')->latest('start_date')->limit(20)->get();

        return response()->json(['data' => $subscriptions->map(fn ($s) => [
            'id' => $s->id,
            'plan' => $s->plan->name,
            'start_date' => $s->start_date->toDateString(),
            'end_date' => $s->end_date->toDateString(),
            'price' => (string) $s->price,
            'status' => ['value' => $s->status->value, 'label' => $s->status->label()],
            'is_current' => $s->isCurrent(),
            'visits_remaining' => $s->isCurrent() ? $s->visitsRemaining() : null,
            'auto_renew' => $s->auto_renew,
        ])]);
    }

    public function enrollments(Request $request): AnonymousResourceCollection
    {
        return ActivityResource::collection($this->member($request)->activities()->with('schedules')->get());
    }

    public function enroll(Request $request, EnrollmentService $service): JsonResponse
    {
        $data = $request->validate(['activity_id' => 'required|integer|exists:activities,id']);
        $enrollment = $service->enroll($this->member($request), Activity::visible()->findOrFail($data['activity_id']));

        return response()->json(['message' => 'Inscripción registrada.', 'data' => ['id' => $enrollment->id]], 201);
    }

    public function unenroll(Request $request, Activity $activity, EnrollmentService $service): JsonResponse
    {
        $enrollment = $this->member($request)->enrollments()
            ->where('activity_id', $activity->id)
            ->where('status', EnrollmentStatus::Active)
            ->firstOrFail();

        $service->unenroll($enrollment, 'Baja solicitada desde la API');

        return response()->json(['message' => 'Baja registrada.']);
    }

    public function reservations(Request $request): AnonymousResourceCollection
    {
        return ReservationResource::collection($this->member($request)->reservations()->with('facility')->latest('date')->paginate(30));
    }

    public function book(Request $request, ReservationService $service): JsonResponse
    {
        $data = $request->validate([
            'facility_id' => 'required|integer|exists:facilities,id',
            'date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'slots' => 'nullable|integer|min:1|max:12',
        ]);

        $reservation = $service->book(
            Facility::findOrFail($data['facility_id']),
            $this->member($request),
            Carbon::parse($data['date']),
            $data['start_time'],
            $data['slots'] ?? 1,
            $request->user(),
        );

        return (new ReservationResource($reservation->load('facility')))->response()->setStatusCode(201);
    }

    public function cancelReservation(Request $request, int $reservation, ReservationService $service): JsonResponse
    {
        $model = $this->member($request)->reservations()->findOrFail($reservation);
        $service->cancel($model, 'Cancelada por el socio (API)', byMember: true);

        return response()->json(['message' => 'Reserva cancelada.']);
    }

    public function availability(Request $request, Facility $facility): JsonResponse
    {
        $date = Carbon::parse($request->validate(['date' => 'required|date_format:Y-m-d'])['date']);
        abort_unless($facility->is_active && $facility->is_bookable, 404);

        $taken = $facility->reservations()->confirmed()->whereDate('date', $date)->get(['start_time', 'end_time']);

        return response()->json([
            'data' => collect($facility->slots())->map(fn ($slot) => [
                ...$slot,
                'available' => ! $taken->contains(fn ($r) => substr($r->start_time, 0, 5) < $slot['end'] && substr($r->end_time, 0, 5) > $slot['start'])
                    && Carbon::parse($date->format('Y-m-d').' '.$slot['start'])->isFuture(),
            ]),
        ]);
    }
}
