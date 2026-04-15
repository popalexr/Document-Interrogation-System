<?php

namespace App\Http\Controllers\Chats;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chats\DeleteEditChatRequest;
use App\Models\EditChat;

class DeleteEditChatController extends Controller
{
    public function __invoke(DeleteEditChatRequest $request)
    {
        EditChat::find($request['chat_id'])?->delete();

        return redirect()->back()->with("success", "Chat has been deleted.");
    }
}
