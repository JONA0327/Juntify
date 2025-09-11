<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class AssistantController extends Controller
{
    /**
     * Display the assistant view.
     */
    public function index(): View
    {
        return view('assistant.index');
    }
}
