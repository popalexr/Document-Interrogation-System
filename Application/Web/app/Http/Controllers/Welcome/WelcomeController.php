<?php

namespace App\Http\Controllers\Welcome;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Laravel\Fortify\Features;

class WelcomeController extends Controller
{
    public function __invoke()
    {
        return Inertia::render('Welcome', [
            'canRegister' => Features::enabled(Features::registration()),
        ]);
    }
}
