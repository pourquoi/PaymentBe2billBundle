<?php

namespace Pourquoi\PaymentBe2billBundle\Client;

use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * This file is part of the RezzzaPaymentBe2billBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT License
 */

/**
 * @author Jérémy Romey <jeremy@free-agent.fr>
 */
class Response
{
    private $secure;
    public $body;

    /**
     * Creates a new response.
     *
     * @param array   $parameters An array of parameters
     * @param boolean $secure     True if 3DS was used for the request
     */
    public function __construct(array $parameters, $secure = false)
    {
        $this->body = new ParameterBag($parameters);
        $this->secure = $secure;
    }

	public function getOrderId()
	{
		return $this->body->get('ORDERID');
	}

    public function getOperationType()
    {
        return $this->body->get('OPERATIONTYPE');
    }

    public function getTransactionId()
    {
        return $this->body->get('TRANSACTIONID');
    }

    public function getExecutionCode()
    {
        return $this->body->get('EXECCODE');
    }

    public function getMessage()
    {
        return $this->body->get('MESSAGE');
    }

    public function getSecureHtml()
    {
        return $this->body->get('3DSECUREHTML');
    }

    public function getAlias()
    {
        return $this->body->get('ALIAS');
    }

	public function getIdentifier()
	{
		return $this->body->get('IDENTIFIER');
	}

	public function getHash()
	{
		return $this->body->get('HASH');
	}

	/**
	 * Card code with only last 4 digits shown (XXXXXXXXXXXX1234).
	 * @return string | null
	 */
	public function getCardCode()
	{
		return $this->body->get('CARDCODE');
	}

	/**
	 * Expiration date format: MM-YY
	 * @return string | null
	 */
	public function getCardValidityDate()
	{
		return $this->body->get('CARDVALIDITYDATE');
	}

	public function getCardType()
	{
		return $this->body->get('CARDTYPE');
	}

	public function getCardFullName()
	{
		return $this->body->get('CARDFULLNAME');
	}

    /**
     * Tells if an action needs to be performed by the user
     * in the context of a 3DS transaction.
     *
     * @return boolean
     */
    public function isSecureActionRequired()
    {
        return '0001' === $this->getExecutionCode();
    }

    public function isSuccess()
    {
        return '0000' === $this->getExecutionCode();
    }

    public function isError()
    {
        return !$this->isSuccess();
    }

    public function isValidationError()
    {
        if (preg_match('/10[0-9][0-9]/', $this->getExecutionCode())) {
            return true;
        }

        return false;
    }

    public function isTransactionUpdateError()
    {
        return 1 == preg_match('/20[0-9][0-9]/', $this->getExecutionCode());
    }

    public function isConfigurationError()
    {
        return 1 == preg_match('/30[0-9][0-9]/', $this->getExecutionCode());
    }

    public function isBankError()
    {
        return 1 == preg_match('/40[0-9][0-9]/', $this->getExecutionCode());
    }

    public function isInternalError()
    {
        return 1 == preg_match('/50[0-9][0-9]/', $this->getExecutionCode());
    }

    public function __toString()
    {
        return $this->toJson();
    }

    public function toArray()
    {
        return $this->body->all();
    }

    public function toJson()
    {
        return json_encode($this->body->all());
    }
}
