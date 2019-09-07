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

class SocialProfilesTable extends Table
{
    /**
     * Initialize table.
     *
     * @param array $config Configuration
     *
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
     *
     * @return \Cake\Database\Schema\TableSchemaInterface
     */
    protected function _initializeSchema(TableSchemaInterface $schema): TableSchemaInterface
    {
        $schema->setColumnType('access_token', 'social-auth.serialized');

        return $schema;
    }
}
