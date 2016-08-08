<?php

use yii\helpers\Html;
use kartik\grid\GridView;
use yii\widgets\Pjax;
/* @var $this yii\web\View */
/* @var $searchModel app\models\TransactionSearch */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = Yii::t('app', 'Transactions');
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="transaction-index">

    <div class="row">
        <div class="col-lg-12">
            <h1 class="page-header"><?= Html::encode($this->title) ?></h1>
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-body">
            <div class="row">
                <?= $this->render('_search', [
                    'model' => $searchModel,
                    'packageItems' => $packageItems,
                ]); ?>
            </div>            
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <?php Pjax::begin(); ?>
                <?= GridView::widget([
                    'dataProvider' => $dataProvider,
                    /*'filterModel' => $searchModel,*/
                    'columns' => [
                        ['class' => 'yii\grid\SerialColumn'],

                        [
                            'attribute' => 'package_item_id',
                            'value' => 'packageItem.title',
                        ],
                        'firstname',
                        'lastname',
                        'status',
                        'check_in:datetime',
                        [
                            'attribute' => 'check_out',
                            'value' => function ($model, $key, $index, $column) {
                                return (empty($model->check_out)) ? null : $model->check_out;
                            },
                            'format' => 'datetime',
                        ],
                        'total_amount',
                        'id',

                        ['class' => 'yii\grid\ActionColumn'],
                    ],
                    'panel'=>[
                        'type' => GridView::TYPE_DEFAULT,
                        'heading' => 'Grid',
                    ],
                    'toolbar' => [
                        [
                            'content' =>
                            Html::a('<i class="fa fa-repeat"></i>', ['index'], [
                                'class' => 'btn btn-default', 
                                'title' => Yii::t('app', 'Reset Grid'),
                                'data-pjax' => 0,
                            ]),
                        ],
                        '{toggleData}',
                    ],
                ]); ?>
            <?php Pjax::end(); ?>
        </div>
    </div>
</div>