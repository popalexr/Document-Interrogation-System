<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Repositories\UploadRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private $userId;

    public function __construct(
        private Request $request,
        private UploadRepository $uploads,
    )
    {
        $this->userId = optional($request->user())->getKey();
    }

    /**
     * Show the dashboard with user's uploads.
     */
    public function __invoke(): Response
    {
        $uploads = $this->getUserUploads();

        return Inertia::render('Dashboard', [
            'uploads' => $uploads,
        ]);
    }

    /**
     * Get the user's uploads.
     */
    private function getUserUploads(): Collection
    {
        if (! $this->request->user()) {
            return collect();
        }

        return $this->uploads->dashboardUploadsForUser((string) $this->userId);
    }
}
