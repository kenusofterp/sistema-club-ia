<x-layouts::site :title="$page->title" :description="$page->meta_description" :transparent-header="true">
    @include('site.partials.page-hero', ['heading' => $page->title, 'image' => $page->imageUrl()])

    <article class="py-16 sm:py-20">
        <div class="prose-club mx-auto max-w-3xl px-4 text-lg text-slate-600 sm:px-6 lg:px-8">
            @foreach (preg_split("/\n\s*\n/", $page->body) as $paragraph)
                <p>{!! nl2br(e($paragraph)) !!}</p>
            @endforeach
        </div>
    </article>
</x-layouts::site>
