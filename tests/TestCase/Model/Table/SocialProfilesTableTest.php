<?php
declare(strict_types=1);

namespace ADmad\SocialAuth\Test\TestCase\Model\Table;

use ADmad\SocialAuth\Model\Table\SocialProfilesTable;
use Cake\TestSuite\TestCase;

class SocialProfilesTableTest extends TestCase
{
    protected SocialProfilesTable $socialProfilesTable;

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
