<?php

class CCTransSlip {

	static $validations = array('BASIC','CVV','ADDRESS','NAME');
	private $validationScheme; 

	private $in;
	private $vfs; //validation failures
	private $validation = 0;

	var $ccnum;
	var $ccexp;

	var $ccfname;
	var $ccmi;
	var $ccsurname;

	var $cvv;

	var $street;
	var $city;
	var $state;
	var $country = 'USA';
	var $zip;

	var $description;
	var $currency;
	var $amount;

	function __construct($args=null) {
		$this->in = is_array($args) && $args ? $args : $_REQUEST;
	}

	function _vf($k,$msg) { // report validation failure
		$this->vfs[$k] = $msg;	
	}

	function _extant($k) {
		//echo "\nextant $k";
		$this->in[$k] = isset($this->in[$k]) ? trim($this->in[$k]) : null;
		if($this->in[$k])
			return true;
		$this->_vf($k,"$k is missing");
		return false;
	}

	function _luhn_check($card_number) {
		$card_number = ereg_replace('[^0-9]', '', $card_number);      
		if ($card_number < 9) 
			return false;
		$card_number = strrev($card_number);
		$total = 0;
		for ($i = 0; $i < strlen($card_number); $i++) {
			$current_number = substr($card_number, $i, 1);
			if ($i % 2 == 1) {
				$current_number *= 2;
			}
			if ($current_number > 9) {
				$first_number = $current_number % 10;
				$second_number = ($current_number - $first_number) / 10;
				$current_number = $first_number + $second_number;
			}
			$total += $current_number;
		}
		return ($total % 10 == 0);
	}

		
	function _validate_ccnum() {
		$k = 'ccnum';
		if(!$this->_extant($k))
			return false;
		if(! preg_match('/^\d{16}$/',$this->in[$k]) ) {
			$this->_vf($k,"The credit card number is not 16 digits");
			return false;
		}
		if(! self::_luhn_check($this->in[$k]) ) {
			$this->_vf($k,"The credit card number doesn't seem to be valid");
			return false;
		}
		return $this->in[$k];
	}

	function _validate_ccexp() {
		$k = 'ccexpyear';
		if(!$this->_extant($k))
			$rv = false;
		else if(!preg_match('/^\d{2}$/',$this->in[$k])) {
			$this->_vf($k,"The expiration year is not a two-digit year");
			$rv = false;
 		} else if($this->in[$k] < date('y')) {
			$this->_vf($k,"The expiration year is in the past");
			$rv = false;
		} else
			$rv = $this->in[$k];
		$yearIsCurrent = ($this->in[$k] == date('y'));

		$k = 'ccexpmonth';
		if(!$this->_extant($k))
			$rv = false;
		else if(! preg_match('/^\d{2}$/',$this->in[$k] ) 
			|| ($this->in[$k] < 1) || ($this->in[$k] > 12) ) {
			$this->_vf($k,"The expiration month is not a two-digit month");
			$rv = false;
		} else if($yearIsCurrent && $this->in[$k] < date('m')) {
			$this->_vf($k,"The expiration month is in the past");
			$rv = false;
		} else if($rv)
			$rv = "{$this->in[$k]}$rv";

		return $rv;
	}

	function _validate_ccfname() {
		$k = 'ccfname';
		return isset($this->in[$k]) ? 
			$this->in[$k] :
			'';
	}

	function _validate_ccmi() {
		$k = 'ccmi';
		return isset($this->in[$k]) ? 
			$this->in[$k] :
			'';
	}

	function _validate_ccsurname() {
		$k = 'ccsurname';
		if(!$this->_extant($k))
			return false;
		else
			return $this->in[$k];
	}

	function _validate_cvv() {
		$k = 'cvv';	
		if(!$this->_extant($k))
			return false;
		if(! preg_match('/^\d{3,4}$/',$this->in[$k]) ) {
			$this->_vf($k,"The CVV/Security Code is not a 3-4 digit number");
			return false;
		}
		return $this->in[$k];
	}

	function _validate_street() {
		$k = 'street';
		if(!$this->_extant($k))
			return false;
		if(!preg_match('/^\w+\s+\w+/',$this->in[$k])) {
			$this->_vf($k,"The street portion of the billing address doesn't seem to be valid");
			return false;
		}
		return $this->in[$k];
	}

	function _validate_city() {
		$k = 'city';
		if(!$this->_extant($k))
			return false;
		return $this->in[$k];
	}

	function _validate_state() {
		$k = 'state';
		if(!$this->_extant($k))
			return false;
		return $this->in[$k];
	}

	function _validate_country() {
		$k = 'country';
		if(!isset($this->in[$k]) || !$this->in[$k])
			return 'USA';
	}

	function _validate_zip() {
		$k = 'zip';
		if(!$this->_extant($k))
			return false;
		if(!preg_match('/^\d{5}(-\d{4})?$/',$this->in[$k])) {
			$this->_vf($k,"The billing zip doesn't seem to be a valid US zip code");
			return false;
		}
		return $this->in[$k];
	}

	function _validate_description() {
		return isset($this->in['description']) ? 
			$this->in['description'] : 
			'';
	} 

	function _validate_currency() {
		return isset($this->in['currency']) ? $this->in['currency'] : 'USD';
	}

	function _validate_amount() {
		$k = 'amount';
		if(!$this->_extant($k))
			return false;
		if(!($this->in[$k] == 0) && !floatval($this->in[$k])) {
			$this->_vf($k,"The given charge amount doesn't seem to be a numeric value");
			return false;	
		}
		return $this->in[$k];
	}

	function _fields_to_validate() {
		$vscheme = $this->validationScheme();

		//$vscheme['BASIC']
		$validate = array('ccnum','ccexp','description','currency','amount'); 

		if($vscheme['CVV']) {
			$validate[] = 'cvv';
		}

		if($vscheme['ADDRESS']) {
			$validate = array_merge($validate,array('street','city','state','zip','country'));
		}

		if($vscheme['NAME']) {
			$validate = array_merge($validate,array('ccfname','ccmi','ccsurname'));
		}

		return $validate;
	}

	function validate() {
		$args = func_get_args();
		$this->validationScheme($args);
		$validate = $this->_fields_to_validate();

		$this->vfs = array(); 
		$rv = true;

		foreach($validate as $v) {
			$tmp = call_user_func( array(&$this,"_validate_$v") );
			if( !($tmp === false) ) {
				$this->$v = $tmp;
			} else {
				$rv = false;
			}
		}
		return $rv;
	}

	function validationScheme($schemes = null) {
		if(is_array($schemes)) {
			$this->validationSchemes = array();

			if(isset($schemes[0]) && ($schemes[0] == 'ALL')) {
				foreach(self::$validations as $validation)
					$this->validationSchemes[$validation] = true;
			} else {
				foreach(self::$validations as $validation)
					$this->validationSchemes[$validation] = false;

				foreach($schemes as $scheme) 
					$this->validationSchemes[$scheme] = true;

				$this->validationSchemes['BASIC'] = true;
			}
		}
		return $this->validationSchemes;
	}

	function validationFailureMsgs($as=null) {
		if($as=='json') 
			return json_encode($this->vfs);
		return $this->vfs;
	}

}

?>
