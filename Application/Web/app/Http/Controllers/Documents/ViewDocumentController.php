<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Upload;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ViewDocumentController extends Controller
{
    private ?string $documentId;
    private ?Upload $document;

    public function __construct(private Request $request)
    {
        $this->documentId = $this->request->get('id', null);

        $this->document = $this->documentId ? Upload::find($this->documentId) : null;
    }

    public function __invoke()
    {
        if (blank($this->document)) {
            return redirect()->back()->with('error', 'Document not found.');
        }

        if ($this->document->user_id !== $this->request->user()->id) {
            return redirect()->back()->with('error', 'Document not found.');
        }

        return Inertia::render('documents/ViewDocument', [
            'document' => [
                '_id' => (string) $this->document->_id,
                'original_name' => $this->document->original_name,
                'mime_type' => (string) $this->document->mime_type,
                'size' => (int) $this->document->size,
                'r2_key' => (string) $this->document->r2_key,
            ]
        ]);
    }
}
