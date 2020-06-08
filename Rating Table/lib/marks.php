<?php

namespace Mhp\Rating;

use Bitrix\Main\Entity;

class MarksTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'mhp_rating_marks';
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