<?php

namespace Commerce\Gateways\PaymentFormModels;

use Craft\BaseModel;
use Craft\AttributeType;
use Omnipay\Common\Helper as OmnipayHelper;

/**
 * Base Payment form model.
 *
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class BasePaymentFormModel extends BaseModel
{
	public function validate()
	{
		// change expiry to month and year
		if (!empty($this->expiry))
		{
			$expiry = explode("/", $this->expiry);

			if (isset($expiry[0]))
			{
				$this->month = trim($expiry[0]);
			}

			if (isset($expiry[1]))
			{
				$this->year = trim($expiry[1]);
			}
		}

		parent::validate();
	}
	/**
	 * @return array
	 */
	public function rules()
	{
		return [
			['firstName, lastName, month, year, cvv, number', 'required'],
			[
				'month',
				'numerical',
				'integerOnly' => true,
				'min'         => 1,
				'max'         => 12
			],
			[
				'year',
				'numerical',
				'integerOnly' => true,
				'min'         => date('Y'),
				'max'         => date('Y') + 12
			],
			['cvv', 'numerical', 'integerOnly' => true],
			['cvv', 'length', 'min' => 3, 'max' => 4],
			['number', 'numerical', 'integerOnly' => true],
			['number', 'length', 'max' => 19],
			['number', 'creditCardLuhn']
		];
	}

	/**
	 * @param $attribute
	 * @param $params
	 */
	public function creditCardLuhn($attribute, $params)
	{
		if (!OmnipayHelper::validateLuhn($this->$attribute))
		{
			$this->addError($attribute, \Craft::t('Not a valid Credit Card Number'));
		}
	}

	/**
	 * @return array
	 */
	protected function defineAttributes()
	{
		return [
			'firstName' => AttributeType::String,
			'lastName'  => AttributeType::String,
			'number'    => AttributeType::Number,
			'month'     => AttributeType::Number,
			'year'      => AttributeType::Number,
			'cvv'       => AttributeType::Number,
			'token'     => AttributeType::String,
			'expiry'     => AttributeType::String,
		];
	}
}