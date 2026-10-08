<div>
    <x-page-header :title="$post ? 'Editar noticia' : 'Nueva noticia'">
        <x-slot:breadcrumb><a href="{{ route('admin.site.posts.index') }}" wire:navigate class="hover:text-brand-700">Noticias</a> /</x-slot:breadcrumb>
    </x-page-header>

    <form wire:submit="save" class="grid gap-6 xl:grid-cols-3">
        <div class="card grid gap-5 p-6 xl:col-span-2">
            <x-field label="Título" for="title" error="title" required>
                <input id="title" wire:model.live.blur="title" class="form-input text-lg">
            </x-field>
            <x-field label="URL" for="slug" error="slug" required help="/noticias/{{ $slug ?: '...' }}">
                <input id="slug" wire:model="slug" class="form-input">
            </x-field>
            <x-field label="Bajada / resumen" for="excerpt" error="excerpt" help="Se muestra en el listado. Si se deja vacío se toma el inicio del texto.">
                <textarea id="excerpt" wire:model="excerpt" rows="2" class="form-input"></textarea>
            </x-field>
            <x-field label="Texto" for="body" error="body" required help="Separá párrafos con una línea en blanco.">
                <textarea id="body" wire:model="body" rows="14" class="form-input"></textarea>
            </x-field>
        </div>
        <div class="space-y-6">
            <div class="card grid gap-4 p-6">
                <x-field label="Estado" for="status" error="status">
                    <select id="status" wire:model="status" class="form-input">
                        @foreach (\App\Enums\PostStatus::options() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Fecha de publicación" for="published_at" error="published_at" help="Una fecha futura la programa.">
                    <input id="published_at" type="datetime-local" wire:model="published_at" class="form-input">
                </x-field>
            </div>
            <div class="card p-6">
                <x-image-upload model="image" :file="$image" :current="$post?->imageUrl()" label="Imagen principal" />
            </div>
            <div class="flex justify-end gap-2">
                <a href="{{ route('admin.site.posts.index') }}" wire:navigate class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled"><x-icon name="check" class="size-4" /> Guardar</button>
            </div>
        </div>
    </form>
</div>
