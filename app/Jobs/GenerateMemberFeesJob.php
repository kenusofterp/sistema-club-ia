<?php

namespace App\Jobs;

use App\Models\Member;
use App\Models\Organization;
use App\Services\FeeService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class GenerateMemberFeesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(public int $memberId, public string $period) {}

    public function uniqueId(): string
    {
        return "{$this->memberId}:{$this->period}";
    }

    public function handle(FeeService $fees): void
    {
        $member = Member::acrossOrganizations()->find($this->memberId);

        if ($member) {
            // La configuración (vencimiento, etc.) es la de la entidad del socio.
            Organization::runFor($member->organization_id, fn () => $fees->generateForMember($member->load('category'), Carbon::parse($this->period)));
        }
    }
}
