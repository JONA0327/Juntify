<?php

namespace App\Http\Controllers;

use App\Models\Analyzer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AnalyzerController extends Controller
{
    protected function authorizeAdmin(): void
    {
        $user = auth()->user();
        if (!$user || !in_array($user->roles, ['superadmin', 'developer'])) {
            abort(403, 'No tienes permisos para acceder a esta sección');
        }
    }

    public function index()
    {
        $this->authorizeAdmin();
        $analyzers = Analyzer::all();
        return view('admin.analyzers', compact('analyzers'));
    }

    public function list()
    {
        return response()->json(Analyzer::all());
    }

    public function show(Analyzer $analyzer)
    {
        $this->authorizeAdmin();
        return response()->json($analyzer);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'id'                  => 'sometimes|string|max:50',
            'name'                => 'required|string|max:100',
            'description'         => 'nullable|string',
            'icon'                => 'nullable|string|max:50',
            'system_prompt'       => 'required|string',
            'user_prompt_template'=> 'required|string',
            'temperature'         => 'nullable|numeric|min:0|max:1',
            'is_system'           => 'nullable|boolean',
        ]);

        $data['id'] = $data['id'] ?? Str::uuid()->toString();
        $data['created_by'] = auth()->user()->username ?? null;
        $data['updated_by'] = auth()->user()->username ?? null;

        $analyzer = Analyzer::create($data);

        return response()->json($analyzer, 201);
    }

    public function update(Request $request, Analyzer $analyzer)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'name'                => 'sometimes|required|string|max:100',
            'description'         => 'nullable|string',
            'icon'                => 'nullable|string|max:50',
            'system_prompt'       => 'sometimes|required|string',
            'user_prompt_template'=> 'sometimes|required|string',
            'temperature'         => 'nullable|numeric|min:0|max:1',
            'is_system'           => 'nullable|boolean',
        ]);

        $data['updated_by'] = auth()->user()->username ?? null;
        $analyzer->update($data);

        return response()->json($analyzer);
    }

    public function destroy(Analyzer $analyzer)
    {
        $this->authorizeAdmin();
        $analyzer->delete();

        return response()->json(['deleted' => true]);
    }
}
