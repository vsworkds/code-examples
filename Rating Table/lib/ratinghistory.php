<?php

namespace Mhp\Rating;

use Bitrix\Main\Entity;

class RatingHistoryTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'mhp_rating_rating_history';
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
            ]),
            new Entity\DateField('DATE_START'),
            new Entity\DateField('DATE_END'),
            new Entity\StringField('CITY_CODE'),
            new Entity\IntegerField('ENTITY_ID'),
            new Entity\StringField('QUANTITY'),
            new Entity\ReferenceField(
                'ENTITY',
                'Mhp\Rating\RatingEntity',
                ['=this.ENTITY_ID' => 'ref.ID'],
                ['join_type' => 'LEFT']
            ),
            new Entity\IntegerField('RATING_TYPE_ID'),
            new Entity\ReferenceField(
                'RATING_TYPE',
                'Mhp\Rating\RatingType',
                ['=this.RATING_TYPE_ID' => 'ref.ID'],
                ['join_type' => 'LEFT']
            ),
            new Entity\IntegerField('POINTS')
        ];
    }
}