<?php

namespace App\Livewire\Admin\Site;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\HeroSlide;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.admin')]
#[Title('Portada (hero)')]
class HeroSlides extends Component
{
    use InteractsWithUi, WithFileUploads;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $title = '';

    public string $subtitle = '';

    public string $button_text = '';

    public string $button_url = '';

    public string $secondary_button_text = '';

    public string $secondary_button_url = '';

    public int $overlay_opacity = 50;

    public bool $is_active = true;

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
        $slide = HeroSlide::findOrFail($id);
        $this->editingId = $slide->id;
        foreach (['title', 'subtitle', 'button_text', 'button_url', 'secondary_button_text', 'secondary_button_url'] as $field) {
            $this->{$field} = (string) $slide->{$field};
        }
        $this->overlay_opacity = $slide->overlay_opacity;
        $this->is_active = $slide->is_active;
        $this->currentImage = $slide->imageUrl();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('sitio.gestionar');
        $data = $this->validate([
            'title' => 'required|string|max:150',
            'subtitle' => 'nullable|string|max:400',
            'button_text' => 'nullable|string|max:60|required_with:button_url',
            'button_url' => 'nullable|string|max:255|required_with:button_text',
            'secondary_button_text' => 'nullable|string|max:60|required_with:secondary_button_url',
            'secondary_button_url' => 'nullable|string|max:255|required_with:secondary_button_text',
            'overlay_opacity' => 'required|integer|between:0,90',
            'is_active' => 'boolean',
            'image' => 'nullable|image|max:6144',
        ]);
        unset($data['image']);
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);

        if ($this->image) {
            $data['image_path'] = $this->image->store('hero', 'public');
        }

        if (! $this->editingId) {
            $data['sort_order'] = (int) HeroSlide::max('sort_order') + 1;
        }

        HeroSlide::updateOrCreate(['id' => $this->editingId], $data);
        $this->showForm = false;
        $this->notify('Diapositiva guardada.');
    }

    public function move(int $id, int $direction): void
    {
        $this->authorize('sitio.gestionar');
        $slides = HeroSlide::orderBy('sort_order')->orderBy('id')->get()->values();
        $index = $slides->search(fn ($s) => $s->id === $id);
        $swap = $index + $direction;

        if ($index === false || ! isset($slides[$swap])) {
            return;
        }

        [$a, $b] = [$slides[$index], $slides[$swap]];
        $slides->each(fn ($s, $i) => $s->sort_order = $i);
        [$a->sort_order, $b->sort_order] = [$b->sort_order, $a->sort_order];
        $slides->each->save();
    }

    public function toggle(int $id): void
    {
        $this->authorize('sitio.gestionar');
        $slide = HeroSlide::findOrFail($id);
        $slide->update(['is_active' => ! $slide->is_active]);
    }

    public function delete(int $id): void
    {
        $this->authorize('sitio.gestionar');
        HeroSlide::whereKey($id)->first()?->delete();
        $this->notify('Diapositiva eliminada.');
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'title', 'subtitle', 'button_text', 'button_url', 'secondary_button_text', 'secondary_button_url', 'overlay_opacity', 'is_active', 'image', 'currentImage']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.admin.site.hero-slides', ['slides' => HeroSlide::orderBy('sort_order')->orderBy('id')->get()]);
    }
}
