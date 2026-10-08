<?php

namespace App\Livewire\Admin\Site;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Page;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.admin')]
#[Title('Páginas')]
class Pages extends Component
{
    use InteractsWithUi, WithFileUploads;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $title = '';

    public string $slug = '';

    public string $body = '';

    public string $meta_description = '';

    public bool $show_in_menu = false;

    public int $menu_order = 0;

    public bool $is_published = true;

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
        $page = Page::findOrFail($id);
        $this->editingId = $page->id;
        $this->title = $page->title;
        $this->slug = $page->slug;
        $this->body = $page->body;
        $this->meta_description = (string) $page->meta_description;
        $this->show_in_menu = $page->show_in_menu;
        $this->menu_order = $page->menu_order;
        $this->is_published = $page->is_published;
        $this->currentImage = $page->imageUrl();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('sitio.gestionar');
        $this->slug = Str::slug($this->slug ?: $this->title);

        $data = $this->validate([
            'title' => 'required|string|max:200',
            'slug' => ['required', 'alpha_dash', 'max:200', org_unique('pages')->ignore($this->editingId)],
            'body' => 'required|string|max:100000',
            'meta_description' => 'nullable|string|max:300',
            'show_in_menu' => 'boolean',
            'menu_order' => 'integer|min:0',
            'is_published' => 'boolean',
            'image' => 'nullable|image|max:6144',
        ]);
        unset($data['image']);
        $data['meta_description'] = $data['meta_description'] ?: null;

        if ($this->image) {
            $data['image_path'] = $this->image->store('pages', 'public');
        }

        Page::updateOrCreate(['id' => $this->editingId], $data);
        $this->showForm = false;
        $this->notify('Página guardada.');
    }

    public function delete(int $id): void
    {
        $this->authorize('sitio.gestionar');
        Page::whereKey($id)->first()?->delete();
        $this->notify('Página eliminada.');
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'title', 'slug', 'body', 'meta_description', 'show_in_menu', 'menu_order', 'is_published', 'image', 'currentImage']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.admin.site.pages', ['pages' => Page::orderBy('title')->get()]);
    }
}
