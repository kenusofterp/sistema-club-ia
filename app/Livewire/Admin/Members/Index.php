<?php

namespace App\Livewire\Admin\Members;

use App\Enums\FeeStatus;
use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\MemberCategory;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Socios')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $category = '';

    #[Url]
    public bool $withDebt = false;

    public function updated($property): void
    {
        if (in_array($property, ['search', 'status', 'category', 'withDebt'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'category', 'withDebt']);
        $this->resetPage();
    }

    public function render()
    {
        $members = Member::query()
            ->with('category')
            ->withCount(['fees as overdue_count' => fn ($q) => $q->where('status', FeeStatus::Overdue)])
            ->search($this->search)
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->category, fn ($q) => $q->where('member_category_id', $this->category))
            ->when($this->withDebt, fn ($q) => $q->whereHas('fees', fn ($f) => $f->where('status', FeeStatus::Overdue)))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20);

        return view('livewire.admin.members.index', [
            'members' => $members,
            'categories' => MemberCategory::orderBy('sort_order')->pluck('name', 'id'),
            'statuses' => MemberStatus::options(),
        ]);
    }
}
