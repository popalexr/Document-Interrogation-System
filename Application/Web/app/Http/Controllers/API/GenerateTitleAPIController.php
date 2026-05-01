<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\GenerateTitleRequest;
use App\Models\AIInterrogationChat;
use App\Models\Chat;
use App\Models\EditChat;
use Illuminate\Support\Facades\Http;

class GenerateTitleAPIController extends Controller
{
    public function __construct(private GenerateTitleRequest $request)
    {}
    
    public function __invoke()
    {
        $chat = Chat::find($this->request['chat_id'])
            ?? EditChat::find($this->request['chat_id'])
            ?? AIInterrogationChat::find($this->request['chat_id']);

        if (blank($chat)) {
            return response()->json(['error' => 'Chat not found'], 404);
        }

        $response = Http::post(config('mcp.host') . ':' . config('mcp.port') . config('mcp.generate_title_endpoint'), [
            'query' => $this->request['query'],
        ]);

        if ($response->successful()) {
            $title = $response->json()['title'];
            $chat->update([
                'title'      => $title,
                'updated_at' => now(),
            ]);

            return response()->json($response->json());
        } else {
            return response()->json(['error' => 'Failed to generate title'], 500);
        }
    }
}
