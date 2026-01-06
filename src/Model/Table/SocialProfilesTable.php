<?php
declare(strict_types=1);

/**
 * ADmad\SocialAuth plugin.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 */

namespace ADmad\SocialAuth\Model\Table;

use Cake\ORM\Table;

/**
 * @method \ADmad\SocialAuth\Model\Entity\SocialProfile newEmptyEntity()
 * @method \ADmad\SocialAuth\Model\Entity\SocialProfile newEntity(array $data, array $options = [])
 * @method array<\ADmad\SocialAuth\Model\Entity\SocialProfile> newEntities(array $data, array $options = [])
 * @method \ADmad\SocialAuth\Model\Entity\SocialProfile get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \ADmad\SocialAuth\Model\Entity\SocialProfile findOrCreate(\Cake\ORM\Query\SelectQuery|callable|array $search, callable|array|null $callback = null, array $options = [])
 * @extends \Cake\ORM\Table<array{Timestamp: \Cake\ORM\Behavior\TimestampBehavior}>
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class SocialProfilesTable extends Table
{
    /**
     * Initialize table.
     *
     * @param array<string, mixed> $config Configuration
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->addBehavior('Timestamp');
        $this->getSchema()->setColumnType('access_token', 'social-auth.serialized');
    }
}
