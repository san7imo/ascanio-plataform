<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class PublicSiteController extends Controller
{
    public function home(): Response
    {
        Inertia::setRootView('public');

        return Inertia::render('public/Home', [
            'canRegister' => Features::enabled(Features::registration()),
        ]);
    }
}
