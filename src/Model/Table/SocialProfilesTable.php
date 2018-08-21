<?php
/**
 * ADmad\SocialAuth plugin.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 */

namespace ADmad\SocialAuth\Model\Table;

use Cake\Database\Schema\TableSchema;
use Cake\ORM\Table;

class SocialProfilesTable extends Table
{
    /**
     * Initialize table.
     *
     * @param array $config Configuration
     *
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->addBehavior('Timestamp');
    }

    /**
     * Set custom type of "access_token" column.
     *
     * @param \Cake\Database\Schema\TableSchema $schema The table definition fetched from database.
     *
     * @return \Cake\Database\Schema\TableSchema
     */
    protected function _initializeSchema(TableSchema $schema)
    {
        $schema->setColumnType('access_token', 'socialauth.serialize');

        return $schema;
    }
}
