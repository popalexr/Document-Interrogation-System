<?php

namespace App\Http\Controllers\Chats;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chats\DeleteChatRequest;
use App\Models\Chat;

class DeleteChatController extends Controller
{
    public function __invoke(DeleteChatRequest $request)
    {
        Chat::find($request['chat_id'])?->delete();

        return redirect()->back()->with("success", "Chat has been deleted.");
    }
}
