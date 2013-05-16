<?php

define('CHARGEOVERAPI_BASEDIR', dirname(__FILE__));

require_once CHARGEOVERAPI_BASEDIR . '/ChargeOverAPI/Loader.php';

ChargeOverAPI_Loader::load('/ChargeOverAPI/Object.php');

ChargeOverAPI_Loader::import('/ChargeOverAPI/Object/');

class ChargeOverAPI
{
	const AUTHMODE_SIGNATURE_V1 = 'signature-v1';
	const AUTHMODE_HTTP_BASIC = 'http-basic';
	
	const METHOD_CREATE = 'create';
	const METHOD_MODIFY = 'modify';
	const METHOD_DELETE = 'delete';
	const METHOD_GET = 'get';
	
	const STATUS_OK = 'OK';
	const STATUS_ERROR = 'Error';
	
	protected $_url;
	protected $_authmode;
	protected $_username;
	protected $_password;
	
	protected $_last_request;
	protected $_last_response;
	protected $_last_error;
	
	public function __construct($url, $authmode, $username, $password)
	{
		$this->_url = rtrim($url, '/');
		$this->_authmode = $authmode;
		$this->_username = $username;
		$this->_password = $password;
		
		$this->_last_request = null;
		$this->_last_response = null;
		$this->_last_error = null;
		
		
	}
	
	protected function _signature($public, $private, $url, $data)
	{
		$tmp = array_merge(range('a', 'z'), range(0, 9));
		shuffle($tmp);
		$nonce = implode('', array_slice($tmp, 0, 8));
		
		$time = time();
		
		$str = $public . '||' . strtolower($url) . '||' . $nonce . '||' . $time . '||' . $data;
		$signature = hash_hmac('sha256', $str, $private);
		
		return 'Authorization: ChargeOver co_public_key="' . $public . '" co_nonce="' . $nonce . '" co_timestamp="' . $time . '" co_signature_method="HMAC-SHA256" co_version="1.0" co_signature="' . $signature . '" ';
	}
	
	protected function _request($method, $uri, $data = null)
	{
		$public = $this->_username;
		$private = $this->_password;
		
		$endpoint = $this->_url . '/' . ltrim($uri, '/');
		
		/*
		if (false === strpos($endpoint, '?'))
		{
			$endpoint .= '?debug=1';
		}
		else
		{
			$endpoint .= '&debug=1';
		}
		*/
		
		// create a new cURL resource
		$ch = curl_init();
		
		if ($this->_authmode == ChargeOverAPI::AUTHMODE_SIGNATURE_V1)
		{
			// Signed requests
			$signature = $this->_signature($public, $private, $endpoint, $data);
			
			curl_setopt($ch, CURLOPT_HTTPHEADER, array( 
				$signature
				));
		
		}
		else if ($this->_authmode == ChargeOverAPI::AUTHMODE_HTTP_BASIC)
		{
			// HTTP basic
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
			curl_setopt($ch, CURLOPT_USERPWD, $public . ':' . $private);
		}
		
		// set URL and other appropriate options
		curl_setopt($ch, CURLOPT_URL, $endpoint);
		
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
		
		if ($data)
		{
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		}
		
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		
		// Build last request string
		$this->_last_request = $method . ' ' . $endpoint . "\r\n\r\n" . json_encode($data);
		
		$out = curl_exec($ch);
		
		// Log last response
		$this->_last_response = $out;
		
		if (!$out)
		{
			$this->_last_error = 'Problem hitting URL [' . $endpoint . ']: ' . print_r(curl_getinfo($ch), true);
			return false;
		}
		
		return json_decode($out);
	}
	
	public function lastRequest()
	{
		return $this->_last_request;
	}
	
	public function lastResponse()
	{
		return $this->_last_response;
	}
	
	public function lastError()
	{
		return $this->_last_error;
	}
	
	public function isError($Object)
	{
		if (!is_object($Object))
		{
			return true;
		}
		else if ($Object->status != ChargeOverAPI::STATUS_OK)
		{
			return true;
		}
		
		return false;
	}
	
	protected function _map($method, $id, $Object)
	{
		$obj_type = '';
		switch (get_class($Object))
		{
			case 'ChargeOverAPI_Object_Customer':
				$obj_type = 'customer';
				break;
			case 'ChargeOverAPI_Object_User':
				$obj_type = 'user';
				break;
			case 'ChargeOverAPI_Object_BillingPackage':
				$obj_type = 'billing_package';
				break;
			case 'ChargeOverAPI_Object_CreditCard':
				$obj_type = 'creditcard';
				break;
		}
		
		if ($method == ChargeOverAPI::METHOD_CREATE)
		{
			$id = null;
		}
		
		if ($id)
		{
			return $obj_type . '/' . $id;
		}
		else
		{
			return $obj_type;
		}
	}
	
	public function rawRequest($method, $uri, $data)
	{
		
	}
	
	public function create($Object)
	{
		$uri = $this->_map(ChargeOverAPI::METHOD_CREATE, null, $Object);
		
		return $this->_request('POST', $uri, $Object->toArray());
	}
	
	public function modify($id, $Object)
	{
		
	}
	
	
}