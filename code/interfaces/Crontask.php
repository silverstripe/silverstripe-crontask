<?php

interface CronTask {
	
	/**
	 * Return a string for a CRON expression
	 * 
	 * @return string
	 */
	public function getSchedule();
}