<?php

namespace Mhp\Rating;

use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\IblockTable;
use Bitrix\Main\Context;
use Bitrix\Main\Entity\ExpressionField;
use Bitrix\Main\Grid\Declension;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\Date;
use Bitrix\Sale\BusinessValue;
use Bitrix\Sale\Internals\PaySystemActionTable;

class RatingCalculationHelper
{
    protected $ratingTypes;
    protected $importances;
    protected $_importances;
    protected $marks;
    protected $_marks;
    protected $entities;
    protected $_entities;
    protected $entityItems;
    protected $pointsTable;

    public $plurals;

    const POSTER_IBLOCK_ID = 1;
    const NEWS_IBLOCK_ID = 7;
    const FACTS_IBLOCK_ID = 31;
    const REVIEWS_IBLOCK_ID = 34;
    const OUR_PROJECTS_IBLOCK_ID = 58;
    const EXHIBITIONS_IBLOCK_ID = 86;
    const EXCURSION_TYPES_IBLOCK_ID = 94;

    function __construct()
    {
        $this->ratingTypes = self::prepareArray(RatingTypeTable::getList()->fetchAll());
        $this->importances = self::prepareArray(ImportanceTable::getList()->fetchAll());
        $this->_importances = self::prepareArray(ImportanceTable::getList()->fetchAll(), 'ID => ALL');
        $this->marks = self::prepareArray(MarksTable::getList()->fetchAll());
        $this->_marks = self::prepareArray(MarksTable::getList()->fetchAll(), 'ID => ALL');
        $this->entities = self::prepareArray(RatingEntityTable::getList()->fetchAll());
        $this->_entities = self::prepareArray(RatingEntityTable::getList()->fetchAll(), 'ID => ALL');

        Loader::includeModule('iblock');
        Loader::includeModule('sale');

        $this->preparePointsTable();

        $this->plurals = [
            self::POSTER_IBLOCK_ID => new Declension('анонс', 'анонса', 'анонсов'),
            self::NEWS_IBLOCK_ID => new Declension('новость', 'новости', 'новостей'),
            self::FACTS_IBLOCK_ID => new Declension('факт', 'факта', 'фактов'),
            self::REVIEWS_IBLOCK_ID => new Declension('отзыв', 'отзыва', 'отзывов'),
            self::OUR_PROJECTS_IBLOCK_ID => new Declension('проект', 'проекта', 'проектов'),
            self::EXHIBITIONS_IBLOCK_ID => new Declension('выставку', 'выставки', 'выставок'),
            'points' => new Declension('очко', 'очка', 'очков'),
        ];
    }

