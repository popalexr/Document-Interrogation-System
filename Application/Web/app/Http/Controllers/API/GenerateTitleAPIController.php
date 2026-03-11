<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\GenerateTitleRequest;
use App\Models\Chat;
use Illuminate\Support\Facades\Http;

class GenerateTitleAPIController extends Controller
{
    public function __construct(private GenerateTitleRequest $request)
    {}
    
    public function __invoke()
    {
        $chat = Chat::find($this->request['chat_id']);

        $response = Http::post(config('mcp.host') . ':' . config('mcp.port') . config('mcp.generate_title_endpoint'), [
            'query' => $this->request['query'],
        ]);

        if ($response->successful()) {
            $title = $response->json()['title'];
            $chat->update(['title' => $title]);

            return response()->json($response->json());
        } else {
            return response()->json(['error' => 'Failed to generate title'], 500);
        }
    }
}
