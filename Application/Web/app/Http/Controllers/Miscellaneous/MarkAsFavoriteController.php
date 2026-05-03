<?php

namespace App\Http\Controllers\Miscellaneous;

use App\Http\Controllers\Controller;
use App\Models\Upload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MarkAsFavoriteController extends Controller
{
    public function __construct(private Request $request) {}

    public function __invoke(): RedirectResponse
    {
        $document = Upload::query()
            ->where('_id', $this->request->get('id'))
            ->where('user_id', $this->request->user()->id)
            ->whereNull('deleted_at')
            ->first();

        if (blank($document)) {
            return redirect()->back()->with('error', 'Document not found.');
        }

        if ((bool) $document->favorite) {
            unset($document->favorite);
        } else {
            $document->favorite = true;
        }

        $document->save();

        return redirect()->back()->with('success', 'Favorite updated successfully.');
    }
}