    public function calculateRelevantPoints($cityCode, $monday = null, $sunday = null)
    {
        $city = getCityId($cityCode);

        $monday = $monday ?: ConvertTimeStamp(MakeTimeStamp(date('Y-m-d H:i:s', strtotime('monday this week')), 'YYYY.MM.DD HH:MI:SS'), 'FULL');
        $sunday = $sunday ?: ConvertTimeStamp(MakeTimeStamp(date('Y-m-d H:i:s', strtotime('next monday -1 second')), 'YYYY.MM.DD HH:MI:SS'), 'FULL');

        $ratingItemsList = RatingItemTable::getList([
            'filter' => [
                'RATING_TYPE_ID' => $this->ratingTypes['RELEVANT'],
            ],
        ])->fetchAll();

        $ratingItems = [];
        $ratingEntities = [];

        foreach ($ratingItemsList as $ratingItem) {
            $ratingItems[$ratingItem['ID']] = $ratingItem;
            $ratingEntities[] = $ratingItem['ENTITY_ID'];
        }

        $items = EntityItemTable::getList([
            'filter' => [
                'ENTITY_ID' => $ratingEntities,
            ],
        ])->fetchAll();

        $entities = [];

        foreach ($items as $item) {
            $entities[$item['ENTITY_ID']][] = $item['ITEM_ID'];
        }

        $ratingItemsResults = [];

        foreach ($ratingItemsList as $ratingItem) {
            $itemEntities = $entities[$ratingItem['ENTITY_ID']];

            foreach ($itemEntities as $itemEntity) {
                $elements = \CIBlockElement::GetList([], [
                    'ACTIVE' => 'Y',
                    '>DATE_CREATE' => $monday,
                    '<DATE_CREATE' => $sunday,
                    'IBLOCK_ID' => $itemEntity,
                    'PROPERTY_CITY' => $city,
                ], []);

                $ratingItemsResults[$ratingItem['ID']][$itemEntity] = $elements;
            }
        }

        $summary = 0;
        $detailed = [];
        $hints = [];

        foreach ($ratingItemsResults as $ratingEntityId => $ratingItemsResult) {
            foreach ($ratingItemsResult as $entityKey => $entityResult) {
                $mark = '';
                $max = 0;
                $good = 0;

                switch ($entityKey) {
                    case self::POSTER_IBLOCK_ID:
                        $max = 4;
                        $good = 2;
                        $mark = $this->rate($entityResult, $max, $good);

                        break;
                    case self::NEWS_IBLOCK_ID:
                        $max = 3;
                        $good = 1;
                        $mark = $this->rate($entityResult, $max , $good);

                        break;
                    case self::REVIEWS_IBLOCK_ID:
                        $max = 1;
                        $good = 1;
                        $mark = $this->rate($entityResult, $max, $good);

                        break;
                    case self::FACTS_IBLOCK_ID:
                        $max = 4;
                        $good = 3;
                        $mark = $this->rate($entityResult, $max, $good);

                        break;
                }

                $points = $this->pointsTable[$ratingItems[$ratingEntityId]['IMPORTANCE_ID']][$this->marks[$mark]];
                $maxPoints = $this->pointsTable[$ratingItems[$ratingEntityId]['IMPORTANCE_ID']][$this->marks['GREAT']];
                $goodPoints = $this->pointsTable[$ratingItems[$ratingEntityId]['IMPORTANCE_ID']][$this->marks['GOOD']];

                if ($points < $goodPoints && $good != $max) {
                    $diff = $goodPoints - $points;
                    $elDiff = $good - $entityResult;
                    $hints[$ratingItems[$ratingEntityId]['ENTITY_ID']][$entityKey]['GOOD'] = "{$elDiff} {$this->plurals[$entityKey]->get($elDiff)}, чтобы получить {$diff} {$this->plurals['points']->get($diff)}";
                }

                if ($points < $maxPoints) {
                    $diff = $maxPoints - $points;
                    $elDiff = $max - $entityResult;
                    $hints[$ratingItems[$ratingEntityId]['ENTITY_ID']][$entityKey]['MAX'] = "{$elDiff} {$this->plurals[$entityKey]->get($elDiff)}, чтобы получить {$diff} {$this->plurals['points']->get($diff)}";
                }

                $detailed[$ratingItems[$ratingEntityId]['ENTITY_ID']] = [
                    'NAME' => $this->_entities[$ratingItems[$ratingEntityId]['ENTITY_ID']]['NAME'],
                    'IMPORTANCE' => $this->_importances[$ratingItems[$ratingEntityId]['IMPORTANCE_ID']]['NAME'],
                    'QUANTITY' => "{$entityResult}/{$max}",
                    'POINTS' => $points
                ];

                $summary += $points;
            }
        }

        return [
            'SUMMARY' => $summary,
            'DETAILED' => $detailed,
            'HINTS' => $hints,
        ];
    }

