<?php

define('_T','    ');
$_t = _T;

class WTestSet {

	var $log;

	function WTestSet() { 
		$this->clearLog(); 
	}

	function clearLog() {
		$this->log = array(); 
	}

	function run($indent=_T) {
		$testMethods = array();
		foreach(get_class_methods(get_class($this)) as $method) {
			$matches = array();
			if(preg_match('/^test_?(.*)$/',$method,$matches)) {
				$testMethods[$matches[1]] = $method;
			}
		}
		$testMethodCount = count($testMethods);
		echo $testMethodCount," test methods\n";
		$passed = 0; $failed = 0; $i = 0;
		foreach($testMethods as $testName => $methodName) {
			$i++;
			echo $indent,"#[$i/$testMethodCount] $testName: ";
			$this->clearLog();
			try {
				if($this->$methodName()) {
					$passed++;
					echo "pass\n";
				} else {
					$failed++;
					echo "fail (",$this->log2str(', '),")\n";
				}
			} catch(Exception $e) {
				$failed++;
				echo "fail (",$e->getMessage(),")\n";
			}
		}
		echo $indent,"pass: $passed  fail: $failed total: $testMethodCount\n";

		return ($passed/$testMethodCount);
	}

	function assert($condition,$failmsg) {
		if(!$condition) {
			$this->log($failmsg);
			return false;
		} else 
			return $this;
	}

	function assertSame($namea,$a,$nameb,$b) {
		if($a != $b) {
			$this->log(
				"\n\t$namea: ",var_export($a,true),
				"\n\tis not",
				"\n\t$nameb: ",var_export($b,true)
			);
			return false;
		} else
			return $this;
	}

	function log() {
		$args = func_get_args();
		$this->log[] = implode(null,$args);
	}

	function log2str($sep="\n") {
		return implode($sep,$this->log);
	}

}

?>
