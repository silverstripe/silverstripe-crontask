<?php

class TestCron implements CronTask {

	/**
	 * 
	 * @return string
	 */
	public function getSchedule() {
		return "* * * * *";
	}

	/**
	 * 
	 * @return void
	 */
	public function process() {
		
	}
}
