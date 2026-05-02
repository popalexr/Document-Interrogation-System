<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Upload;
use Illuminate\Http\Request;

class RestoreDocumentController extends Controller
{
    private $documentId;
    private ?Upload $document = null;

    public function __construct(private Request $request)
    {
        $this->documentId = $this->request->get('id', null);

        if ($this->documentId) {
            $this->document = Upload::where('_id', $this->documentId)->first();
        }
    }

    public function __invoke()
    {
        if (blank($this->document)) {
            return redirect()->back()->with('error', 'Document not found.');
        }

        if ($this->document->user_id !== $this->request->user()->id) {
            return redirect()->back()->with('error', 'Document not found.');
        }

        $this->document->deleted_at = null;
        $this->document->save();

        return redirect()->back()->with('success', 'Document restored successfully.');
    }
}
