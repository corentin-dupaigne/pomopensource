<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function __invoke()
    {
        $projects = [];

        if (Auth::check()) {
            $projects = Auth::user()->projects()->with('tasks')->get();
        }

        return Inertia::render('App', [
            'projects' => $projects,
            'isAuthenticated' => Auth::check(),
        ]);
    }
}
