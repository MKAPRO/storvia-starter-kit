<?php

namespace App\Http\Requests\FileManager;

use App\Models\FileSpace;
use App\Models\Node;
use App\Models\User;
use App\Services\FileManager\FileManagerRouteResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BrowseNodesRequest extends FormRequest
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

        return $actor->can('view', $fileSpace);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'uuid'],
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in([Node::TYPE_FOLDER, Node::TYPE_FILE])],
            'sort' => ['nullable', Rule::in(['name', 'type', 'size', 'created_at', 'updated_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function fileSpace(): FileSpace
    {
        /** @var FileSpace $fileSpace */
        $fileSpace = $this->attributes->get('file_space');

        return $fileSpace;
    }
}
