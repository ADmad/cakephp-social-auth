<?php
declare(strict_types=1);

namespace ADmad\SocialAuth\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * UsersFixture
 */
class UsersFixture extends TestFixture
{
    /**
     * Fields
     *
     * @var array
     */
    // phpcs:disable
    public $fields = [
        'id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'autoIncrement' => true],
        'email' => ['type' => 'string', 'length' => 255, 'null' => true, 'default' => null],
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
