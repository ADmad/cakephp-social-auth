<?php
/**
 * ADmad\SocialAuth plugin.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 */

namespace ADmad\SocialAuth\Model\Entity;

use Cake\ORM\Entity;

class SocialProfile extends Entity
{
    protected $_accessible = [
        '*' => true,
        'id' => false,
    ];
}
