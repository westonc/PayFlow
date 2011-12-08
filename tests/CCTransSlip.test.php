<?php

require_once('../CCTransSlip.class.php');
require_once('WTestSet.class.php');

/* data sets */

class Test_CCTransSlip extends WTestSet {

	function test_validate_catches_empty() {
		$data = array();

		$emsg = array(
			'ccnum' => 'ccnum is missing',
			'ccexpmonth' => 'ccexpmonth is missing',
			'ccexpyear' => 'ccexpyear is missing',
			'amount' => 'amount is missing'
		);

		$ccts = new CCTransSlip($data);
		$result = $ccts->validate();
		return 
			$this->assert($result == false,"failed to catch empty dataset")
			&&
			$this->assertSame('failure msgset',$ccts->validationFailureMsgs(),'baseline msgset',$emsg);
			$this->assertSame('failure msgset',$ccts->validationFailureMsgs(),'baseline msgset',$emsg);
	}



	function test_validate_catches_bad_card() {
		$data = array(
			'ccnum'			=> '5151515151514142',
			'ccexpmonth'	=> '06',
			'ccexpyear'		=> '18',
			'amount'		=> '1.00'
		);

		$emsg = array('ccnum' => "The credit card number doesn't seem to be valid");

		$ccts = new CCTransSlip($data);
		$result = $ccts->validate();

		return 
			$this->assert($result == false,"failed to catch bad card number")
			&&
			$this->assertSame('failure msgset',$ccts->validationFailureMsgs(),'baseline msgset',$emsg);
	}



	function test_validate_catches_missing_expmonth() {
		$data = array(
			'ccnum'			=> '5555555555554444',
			'ccexpyear'		=> '15',
			'amount'		=> '1.00'
		);
		$emsg = array('ccexpmonth' => 'ccexpmonth is missing');

		$ccts = new CCTransSlip($data);
		$result = $ccts->validate();
	
		return 
			$this->assert($result == false, "failed to catch missing exp month")
			&&
			$this->assertSame('failure msgset',$ccts->validationFailureMsgs(),'baseline msgset',$emsg);
	}



	function test_validate_catches_missing_expyear() {
		$data = array(
			'ccnum'			=> '5555555555554444',
			'ccexpmonth'	=> '09',
			'amount'		=> '1.00'
		);
		$emsg = array('ccexpyear' => 'ccexpyear is missing');

		$ccts = new CCTransSlip($data);
		$result = $ccts->validate();

		return 
			$this->assert($result == false, "failed to catch missing exp year")
			&&
			$this->assertSame('failure msgset',$ccts->validationFailureMsgs(),'baseline msgset',$emsg);
	}



	function test_validate_catches_bad_exp() {
		$data = array(
			'ccnum'			=> '5555555555554444',
			'ccexpmonth'	=> 'A4',
			'ccexpyear'		=> '9x',
			'amount'		=> '1.00'
		);
		$emsg = array(
  			'ccexpmonth' => 'The expiration month is not a two-digit month',
  			'ccexpyear' => 'The expiration year is not a two-digit year',
		);

		$ccts = new CCTransSlip($data);
		$result = $ccts->validate();

		return
			$this->assert($result == false, "failed to catch missing bad expiration data")
			&&
			$this->assertSame('failure msgset',$ccts->validationFailureMsgs(),'baseline msgset',$emsg);
	}



	function test_validate_catches_past_year() {
		$data = array(
			'ccnum'			=> '5555555555554444',
			'ccexpmonth'	=> '06',
			'ccexpyear'		=> '10',
			'amount'		=> '1.00'
		);
		$emsg = array('ccexpyear' => "The expiration year is in the past");

		$ccts = new CCTransSlip($data);
		$result = $ccts->validate();

		return
			$this->assert($result == false, "failed to catch missing past year")
			&&
			$this->assertSame('failure msgset',$ccts->validationFailureMsgs(),'baseline msgset',$emsg);
	}



	function test_validate_catches_past_month() {
		if($this->assert(
			date('m',strtotime('january')) != '01',
			"Can't run this test in January")
		)
			return false;

		$data = array(
			'ccnum'			=> '5555555555554444',
			'ccexpmonth'	=> date('m',strtotime('last month')),
			'ccexpyear'		=> date('y'),
			'amount'		=> '1.00'
		);
		$emsg = array('ccexpmonth' => "The expiration month is in the past");

		$ccts = new CCTransSlip($data);
		$result = $ccts->validate();

		return
			$this->assert($result == false, "failed to catch missing past month")
			&&
			$this->assertSame('failure msgset',$ccts->validationFailureMsgs(),'baseline msgset',$emsg);
	}



