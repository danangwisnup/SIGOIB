<?php
// Authorization scope di BACKEND (bukan hanya menyembunyikan menu).
// KOMANDAN/WADAN/ADMIN -> semua. DANKI -> Kompinya. DANTON -> Peletonnya.

class Scope
{
    public const ALL = 'ALL';
    public const COMPANY = 'COMPANY';
    public const PLATOON = 'PLATOON';

    public static function of(array $user): array
    {
        switch ($user['role']) {
            case 'DANKI':
                return ['mode' => self::COMPANY, 'org_id' => (int)$user['organization_id']];
            case 'DANTON':
                return ['mode' => self::PLATOON, 'org_id' => (int)$user['organization_id']];
            default:
                return ['mode' => self::ALL, 'org_id' => null];
        }
    }

    // Klausa WHERE untuk query yang join/berasal dari personnel (alias p).
    public static function personnelClause(array $scope, string $alias = 'p', string $and = 'AND'): array
    {
        if ($scope['mode'] === self::COMPANY) {
            return ["$and $alias.company_id = ?", [$scope['org_id']]];
        }
        if ($scope['mode'] === self::PLATOON) {
            return ["$and $alias.platoon_id = ?", [$scope['org_id']]];
        }
        return ['', []];
    }

    public static function canAccessPersonnel(array $scope, array $personnel): bool
    {
        if ($scope['mode'] === self::ALL) {
            return true;
        }
        if ($scope['mode'] === self::COMPANY) {
            return (int)$personnel['company_id'] === $scope['org_id'];
        }
        return (int)$personnel['platoon_id'] === $scope['org_id'];
    }

    public static function requireRole(array $user, array $roles): void
    {
        if (!in_array($user['role'], $roles, true)) {
            Response::error('Anda tidak memiliki hak akses untuk aksi ini.', 403);
        }
    }
}
