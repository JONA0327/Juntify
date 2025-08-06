<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class MeetingController extends Controller
{
    /**
     * Muestra la página principal de reuniones.
     *
     * @return \Illuminate\View\View
     */
    public function index(): View
    {
        // En una aplicación real, estos datos vendrían de tu base de datos,
        // por ejemplo: $conversations = Conversation::where('user_id', auth()->id())->get();
        $conversations = [
            ['id' => 1, 'title' => 'Fernanda CEP 2', 'date' => '31 jul 2025', 'time' => '2:10', 'participants' => '1 participante'],
            ['id' => 2, 'title' => 'Fernanda CEP', 'date' => '31 jul 2025', 'time' => '4:08', 'participants' => '1 participante'],
            ['id' => 3, 'title' => 'segunda parte de reunion con Notaria', 'date' => '30 jul 2025', 'time' => '36:52', 'participants' => '3 participantes'],
            ['id' => 4, 'title' => 'Notaria 35', 'date' => '30 jul 2025', 'time' => '119:02', 'participants' => '3 participantes'],
            ['id' => 5, 'title' => 'Kualifin 6', 'date' => '23 jul 2025', 'time' => '80:08', 'participants' => '5 participantes'],
        ];

        // Pasamos la variable $conversations a la vista
        return view('reuniones', [
            'conversations' => $conversations
        ]);
    }
}
