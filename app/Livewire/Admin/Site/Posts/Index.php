<?php

namespace App\Livewire\Admin\Site\Posts;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Post;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Noticias')]
class Index extends Component
{
    use InteractsWithUi, WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $this->authorize('sitio.gestionar');
        Post::whereKey($id)->first()?->delete();
        $this->notify('Noticia eliminada.');
    }

    public function render()
    {
        return view('livewire.admin.site.posts.index', [
            'posts' => Post::with('author')
                ->when($this->search, fn ($q) => $q->where('title', 'ilike', "%{$this->search}%"))
                ->latest('published_at')->latest('id')->paginate(15),
        ]);
    }
}
