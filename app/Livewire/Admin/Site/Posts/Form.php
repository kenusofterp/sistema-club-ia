<?php

namespace App\Livewire\Admin\Site\Posts;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.admin')]
class Form extends Component
{
    use WithFileUploads;

    public ?Post $post = null;

    public string $title = '';

    public string $slug = '';

    public string $excerpt = '';

    public string $body = '';

    public string $status = 'draft';

    public string $published_at = '';

    public $image = null;

    public function mount(?Post $post = null): void
    {
        if ($post?->exists) {
            $this->post = $post;
            $this->title = $post->title;
            $this->slug = $post->slug;
            $this->excerpt = (string) $post->excerpt;
            $this->body = $post->body;
            $this->status = $post->status->value;
            $this->published_at = (string) $post->published_at?->format('Y-m-d\TH:i');
        } else {
            $this->published_at = now()->format('Y-m-d\TH:i');
        }
    }

    public function updatedTitle(): void
    {
        if (! $this->post) {
            $this->slug = Str::slug($this->title);
        }
    }

    public function save()
    {
        $this->authorize('sitio.gestionar');

        $data = $this->validate([
            'title' => 'required|string|max:200',
            'slug' => ['required', 'alpha_dash', 'max:200', org_unique('posts')->ignore($this->post?->id)],
            'excerpt' => 'nullable|string|max:500',
            'body' => 'required|string|max:50000',
            'status' => ['required', Rule::enum(PostStatus::class)],
            'published_at' => 'nullable|date|required_if:status,published',
            'image' => 'nullable|image|max:6144',
        ]);
        unset($data['image']);
        $data['excerpt'] = $data['excerpt'] ?: Str::limit(strip_tags($data['body']), 160);
        $data['published_at'] = $data['published_at'] ?: null;

        if ($this->image) {
            $data['image_path'] = $this->image->store('posts', 'public');
        }

        if (! $this->post) {
            $data['author_id'] = auth()->id();
        }

        Post::updateOrCreate(['id' => $this->post?->id], $data);

        session()->flash('success', 'Noticia guardada.');

        return $this->redirectRoute('admin.site.posts.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.site.posts.form')->title($this->post ? 'Editar noticia' : 'Nueva noticia');
    }
}
