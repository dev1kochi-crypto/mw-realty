<?php

namespace App\Http\Controllers\Api;

use App\Services\ChatbotService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/** Public endpoint the site-wide AI chat widget posts each message to. See ChatbotService. */
class ChatbotController extends Controller
{
    public function __construct(private readonly ChatbotService $chatbot)
    {
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'message' => 'required|string|max:1000',
            'history' => 'nullable|array|max:20',
            'history.*.role' => 'required_with:history|in:user,model',
            'history.*.text' => 'required_with:history|string|max:2000',
            'lang' => 'nullable|string|max:5',
        ]);

        return response()->json($this->chatbot->reply(
            $data['message'],
            $data['history'] ?? [],
            $data['lang'] ?? app()->getLocale(),
        ));
    }
}
