<?php
/**
* PHP SDK for using the PayFlow Pro HTTPS API
*
* This is a rewrite of http://code.google.com/p/paypal-payflowpro-php/
*
* What's different?  
*	1) cURL requqests are isolated into a single internal method, rather than repeated for each different call
*
*   2) Card/Cardholder/Transaction information is isolated into a Credit Card Transaction Slip Object
*
*/

if(!function_exists('curl_init')) 
	throw new Exception("the curl extension is not installed");

require_once('CCTransSlip.class.php');

define('PFP_LIVE_URL','https://payflowpro.verisign.com/transaction');
define('PFP_TEST_URL','https://pilot-payflowpro.verisign.com/transaction');
define('PFP_USER_AGENT',"Mozilla/4.0 (compatible; PHP CURL PayFlow SDK)"); 
define('PFP_TIMEOUT',45);
define('PFP_V','0.2');
define('PFP_MODE_TEST',1);
define('PFP_MODE_LIVE',0);

define('PFP_STATUS_OK', 1);
define('PFP_USER_ERR', 0);
define('PFP_SYS_ERR', -1);

class PayFlow {
    
	var $vendor;
	var $user;
	var $partner;
	var $password;

	var $errors;
	
	var $currencies_allowed = array('USD', 'EUR', 'GBP', 'CAD', 'JPY', 'AUD');

	var $test_mode = 1; // 1 = true, 0 = production

	var $request_id;
    
    function __construct($args) {
		$requireds = array('vendor','user','partner','password');
		$missings = array();
		foreach($requireds as $arg) {
			if(isset($args[$arg]) && trim($args[$arg]))
				$this->$arg = $args[$arg];
			else
				$missings[] = $arg;
		}
		if(isset($args['mode']))
			$this->test_mode = $args['mode'] ? PFP_MODE_TEST : PFP_MODE_LIVE;

		if($missings) 
			throw new Exception("Missing required arguments: ".implode(', ',$missings));
    }

    // sale
    function sale_transaction($ccts) {
		$this->errors = array();
		$this->_make_request_id($ccts);
		$body = $this->_request_body('S',$ccts);
		$result = $this->_curl_request($body);
		return $this->_response2arr($result);
	}

    // Authorization
    function authorization($ccts) {
		$this->authorize($ccts);
	}

    function authorize($ccts) {
		$this->errors = array();
		$this->_make_request_id($ccts);
		$body = $this->_request_body('A',$ccts);
		$result = $this->_curl_request($body);
		return $this->_response2arr($result);
    }

	function void($pnref) {
		$this->errors = array();
		//$this->_make_request_id($pnref);
		$body = $this->_void_request_body($pnref);
		$result = $this->_curl_request($body);
		return $this->_response2arr($result);
	}

    // Delayed Capture
    function delayed_capture($origid, $ccts) {
		$this->errors = array();
		if(!$ccts instanceof CCTransSlip) 
			throw new Exception("bad Credit Card Transaction argument");

		if (strlen($origid) < 3) {
			$this->error('OrigID not valid');
			return; 
		}

		$body = $this->_request_body('D',$ccts);
		$result = $this->_curl_request($body);
	
		return $this->_response2arr($result);
    }

    // Credit Transaction
    function credit($origid) {
		if (strlen($origid) < 3) {
			$this->error('OrigID not valid');
			return; 
		}

		$plist .= 'TENDER=' . 'C' . '&'; // C = credit card, P = PayPal
		$plist .= 'TRXTYPE=' . 'C' . '&'; //  S = Sale transaction, A = Authorisation, C = Credit, D = Delayed Capture, V = Void
		$plist .= "ORIGID=" . $origid . "&"; // ORIGID to the PNREF value returned from the original transaction
	}
    
    // Void Transaction
    function void_transaction($origid) {
		$plist .= 'TENDER=' . 'C' . '&'; // C = credit card, P = PayPal
		$plist .= 'TRXTYPE=' . 'V' . '&'; //  S = Sale transaction, A = Authorisation, C = Credit, D = Delayed Capture, V = Void                        
		$plist .= "ORIGID=" . $origid . "&"; // ORIGID to the PNREF value returned from the original transaction
    }

	function get_errors() {
		return $this->errors;
    }
 
	function clear_errors() {
		$this->errors = array();
	} 

    function error($string) {
		$this->errors[] = $string;
    }

	function get_version() {
		return PFP_V;
	}    

