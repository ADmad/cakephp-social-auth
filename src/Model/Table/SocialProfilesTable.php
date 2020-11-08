<?php
declare(strict_types=1);

/**
 * ADmad\SocialAuth plugin.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 */

namespace ADmad\SocialAuth\Model\Table;

use Cake\Database\Schema\TableSchemaInterface;
use Cake\ORM\Table;

/**
 * @method \ADmad\SocialAuth\Model\Entity\SocialProfile newEmptyEntity()
 * @method \ADmad\SocialAuth\Model\Entity\SocialProfile newEntity(array $data, array $options = [])
 * @method \ADmad\SocialAuth\Model\Entity\SocialProfile[] newEntities(array $data, array $options = [])
 * @method \ADmad\SocialAuth\Model\Entity\SocialProfile get($primaryKey, $options = [])
 * @method \ADmad\SocialAuth\Model\Entity\SocialProfile findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \ADmad\SocialAuth\Model\Entity\SocialProfile patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \ADmad\SocialAuth\Model\Entity\SocialProfile[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \ADmad\SocialAuth\Model\Entity\SocialProfile|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \ADmad\SocialAuth\Model\Entity\SocialProfile saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \ADmad\SocialAuth\Model\Entity\SocialProfile[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \ADmad\SocialAuth\Model\Entity\SocialProfile[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \ADmad\SocialAuth\Model\Entity\SocialProfile[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \ADmad\SocialAuth\Model\Entity\SocialProfile[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class SocialProfilesTable extends Table
{
    /**
     * Initialize table.
     *
     * @param array $config Configuration
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->addBehavior('Timestamp');
    }

    /**
     * Set custom type of "access_token" column.
     *
     * @param \Cake\Database\Schema\TableSchemaInterface $schema The table definition fetched from database.
     * @return \Cake\Database\Schema\TableSchemaInterface
     */
    protected function _initializeSchema(TableSchemaInterface $schema): TableSchemaInterface
    {
        $schema->setColumnType('access_token', 'social-auth.serialized');

        return $schema;
    }
}
