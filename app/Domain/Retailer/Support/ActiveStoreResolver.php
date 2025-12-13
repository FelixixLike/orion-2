<?php

namespace App\Domain\Retailer\Support;

use App\Domain\Store\Models\Store;
use App\Domain\User\Models\User;

class ActiveStoreResolver
{
    private const SESSION_KEY = 'portal.active_store_id';

    public static function getActiveStoreId(?User $user): ?int
    {
        if (! $user) {
            return null;
        }

        $storeIds = self::getUserStoreIds($user);

        if (empty($storeIds)) {
            return null;
        }

        $storeId = session(self::SESSION_KEY);
        if ($storeId && in_array((int) $storeId, $storeIds, true)) {
            return (int) $storeId;
        }

        // Si no hay tienda en sesiÓn o ya no pertenece al usuario,
        // tomamos la primera tienda disponible.
        $first = (int) $storeIds[0];
        session([self::SESSION_KEY => $first]);

        return $first;
    }

    public static function setActiveStoreId(User $user, int $storeId): void
    {
        $storeIds = self::getUserStoreIds($user);

        // Validar que la tienda pertenezca al usuario (pivot o legacy user_id)
        if (! in_array($storeId, $storeIds, true)) {
            return;
        }

        session([self::SESSION_KEY => $storeId]);
    }

    /**
     * @return array<int,int>  IDs de tiendas asociadas al usuario
     */
    private static function getUserStoreIds(User $user): array
    {
        $pivotIds = $user->stores()->pluck('stores.id')->all();

        // Fallback legacy: stores.user_id
        $ownedIds = Store::query()
            ->where('user_id', $user->id)
            ->pluck('id')
            ->all();

        return array_values(array_unique(array_map('intval', array_merge($pivotIds, $ownedIds))));
    }
}
