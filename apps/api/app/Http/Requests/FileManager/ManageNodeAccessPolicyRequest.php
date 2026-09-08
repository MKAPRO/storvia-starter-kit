<?php

namespace App\Http\Requests\FileManager;

use App\Models\FileSpace;
use App\Models\Node;
use App\Models\User;
use App\Services\FileManager\FileManagerRouteResolver;
use App\Services\FileManager\NodePrivacyManagementService;
use Illuminate\Foundation\Http\FormRequest;

class ManageNodeAccessPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        if (! $actor instanceof User) {
            return false;
        }

        $resolver = app(FileManagerRouteResolver::class);
        $fileSpace = $resolver->visibleSpaceOrFail(
            $actor,
            (string) $this->route('fileSpace'),
        );
        $node = $resolver->nodeInSpaceOrFail(
            $fileSpace,
            (string) $this->route('node'),
        );

        $this->attributes->set('file_space', $fileSpace);
        $this->attributes->set('node', $node);

        return app(NodePrivacyManagementService::class)
            ->canManage($actor, $fileSpace, $node);
    }

    /** @return array<string, list<mixed>> */
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

    public function node(): Node
    {
        /** @var Node $node */
        $node = $this->attributes->get('node');

        return $node;
    }
}
