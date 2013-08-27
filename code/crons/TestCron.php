<?php

class TestCron implements CronTask {

	/**
	 * 
	 * @return string
	 */
	public function getSchedule() {
		return "* * * * *";
	}

}