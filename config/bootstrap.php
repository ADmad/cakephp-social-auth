<?php
use ADmad\SocialAuth\Database\Type\SerializeType;
use Cake\Database\Type;

Type::map('socialauth.serialize', SerializeType::class);
