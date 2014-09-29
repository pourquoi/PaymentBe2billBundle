<?php

namespace Pourquoi\PaymentBe2billBundle\Tests\Units\Client;

use mageekguy\atoum;
use Pourquoi\PaymentBe2billBundle\Client\Parameters as TestedParameters;

class Parameters extends atoum\test
{

	public function testSortParameters()
	{
		$this
			->if($parameters = array(
				'CLIENTIDENT'      => '404',
				'CLIENTREFERRER'   => 'example.org',
				'CLIENTUSERAGENT'  => 'Mozilla/5.0 (Windows CE) AppleWebKit/5350 (KHTML, like Gecko) Chrome/13.0.888.0 Safari/5350',
				'CLIENTIP'         => '127.0.0.1',
				'DESCRIPTION'      => 'Winter is coming',
				'ORDERID'          => '13003',
				'AMOUNT'           => '23.99',
				'VERSION'          => '2.0',
				'CARDFULLNAME'     => 'CHUCK NORRIS',
				'CLIENTEMAIL'      => 'chucknorris@example.org',
				'CARDCODE'         => '1111111111111111',
				'CARDCVV'          => '123',
				'CARDVALIDITYDATE' => '07-20',
				'CREATEALIAS'      => 'no',
			))
			->array(TestedParameters::sortParameters($parameters))
			->isIdenticalTo(array(
				'AMOUNT'           => '23.99',
				'CARDCODE'         => '1111111111111111',
				'CARDCVV'          => '123',
				'CARDFULLNAME'     => 'CHUCK NORRIS',
				'CARDVALIDITYDATE' => '07-20',
				'CLIENTEMAIL'      => 'chucknorris@example.org',
				'CLIENTIDENT'      => '404',
				'CLIENTIP'         => '127.0.0.1',
				'CLIENTREFERRER'   => 'example.org',
				'CLIENTUSERAGENT'  => 'Mozilla/5.0 (Windows CE) AppleWebKit/5350 (KHTML, like Gecko) Chrome/13.0.888.0 Safari/5350',
				'CREATEALIAS'      => 'no',
				'DESCRIPTION'      => 'Winter is coming',
				'ORDERID'          => '13003',
				'VERSION'          => '2.0',
			))
		;
	}

	public function testGetSignature()
	{
		$this
			->if($parameters = array(
				'CLIENTREFERRER'=> 'example.org',
				'CLIENTIDENT'   => '404',
			))
			->string(TestedParameters::getSignature('CuirMoustache', $parameters))
			->isIdenticalTo(hash('sha256', 'CuirMoustacheCLIENTIDENT=404CuirMoustacheCLIENTREFERRER=example.orgCuirMoustache'))
		;
	}

}