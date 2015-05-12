<?php
namespace Pourquoi\PaymentBe2billBundle\Client;

use Pourquoi\PaymentBe2billBundle\Plugin\Be2billDirectLinkPlugin;
use Symfony\Component\BrowserKit\Response as RawResponse;
use JMS\Payment\CoreBundle\BrowserKit\Request;
use JMS\Payment\CoreBundle\Plugin\Exception\CommunicationException;

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
class Client
{
    const SECURE_3DS_PARAM = '3DSECURE';
    const DISPLAY_MODE_3DS_PARAM = '3DSECUREDISPLAYMODE';

    protected $formEndpoints;
    protected $apiEndpoints;
    protected $identifier;
    protected $password;
    protected $isDebug;
    protected $curlOptions;
    protected $default3dsDisplayMode;
	protected $version;

    public function __construct($identifier, $password, $isDebug, $debugBaseUrl, $default3dsDisplayMode, $version)
    {
        $this->identifier = $identifier;
        $this->password = $password;
        $this->isDebug = (bool) $isDebug;
        $this->default3dsDisplayMode = $default3dsDisplayMode;
		$this->version = $version;
        $this->curlOptions = array();
        $this->formEndpoints = array(
            'sandbox' => $debugBaseUrl . 'front/form/process',
            'production' => 'https://secure-magenta1.be2bill.com/front/form/process'
        );
        $this->apiEndpoints = array(
            'sandbox' => array(
				$debugBaseUrl . 'front/service/rest/process',
            ),
            'production' => array(
                'https://secure-magenta1.be2bill.com/front/service/rest/process',
                'https://secure-magenta2.be2bill.com/front/service/rest/process',
            )
        );
    }

    public function setDebug($isDebug)
    {
        $this->isDebug = !!$isDebug;
    }

	public function setDebugBaseUrl($debugBaseUrl)
	{
		$this->formEndpoints['sandbox'] = $debugBaseUrl . 'front/form/process';
		$this->apiEndpoints['sandbox'][0] = $debugBaseUrl . 'front/service/rest/process';
	}

    public function getDebug()
    {
        return $this->isDebug;
    }

    public function getApiEndpoints()
    {
        return (true === $this->getDebug()) ? $this->apiEndpoints['sandbox'] : $this->apiEndpoints['production'];
    }

    public function getFormEndpoint()
    {
        return (true === $this->getDebug() ) ? $this->formEndpoints['sandbox'] : $this->formEndpoints['production'];
    }

    public function configureParameters($operation, array $parameters)
    {
        $this->configure3dsParameters($operation, $parameters);

        $parameters['IDENTIFIER'] = $this->identifier;
        $parameters['OPERATIONTYPE'] = $operation;
		$parameters['VERSION'] = $this->version;

        $parameters['HASH'] = Parameters::getSignature($this->password, $parameters);

        $parameters = array(
            'method' => $operation,
            'params' => Parameters::sortParameters($parameters),
        );

        return $parameters;
    }

    private function configure3dsParameters($operation, array &$parameters)
    {
        // 3DS is only supported on payment and authorization operations
        if (Be2billDirectLinkPlugin::AUTHORIZATION_OPERATION !== $operation && Be2billDirectLinkPlugin::PAYMENT_OPERATION!== $operation) {
            if (isset($parameters[self::SECURE_3DS_PARAM])) {
                unset($parameters[self::SECURE_3DS_PARAM]);
            }
            if (isset($parameters[self::DISPLAY_MODE_3DS_PARAM])) {
                unset($parameters[self::DISPLAY_MODE_3DS_PARAM]);
            }

            return;
        }

        // Set the default mode if not set
        if ($this->is3dsEnabledFromParameters($parameters)) {
            if (!isset($parameters[self::DISPLAY_MODE_3DS_PARAM])) {
                $parameters[self::DISPLAY_MODE_3DS_PARAM] = $this->default3dsDisplayMode;
            }
        }
    }

    /**
     * Checks if 3DS is enabled by inspecting the parameters.
     *
     * @param array $parameters
     *
     * @return boolean
     */
    private function is3dsEnabledFromParameters(array $parameters)
    {
        return isset($parameters[self::SECURE_3DS_PARAM]) && 'yes' === $parameters[self::SECURE_3DS_PARAM];
    }

    public function sendApiRequest(array $parameters)
    {
        $apiEndPoints = $this->getApiEndpoints();
        if (empty($apiEndPoints)) {
            throw new CommunicationException('No Api Endpoint configured.');
        }
        foreach ($apiEndPoints as $apiEndPoint) {
            $request = new Request(
                $apiEndPoint,
                'POST',
                $parameters
            );

            // If the request is secure, we set a flag on the response to process it easier
            $secure = $this->is3dsEnabledFromParameters($parameters['params']);

            $response = $this->request($request);
            if (200 === $response->getStatus()) {
                $parameters = json_decode($response->getContent(), true);

                return new Response($parameters, $secure);
            }
        }

        throw new CommunicationException('The API request was not successful (Status: '.$response->getStatus().'): '.$response->getContent());
    }

    public function convertAmountToBe2billFormat($amount)
    {
        return intval($amount * 100);
    }

    public function setCurlOption($name, $value)
    {
        $this->curlOptions[$name] = $value;
    }

    /**
     * Performs a request to an external payment service
     *
     * @throws CommunicationException when an curl error occurs
     * @param Request $request
     * @param mixed $parameters either an array for form-data, or an url-encoded string
     * @return Response
     */
    public function request(Request $request)
    {
        if (!extension_loaded('curl')) {
            throw new \RuntimeException('The cURL extension must be loaded.');
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt_array($curl, $this->curlOptions);
        curl_setopt($curl, CURLOPT_URL, $request->getUri());
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, true);

        // add headers
        $headers = array();
        foreach ($request->headers->all() as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $subValue) {
                    $headers[] = sprintf('%s: %s', $name, $subValue);
                }
            } else {
                $headers[] = sprintf('%s: %s', $name, $value);
            }
        }
        if (count($headers) > 0) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }

        // set method
        $method = strtoupper($request->getMethod());
        if ('POST' === $method) {
            curl_setopt($curl, CURLOPT_POST, true);

            if (!$request->headers->has('Content-Type') || 'multipart/form-data' !== $request->headers->get('Content-Type')) {
                $postFields = http_build_query($request->request->all());
            } else {
                $postFields = $request->request->all();
            }
            curl_setopt($curl, CURLOPT_POSTFIELDS, $postFields);
        } else if ('PUT' === $method) {
            curl_setopt($curl, CURLOPT_PUT, true);
        } else if ('HEAD' === $method) {
            curl_setopt($curl, CURLOPT_NOBODY, true);
        }

        // perform the request
        if (false === $returnTransfer = curl_exec($curl)) {
            throw new CommunicationException(
                'cURL Error: '.curl_error($curl), curl_errno($curl)
            );
        }

        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $headers = array();
        if (preg_match_all('#^([^:\r\n]+):\s+([^\n\r]+)#m', substr($returnTransfer, 0, $headerSize), $matches)) {
            foreach ($matches[1] as $key => $name) {
                $headers[$name] = $matches[2][$key];
            }
        }

        $response = new RawResponse(
            substr($returnTransfer, $headerSize),
            curl_getinfo($curl, CURLINFO_HTTP_CODE),
            $headers
        );
        curl_close($curl);

        return $response;
    }
}
