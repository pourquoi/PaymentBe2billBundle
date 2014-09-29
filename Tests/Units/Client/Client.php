<?php

namespace Pourquoi\PaymentBe2billBundle\Tests\Units\Client;

use mageekguy\atoum;
use Pourquoi\PaymentBe2billBundle\Client\Client as TestedClient;

/**
 * This file is part of the RezzzaPaymentBe2billBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT License
 */

/**
 * Client
 *
 * @uses atoum\test
 * @author Jérémy Romey <jeremy@free-agent.fr>
 */
class Client extends atoum\test
{
    public function testConstruct()
    {
        $this
            ->if($client = new TestedClient('CHUCKNORRIS', 'CuirMoustache', false, 'main', '2.0'))
                ->boolean($client->getDebug())
                    ->isFalse()
            ->if($client = new TestedClient('CHUCKNORRIS', 'CuirMoustache', true, 'main', '2.0'))
                ->boolean($client->getDebug())
                    ->isTrue()
            ->if($client = new TestedClient('CHUCKNORRIS', 'CuirMoustache', false, 'main', '2.0'))
                ->boolean($client->getDebug())
                    ->isFalse()
        ;
    }

    public function testSetDebug()
    {
        $this
            ->if($client = new TestedClient('CHUCKNORRIS', 'CuirMoustache', false, 'main', '2.0'))
                ->boolean($client->getDebug())
                    ->isFalse()
            ->if($client->setDebug(true))
                ->boolean($client->getDebug())
                    ->isTrue()
            ->if($client->setDebug(false))
                ->boolean($client->getDebug())
                    ->isFalse()
        ;
    }

    public function testGetApiEndpoints()
    {
        $apiEndPoints = array(
            'sandbox' => array(
                'https://secure-test.be2bill.com/front/service/rest/process',
            ),
            'production' => array(
                'https://secure-magenta1.be2bill.com/front/service/rest/process',
                'https://secure-magenta2.be2bill.com/front/service/rest/process',
            ),
        );

        $this
            ->if($client = new TestedClient('CHUCKNORRIS', 'CuirMoustache', false, 'main', '2.0'))
                ->array($client->getApiEndpoints())
                    ->isIdenticalTo($apiEndPoints['production'])
            ->if($client->setDebug(true))
                ->array($client->getApiEndpoints())
                    ->isIdenticalTo($apiEndPoints['sandbox'])
        ;
    }

	public function testGetFormendpoint()
	{
		$formEndPoints = array(
			'sandbox' =>'https://secure-test.be2bill.com/front/form/process',
			'production' => 'https://secure-magenta1.be2bill.com/front/form/process'
		);

		$this
			->if($client = new TestedClient('CHUCKNORRIS', 'CuirMoustache', false, 'main', '2.0'))
				->string($client->getFormEndpoint())
					->isIdenticalTo($formEndPoints['production'])
			->if($client->setDebug(true))
				->string($client->getFormEndpoint())
					->isIdenticalTo($formEndPoints['sandbox'])
		;
	}

    public function testConvertAmountToBe2billFormat()
    {
        $this
            ->if($client = new TestedClient('CHUCKNORRIS', 'CuirMoustache', false, 'main', '2.0'))
                ->integer($amount = $client->convertAmountToBe2billFormat('23.99'))
                    ->isIdenticalTo(2399)
            ->if($client = new TestedClient('CHUCKNORRIS', 'CuirMoustache', false, 'main', '2.0'))
                ->integer($client->convertAmountToBe2billFormat('23'))
                    ->isIdenticalTo(2300)
        ;
    }

    public function testConfigure3dsParametersUnsupportedOperation()
    {
        $this
            ->if($client = new TestedClient('CHUCKNORRIS', 'CuirMoustache', false, 'main', '2.0'))
            ->and($parameters = array('3DSECURE' => 'yes', '3DSECUREDISPLAYMODE' => 'main', '2.0'))
            ->and($parameters = $client->configureParameters('invalid', $parameters))
                ->array($params = $parameters['params'])
                    ->notHasKeys(array('3DSECURE', '3DSECUREDISPLAYMODE'))
        ;
    }

    public function testConfigure3dsParameters()
    {
        $this
            ->if($client = new TestedClient('CHUCKNORRIS', 'CuirMoustache', false, 'main', '2.0'))
            ->and($parameters = array('3DSECURE' => 'yes', '3DSECUREDISPLAYMODE' => 'top'))
            ->and($parameters = $client->configureParameters('payment', $parameters))
            ->and($params = $parameters['params'])
                ->string($params['3DSECURE'])
                    ->isEqualTo('yes')
                ->string($params['3DSECUREDISPLAYMODE'])
                    ->isEqualTo('top')
        ;
    }

    public function testConfigure3dsParametersDefaultMode()
    {
        $this
            ->if($client = new TestedClient('CHUCKNORRIS', 'CuirMoustache', false, 'main', '2.0'))
            ->and($parameters = array('3DSECURE' => 'yes'))
            ->and($parameters = $client->configureParameters('payment', $parameters))
            ->and($params = $parameters['params'])
                ->string($params['3DSECURE'])
                    ->isEqualTo('yes')
                ->string($params['3DSECUREDISPLAYMODE'])
                    ->isEqualTo('main')
        ;
    }
}
