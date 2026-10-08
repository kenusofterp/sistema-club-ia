<?php

namespace App\Livewire\Admin\Site;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\SiteSection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.admin')]
#[Title('Secciones del sitio')]
class Sections extends Component
{
    use InteractsWithUi, WithFileUploads;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $type = 'text';

    public string $key = '';

    public string $menu_label = '';

    public string $title = '';

    public string $subtitle = '';

    public string $content = '';

    public bool $is_active = true;

    /** @var array<int, array<string, string>> */
    public array $items = [];

    public $image = null;

    public ?string $currentImage = null;

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->resetForm();
        $s = SiteSection::findOrFail($id);
        $this->editingId = $s->id;
        $this->type = $s->type;
        $this->key = $s->key;
        $this->menu_label = (string) $s->menu_label;
        $this->title = (string) $s->title;
        $this->subtitle = (string) $s->subtitle;
        $this->content = (string) $s->content;
        $this->is_active = $s->is_active;
        $this->items = array_values($s->items ?? []);
        $this->currentImage = $s->imageUrl();
        $this->showForm = true;
    }

    public function addItem(): void
    {
        $this->items[] = $this->type === 'stats' ? ['value' => '', 'title' => ''] : ['icon' => 'sparkles', 'title' => '', 'text' => ''];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save(): void
    {
        $this->authorize('sitio.gestionar');
        $this->key = Str::slug($this->key ?: $this->title);

        $data = $this->validate([
            'type' => ['required', Rule::in(array_keys(SiteSection::TYPES))],
            'key' => ['required', 'alpha_dash', 'max:60', org_unique('site_sections')->ignore($this->editingId)],
            'menu_label' => 'nullable|string|max:60',
            'title' => 'nullable|string|max:200',
            'subtitle' => 'nullable|string|max:255',
            'content' => 'nullable|string|max:10000',
            'is_active' => 'boolean',
            'items' => 'array|max:12',
            'items.*.title' => 'required|string|max:120',
            'items.*.text' => 'nullable|string|max:300',
            'items.*.value' => 'nullable|string|max:30',
            'items.*.icon' => 'nullable|string|max:30',
            'image' => 'nullable|image|max:6144',
        ], [], ['items.*.title' => 'título del ítem', 'key' => 'identificador']);

        unset($data['image']);
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);
        $data['items'] = in_array($data['type'], SiteSection::TYPES_WITH_ITEMS, true) ? array_values($this->items) : null;

        if ($this->image) {
            $data['image_path'] = $this->image->store('sections', 'public');
        }
        if (! $this->editingId) {
            $data['sort_order'] = (int) SiteSection::max('sort_order') + 10;
        }

        SiteSection::updateOrCreate(['id' => $this->editingId], $data);
        $this->showForm = false;
        $this->notify('Sección guardada.');
    }

    public function move(int $id, int $direction): void
    {
        $this->authorize('sitio.gestionar');
        $sections = SiteSection::orderBy('sort_order')->orderBy('id')->get()->values();
        $index = $sections->search(fn ($s) => $s->id === $id);
        if ($index === false || ! isset($sections[$index + $direction])) {
            return;
        }
        $sections->each(fn ($s, $i) => $s->sort_order = ($i + 1) * 10);
        [$sections[$index]->sort_order, $sections[$index + $direction]->sort_order] = [$sections[$index + $direction]->sort_order, $sections[$index]->sort_order];
        $sections->each->save();
    }

    public function toggle(int $id): void
    {
        $this->authorize('sitio.gestionar');
        $section = SiteSection::findOrFail($id);
        $section->update(['is_active' => ! $section->is_active]);
    }

    public function removeImage(): void
    {
        $this->authorize('sitio.gestionar');
        SiteSection::whereKey($this->editingId)->first()?->update(['image_path' => null]);
        $this->currentImage = null;
    }

    public function delete(int $id): void
    {
        $this->authorize('sitio.gestionar');
        SiteSection::whereKey($id)->first()?->delete();
        $this->notify('Sección eliminada.');
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'type', 'key', 'menu_label', 'title', 'subtitle', 'content', 'is_active', 'items', 'image', 'currentImage']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.admin.site.sections', [
            'sections' => SiteSection::orderBy('sort_order')->orderBy('id')->get(),
            'types' => SiteSection::TYPES,
        ]);
    }
}
