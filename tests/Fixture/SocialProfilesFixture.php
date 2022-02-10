<?php
declare(strict_types=1);

namespace ADmad\SocialAuth\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * SocialProfilesFixture
 */
class SocialProfilesFixture extends TestFixture
{
    /**
     * Fields
     *
     * @var array
     */
    // phpcs:disable
    public $fields = [
        'id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'autoIncrement' => true],
        'user_id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => true, 'default' => null, 'autoIncrement' => null],
        'provider' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null],
        'access_token' => ['type' => 'binary', 'length' => null, 'null' => false, 'default' => null],
        'identifier' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null],
        'username' => ['type' => 'string', 'length' => 255, 'null' => true, 'default' => null],
        'first_name' => ['type' => 'string', 'length' => 255, 'null' => true, 'default' => null],
        'last_name' => ['type' => 'string', 'length' => 255, 'null' => true, 'default' => null],
        'full_name' => ['type' => 'string', 'length' => 255, 'null' => true, 'default' => null],
        'email' => ['type' => 'string', 'length' => 255, 'null' => true, 'default' => null],
        'birth_date' => ['type' => 'date', 'length' => null, 'null' => true, 'default' => null],
        'gender' => ['type' => 'string', 'length' => 255, 'null' => true, 'default' => null],
        'picture_url' => ['type' => 'string', 'length' => 255, 'null' => true, 'default' => null],
        'email_verified' => ['type' => 'boolean', 'length' => null, 'null' => false, 'default' => '0'],
        'created' => ['type' => 'datetime', 'length' => null, 'null' => true, 'default' => null],
        'modified' => ['type' => 'datetime', 'length' => null, 'null' => true, 'default' => null],
        '_indexes' => [
            'user_id' => ['type' => 'index', 'columns' => ['user_id'], 'length' => []],
        ],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id'], 'length' => []],
        ],
    ];
    // phpcs:enable

    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [];

        parent::init();
    }
}
