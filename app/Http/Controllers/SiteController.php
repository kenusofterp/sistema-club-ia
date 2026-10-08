<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Facility;
use App\Models\HeroSlide;
use App\Models\Member;
use App\Models\Organization;
use App\Models\Page;
use App\Models\Plan;
use App\Models\Post;
use App\Models\SiteSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function home(): View|RedirectResponse
    {
        // Instalación sin entidades todavía: no hay sitio que mostrar.
        if (! Organization::current()) {
            return redirect()->route('login');
        }

        $sections = SiteSection::active()->get();
        $types = $sections->pluck('type');

        return view('site.home', [
            'slides' => HeroSlide::active()->get(),
            'sections' => $sections,
            'activities' => $types->contains('activities')
                ? Activity::visible()->with('schedules')->withCount('activeEnrollments')->limit(8)->get()
                : collect(),
            'facilities' => $types->contains('facilities') ? Facility::visible()->limit(6)->get() : collect(),
            'plans' => $types->contains('plans') && uses_gym() ? Plan::visible()->with('activities')->get() : collect(),
            'posts' => $types->contains('news') ? Post::published()->limit(3)->get() : collect(),
        ]);
    }

    public function activities(): View
    {
        return view('site.activities', [
            'activities' => Activity::visible()->with('schedules')->withCount('activeEnrollments')->get(),
        ]);
    }

    public function activity(Activity $activity): View
    {
        abort_unless($activity->is_active && $activity->is_public, 404);

        return view('site.activity', [
            'activity' => $activity->load(['schedules', 'instructor'])->loadCount('activeEnrollments'),
        ]);
    }

    public function news(): View
    {
        return view('site.news', ['posts' => Post::published()->paginate(9)]);
    }

    public function post(Post $post): View
    {
        abort_unless($post->status->value === 'published' && $post->published_at?->isPast(), 404);

        return view('site.post', [
            'post' => $post,
            'related' => Post::published()->whereKeyNot($post->id)->limit(3)->get(),
        ]);
    }

    public function page(Page $page): View
    {
        abort_unless($page->is_published, 404);

        return view('site.page', ['page' => $page]);
    }

    /** Verificación pública del carnet digital (QR). Solo expone datos mínimos. */
    public function verify(string $uuid): View
    {
        $member = Member::acrossOrganizations()->where('uuid', $uuid)->firstOrFail();

        // El carnet se muestra con la identidad de la entidad que lo emitió.
        Organization::setCurrent($member->organization);

        return view('site.verify', ['member' => $member->load('category')]);
    }
}
