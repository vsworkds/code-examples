<?php

namespace Mhp\Rating;

use Bitrix\Main\Entity;

class EntityItemTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'mhp_rating_entity_item';
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
            ]),
            new Entity\StringField('CODE'),
            new Entity\IntegerField('ENTITY_ID'),
            new Entity\ReferenceField(
                'ENTITY',
                'Mhp\Rating\RatingEntity',
                ['=this.ENTITY_ID' => 'ref.ID'],
                ['join_type' => 'LEFT']
            ),
            new Entity\IntegerField('ITEM_ID')
        ];
    }
}