    public function calculateFilledPoints($cityCode)
    {
        $city = getCityId($cityCode);

        $ratingItemsList = RatingItemTable::getList([
            'filter' => [
                'RATING_TYPE_ID' => $this->ratingTypes['FILLED'],
            ],
        ])->fetchAll();

        $ratingItems = [];
        $ratingEntities = [];

        foreach ($ratingItemsList as $ratingItem) {
            $ratingItems[$ratingItem['ID']] = $ratingItem;
            $ratingEntities[] = $ratingItem['ENTITY_ID'];
        }

        $items = EntityItemTable::getList([
            'filter' => [
                'ENTITY_ID' => $ratingEntities,
            ],
        ])->fetchAll();

        $entities = [];

        foreach ($items as $item) {
            $entities[$item['ENTITY_ID']][] = $item['ITEM_ID'];
        }

        $ratingItemsResults = [];

        foreach ($ratingItemsList as $ratingItem) {
            $itemEntities = $entities[$ratingItem['ENTITY_ID']];

            foreach ($itemEntities as $itemEntity) {
                $elements = \CIBlockElement::GetList([], [
                    'IBLOCK_ID' => $itemEntity,
                    'PROPERTY_CITY' => $city,
                ], []);

                $ratingItemsResults[$ratingItem['ID']][$itemEntity] = $elements;
            }
        }

        $summary = 0;
        $detailed = [];
        $hints = [];

        foreach ($ratingItemsResults as $ratingEntityId => $ratingItemsResult) {
            foreach ($ratingItemsResult as $entityKey => $entityResult) {
                $mark = '';
                $max = 0;
                $good = 0;

                switch ($entityKey) {
                    case self::OUR_PROJECTS_IBLOCK_ID:
                        $max = 4;
                        $good = 3;
                        $mark = $this->rate($entityResult, $max, $good);

                        break;
                    case self::EXHIBITIONS_IBLOCK_ID:
                        $max = 1;
                        $good = 1;
                        $mark = $this->rate($entityResult, $max, $good);

                        break;
                    case self::FACTS_IBLOCK_ID:
                        $max = 6;
                        $good = 3;
                        $mark = $this->rate($entityResult, $max, $good);

                        break;
                }

                if ($mark) {
                    $points = $this->pointsTable[$ratingItems[$ratingEntityId]['IMPORTANCE_ID']][$this->marks[$mark]];
                    $maxPoints = $this->pointsTable[$ratingItems[$ratingEntityId]['IMPORTANCE_ID']][$this->marks['GREAT']];
                    $goodPoints = $this->pointsTable[$ratingItems[$ratingEntityId]['IMPORTANCE_ID']][$this->marks['GOOD']];

                    $detailed[$ratingItems[$ratingEntityId]['ENTITY_ID']] = [
                        'NAME' => $this->_entities[$ratingItems[$ratingEntityId]['ENTITY_ID']]['NAME'],
                        'IMPORTANCE' => $this->_importances[$ratingItems[$ratingEntityId]['IMPORTANCE_ID']]['NAME'],
                        'QUANTITY' => "{$entityResult}/{$max}",
                        'POINTS' => $points
                    ];

                    if ($points < $goodPoints && $good != $max) {
                        $diff = $goodPoints - $points;
                        $elDiff = $good - $entityResult;
                        $hints[$ratingItems[$ratingEntityId]['ENTITY_ID']][$entityKey]['GOOD'] = "{$elDiff} {$this->plurals[$entityKey]->get($elDiff)}, чтобы получить {$diff} {$this->plurals['points']->get($diff)}";
                    }

                    if ($points < $maxPoints) {
                        $diff = $maxPoints - $points;
                        $elDiff = $max - $entityResult;
                        $hints[$ratingItems[$ratingEntityId]['ENTITY_ID']][$entityKey]['MAX'] = "{$elDiff} {$this->plurals[$entityKey]->get($elDiff)}, чтобы получить {$diff} {$this->plurals['points']->get($diff)}";
                    }

                    $summary += $points;
                }
            }
        }

        $dbPaySystem = PaySystemActionTable::getList([
            'filter' => [
                'CODE' => $cityCode
            ]
        ]);

        if ($arPaySystem = $dbPaySystem->fetch()) {
            if (
                $arPaySystem['PS_MODE'] == 'PC'
                && $arPaySystem['PAY_SYSTEM_ID']
                && $arPaySystem['ACTIVE'] == 'Y'
                && BusinessValue::get('YANDEX_SHOP_ID', "PAYSYSTEM_{$arPaySystem['PAY_SYSTEM_ID']}")
                && BusinessValue::get('YANDEX_SCID', "PAYSYSTEM_{$arPaySystem['PAY_SYSTEM_ID']}")
                && BusinessValue::get('YANDEX_SHOP_KEY', "PAYSYSTEM_{$arPaySystem['PAY_SYSTEM_ID']}")
            ) {
                $points = $this->pointsTable[$ratingItems[$this->entities['TICKETS']]['IMPORTANCE_ID']][$this->marks['GREAT']];
                $summary += $points;

                $detailed[$this->entities['TICKETS']] = [
                    'NAME' => $this->_entities[$this->entities['TICKETS']]['NAME'],
                    'IMPORTANCE' => $this->_importances[$this->entities['TICKETS']['IMPORTANCE_ID']]['NAME'],
                    'QUANTITY' => "Касса настроена",
                    'POINTS' => $points
                ];
            }
        } else {
            $detailed[$this->entities['TICKETS']] = [
                'NAME' => $this->_entities[$this->entities['TICKETS']]['NAME'],
                'IMPORTANCE' => $this->_importances[$this->entities['TICKETS']['IMPORTANCE_ID']]['NAME'],
                'QUANTITY' => "Касса не настроена",
                'POINTS' => 0
            ];

            $points = $this->pointsTable[$ratingItems[$this->entities['TICKETS']]['IMPORTANCE_ID']][$this->marks['GREAT']];
            $hints[$this->entities['TICKETS']]['checkout']['MAX'] = "онлайн-оплату, чтобы получить {$points} {$this->plurals['points']->get($points)}";
        }

        return [
            'SUMMARY' => $summary,
            'DETAILED' => $detailed,
            'HINTS' => $hints,
        ];
    }

