<?php

namespace App\Support\Audit;

use LogicException;

final class AuditAction
{
    public const USER_CREATED = 'user.created';

    public const USER_UPDATED = 'user.updated';

    public const USER_STATUS_CHANGED = 'user.status_changed';

    public const USER_ROLES_CHANGED = 'user.roles_changed';

    public const USER_DEPARTMENTS_CHANGED = 'user.departments_changed';

    public const USER_PASSWORD_RESET = 'user.password_reset';

    public const DEPARTMENT_CREATED = 'department.created';

    public const DEPARTMENT_UPDATED = 'department.updated';

    public const DEPARTMENT_DELETED = 'department.deleted';

    public const DEPARTMENT_MEMBERS_CHANGED = 'department.members_changed';

    public const ROLE_CREATED = 'role.created';

    public const ROLE_UPDATED = 'role.updated';

    public const ROLE_PERMISSIONS_CHANGED = 'role.permissions_changed';

    public const STORAGE_QUOTA_UPDATED = 'storage.quota_updated';

    public const FILE_TYPE_CREATED = 'file_type.created';

    public const FILE_TYPE_UPDATED = 'file_type.updated';

    public const FILE_TYPE_DEPARTMENT_POLICY_UPDATED = 'file_type.department_policy_updated';

    public const FILE_TYPE_USER_POLICY_UPDATED = 'file_type.user_policy_updated';

    public const FOLDER_CREATED = 'folder.created';

    public const FILE_UPLOADED = 'file.uploaded';

    public const NODE_UPDATED = 'node.updated';

    public const NODE_TRASHED = 'node.trashed';

    public const NODE_RESTORED = 'node.restored';

    public const TRASH_PURGED = 'trash.purged';

    public const NODE_PRIVACY_CHANGED = 'node.privacy_changed';

    public const NODE_PASSWORD_SET = 'node.password_set';

    public const NODE_PASSWORD_CHANGED = 'node.password_changed';

    public const NODE_PASSWORD_REMOVED = 'node.password_removed';

    public const NODE_ACCESS_GRANTED = 'node.access_granted';

    public const NODE_ACCESS_REVOKED = 'node.access_revoked';

    /** @return array<string, string> */
    private static function categories(): array
    {
        return [
            self::USER_CREATED => AuditCategory::ADMINISTRATION,
            self::USER_UPDATED => AuditCategory::ADMINISTRATION,
            self::USER_STATUS_CHANGED => AuditCategory::ADMINISTRATION,
            self::USER_ROLES_CHANGED => AuditCategory::ADMINISTRATION,
            self::USER_DEPARTMENTS_CHANGED => AuditCategory::ADMINISTRATION,
            self::USER_PASSWORD_RESET => AuditCategory::ADMINISTRATION,
            self::DEPARTMENT_CREATED => AuditCategory::ADMINISTRATION,
            self::DEPARTMENT_UPDATED => AuditCategory::ADMINISTRATION,
            self::DEPARTMENT_DELETED => AuditCategory::ADMINISTRATION,
            self::DEPARTMENT_MEMBERS_CHANGED => AuditCategory::ADMINISTRATION,
            self::ROLE_CREATED => AuditCategory::ADMINISTRATION,
            self::ROLE_UPDATED => AuditCategory::ADMINISTRATION,
            self::ROLE_PERMISSIONS_CHANGED => AuditCategory::ADMINISTRATION,
            self::STORAGE_QUOTA_UPDATED => AuditCategory::STORAGE,
            self::FILE_TYPE_CREATED => AuditCategory::ADMINISTRATION,
            self::FILE_TYPE_UPDATED => AuditCategory::ADMINISTRATION,
            self::FILE_TYPE_DEPARTMENT_POLICY_UPDATED => AuditCategory::ADMINISTRATION,
            self::FILE_TYPE_USER_POLICY_UPDATED => AuditCategory::ADMINISTRATION,
            self::FOLDER_CREATED => AuditCategory::CONTENT,
            self::FILE_UPLOADED => AuditCategory::CONTENT,
            self::NODE_UPDATED => AuditCategory::CONTENT,
            self::NODE_TRASHED => AuditCategory::CONTENT,
            self::NODE_RESTORED => AuditCategory::CONTENT,
            self::TRASH_PURGED => AuditCategory::STORAGE,
            self::NODE_PRIVACY_CHANGED => AuditCategory::CONTENT,
            self::NODE_PASSWORD_SET => AuditCategory::CONTENT,
            self::NODE_PASSWORD_CHANGED => AuditCategory::CONTENT,
            self::NODE_PASSWORD_REMOVED => AuditCategory::CONTENT,
            self::NODE_ACCESS_GRANTED => AuditCategory::SHARING,
            self::NODE_ACCESS_REVOKED => AuditCategory::SHARING,
        ];
    }

    /** @return array<string, list<string>> */
    private static function metadataAllowlist(): array
    {
        return [
            self::USER_CREATED => [],
            self::USER_UPDATED => ['changed_fields'],
            self::USER_STATUS_CHANGED => ['is_active'],
            self::USER_ROLES_CHANGED => ['roles'],
            self::USER_DEPARTMENTS_CHANGED => ['departments'],
            self::USER_PASSWORD_RESET => [],
            self::DEPARTMENT_CREATED => ['parent_uuid', 'is_active'],
            self::DEPARTMENT_UPDATED => ['changed_fields', 'parent_uuid', 'is_active'],
            self::DEPARTMENT_DELETED => [],
            self::DEPARTMENT_MEMBERS_CHANGED => ['member_count'],
            self::ROLE_CREATED => [],
            self::ROLE_UPDATED => ['changed_fields'],
            self::ROLE_PERMISSIONS_CHANGED => ['permissions'],
            self::STORAGE_QUOTA_UPDATED => ['previous_limit_bytes', 'limit_bytes'],
            self::FILE_TYPE_CREATED => [],
            self::FILE_TYPE_UPDATED => ['changed_fields'],
            self::FILE_TYPE_DEPARTMENT_POLICY_UPDATED => ['disabled_count'],
            self::FILE_TYPE_USER_POLICY_UPDATED => ['disabled_count'],
            self::FOLDER_CREATED => ['parent_uuid'],
            self::FILE_UPLOADED => ['parent_uuid', 'size_bytes'],
            self::NODE_UPDATED => ['changed_fields', 'parent_uuid'],
            self::NODE_TRASHED => ['subtree_nodes'],
            self::NODE_RESTORED => ['restored_nodes'],
            self::TRASH_PURGED => ['purged_nodes', 'purged_files', 'released_bytes'],
            self::NODE_PRIVACY_CHANGED => ['visibility', 'revoked_grants_count'],
            self::NODE_PASSWORD_SET => [],
            self::NODE_PASSWORD_CHANGED => [],
            self::NODE_PASSWORD_REMOVED => [],
            self::NODE_ACCESS_GRANTED => ['recipient_user_uuid'],
            self::NODE_ACCESS_REVOKED => ['recipient_user_uuid'],
        ];
    }

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::categories());
    }

    public static function categoryFor(string $action): string
    {
        return self::categories()[$action]
            ?? throw new LogicException("Unknown audit action [{$action}].");
    }

    /** @return list<string> */
    public static function allowedMetadataKeys(string $action): array
    {
        if (! array_key_exists($action, self::categories())) {
            throw new LogicException("Unknown audit action [{$action}].");
        }

        return self::metadataAllowlist()[$action] ?? [];
    }
}
