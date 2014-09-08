<?php

namespace Pourquoi\PaymentBe2billBundle\Plugin;

use JMS\Payment\CoreBundle\Plugin\PluginInterface;
use JMS\Payment\CoreBundle\Plugin\Exception\FinancialException;
use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
use JMS\Payment\CoreBundle\Plugin\AbstractPlugin;
use JMS\Payment\CoreBundle\Entity\ExtendedData;

use Pourquoi\PaymentBe2billBundle\Client\Client;
use Pourquoi\PaymentBe2billBundle\Client\Response;
use Pourquoi\PaymentBe2billBundle\Plugin\Exception\SecureActionRequiredException;

/**
 * This file is part of the RezzzaPaymentBe2billBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT License
 */

/**
 * Be2bill direct link plugin
 *
 * @author Jérémy Romey <jeremy@free-agent.fr>
 */
class Be2billDirectLinkPlugin extends AbstractPlugin
{
	const PAYMENT_OPERATION = 'payment';
	const AUTHORIZATION_OPERATION = 'authorization';
	const REFUND_OPERATION = 'refund';
	const CREDIT_OPERATION = 'credit';
	const CAPTURE_OPERATION = 'capture';

	protected $client;
	protected $formResponse;

	public function __construct(Client $client, $isDebug)
	{
		$this->client = $client;
		$this->client->setDebug($isDebug);

		parent::__construct($isDebug);
	}

	public function processes($paymentSystemName)
	{
		return 'be2bill' === $paymentSystemName;
	}

	/**
	 * Set the response parsed by the notification callback (form payment or authorization).
	 * This should be called before calling the approve/approveAndDeposit methods wich will then clear this response.
	 *
	 * @param Response $response
	 */
	public function setFormResponse(Response $response)
	{
		$this->formResponse = $response;
	}

	/**
	 * Performs the be2bill 'PAYMENT' method
	 *
	 * @param FinancialTransactionInterface $transaction
	 * @param bool $retry
	 */
	public function approveAndDeposit(FinancialTransactionInterface $transaction, $retry)
	{
		$this->executeMethod($transaction, self::PAYMENT_OPERATION);
	}

	/**
	 * Performs the be2bill 'AUTHORIZATION' method
	 *
	 * @param FinancialTransactionInterface $transaction
	 * @param bool $retry
	 */
	public function approve(FinancialTransactionInterface $transaction, $retry)
	{
		$this->executeMethod($transaction, self::AUTHORIZATION_OPERATION);
	}

	/**
	 * Performs the be2bill 'CAPTURE' method
	 *
	 * @param FinancialTransactionInterface $transaction
	 * @param bool $retry
	 */
	public function deposit(FinancialTransactionInterface $transaction, $retry)
	{
		$this->executeMethod($transaction, self::CAPTURE_OPERATION);
	}

	/**
	 * Performs the be2bill 'CREDIT' method
	 *
	 * @param FinancialTransactionInterface $transaction
	 * @param bool $retry
	 */
	public function credit(FinancialTransactionInterface $transaction, $retry)
	{
		$this->executeMethod($transaction, self::CREDIT_OPERATION);
	}

	/**
	 * @param FinancialTransactionInterface $transaction
	 * @param $method
	 *
	 * @throws \JMS\Payment\CoreBundle\Plugin\Exception\FinancialException
	 * @throws Exception\SecureActionRequiredException
	 */
	protected function executeMethod(FinancialTransactionInterface $transaction, $method)
	{
		$data = $transaction->getPayment()->getPaymentInstruction()->getExtendedData();
		$parameters = $data->get('be2bill_params');

		$response = $this->formResponse ? : $this->client->sendApiRequest($this->client->configureParameters($method, $parameters));
		$this->formResponse = null;

		$transaction->setTrackingId($response->getTransactionId());

		if ($response->isSecureActionRequired()) {
			$exception = new SecureActionRequiredException(sprintf('%s : transaction "%s" waits approval by 3DS', $method, $response->getTransactionId()));
			$exception->setHtml($response->getSecureHtml());

			throw $exception;
		}

		$extendedData = new ExtendedData;

		if ($response->getAlias()) {
			$extendedData->set('ALIAS', $response->getAlias());
		}
		if ($response->getCardCode()) {
			$extendedData->set('CARDCODE', $response->getCardCode());
		}
		if ($response->getCardFullName()) {
			$extendedData->set('CARDFULLNAME', $response->getCardFullName());
		}
		if ($response->getCardValidityDate()) {
			$extendedData->set('CARDVALIDITYDATE', $response->getCardValidityDate());
		}
		if ($response->getCardType()) {
			$extendedData->set('CARDTYPE', $response->getCardType());
		}

		$transaction->setExtendedData($extendedData);

		if (!$response->isSuccess()) {
			$exception = new FinancialException(sprintf('%s : transaction "%s" is not valid', $method, $response->getTransactionId()));
			$exception->setFinancialTransaction($transaction);
			$transaction->setResponseCode($response->getExecutionCode());
			$transaction->setReasonCode($response->getMessage());

			throw $exception;
		}

		$transaction->setProcessedAmount($transaction->getPayment()->getTargetAmount());
		$transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
		$transaction->setReasonCode(PluginInterface::REASON_CODE_SUCCESS);
	}
}
