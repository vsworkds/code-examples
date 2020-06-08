<?php

namespace Mhp\Rating;

use Bitrix\Main\Entity;

class PointsTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'mhp_rating_points';
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
            ]),
            new Entity\IntegerField('IMPORTANCE_ID'),
            new Entity\ReferenceField(
                'IMPORTANCE',
                'Mhp\Rating\Importance',
                ['=this.IMPORTANCE_ID' => 'ref.ID'],
                ['join_type' => 'LEFT']
            ),
            new Entity\IntegerField('MARK_ID'),
            new Entity\ReferenceField(
                'MARK',
                'Mhp\Rating\Marks',
                ['=this.MARK_ID' => 'ref.ID'],
                ['join_type' => 'LEFT']
            ),
            new Entity\IntegerField('POINTS'),
        ];
    }
}