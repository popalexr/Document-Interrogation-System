<?php

namespace App\Http\Controllers\Miscellaneous;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\EditChat;
use App\Models\EditInterrogation;
use App\Models\Interrogation;
use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class RecentDocumentsController extends Controller
{
    private ?string $userId;

    public function __construct(private Request $request)
    {
        $this->userId = optional($request->user())->getKey();
    }

    public function __invoke(): Response
    {
        return Inertia::render('documents/RecentDocuments', [
            'recentDocuments' => $this->getRecentDocuments(),
        ]);
    }

    private function getRecentDocuments(): Collection
    {
        if (blank($this->userId)) {
            return collect();
        }

        $uploads = Upload::query()
            ->where('user_id', $this->userId)
            ->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->limit(250)
            ->get([
                '_id',
                'original_name',
                'mime_type',
                'size',
                'status',
                'r2_key',
                'meta',
                'created_at',
                'updated_at',
            ]);

        if ($uploads->isEmpty()) {
            return collect();
        }

        $uploadsById = $uploads->keyBy(fn (Upload $upload) => (string) $upload->_id);
        $actions = [];

        foreach ($uploads as $upload) {
            $documentId = (string) $upload->_id;

            $this->rememberAction($actions, $documentId, 'uploaded', $upload->created_at);

            $overriddenAt = data_get($upload->meta, 'last_override.overridden_at');
            if (!blank($overriddenAt)) {
                $this->rememberAction($actions, $documentId, 'updated', $overriddenAt);
            } elseif ($this->isMeaningfulUploadUpdate($upload)) {
                $this->rememberAction($actions, $documentId, 'updated', $upload->updated_at);
            }
        }

        $documentIds = $uploadsById->keys()->all();

        $chatDocumentIds = $this->getChatDocumentIds(Chat::class, $documentIds);
        $this->rememberLatestInterrogationActions(
            $actions,
            $chatDocumentIds,
            Interrogation::class,
            'interrogated'
        );

        $editChatDocumentIds = $this->getChatDocumentIds(EditChat::class, $documentIds);
        $this->rememberLatestInterrogationActions(
            $actions,
            $editChatDocumentIds,
            EditInterrogation::class,
            'edited'
        );

        return collect($actions)
            ->sortByDesc(fn (array $action) => $action['at_timestamp'])
            ->take(10)
            ->map(function (array $action) use ($uploadsById) {
                $upload = $uploadsById->get($action['document_id']);

                return [
                    '_id' => (string) $upload->_id,
                    'original_name' => $upload->original_name,
                    'mime_type' => $upload->mime_type,
                    'size' => (int) $upload->size,
                    'status' => (string) $upload->status,
                    'r2_key' => (string) $upload->r2_key,
                    'created_at' => $upload->created_at,
                    'updated_at' => $upload->updated_at,
                    'recent_action' => $action['action'],
                    'recent_action_at' => $action['at'],
                ];
            })
            ->values();
    }

    private function getChatDocumentIds(string $chatModel, array $documentIds): Collection
    {
        return $chatModel::query()
            ->where('user_id', $this->userId)
            ->whereIn('document_id', $documentIds)
            ->get(['_id', 'document_id'])
            ->mapWithKeys(fn ($chat) => [(string) $chat->_id => (string) $chat->document_id]);
    }

    private function rememberLatestInterrogationActions(
        array &$actions,
        Collection $chatDocumentIds,
        string $interrogationModel,
        string $action
    ): void {
        if ($chatDocumentIds->isEmpty()) {
            return;
        }

        $latestByDocument = [];

        $interrogationModel::query()
            ->whereIn('chat_id', $chatDocumentIds->keys()->all())
            ->orderByDesc('created_at')
            ->limit(500)
            ->get(['chat_id', 'created_at'])
            ->each(function ($interrogation) use ($chatDocumentIds, &$latestByDocument) {
                $documentId = $chatDocumentIds->get((string) $interrogation->chat_id);

                if (blank($documentId) || isset($latestByDocument[$documentId])) {
                    return;
                }

                $latestByDocument[$documentId] = $interrogation->created_at;
            });

        foreach ($latestByDocument as $documentId => $actedAt) {
            $this->rememberAction($actions, $documentId, $action, $actedAt);
        }
    }

    private function rememberAction(array &$actions, string $documentId, string $action, mixed $actedAt): void
    {
        $date = $this->asCarbon($actedAt);

        if (blank($date)) {
            return;
        }

        $timestamp = $date->getTimestamp();

        if (isset($actions[$documentId]) && $actions[$documentId]['at_timestamp'] >= $timestamp) {
            return;
        }

        $actions[$documentId] = [
            'document_id' => $documentId,
            'action' => $action,
            'at' => $date,
            'at_timestamp' => $timestamp,
        ];
    }

    private function isMeaningfulUploadUpdate(Upload $upload): bool
    {
        if (blank($upload->created_at) || blank($upload->updated_at)) {
            return false;
        }

        return $upload->updated_at->greaterThan($upload->created_at->copy()->addSeconds(5));
    }

    private function asCarbon(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
