<?php
declare(strict_types=1);

namespace ADmad\SocialAuth\Test\TestCase\Model\Table;

use ADmad\SocialAuth\Model\Table\SocialProfilesTable;
use Cake\TestSuite\TestCase;
use SocialConnect\OAuth2\AccessToken;

class SocialProfilesTableTest extends TestCase
{
    protected SocialProfilesTable $socialProfilesTable;

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->loadPlugins(['ADmad/SocialAuth']);
        $this->socialProfilesTable = $this->getTableLocator()->get('ADmad/SocialAuth.SocialProfiles');
    }

    /**
     * @return void
     */
    public function testSave()
    {
        $array = [
            'id' => 999,
            'provider' => 'github',
            'identifier' => '12345',
            'access_token' => serialize('DEMO'),
        ];
        $socialProfile = $this->socialProfilesTable->newEntity($array);
        $this->assertNull($socialProfile->id);

        $this->socialProfilesTable->saveOrFail($socialProfile);

        $socialProfile = $this->socialProfilesTable->get($socialProfile->id);
        $this->assertSame('DEMO', $socialProfile->access_token);

        $token = new AccessToken(['access_token' => 'DEMO', 'refresh_token' => 'REFRESH']);
        $socialProfile->set('access_token', $token);
        $this->socialProfilesTable->saveOrFail($socialProfile);

        $socialProfile = $this->socialProfilesTable->get($socialProfile->id);
        $this->assertEquals($token, $socialProfile->access_token);
    }
}
