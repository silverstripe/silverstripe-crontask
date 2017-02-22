<?php
/**
 * By implementing this interface a /dev/cron will be able to start in on the
 * expression that you return frmo getSchedule();
 *
 */
interface CronTask {

	/**
	 * Return a string for a CRON expression
	 *
	 * @return string
	 */
	public function getSchedule();

	/**
	 * When this script is supposed to run the CronTaskController will execute
	 * process().
	 *
	 * @return void
	 */
	public function process();

	/**
	 * Checks whether this task should run or not.
	 *
	 * @return boolean
	 */
	public function canRunTask();

	/**
	 * Checks whether this task can be ran on demand, or whether it should always stick to the designated schedule.
	 *
	 * @return boolean
	 */
	public function enforceSchedule();

}
