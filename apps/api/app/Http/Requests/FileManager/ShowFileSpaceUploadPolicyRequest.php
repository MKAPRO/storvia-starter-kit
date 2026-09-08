<?php

namespace App\Http\Requests\FileManager;

use App\Models\FileSpace;
use App\Models\User;
use App\Services\FileManager\FileManagerRouteResolver;
use App\Services\FileManager\FileSpaceAccessService;
use Illuminate\Foundation\Http\FormRequest;

final class ShowFileSpaceUploadPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        if (! $actor instanceof User) {
            return false;
        }

        $fileSpace = app(FileManagerRouteResolver::class)
            ->visibleSpaceOrFail($actor, (string) $this->route('fileSpace'));
        $this->attributes->set('file_space', $fileSpace);

        return app(FileSpaceAccessService::class)->canView($actor, $fileSpace);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [];
    }

    public function fileSpace(): FileSpace
    {
        /** @var FileSpace $fileSpace */
        $fileSpace = $this->attributes->get('file_space');

        return $fileSpace;
    }
}