	function _request_body($txnType,$tokenOrCCTransSlip) {
		if(!in_array($txnType,array('S','A','C','D','V')))
			throw new Exception("transaction type must be S,A,C,D, or V");
		// that is, (S)ale, (A)uthorisation, (C)redit, (D)elayed Capture, (V)oid 

		$rv = array(   
      		"USER={$this->user}",
			"VENDOR={$this->vendor}",
      		"PARTNER={$this->partner}",
			"PWD={$this->password}",

			"VERBOSITY=MEDIUM",

			"TENDER=C",					// (C)redit card, (P)ayPal
			"TRXTYPE=$txnType", 
		);

		if(is_string($tokenOrCCTransSlip)) { 
			$token = $tokenOrCCTransSlip;
			if(strlen($token) < 3) {
				$this->error('OrigID not valid');
				return false; 
      		} 
			$rv[] = "ORIGID=$token"; 

			return implode('&',$rv);

		} else if(!($tokenOrCCTransSlip instanceof CCTransSlip)) {
			$this->error('2nd argument to _request_body neither original transaction id token nor CCTransSlip');
			return false;
		} 

		$ccts = $tokenOrCCTransSlip;

		$rv[] = "AMT={$ccts->amount}";
		if(isset($_SERVER['REMOTE_ADDR']))
      		$rv[] = "CLIENTIP={$_SERVER['REMOTE_ADDR']}";
		$rv[] = "ACCT={$ccts->ccnum}";
		$rv[] = "EXPDATE={$ccts->ccexp}";

		if($ccts->cvv)
			$rv[] = "CVV2={$ccts->cvv}";

		if($ccts->ccsurname)
			$rv[] = "LASTNAME={$ccts->ccsurname}";
		if($ccts->ccfname)
			$rv[] = "FIRSTNAME={$ccts->ccfname}";
		if($ccts->ccmi)
			$rv[] = "MIDDLENAME={$ccts->ccmi}";
		
		if($ccts->street)
			$rv[] = "STREET={$ccts->street}";
		if($ccts->city)
			$rv[] = "CITY={$ccts->city}";
		if($ccts->zip)
			$rv[] = "ZIP={$ccts->zip}";
		if($ccts->country)
			$rv[] = "COUNTRY={$ccts->country}";

		if($ccts->description)
			$rv[] = "COMMENT1={$ccts->description}";

		return implode('&',$rv);
	}

	function _make_request_id($ccts,$seq=1) {
		return $this->request_id = md5(
			implode(null,array(
				$ccts->ccnum,
				$ccts->amount,
				date('YmdGis'),
				$seq
			))
		); 
	}

	function _void_request_body($orig_id) {
		return implode('&',array(
			'TRXTYPE=V',
			'TENDER=C',
			"PARTNER={$this->partner}",
			"VENDOR={$this->vendor}",
			"USER={$this->user}",
			"PWD={$this->password}",
			"ORIGID={$orig_id}",
			'VERBOSITY=MEDIUM'
		));
	}

	function _curl_request($body) {
		$sheaders = array();
		$sheaders[] = "Content-Type: text/namevalue"; //or maybe text/xml
		$sheaders[] = "X-VPS-Timeout: ".PFP_TIMEOUT;
		$sheaders[] = "X-VPS-VIT-OS-Name: ".php_uname('s');  // Name of your OS
		$sheaders[] = "X-VPS-VIT-OS-Version: ".php_uname('r');  // OS Version
		$sheaders[] = "X-VPS-VIT-Client-Type: PHP/cURL";  // What you are using
		$sheaders[] = "X-VPS-VIT-Client-Version: ".PFP_V;  // For your info
		$sheaders[] = "X-VPS-Request-ID: ".$this->request_id;

		$ch = curl_init(); 
		curl_setopt($ch, CURLOPT_URL, $this->test_mode ? PFP_TEST_URL : PFP_LIVE_URL);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $sheaders);
		curl_setopt($ch, CURLOPT_USERAGENT, PFP_USER_AGENT);
		curl_setopt($ch, CURLOPT_HEADER, 1); // tells curl to include headers in response
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // return into a variable
		curl_setopt($ch, CURLOPT_TIMEOUT, PFP_TIMEOUT); // times out after 45 secs
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // this line makes it work under https
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,  2); //verifies ssl certificate
		curl_setopt($ch, CURLOPT_FORBID_REUSE, TRUE); //forces closure of connection when done 
		curl_setopt($ch, CURLOPT_POST, 1); //data sent as POST 
		curl_setopt($ch, CURLOPT_POSTFIELDS, $body); //adding POST data

		$result = curl_exec($ch);
		$rheaders = curl_getinfo($ch);
		curl_close($ch);

		return $result;
	}

	function _response2arr($response) {
		$body = strstr($response, 'RESULT');    
		$rv = array();
		$kvps = explode('&',$body);
		foreach($kvps as $kvp) {
			list($k,$v) = explode('=',$kvp);
			$rv[$k] = $v;	
		}
		return $rv;	
	}
} 


