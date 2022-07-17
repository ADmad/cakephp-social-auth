<?php
declare(strict_types=1);

/**
 * ADmad\SocialAuth plugin.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 */

namespace ADmad\SocialAuth\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $provider
 * @property \SocialConnect\Provider\AccessTokenInterface $access_token
 * @property string $identifier
 * @property string|null $username
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $full_name
 * @property string|null $email
 * @property \Cake\I18n\FrozenDate|null $birth_date
 * @property string|null $gender
 * @property string|null $picture_url
 * @property bool $email_verified
 * @property \Cake\I18n\FrozenTime|null $created
 * @property \Cake\I18n\FrozenTime|null $modified
 */
class SocialProfile extends Entity
{
    /**
     * @var array<string, bool>
     */
    protected $_accessible = [
        '*' => true,
        'id' => false,
    ];
}
