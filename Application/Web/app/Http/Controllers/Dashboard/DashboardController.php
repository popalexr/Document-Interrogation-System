<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Upload;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the dashboard with user's uploads.
     */
    public function index(Request $request): Response
    {
        $userId = optional($request->user())->getKey();

        $uploads = $userId ? Upload::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['_id', 'original_name', 'mime_type', 'size', 'status', 'r2_key', 'created_at'])
            ->map(function ($u) {
                return [
                    '_id' => (string) $u->_id,
                    'original_name' => $u->original_name,
                    'mime_type' => $u->mime_type,
                    'size' => (int) $u->size,
                    'status' => (string) $u->status,
                    'r2_key' => (string) $u->r2_key,
                    'created_at' => $u->created_at,
                ];
            }) : collect();

        return Inertia::render('Dashboard', [
            'uploads' => $uploads,
        ]);
    }
}

