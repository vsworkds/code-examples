<?php

use Bitrix\Main\Page\Asset;

$asset = Asset::getInstance();
$asset->addJs("https://code.jquery.com/jquery-3.3.1.min.js"); // initialize jquery core
$asset->addJs("https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"); // initialize jquery ui

#check linked floor image
if (!$schemePicture) { ?>
    <span style="background: #ffcc00; padding: 4px;">Сначала необходимо привязать схему этажа и применить/сохранить изменения</span>
<? } else { ?>
    <div class="prop-parent">
        <img class="background" src="<?= $schemePicture ?>" alt="">
        <div class="hall"></div>
    </div>
    Ширина:&nbsp<input class="input input-width" type="text">
    Высота:&nbsp<input class="input input-height" type="text">

    <input type="hidden" name="<?= $strHTMLControlName['VALUE'] ?>" class="prop-value" value="<?= $values['VALUE'] ?: '0|0|100px|100px' ?>">

    <style>
        .prop-parent {
            width: 900px;
            position: relative;
        }

        .background {
            width: 100%;
            display: block;
            margin-bottom: 3px;
        }

        .hall {
            position: absolute;
            border: 3px solid #999999;
            box-sizing: border-box;
        }

        .input {
            margin: 3px;
        }
    </style>

    <script>
        $(function () {
            var hall = $(".hall");
            var propValue = $(".prop-value").val().split("|");
            var inputWidth = $(".input-width");
            var inputHeight = $(".input-height");

            hall.css("top", propValue[0]);
            hall.css("left", propValue[1]);
            hall.css("width", propValue[2]);
            hall.css("height", propValue[3]);

            inputWidth.val(propValue[2].replace("px", ""));
            inputHeight.val(propValue[3].replace("px", ""));

            hall.draggable({
                containment: "parent",
                stop: function () {
                    setPropVal();
                }
            });

            inputWidth.on("input", function () {
                var inputValue = $(this).val();
                var width = $('.background').width();
                if (inputValue <= (width - hall.css("left").replace("px", ""))) {
                    hall.css("width", inputValue);
                    setPropVal();
                }
            });

            inputHeight.on("input", function () {
                var inputValue = $(this).val();
                var height = $('.background').height();
                if (inputValue <= (height - hall.css("top").replace("px", ""))) {
                    hall.css("height", inputValue);
                    setPropVal();
                }
            });

            function setPropVal() {
                $(".prop-value").val(hall.css("top") + '|' + hall.css("left") + '|' + hall.css("width") + '|' + hall.css("height"));
            }
        });
    </script>
<? } ?>