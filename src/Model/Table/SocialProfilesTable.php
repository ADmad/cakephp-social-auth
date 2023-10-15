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
 * @method \ADmad\SocialAuth\Model\Entity\SocialProfile[] newEntities(array $data, array $options = [])
 * @method \ADmad\SocialAuth\Model\Entity\SocialProfile get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \ADmad\SocialAuth\Model\Entity\SocialProfile findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \ADmad\SocialAuth\Model\Entity\SocialProfile patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \ADmad\SocialAuth\Model\Entity\SocialProfile[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \ADmad\SocialAuth\Model\Entity\SocialProfile|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \ADmad\SocialAuth\Model\Entity\SocialProfile saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \ADmad\SocialAuth\Model\Entity\SocialProfile[]|false saveMany(iterable $entities, $options = [])
 * @method \ADmad\SocialAuth\Model\Entity\SocialProfile[] saveManyOrFail(iterable $entities, $options = [])
 * @method \ADmad\SocialAuth\Model\Entity\SocialProfile[]|false deleteMany(iterable $entities, $options = [])
 * @method \ADmad\SocialAuth\Model\Entity\SocialProfile[] deleteManyOrFail(iterable $entities, $options = [])
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
