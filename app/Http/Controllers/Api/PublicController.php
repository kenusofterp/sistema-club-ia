<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Models\HeroSlide;
use App\Models\Plan;
use App\Models\Post;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Datos públicos del sitio institucional (para apps o integraciones). */
class PublicController extends Controller
{
    public function site(): JsonResponse
    {
        return response()->json([
            'name' => setting('site.name'),
            'tagline' => setting('site.tagline'),
            'logo_url' => Setting::imageUrl('site.logo'),
            'colors' => ['primary' => setting('site.primary_color'), 'accent' => setting('site.accent_color')],
            'contact' => [
                'email' => setting('contact.email'),
                'phone' => setting('contact.phone'),
                'whatsapp' => setting('contact.whatsapp'),
                'address' => setting('contact.address'),
                'hours' => setting('contact.hours'),
            ],
            'hero' => HeroSlide::active()->get()->map(fn ($s) => [
                'title' => $s->title,
                'subtitle' => $s->subtitle,
                'image_url' => $s->imageUrl(),
                'button' => $s->button_text ? ['text' => $s->button_text, 'url' => $s->button_url] : null,
            ]),
            'news' => Post::published()->limit(5)->get()->map(fn ($p) => [
                'title' => $p->title,
                'excerpt' => $p->excerpt,
                'image_url' => $p->imageUrl(),
                'url' => route('site.post', $p),
                'published_at' => $p->published_at->toIso8601String(),
            ]),
        ]);
    }

    public function plans(): JsonResponse
    {
        abort_unless(uses_gym(), 404);

        return response()->json(['data' => Plan::visible()->with('activities:id,name')->get()->map(fn (Plan $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'description' => $p->description,
            'price' => (string) $p->price,
            'duration' => $p->durationLabel(),
            'access_type' => ['value' => $p->access_type->value, 'label' => $p->access_type->label()],
            'visits' => $p->visitLimitLabel(),
            'windows' => $p->windowsLabels(),
            'activities' => $p->activities->pluck('name'),
            'featured' => $p->is_featured,
        ])]);
    }

    public function activities(): AnonymousResourceCollection
    {
        return ActivityResource::collection(Activity::visible()->with('schedules')->withCount('activeEnrollments')->get());
    }
}
