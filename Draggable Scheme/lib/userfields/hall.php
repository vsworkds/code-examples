<?php

namespace Scheme\Draggable\Userfields;

use Bitrix\Main\Context;

class Hall
{
    function GetUserTypeDescription()
    {
        return array(
            'PROPERTY_TYPE' => 'S',
            'USER_TYPE' => 'HALL',
            'DESCRIPTION' => "Передвигаемый зал",
            'GetPropertyFieldHtml' => array(__CLASS__, 'GetPropertyFieldHtml'),
            'GetPublicViewHTML' => array(__CLASS__, 'GetPublicViewHTML'),
            'ConvertFromDB' => array(__CLASS__, 'ConvertFromDB'),
            'ConvertToDB' => array(__CLASS__, 'ConvertToDB')
        );
    }

    function GetPropertyFieldHtml($property, $values, $strHTMLControlName)
    {
        /*
         * Todo
         * 1. Prepare fields to store data
         * 2. Get image of linked element
         * 3. Render HTML
         */

        $request = Context::getCurrent()->getRequest()->toArray();
        #debug info
        //pre($property);
        //pre($values);
        //pre($strHTMLControlName);

        #get current element
        $parentElement = \CIBlockElement::GetList([], [
            'IBLOCK_TYPE' => strval($request['type']),
            'IBLOCK_ID' => intval($request['IBLOCK_ID']),
            'ID' => intval($request['ID']),
        ], false, false, [
            'ID',
            'IBLOCK_ID',
            'PROPERTY_SCHEME',
            'PROPERTY_SCHEME.PREVIEW_PICTURE',
        ])->Fetch();

        //$schemeID = $parentElement["PROPERTY_SCHEME"];
        $schemePicture = \CFile::GetPath($parentElement["PROPERTY_SCHEME_PREVIEW_PICTURE"]);

        //echo $schemeID;
        //echo $schemePicture;

        include_once __DIR__.'/templates/hall.php';
    }

    function GetPublicViewHTML($property, $value, $strHTMLControlName)
    {
        return true;
    }

    function ConvertFromDB($property, $value)
    {
        return $value;
    }

    function ConvertToDB($property, $value)
    {
        return $value;
    }
}
