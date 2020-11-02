<?php

/* @var $this \yii\web\View */
/* @var $rurEurRate float */
/* @var $margins array */
/* @var $model \app\models\SearchForm */

use yii\helpers\Html;
use \app\models\SearchLog;
use \app\models\User;
use app\models\PriceLearnForm;
use app\models\ProductModel;
use app\models\EbayItem;
use \app\models\CurrencyModel;
use \app\models\Settings;

$this->title = Yii::$app->name;
$isAdmin             = User::hasAuthUserAdminRole();
$isPurchasingManager = User::hasAuthUserPurchasingManagerRole();
$isManager = User::hasAuthUserManagerRole();

$formatCurrencyEur = [
    'currency',
    CurrencyModel::EUR,
];
$formatCurrencyRur = [
    'currency',
    CurrencyModel::RUR,
];

$hasLocalPrices = !empty($prices) && !empty($prices['local']->allModels);
$hasExternalPrices = !empty($prices) && !empty($prices['external']);
$hasPrices = $hasLocalPrices || $hasExternalPrices;

$marginTable = null;
if ($margins) {
    $conditionTitle = [
        'new'  => 'новый',
        'used' => 'б/у',
    ];
    $sourceTitle = [
        'local'    => 'прайс',
        'external' => 'интернет',
    ];

    $newConditionPrices = array_column(
        array_filter($margins, function($priceData){
            return EbayItem::CONDITION_NEW == $priceData['condition'];
        }),
        'price'
    );
    $minPrice = $newConditionPrices ? min($newConditionPrices) : -1;

    $columns = [
        [
            'attribute' => 'artikul',
            'label' => 'Артикул',
        ],
        [
            'attribute' => 'condition',
            'label' => 'Состояние - Источник',
            'value' => function($data) use ($conditionTitle, $sourceTitle) {
                return sprintf(
                    '%s - %s',
                    $conditionTitle[$data['condition']],
                    $sourceTitle[$data['source']]
                );
            },
        ],
    ];
    if (!$isManager) {
        $columns = array_merge($columns, [
            [
                'attribute' => 'origPrice',
                'label' => 'Исходная цена',
                'format' => $formatCurrencyEur,
            ],
            [
                'attribute' => 'cost',
                'label' => 'С/с',
                'format' => $formatCurrencyEur,
            ],
        ]);
    }
    $columns = array_merge($columns, [
        [
            'attribute' => 'price',
            'label' => 'Конечная цена',
            'format' => $formatCurrencyEur,
        ],
        [
            'attribute' => 'price',
            'label' => 'Конечная цена, р.',
            'format' => $formatCurrencyRur,
            'contentOptions' => function($data) use ($minPrice) {
                if ($minPrice == $data['price']) {
                    return ['class' => 'minPrice'];
                }
                return [];
            },
            'value' => function($data) use ($rurEurRate) {
                return $data['price'] * $rurEurRate;
            },
        ],
    ]);
    $marginTable = \yii\grid\GridView::widget([
        'dataProvider' => new \yii\data\ArrayDataProvider([
            'allModels' => $margins,
        ]),
        'columns' => $columns,
        'formatter' => [
            'class' => 'yii\i18n\Formatter',
            'nullDisplay' => '',
        ],
        // 'options'=>['style'=>'width: auto; max-width: auto; white-space: normal;'],
    ]);
}

$labels = (new PriceLearnForm)->attributeLabels();
$productLabels = (new ProductModel)->attributeLabels();
$columns = [
    ['class' => 'yii\grid\SerialColumn'],
    [
        'attribute' => 'artikul',
        'label' => $labels['fileColumnArtikul'],
        'format' => 'raw',
        'value' => function ($data) {
            if (!\app\models\Action::isRoleValid(\app\models\Action::ACTION_PRODUCT)) {
                return $data->artikul;
            }
            return Html::a(Html::encode($data->artikul), ['/product', 'artikul' => $data->artikul, 'supplierId' => $data->supplierId]);
        },
    ],
    [
        'attribute' => 'price',
        'label' => $labels['fileColumnPrice'],
        'format' => $formatCurrencyRur,
        'value' => function($data) use ($rurEurRate) {
            return $data['price'] * $rurEurRate;
        },
    ],
    [
        'attribute' => 'price',
        'label' => 'Маржа',
        'format' => $formatCurrencyEur,
        'value' => function ($data) use ($prices) {
            return $data->getMargin($prices);
        },
    ]
];

