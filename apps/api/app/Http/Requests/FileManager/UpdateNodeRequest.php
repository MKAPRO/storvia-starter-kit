<?php

namespace App\Http\Requests\FileManager;

use App\Models\FileSpace;
use App\Models\Node;
use App\Models\User;
use App\Services\FileManager\FileManagerRouteResolver;
use App\Services\FileManager\FileSpaceAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        if (! $actor instanceof User) {
            return false;
        }

        $resolver = app(FileManagerRouteResolver::class);
        $fileSpace = $resolver->visibleSpaceOrFail($actor, (string) $this->route('fileSpace'));
        $node = $resolver->nodeInSpaceOrFail($fileSpace, (string) $this->route('node'));

        $this->attributes->set('file_space', $fileSpace);
        $this->attributes->set('node', $node);

        $access = app(FileSpaceAccessService::class);

        if ($this->has('name') && ! $access->canRenameNode($actor, $fileSpace, $node)) {
            return false;
        }

        if ($this->has('parent_id')) {
            if (! $actor->can('move', $node)) {
                return false;
            }

            if (
                filled($this->input('parent_id'))
                && ! $access->canViewFoldersInSpace($actor, $fileSpace)
            ) {
                return false;
            }
        }

        if (! $this->hasAny(['name', 'parent_id'])) {
            return $access->canManage($actor, $fileSpace);
        }

        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'parent_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->hasAny(['name', 'parent_id'])) {
                    $validator->errors()->add('name', 'At least one node change is required.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                'name' => trim((string) $this->input('name')),
            ]);
        }
    }

    public function fileSpace(): FileSpace
    {
        /** @var FileSpace $fileSpace */
        $fileSpace = $this->attributes->get('file_space');

        return $fileSpace;
    }

    public function node(): Node
    {
        /** @var Node $node */
        $node = $this->attributes->get('node');

        return $node;
    }
}
