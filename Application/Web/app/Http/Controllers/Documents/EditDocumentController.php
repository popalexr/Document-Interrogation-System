<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Upload;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EditDocumentController extends Controller
{
    private ?string $documentId = null;
    private ?Upload $document = null;

    public function __construct(private Request $request)
    {
        $this->documentId = $this->request->get('id');
        $this->document = Upload::find($this->documentId);
    }

    public function __invoke()
    {
        if (blank($this->document)) {
            return redirect()->back()->with('error', 'Document not found.');
        }

        return Inertia::render('documents/EditDocument', [
            'document' => $this->document,
        ]);
    }
}
