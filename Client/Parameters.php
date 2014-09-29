<?php

namespace Pourquoi\PaymentBe2billBundle\Client;

class Parameters
{
	public static function sortParameters($params)
	{
		ksort($params);

		foreach ($params as $name => $value) {
			if (is_array($value)) {
				$params[$name] = self::sortParameters($value);
			}
		}

		return $params;
	}

	public static function getSignature($password, $params)
	{
		$parameters = self::sortParameters($params);

		if( isset($parameters['HASH']) ) unset($parameters['HASH']);

		$signature = $password;
		foreach ($parameters as $name => $value) {
			if (is_array($value) == true) {
				foreach ($value as $index => $val) {
					$signature .= sprintf('%s[%s]=%s%s', $name, $index, $val, $password);
				}
			} else {
				$signature .= sprintf('%s=%s%s', $name, $value, $password);
			}
		}

		return hash('sha256', $signature);
	}
}