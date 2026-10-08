<?php

namespace App\Livewire\Portal\Concerns;

use App\Models\Member;

trait ForCurrentMember
{
    protected function member(): Member
    {
        $member = auth()->user()->currentMember();
        abort_unless($member, 403);

        return $member;
    }
}