$columns = array_merge($columns, [
    [
        'attribute' => 'supplierId',
        'label' => 'Поставщик',
        'value' => function ($data) {
            if (!$data->supplierId) {
                return;
            }
            return \app\models\Supplier::findOne($data->supplierId)->name;
        },
    ],
    [
        'attribute' => 'name',
        'label' => $labels['fileColumnName'],
    ],
    [
        'attribute' => 'unitMeasurementId',
        'label' => $productLabels['unitMeasurement'],
        'value' => function ($data) {
            if (!$data->unitMeasurementId) {
                return;
            }
            return \app\models\UnitMeasurement::findOne($data->unitMeasurementId)->name;
        },
    ],
    [
        'attribute' => 'qtyMin',
        'label' => $productLabels['qtyMin'],
        'format' => 'integer'
    ],
    [
        'attribute' => 'deliveryTime',
        'label' => $labels['fileDeliveryTime'],
    ],
]);

if ($isAdmin) {
    $columns = array_merge($columns, [
        [
            'attribute' => 'costRate',
            'label' => 'Коэф-т с/с',
            // 'format' => 'decimal',
            'value' => function ($data) {
                return 1*$data->costRate;
            },
        ],
        [
            'attribute' => 'costRate',
            'label' => 'C/с',
            'format' => $formatCurrencyEur,
            'value' => function ($data) {
                return SearchLog::calcCostPrice($data, 2);
            },
        ],
        [
            'attribute' => 'price',
            'label' => 'Исходная цена',
            'format' => $formatCurrencyEur,
            'value' => function ($data) {
                return SearchLog::calcOrigPrice($data, 2);
            },
        ]
    ]);
}
?>
<div class="site-index">

    <div class="row">
    <?php $form = \yii\bootstrap\ActiveForm::begin([
        // 'layout' => 'horizontal',
        'class' => 'form-inline',
        // 'fieldConfig' => [
        //     'template' => "{label}\n<div class=\"col-lg-3\">{input}</div>\n<div class=\"col-lg-8\">{error}</div>",
        //     'labelOptions' => ['class' => 'col-lg-1 control-label'],
        // ],
    ]); ?>

        <?= $form->field($model, 'artikul')->textInput(['autofocus' => true, 'style' => 'font-size: 150%;', 'class' => 'form-control', 'placeholder' => 'Артикул']) ?>

        <?= $form->field($model, 'qty')->dropdownList(array_merge([''], range(1, Settings::getMaxQty()))); ?>

        <div class="form-group">
                <?= \yii\helpers\Html::submitButton('Поиск', ['class' => 'btn btn-primary']) ?>
        </div>

    <?php $form->end(); ?>
    </div>

    <div class="body-content">

        <div class="row">
        <? if (!$hasPrices && !empty($searchLog->query)): ?>
            <div class="form-group">
                Ничего не найдено.
                <p>
                <?php $form = \yii\bootstrap\ActiveForm::begin([
                    'layout' => 'horizontal',
                    'method' => 'get',
                    // 'action' => 'http://crm/site/request',
                ]); ?>

                    <input type="hidden" name="artikul" value="<?= Html::encode($searchLog->query)?>">

                    <div class="form-group">
                        <?= \yii\helpers\Html::submitButton('Отправить запрос в CRM', ['class' => 'btn btn-success']) ?>
                    </div>

                <?php $form->end(); ?>
            </div>
        <? elseif ($hasPrices): ?>
            <? if ($marginTable): ?>
                <h2>Итоги</h2>
                <?= $marginTable ?>
                <br>
            <? endif; ?>
            <? foreach ($prices as $type => $typePrices): ?>
                <? if ('local' != $type): ?>
                <br>
                <h2><?
                    $aliases = [];
                    foreach ($typePrices as $price) {
                        $aliases[] = $price->getAlias();
                    }
                    echo implode(', ', array_unique($aliases));
                ?></h2>
                    <? foreach ($typePrices as $price): ?>
                        <div class="col-lg-4">
                            <h2>€ <?= $price->format($price->getPrice()) ?></h2>
                            <? if ($isAdmin): ?>
                                <h3><?= $price->getSupplierName() ?></h3>
                            <? endif; ?>
                            <h4><?= $price->getCondition() ?></h4>

                            <? if ($isAdmin || $isPurchasingManager): ?>
                                Маржа: € <?= $price->format($price->getMargin()) ?><br>
                            <? endif; ?>

                            <p>
                            <? if ($isAdmin): ?>
                                <br>Коэф-т с/c: <?= $price->format(1*$price->getCostRate()) ?><br>
                                C/c: € <?= $price->format($price->getCost()) ?><br>

                                <br>Исходная цена: € <?= $price->format($price->getOriginalPrice()) ?><br>
                            <? endif; ?>
                            <?if ($isAdmin || (!$isAdmin && $price->isQtyItemsExceeded())): ?>
                                    <br>Лотов найдено: <?= $price->getItemsFound() ?><br>
                            <? endif; ?>
                                Лотов использовано: <?= $price->getItemsUsed() ?>
                            </p>
                            <p><? if ($price->hasLoadPageErrors()): ?>
                                Не удалось загрузить все страницы результатов поиска. Средняя цена может быть посчитана не точно. Рекомендуется повторить поиск.<br>
                            <? endif; ?></p>

