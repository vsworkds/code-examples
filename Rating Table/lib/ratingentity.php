<?php

namespace Mhp\Rating;

use Bitrix\Main\Entity;

class RatingEntityTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'mhp_rating_rating_entity';
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
            ]),
            new Entity\StringField('NAME'),
            new Entity\StringField('CODE', [
                'unique' => true,
            ]),
        ];
    }
}