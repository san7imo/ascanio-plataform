<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class PublicSiteController extends Controller
{
    public function home(): Response
    {
        return $this->page('public/Home');
    }

    public function service(string $service): Response
    {
        $services = json_decode(file_get_contents(resource_path('content/public-site/services.json')), true, flags: JSON_THROW_ON_ERROR);
        abort_unless(array_key_exists($service, $services), 404);

        return $this->page('public/Service', ['service' => $services[$service]]);
    }

    public function guide(): Response
    {
        return $this->page('public/Guide', [
            'guide' => json_decode(file_get_contents(resource_path('content/public-site/guide.json')), true, flags: JSON_THROW_ON_ERROR),
        ]);
    }

    /** @param array<string, mixed> $props */
    private function page(string $component, array $props = []): Response
    {
        Inertia::setRootView('public');

        return Inertia::render($component, [
            ...$props,
            'canRegister' => Features::enabled(Features::registration()),
        ]);
    }
}