	function test_card_only_good() {
		$data = array(
			'ccnum'			=> '5555555555554444',
			'ccexpmonth'	=> '06',
			'ccexpyear'		=> date('y',strtotime('next year')),
			'amount'		=> '1.00'
		);

		$ccts = new CCTransSlip($data);
		$result = $ccts->validate();

		return $this->assert($result == true, "failed to clear good card");
	}

	function test_card_plus_name_fails() {
		$data = array(
			'ccnum'			=> '5555555555554444',
			'ccexpmonth'	=> '06',
			'ccexpyear'		=> date('y',strtotime('next year')),
			'amount'		=> '1.00'
		);
		$emsg = array( 'ccsurname' => 'ccsurname is missing' );

		$ccts = new CCTransSlip($data);
		$result = $ccts->validate('NAME');

		return $this->assert($result == false, "failed to catch bad card name");
	}

	function test_card_plus_name_good() {
		$data = array(
			'ccnum'			=> '5555555555554444',
			'ccexpmonth'	=> '06',
			'ccexpyear'		=> date('y',strtotime('next year')),
			'ccname'		=> 'Testing',
			'ccmi'			=> 'R',
			'ccsurname'		=> 'Cannon',
			'amount'		=> '1.00'
		);

		$ccts = new CCTransSlip($data);
		$result = $ccts->validate('NAME');

		return $this->assert($result == true, "failed to clear good card");
	}

	function test_validate_cvv_fails() {
		$data = array(
			'ccnum'			=> '5555555555554444',
			'ccexpmonth'	=> '06',
			'ccexpyear'		=> date('y',strtotime('next year')),
			'cvv'			=> 'x x',
			'amount'		=> '1.00'
		);
		$emsg = array('cvv' => "The CVV/Security Code is not a 3-4 digit number");

		$ccts = new CCTransSlip($data);
		$result = $ccts->validate('CVV');

		return $this->assert($result == false, "failed to catch bad cvv")
			&& $this->assertSame('failure msgset',$ccts->validationFailureMsgs(),'baseline msgset',$emsg);
	}

	function test_validate_cvv_passes() {
		$data = array(
			'ccnum'			=> '5555555555554444',
			'ccexpmonth'	=> '06',
			'ccexpyear'		=> date('y',strtotime('next year')),
			'cvv'			=> '456',
			'amount'		=> '1.00'
		);

		$ccts = new CCTransSlip($data);
		$result = $ccts->validate('CVV');

		return $this->assert($result == true, "failed to clear good cvv");
	}

	function test_validate_address_fails() {
		$data = array(
			'ccnum'			=> '5555555555554444',
			'ccexpmonth'	=> '06',
			'ccexpyear'		=> date('y',strtotime('next year')),
			'ccfname'		=> 'Testing',
			'ccmi'			=> 'R',
			'ccsurname'		=> 'Cannon',
			'cvv'			=> '456',
			'amount'		=> '1.00'
		);
		$emsg = array (
			'street' => 'street is missing',
			'city' => 'city is missing',
			'state' => 'state is missing',
			'zip' => 'zip is missing'
		);

		$ccts = new CCTransSlip($data);
		$result = $ccts->validate('ADDRESS');

		return $this->assert($result == false, "failed to catch missing address")
			&& $this->assertSame('failure msgset',$ccts->validationFailureMsgs(),'baseline msgset',$emsg);
	}

	function test_validate_all_passes() {
		$data = array(
			'ccnum'			=> '5555555555554444',
			'ccexpmonth'	=> '06',
			'ccexpyear'		=> date('y',strtotime('next year')),
			'ccname'		=> 'Testing',
			'ccmi'			=> 'R',
			'ccsurname'		=> 'Cannon',
			'cvv'			=> '456',
			'street'		=> '123 Test',
			'city'			=> 'Test',
			'state'			=> 'TS',
			'country'		=> 'USA',
			'zip'			=> '94055',
			'amount'		=> '1.00'
		);

		$ccts = new CCTransSlip($data);
		$result = $ccts->validate('ALL');

		return $this->assert($result == true, "failed to pass validated address");
	}

}