    public static function prepareArray($array, $mode = 'code => id')
    {
        switch (strtolower($mode)) {
            case 'code => id':
                $result = [];

                foreach ($array as $item) {
                    if (array_key_exists('CODE', $item) && array_key_exists('ID', $item)) {
                        $result[$item['CODE']] = $item['ID'];
                    }
                }

                return $result ?: $array;
            case 'id => code':
                $result = [];

                foreach ($array as $item) {
                    if (array_key_exists('CODE', $item) && array_key_exists('ID', $item)) {
                        $result[$item['ID']] = $item['CODE'];
                    }
                }

                return $result ?: $array;
            case 'id => all':
                $result = [];

                foreach ($array as $item) {
                    if (array_key_exists('ID', $item)) {
                        $result[$item['ID']] = $item;
                    }
                }

                return $result ?: $array;
            default:
                return $array;
        }
    }

    public function preparePointsTable()
    {
        $arPoints = PointsTable::getList()->fetchAll();

        foreach ($arPoints as $arPoint) {
            $this->pointsTable[$arPoint['IMPORTANCE_ID']][$arPoint['MARK_ID']] = $arPoint['POINTS'];
        }
    }

    protected function rate($result, $great, $good)
    {
        if ($result >= $great) {
            return 'GREAT';
        }

        if ($result >= $good) {
            return 'GOOD';
        }

        return 'BAD';
    }

    public static function getCities()
    {
        $cities = \CIBlockElement::GetList(
            ['ID' => 'ASC'],
            [
                'IBLOCK_ID' => getCityIblockId(),
                'ACTIVE' => 'Y',
            ],
            false,
            false,
            [
                'ID',
                'IBLOCK_ID',
                'NAME',
                'PROPERTY_CODE',
            ]
        );

        $result = [];

        while ($city = $cities->Fetch()) {
            $result[$city['ID']] = $city;
        }

        return $result;
    }

    public static function sortByPoints($a, $b)
    {
        if ($a['POINTS'] == $b['POINTS']) {
            return $a['NAME'] > $b['NAME'] ? 1 : -1;
        }

        return $a['POINTS'] > $b['POINTS'] ? -1 : 1;
    }

