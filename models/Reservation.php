<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;
use yii\helpers\Url;
use yii\base\UserException;
use yii\helpers\Json;

use PayPal\Api\CreditCard;
use PayPal\Api\CreditCardToken;
use PayPal\Api\FundingInstrument;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\Transaction;
use PayPal\Api\Amount;
use PayPal\Exception\PPConnectionException;
use PayPal\Exception\PayPalConnectionException;

/**
 * This is the model class for table "{{%reservation}}".
 *
 * @property integer $id
 * @property integer $package_item_id
 * @property string $firstname
 * @property string $lastname
 * @property string $contact
 * @property string $email
 * @property integer $status
 * @property string $check_in
 * @property integer $quantity_of_guest
 * @property string $remark
 * @property string $address
 * @property integer $created_at
 * @property integer $updated_at
 * @property string $creditcard_id
 *
 * @property PackageItem $packageItem
 */
class Reservation extends \yii\db\ActiveRecord
{
    public $verifyCode;
    public $cc_type;
    public $cc_number;
    public $cc_cvv;
    public $cc_expiry_month;
    public $cc_expiry_year;

    const STATUS_FOR_VERIFICATION = 5;
    const STATUS_NEW = 10;
    const STATUS_CONFIRM = 15;
    const STATUS_DONE = 20;
    const STATUS_CANCEL = 50;

    const SCENARIO_NEW = 'new';
    const SCENARIO_CHANGE_STATUS = 'change_status';

