<?php
class MQMonitor extends Thread {
	private $MQNAME = null;
	private $MQEXCHANGE = null;
	private $MQROUTINGKey = null;
	private $CLASSNAME = null;
	private $FNNAME = null;
	
	function __construct($MQEXCHANGE,$MQNAME,$MQROUTINGKey,$CLASSNAME,$FNNAME) {
		$this->MQEXCHANGE = $MQEXCHANGE;
		$this->MQNAME = $MQNAME;
		$this->MQROUTINGKey = $MQROUTINGKey;
		$this->CLASSNAME = $CLASSNAME;
		$this->FNNAME = $FNNAME;
	}
	
	public function run(){
		$obj = new $this->CLASSNAME();
		$ra = new RabbitMQCommand($this->MQEXCHANGE,$this->MQNAME,$this->MQROUTINGKey);
		
		$ra->run(array($obj,$this->FNNAME),false);
	}
}