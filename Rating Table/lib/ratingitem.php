<?php

namespace Mhp\Rating;

use Bitrix\Main\Entity;

class RatingItemTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'mhp_rating_rating_item';
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
            ]),
            new Entity\IntegerField('RATING_TYPE_ID'),
            new Entity\ReferenceField(
                'RATING_TYPE',
                'Mhp\Rating\RatingType',
                ['=this.RATING_TYPE_ID' => 'ref.ID'],
                ['join_type' => 'LEFT']
            ),
            new Entity\IntegerField('ENTITY_ID'),
            new Entity\IntegerField('IMPORTANCE_ID'),
            new Entity\ReferenceField(
                'IMPORTANCE',
                'Mhp\Rating\Importance',
                ['=this.IMPORTANCE_ID' => 'ref.ID'],
                ['join_type' => 'LEFT']
            ),
        ];
    }
}