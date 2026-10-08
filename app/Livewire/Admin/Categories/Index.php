<?php

namespace App\Livewire\Admin\Categories;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\MemberCategory;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Categorías de socios')]
class Index extends Component
{
    use InteractsWithUi;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public string $monthly_fee = '0';

    public string $admission_fee = '0';

    public ?int $min_age = null;

    public ?int $max_age = null;

    public bool $is_active = true;

    public int $sort_order = 0;

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->resetForm();
        $category = MemberCategory::findOrFail($id);
        $this->editingId = $category->id;
        $this->fill([
            'name' => $category->name,
            'description' => (string) $category->description,
            'monthly_fee' => (string) $category->monthly_fee,
            'admission_fee' => (string) $category->admission_fee,
            'min_age' => $category->min_age,
            'max_age' => $category->max_age,
            'is_active' => $category->is_active,
            'sort_order' => $category->sort_order,
        ]);
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('categorias.gestionar');

        $data = $this->validate([
            'name' => ['required', 'string', 'max:80', org_unique('member_categories')->ignore($this->editingId)],
            'description' => 'nullable|string|max:255',
            'monthly_fee' => 'required|numeric|min:0|max:99999999',
            'admission_fee' => 'required|numeric|min:0|max:99999999',
            'min_age' => 'nullable|integer|min:0|max:120',
            'max_age' => 'nullable|integer|min:0|max:120|gte:min_age',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        MemberCategory::updateOrCreate(['id' => $this->editingId], [...$data, 'description' => $data['description'] ?: null]);

        $this->showForm = false;
        $this->notify($this->editingId ? 'Categoría actualizada.' : 'Categoría creada.');
    }

    public function delete(int $id): void
    {
        $this->authorize('categorias.gestionar');
        $category = MemberCategory::withCount('members')->findOrFail($id);

        if ($category->members_count > 0) {
            $this->notify('La categoría tiene socios asignados. Desactivala en lugar de eliminarla.', 'error');

            return;
        }

        $category->delete();
        $this->notify('Categoría eliminada.');
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'description', 'monthly_fee', 'admission_fee', 'min_age', 'max_age', 'is_active', 'sort_order']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.admin.categories.index', [
            'categories' => MemberCategory::withCount(['members' => fn ($q) => $q->active()])->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }
}
