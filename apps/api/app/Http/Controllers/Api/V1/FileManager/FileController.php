<?php

namespace App\Http\Controllers\Api\V1\FileManager;

use App\Actions\FileManager\CreateFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\FileManager\DownloadFileRequest;
use App\Http\Requests\FileManager\StoreFileRequest;
use App\Http\Resources\FileManager\NodeResource;
use App\Models\Node;
use App\Models\User;
use App\Services\FileManager\FileStorageService;
use App\Services\FileManager\NodeActionCapabilityService;
use App\Services\FileManager\NodeResourceAccessService;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class FileController extends Controller
{
    public function store(
        StoreFileRequest $request,
        CreateFile $createFile,
        NodeActionCapabilityService $capabilities,
        NodeResourceAccessService $resourceAccess,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();
        $attributes = [
            'name' => $request->safeFileName(),
            'parent_id' => $validated['parent_id'] ?? null,
        ];
        $uploadedFile = $request->uploadedFile();
        $stream = fopen($uploadedFile->getPathname(), 'rb');

        if ($stream === false) {
            throw new RuntimeException('Unable to open the validated upload stream.');
        }

        try {
            $file = $createFile->handle(
                $request->fileSpace(),
                $actor,
                $attributes,
                $stream,
            );
        } finally {
            fclose($stream);
        }

        $file->load(['parent:id,uuid', 'owner:id,uuid,name'])
            ->loadCount('children');
        $resourceAccess->annotate($actor, $file, $request->fileSpace());
        $file->setAttribute(
            'allowed_actions',
            $capabilities->forActiveNode($actor, $request->fileSpace(), $file),
        );

        return (new NodeResource($file))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function download(
        DownloadFileRequest $request,
        FileStorageService $storage,
        NodeResourceAccessService $resourceAccess,
    ): StreamedResponse {
        $file = $request->node();
        /** @var User $actor */
        $actor = $request->user();
        $resourceAccess->assertAccessible($actor, $file);
        $stream = $storage->readNodeStream($file);

        return response()->streamDownload(
            static function () use ($stream): void {
                try {
                    fpassthru($stream);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            },
            $this->downloadFileName($file),
            [
                'Content-Type' => $this->downloadMimeType($file),
                'Content-Length' => (string) $file->size,
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    private function downloadFileName(Node $file): string
    {
        $name = preg_replace(
            '/[\x00-\x1F\x7F\/\\\\]+/u',
            '_',
            (string) $file->name,
        );

        if (! is_string($name)) {
            return 'download';
        }

        $name = trim($name);

        if ($name === '' || $name === '.' || $name === '..') {
            return 'download';
        }

        return $name;
    }

    private function downloadMimeType(Node $file): string
    {
        $mimeType = trim((string) $file->mime_type);

        if (
            $mimeType === ''
            || preg_match(
                '/\A[a-zA-Z0-9][a-zA-Z0-9!#$&^_.+-]*\/[a-zA-Z0-9][a-zA-Z0-9!#$&^_.+-]*\z/',
                $mimeType,
            ) !== 1
        ) {
            return 'application/octet-stream';
        }

        return $mimeType;
    }
}
