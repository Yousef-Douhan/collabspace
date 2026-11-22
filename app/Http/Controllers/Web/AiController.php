<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AiAssistant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class AiController extends Controller
{
    public function index(Request $request): View
    {
        return view('ai.index', [
            'messages' => $request->user()->aiMessages()->latest('id')->limit(50)->get()->reverse()->values(),
        ]);
    }

    public function ask(Request $request, AiAssistant $assistant): RedirectResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:1000']]);

        try {
            $assistant->ask($request->user(), $data['message']);
        } catch (RuntimeException $e) {
            return redirect()->route('ai.index')->withInput()->withErrors(['message' => $e->getMessage()]);
        }

        return redirect()->route('ai.index');
    }

    public function clear(Request $request): RedirectResponse
    {
        $request->user()->aiMessages()->delete();

        return redirect()->route('ai.index');
    }
}
