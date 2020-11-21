<?php
declare(strict_types=1);

namespace ADmad\SocialAuth\Test\TestCase\Model\Table;

use Cake\TestSuite\TestCase;

/**
 * @property \ADmad\SocialAuth\Model\Table\SocialProfilesTable $socialProfilesTable
 */
class SocialProfilesTableTest extends TestCase
{
    /**
     * @var string[]
     */
    protected $fixtures = [
        'plugin.ADmad/SocialAuth.SocialProfiles',
    ];

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->socialProfilesTable = $this->getTableLocator()->get('ADmad/SocialAuth.SocialProfiles');
    }

    /**
     * @return void
     */
    public function testSave()
    {
        $array = [
            'provider' => 'github',
            'identifier' => '12345',
            'access_token' => serialize('DEMO'),
        ];
        $socialProfile = $this->socialProfilesTable->newEntity($array);

        $this->socialProfilesTable->saveOrFail($socialProfile);

        $socialProfile = $this->socialProfilesTable->get($socialProfile->id);
        $this->assertSame('DEMO', $socialProfile->access_token);
    }
}
