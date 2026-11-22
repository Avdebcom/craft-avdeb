<?php

namespace avdeb\craftavdeb\stores;

use avdeb\craftavdeb\lib\Store\StoreInterface;
use Craft;
use yii\caching\TagDependency;

/**
 * Key/value store for the catalog client on top of the site's cache component (file, database,
 * Redis…). Entries live for a week plus an hour (the "last good copy" window); freshness is decided
 * by the client. Every entry carries the `avdeb` tag so "Refresh products now" drops them all.
 */
final class CraftCacheStore implements StoreInterface
{
    public const TAG = 'avdeb';
    private const PREFIX = 'avdeb:';
    private const TTL = 7 * 24 * 3600 + 3600;

    public function get(string $key): mixed
    {
        $value = Craft::$app->getCache()->get(self::PREFIX . $key);

        return $value === false ? null : $value;
    }

    public function set(string $key, mixed $value): void
    {
        Craft::$app->getCache()->set(self::PREFIX . $key, $value, self::TTL, new TagDependency(['tags' => [self::TAG]]));
    }

    public function delete(string $key): void
    {
        Craft::$app->getCache()->delete(self::PREFIX . $key);
    }

    public function clear(): void
    {
        TagDependency::invalidate(Craft::$app->getCache(), self::TAG);
    }
}