<? if ($isAdmin || $isPurchasingManager || $isManager): ?>
    <table class="search_result__items">
    <? foreach ($price->getItems() as $i => $item): ?>
        <? if ($isAdmin || $isPurchasingManager): ?>
            <tr<?= $item->usedInCalculation ? ' class="usedInCalculation"' : '' ?>>
                <td rowspan=2><a href="<?=$item->url?>" target="_blank"><img src="<?=$item->img?>" width=100></a></td>
                <td colspan=4 class="title"><b><?=$i+1?>.</b>
                    <a href="<?=$item->url?>" target="_blank"><?=$item->title?></a>
                </td>
            </tr>
            <tr<?= $item->usedInCalculation ? ' class="usedInCalculation"' : '' ?>>
                <td title="Кол-во" class="qty"><?=$item->qty ? $item->qty . ' шт.' : ''?></td>
                <td title="Цена">
                    <?printf(
                        '<span%s>€%s</span>',
                        $item->isShippingCostApplied ? '' : ' style="font-weight: bold;" title="Использована цена без учёта доставки"',
                        $price->format($item->price)
                    )?>
                </td>
                <td>
                    <?printf(
                        '<span%s title="Доставка%s%s">€%s</span>',
                        null === $item->shippingCost ? ' style="color: orange;"' : '',
                        null === $item->shippingCost ? ' - cтоимость неизвестна' : '',
                        $item->defaultShippingCostWhenApplied ? '. Применена стоимость по-умолчанию. ' : '',
                        $item->defaultShippingCostWhenApplied ? $price->format($item->defaultShippingCostWhenApplied) : $price->format($item->shippingCost)
                    )?>
                </td>
                <td title="Итого">
                    <?printf(
                        '<span%s>€%s</span>',
                        $item->isShippingCostApplied ? ' style="font-weight: bold;" title="Использована цена товара с учётом доставки"' : '',
                        $price->format($item->total)
                    )?>
                </td>
            </tr>
            <tr>
                <td colspan=5>&nbsp;</td>
            </tr>
        <? elseif ($isManager): ?>
            <tr<?= $item->usedInCalculation ? ' class="usedInCalculation"' : '' ?>>
                <td><img src="<?=$item->img?>" width=100></td>
            </tr>
            <tr>
                <td>&nbsp;</td>
            </tr>
        <? endif; ?>
    <? endforeach; ?>
    </table>
<? endif; ?>

                            <? if (0): ?>
                                <pre>
                                    <? print_r($price) ?>
                                </pre>
                            <? endif; ?>
                        </div>
                    <? endforeach; ?>
                    <? continue; ?>
                <? endif; ?>
                <? if (!$isManager): ?>
                    <h2>Прайс</h2>
                    <?= \yii\grid\GridView::widget([
                        'dataProvider' => $typePrices,
                        'columns' => $columns,
                        'formatter' => [
                            'class' => 'yii\i18n\Formatter',
                            'nullDisplay' => '',
                        ],
                    ]) ?>
                <? endif; ?>
            <? endforeach; ?>
        <? endif; ?>
        </div>
        <? if ($searchLog): ?>
            <div class="row">
                <small>
                    <br>Дата поиска:
                    <?= date('d.m.Y H:i:s', strtotime($searchLog->created))?>
                <? if ($isAdmin): ?>
                    <br>Пользователь:
                    <?= Html::a(Html::encode(\app\models\User::get($searchLog->userId)->name), ['user/edit', 'id' => $searchLog->userId]) ?>
                    (Роль:
                    <?= \app\models\UserRole::getName(\app\models\User::get($searchLog->userId)->roleId) ?>,
                    ID: <?= $searchLog->userId ?>)

                    <br>ID поиска: <?= $searchLog->id ?>
                    <br><?= Html::a(Html::encode('Файлы'), ['/search/files', 'id' => $searchLog->id]) ?>
                <? endif; ?>
                </small>
            </div>
        <? endif; ?>
    </div>
</div>
