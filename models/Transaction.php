<?php

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\behaviors\BlameableBehavior;

/**
 * This is the model class for table "{{%transaction}}".
 *
 * @property integer $id
 * @property integer $package_item_id
 * @property string $firstname
 * @property string $lastname
 * @property string $contact
 * @property integer $status
 * @property integer $quantity_of_guest
 * @property integer $check_in
 * @property integer $check_out
 * @property string $total_amount
 * @property integer $created_by
 * @property integer $updated_by
 * @property integer $created_at
 * @property integer $updated_at
 *
 * @property Order[] $orders
 * @property User $updatedBy
 * @property User $createdBy
 * @property PackageItem $packageItem
 */
class Transaction extends \yii\db\ActiveRecord
{
    public $toggle_date_time;

    const SCENARIO_CHECK_IN = 'check_in';

    public function scenarios()
    {
        $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_CHECK_IN] = ['package_item_id', 'quantity_of_guest', 'check_in', 'firstname', 'lastname', 'contact', 'toggle_date_time'];
        return $scenarios;
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%transaction}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['package_item_id', 'firstname', 'lastname', 'contact', 'status', 'quantity_of_guest', 'check_in', 'created_by', 'updated_by', 'created_at', 'updated_at'], 'required'],
            [['package_item_id', 'status', 'quantity_of_guest', 'created_by', 'updated_by', 'created_at', 'updated_at'], 'integer'],
            [['total_amount'], 'number'],
            [['check_in', 'check_out'], 'date', 'format' => 'php:Y-m-d H:i:s'],
            [['check_in'], 'validateCheckIn'],
            [['firstname', 'lastname'], 'string', 'max' => 25],
            [['contact'], 'string', 'max' => 50],
            [['updated_by'], 'exist', 'skipOnError' => true, 'targetClass' => User::className(), 'targetAttribute' => ['updated_by' => 'id']],
            [['created_by'], 'exist', 'skipOnError' => true, 'targetClass' => User::className(), 'targetAttribute' => ['created_by' => 'id']],
            [['package_item_id'], 'exist', 'skipOnError' => true, 'targetClass' => PackageItem::className(), 'targetAttribute' => ['package_item_id' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'package_item_id' => Yii::t('app', 'Package Item'),
            'firstname' => Yii::t('app', 'First Name'),
            'lastname' => Yii::t('app', 'Last Name'),
            'contact' => Yii::t('app', 'Contact'),
            'status' => Yii::t('app', 'Status'),
            'quantity_of_guest' => Yii::t('app', '# Of Guest'),
            'check_in' => Yii::t('app', 'Check In'),
            'check_in' => Yii::t('app', 'Check In'),
            'check_out' => Yii::t('app', 'Check Out'),
            'total_amount' => Yii::t('app', 'Total Amount'),
            'created_by' => Yii::t('app', 'Created By'),
            'updated_by' => Yii::t('app', 'Updated By'),
            'created_at' => Yii::t('app', 'Created At'),
            'updated_at' => Yii::t('app', 'Updated At'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getOrders()
    {
        return $this->hasMany(Order::className(), ['transaction_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUpdatedBy()
    {
        return $this->hasOne(User::className(), ['id' => 'updated_by']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCreatedBy()
    {
        return $this->hasOne(User::className(), ['id' => 'created_by']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getPackageItem()
    {
        return $this->hasOne(PackageItem::className(), ['id' => 'package_item_id']);
    }

    public function beforeSave($insert)
    {
        if ($this->isNewRecord) {
            date_default_timezone_set('Asia/Manila');
            $this->setAttribute('check_in', strtotime($this->check_in));
            /*var_dump($this->check_in); die();*/
        }
        return parent::beforeSave($insert);
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::className(),
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => 'updated_at',
                'value' => time(),
            ],
            [
                'class' => BlameableBehavior::className(),
                'createdByAttribute' => 'created_by',
                'updatedByAttribute' => 'updated_by',
            ],
        ];
    }

    public function beforeValidate()
    {
        if ($this->isNewRecord) {
            if (isset($this->toggle_date_time) && (intval($this->toggle_date_time) === 0)) {
                $this->setAttribute('check_in', date('Y-m-d H:i:s'));
            }
        }
        return parent::beforeValidate();
    }

    public function validateCheckIn($attribute, $params)
    {
        $dateToCompare = $this->$attribute;
        $now = date('Y-m-d H:i:s');
        if (strtotime($dateToCompare) < strtotime($now)) {
            $this->addError($attribute, 'The check-in date should not set to period earlier than today.');
        }
    }
}
