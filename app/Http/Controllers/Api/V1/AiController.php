<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AiMessageResource;
use App\Services\AiAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use RuntimeException;

class AiController extends Controller
{
    /** POST /ai/ask  {message} → the assistant's answer (Markdown). */
    public function ask(Request $request, AiAssistant $assistant): AiMessageResource|JsonResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:1000']]);

        try {
            $answer = $assistant->ask($request->user(), $data['message']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return new AiMessageResource($answer);
    }

    /** GET /ai/messages — the last 50 messages, oldest first (chat order). */
    public function history(Request $request): AnonymousResourceCollection
    {
        $messages = $request->user()->aiMessages()->latest('id')->limit(50)->get()->reverse()->values();

        return AiMessageResource::collection($messages);
    }

    public function clear(Request $request): Response
    {
        $request->user()->aiMessages()->delete();

        return response()->noContent();
    }
}
