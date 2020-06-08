<?php

namespace Mhp\Rating;

use Bitrix\Main\Entity;

class ImportanceTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'mhp_rating_importance';
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