    public function scenarios()
    {
        $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_CHANGE_STATUS] = ['status'];
        $scenarios[self::SCENARIO_NEW] = ['firstname', 'lastname', 'contact', 'check_in', 'quantity_of_guest', 'email', 'address', 'remark', 'verifyCode', 'cc_type', 'cc_number', 'cc_cvv', 'cc_expiry_month', 'cc_expiry_year'];
        return $scenarios;
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%reservation}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['package_item_id', 'firstname', 'lastname', 'contact', 'email', 'status', 'check_in', 'quantity_of_guest', 'created_at', 'updated_at', 'cc_type', 'cc_number', 'cc_cvv', 'cc_expiry_month', 'cc_expiry_year'], 'required'],
            [['package_item_id', 'status', 'quantity_of_guest', 'created_at', 'updated_at', 'cc_cvv', 'cc_expiry_month', 'cc_expiry_year'], 'integer'],
            [['contact'], 'match', 'pattern' => '/^[\d()\s-]+$/', 'message' => 'Contact should only contain numbers, spaces, dashes, and parentheses'],
            [['check_in'], 'safe'],
            [['check_in'], 'validateCheckIn'],
            [['email'], 'email'],
            [['remark'], 'string'],
            [['verifyCode'], 'captcha', 'on' => self::SCENARIO_NEW],
            [['firstname', 'lastname'], 'string', 'max' => 25],
            [['contact'], 'string', 'max' => 50],
            [['email', 'address'], 'string', 'max' => 150],
            [['creditcard_id', 'cc_number'], 'string', 'max' => 40],
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
            'email' => Yii::t('app', 'Email'),
            'status' => Yii::t('app', 'Status'),
            'check_in' => Yii::t('app', 'Check In'),
            'quantity_of_guest' => Yii::t('app', '# of Guest'),
            'remark' => Yii::t('app', 'Additional Details'),
            'address' => Yii::t('app', 'Address'),
            'created_at' => Yii::t('app', 'Created At'),
            'updated_at' => Yii::t('app', 'Updated At'),
            'verifyCode' => Yii::t('app', 'Verification Code'),
            'creditcard_id' => Yii::t('app', 'Creditcard ID'),
            'cc_type' => Yii::t('app', 'Type'),
            'cc_number' => Yii::t('app', 'Number'),
            'cc_cvv' => Yii::t('app', 'Cvv2'),
            'cc_expiry_month' => Yii::t('app', 'Expiry Month'),
            'cc_expiry_year' => Yii::t('app', 'Expiry Year'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getPackageItem()
    {
        return $this->hasOne(PackageItem::className(), ['id' => 'package_item_id']);
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['created_at', 'updated_at'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_at'],
                ],
                'value' => time(),
            ],
        ];
    }

    public function placeReservation($packageItem)
    {
        $paypalContext = Yii::$app->myPaypalPayment->getContext();

        try {
            $card = new CreditCard();
            $card->setType($this->cc_type);
            $card->setNumber($this->cc_number);
            $card->setExpireMonth($this->cc_expiry_month);
            $card->setExpireYear($this->cc_expiry_year);
            $card->setCvv2($this->cc_cvv);
            $card->create($paypalContext);
        } catch (PayPalConnectionException $ex) {
            $exception = Json::decode($ex->getData());
            throw new UserException($exception['details'][0]['issue']);
        }

        $this->setAttribute('package_item_id', $packageItem->id);
        $this->setAttribute('status', self::STATUS_FOR_VERIFICATION);
        $this->setAttribute('creditcard_id', $card->getId());



        if ($this->save()) {
            $this->refresh();

            try {
                $ccToken = new CreditCardToken();
                $ccToken->setCreditCardId($this->getAttribute('creditcard_id'));

                $fi = new FundingInstrument();
                $fi->setCreditCardToken($ccToken);

                $payer = new Payer();
                $payer->setPaymentMethod("credit_card");
                $payer->setFundingInstruments([$fi]);

                $amount = new Amount();
                $amount->setCurrency(Yii::$app->myPaypalPayment->getCurrency());
                $amount->setTotal(($this->packageItem->rate * 0.5) / 46.52);

                $transaction = new Transaction();
                $transaction->setAmount($amount);
                $transaction->setDescription('Hotel Reservation Fee - ' . $this->getAttribute(Yii::$app->formatter->asDateTime($this->getAttribute('check_in'))));

                $payment = new Payment();
                $payment->setIntent("sale");
                $payment->setPayer($payer);
                $payment->setTransactions(array($transaction));

                $payment->create($paypalContext);
            } catch (PPConnectionException $ex) {
                throw new UserException($ex->getData());
            } catch (\Exception $ex) {
                throw new UserException($ex->getMessage());
            }

            $this->setAttribute('status', self::STATUS_NEW);
            $this->update(false);

            return true;
        }
        return false;
    }

    public function changeStatus($status)
    {
        $this->setAttribute('status', $status);
        $this->scenario = self::SCENARIO_CHANGE_STATUS;
        return $this->save();
    }

    /*public function confirmReservation()
    {
        $this->setAttribute('status', self::STATUS_NEW);
        $this->scenario = self::SCENARIO_CHANGE_STATUS;
        return $this->save();
    }

    public function cancel()
    {
        $this->scenario = self::SCENARIO_CHANGE_STATUS;
        $this->setAttribute('status', self::STATUS_CANCEL);
        return $this->save();
    }

    public function checkIn()
    {
        $this->scenario = self::SCENARIO_CHANGE_STATUS;
        $this->setAttribute('status', self::STATUS_CHECK_IN);
        return $this->save();
    }*/

    public static function getStatusDropdownList($template = 'raw')
    {
        if ($template === 'raw') {
            $model = [
                self::STATUS_FOR_VERIFICATION => 'For email verification',
                self::STATUS_NEW => 'New',
                self::STATUS_CONFIRM => 'Confirmed',
                self::STATUS_DONE => 'Done',
                self::STATUS_CANCEL => 'Cancelled'
            ];
        } else if ($template === 'html') {
            $model = [
                self::STATUS_FOR_VERIFICATION => '<span class="label label-warning">For email verification</span>',
                self::STATUS_NEW => '<span class="label label-primary">New</span>',
                self::STATUS_CONFIRM => '<span class="label label-success">Confirmed</span>',
                self::STATUS_DONE => '<span class="label label-info">Done</span>',
                self::STATUS_CANCEL => '<span class="label label-danger">Cancelled</span>',
            ];
        }
        return $model;
    }

    public static function getStatusValue($id)
    {
        $status = self::getStatusDropdownList('html');
        if (isset($status[$id])) {
            return $status[$id];
        }
    }

    public function validateCheckIn($attribute, $params)
    {
        $dateToCompare = $this->$attribute;
        $now = date('Y-m-d');
        if (strtotime($dateToCompare) < strtotime($now)) {
            $this->addError($attribute, 'The check-in date should not set to period earlier than today.');
        }
    }

    public static function getReservationStatusCount($status)
    {
        return self::find()
            ->where(['status' => $status])
            ->count();
    }
}