    public function updateHistory()
    {
        $cityList = self::getCities();

        foreach ($cityList as $city) {
            $monday = Date::createFromTimestamp(MakeTimeStamp(date('Y-m-d H:i:s', strtotime('monday this week -7 days')), 'YYYY.MM.DD HH:MI:SS'));
            $sunday = Date::createFromTimestamp(MakeTimeStamp(date('Y-m-d H:i:s', strtotime('monday this week -1 second')), 'YYYY.MM.DD HH:MI:SS'));

            $relevant = $this->calculateRelevantPoints($city['PROPERTY_CODE_VALUE'], $monday, $sunday);

            foreach ($relevant['DETAILED'] as $ratingEntity => $entityItem) {
                RatingHistoryTable::add([
                    'DATE_START' => $monday,
                    'DATE_END' => $sunday,
                    'CITY_CODE' => $city['PROPERTY_CODE_VALUE'],
                    'POINTS' => $entityItem['POINTS'],
                    'QUANTITY' => $entityItem['QUANTITY'],
                    'ENTITY_ID' => $ratingEntity,
                    'RATING_TYPE_ID' => $this->ratingTypes['RELEVANT']
                ]);
            }
        }
    }

    public function getPreviousPoints($cityList, $entities = 'relevant', $date = false)
    {
        $previous = [];
        $ratingItems = [];

        $entitiesTypes = [
            'relevant' => $this->ratingTypes['RELEVANT'],
            'filled' => $this->ratingTypes['FILLED'],
        ];

        $ratingItemsList = RatingItemTable::getList([
            'filter' => [
                'RATING_TYPE_ID' => $entitiesTypes[$entities],
            ],
        ])->fetchAll();

        $arEntities = [];

        foreach ($ratingItemsList as $ratingItem) {
            $ratingItems[$ratingItem['ENTITY_ID']] = $ratingItem;
            $arEntities[] = $ratingItem['ENTITY_ID'];
        }

        if ($date) {
            $table = [];

            foreach ($cityList as $city) {
                $city = $city['PROPERTY_CODE_VALUE'] ?: $city;

                $db = RatingHistoryTable::getList([
                    'filter' => [
                        '>=DATE_START' => new Date($date['START'], 'Y.m.d'),
                        '<=DATE_END' => new Date($date['END'], 'Y.m.d'),
                        'CITY_CODE' => $city,
                        'ENTITY_ID' => $arEntities,
                        'RATING_TYPE_ID' => $entitiesTypes[$entities]
                    ],
                ]);

                $cityData = [];
                $pointSum = 0;
                while ($data = $db->fetch()) {
                    $cityData[$data['ENTITY_ID']] = [
                        'NAME' => $this->_entities[$data['ENTITY_ID']]['NAME'],
                        'IMPORTANCE' => $this->_importances[$ratingItems[$data['ENTITY_ID']]['IMPORTANCE_ID']]['NAME'],
                        'QUANTITY' => $data['QUANTITY'],
                        'POINTS' => $data['POINTS'],
                    ];

                    $pointSum += $data['POINTS'];
                }

                $table[$city] = [
                    'DETAILED' => $cityData,
                    'SUMMARY' => $pointSum,
                ];
            }

            return $table;
        } else {
            foreach ($cityList as $city) {
                $city = $city['PROPERTY_CODE_VALUE'] ?: $city;

                $db = RatingHistoryTable::getList([
                    'filter' => [
                        '<=DATE_END' => Date::createFromTimestamp(
                            MakeTimeStamp(
                                date('Y-m-d H:i:s', strtotime('monday this week -1 second')),
                                'YYYY.MM.DD HH:MI:SS'
                            )
                        ),
                        'CITY_CODE' => $city,
                        'ENTITY_ID' => $arEntities,
                        'RATING_TYPE_ID' => $entitiesTypes[$entities],
                    ],
                    'runtime' => [new ExpressionField('PTS', 'SUM(POINTS)')],
                    'select' => ['PTS']
                ])->fetch();

                $previous[$city] = $db ? intval($db['PTS']) : 0;
            }
        }

        return $previous;
    }

