<?php

namespace App\Http\Controllers\Collabora;

use App\Http\Controllers\Collabora\Concerns\HandlesWopiFiles;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PreviewController extends Controller
{
    use HandlesWopiFiles;

    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate([
            'id' => ['required', 'string'],
            'source' => ['nullable', 'in:upload,edit'],
        ]);

        $source = (string) $request->query('source', 'upload');
        $file = $this->resolveFileForUser(
            (string) $request->query('id'),
            (string) $request->user()->getKey(),
            $source,
        );

        $wopiSrc = rtrim((string) config('services.collabora.wopi_url'), '/')
            .route('collabora.wopi.files.show', ['file' => (string) $file->getKey()], false);

        $collaboraUrl = rtrim((string) config('services.collabora.public_url'), '/')
            .'/browser/dist/cool.html?'
            .http_build_query([
                'WOPISrc' => $wopiSrc,
                'access_token' => $this->makeAccessToken($file, $source),
                'lang' => app()->getLocale(),
            ], '', '&', PHP_QUERY_RFC3986);

        return redirect()->away($collaboraUrl);
    }
}