    public static function getDateString($date = [], $dateOnly = false, $format = 'Y.m.d')
    {
        $months = [
            '01' => 'января',
            '02' => 'февраля',
            '03' => 'марта',
            '04' => 'апреля',
            '05' => 'мая',
            '06' => 'июня',
            '07' => 'июля',
            '08' => 'августа',
            '09' => 'сентября',
            '10' => 'октября',
            '11' => 'ноября',
            '12' => 'декабря',
        ];

        if ($date) {
            $monday = ParseDateTime(new Date($date['START'], $format));
            $sunday = ParseDateTime(new Date($date['END'], $format));

            if ($dateOnly) {
                if ($monday['MM'] == $sunday['MM']) {
                    return "{$monday['DD']}-{$sunday['DD']} {$months[$sunday['MM']]}";
                }

                return "{$monday['DD']} {$months[$monday['MM']]} - {$sunday['DD']} {$months[$sunday['MM']]}";
            }

            $str = "Результаты недели {$monday['DD']} {$months[$monday['MM']]}-{$sunday['DD']} {$months[$sunday['MM']]}";

            if ($monday['MM'] == $sunday['MM']) {
                $str = "Результаты недели {$monday['DD']} - {$sunday['DD']} {$months[$sunday['MM']]}";
            }

            return $str;
        }

        $monday = ParseDateTime(Date::createFromTimestamp(
            MakeTimeStamp(
                date('Y-m-d H:i:s', strtotime('monday this week')),
                'YYYY.MM.DD HH:MI:SS'
            )
        ));

        $sunday = ParseDateTime(Date::createFromTimestamp(
            MakeTimeStamp(
                date('Y-m-d H:i:s', strtotime('sunday this week')),
                'YYYY.MM.DD HH:MI:SS'
            )
        ));

        if ($dateOnly) {
            if ($monday['MM'] == $sunday['MM']) {
                return "{$monday['DD']}-{$sunday['DD']} {$months[$sunday['MM']]}";
            }

            return "{$monday['DD']} {$months[$monday['MM']]} - {$sunday['DD']} {$months[$sunday['MM']]}";
        }

        $str = "Результаты текущей недели {$monday['DD']} {$months[$monday['MM']]} - {$sunday['DD']} {$months[$sunday['MM']]}";

        if ($monday['MM'] == $sunday['MM']) {
            $str = "Результаты текущей недели {$monday['DD']}-{$sunday['DD']} {$months[$sunday['MM']]}";
        }

        return $str;
    }

    public static function getAvailablePeriods()
    {
        $history = RatingHistoryTable::getList([
            'order' => [
                'DATE_START' => 'ASC'
            ],
            'filter' => [
                '>=DATE_START' =>  Date::createFromTimestamp(
                    MakeTimeStamp(
                        date('Y-m-d H:i:s', strtotime('monday this week -10 weeks')),
                        'YYYY.MM.DD HH:MI:SS'
                    )
                ),
            ]
        ])->fetchAll();

        $dates = [];

        foreach ($history as $record) {
            $dates[$record['DATE_START'].$record['DATE_END']] = [
                'START' => FormatDate('Y.m.d', MakeTimeStamp($record['DATE_START'])),
                'END' => FormatDate('Y.m.d', MakeTimeStamp($record['DATE_END'])),
            ];
        }

        return $dates;
    }

    public static function getSelectedPeriod()
    {
        $request = Context::getCurrent()->getRequest()->toArray();

        if ($request['from'] && $request['to']) {
            return [
                'START' => $request['from'],
                'END' => $request['to']
            ];
        }

        return false;
    }

    public static function getIblockLinks()
    {
        $dbIblocks = self::prepareArray(IblockTable::getList()->fetchAll(), 'id => all');

        $links = [];

        foreach ($dbIblocks as $dbIblock) {
            $links[$dbIblock['ID']] = "/bitrix/admin/iblock_element_admin.php?IBLOCK_ID={$dbIblock['ID']}&type={$dbIblock['IBLOCK_TYPE_ID']}";
        }

        return $links;
    }
}