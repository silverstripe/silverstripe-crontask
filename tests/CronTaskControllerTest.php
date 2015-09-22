<?php

class CronTaskControllerTest extends SapphireTest {

	public function setUp() {
		parent::setUp();
		CronTaskTest_TestCron::$times_run = 0;
	}

	/**
	 * Tests CronTaskController::isTaskDue
	 */
	public function testIsTaskDue() {
		$this->cleanCronTaskStatus();
		$runner = CronTaskController::create();
		$task = new CronTaskTest_TestCron();
		$cron = Cron\CronExpression::factory($task->getSchedule());

		// Assuming first run, match the exact time (seconds are ignored)
		SS_Datetime::set_mock_now('2010-06-20 13:00:10');
		$this->assertTrue($runner->isTaskDue($task, $cron));

		// Assume first run, do not match time just before or just after schedule
		SS_Datetime::set_mock_now('2010-06-20 13:01:10');
		$this->assertFalse($runner->isTaskDue($task, $cron));
		SS_Datetime::set_mock_now('2010-06-20 12:59:50');
		$this->assertFalse($runner->isTaskDue($task, $cron));

		// Mock a run and test that subsequent runs are properly scheduled
		SS_Datetime::set_mock_now('2010-06-20 13:30:10');
		$task->updateTaskStatus(array(
			'LastRun'		=> SS_Datetime::now()->getValue(),
			'Status'		=> 'On',
			'LastChecked'	=> SS_Datetime::now()->getValue(),
		));

		// Job prior to next hour mark should not run
		SS_Datetime::set_mock_now('2010-06-20 13:40:00');
		$this->assertFalse($runner->isTaskDue($task, $cron));

		// Jobs just after the next hour mark should run
		SS_Datetime::set_mock_now('2010-06-20 14:10:00');
		$this->assertTrue($runner->isTaskDue($task, $cron));

		// Jobs somehow delayed a whole day should be run
		SS_Datetime::set_mock_now('2010-06-21 13:40:00');
		$this->assertTrue($runner->isTaskDue($task, $cron));
	}

	/**
	 * Test CronTaskController::runTask for task with status 'On'
	 */
	public function testRunTaskStatusOn() {
		$this->cleanCronTaskStatus();
		$runner = CronTaskController::create();
		$runner->setQuiet(true);
		$task = new CronTaskTest_TestCron();

		// Assuming first run, match the exact time (seconds are ignored)
		$this->assertEquals(0, CronTaskTest_TestCron::$times_run);
		SS_Datetime::set_mock_now('2010-06-20 13:00:10');
		$runner->runTask($task);
		$this->assertEquals(1, CronTaskTest_TestCron::$times_run);

		// Test that re-requsting the task in the same minute do not retrigger another run
		SS_Datetime::set_mock_now('2010-06-20 13:00:40');
		$runner->runTask($task);
		$this->assertEquals(1, CronTaskTest_TestCron::$times_run);

		// Job prior to next hour mark should not run
		SS_Datetime::set_mock_now('2010-06-20 13:40:00');
		$runner->runTask($task);
		$this->assertEquals(1, CronTaskTest_TestCron::$times_run);

		// Jobs just after the next hour mark should run
		SS_Datetime::set_mock_now('2010-06-20 14:10:00');
		$runner->runTask($task);
		$this->assertEquals(2, CronTaskTest_TestCron::$times_run);

		// Jobs run on the exact next expected date should run
		SS_Datetime::set_mock_now('2010-06-20 15:00:00');
		$runner->runTask($task);
		$this->assertEquals(3, CronTaskTest_TestCron::$times_run);

		// Jobs somehow delayed a whole day should be run
		SS_Datetime::set_mock_now('2010-06-21 13:40:00');
		$runner->runTask($task);
		$this->assertEquals(4, CronTaskTest_TestCron::$times_run);
	}

	/**
	 * Test CronTaskController::runTask for task with status 'Off'
	 */
	public function testRunTaskStatusOff() {
		$this->runTaskStatusOffOrError('Off');
	}

	/**
	 * Test CronTaskController::runTask for task with status 'Error'
	 */
	public function testRunTaskStatusError() {
		$this->runTaskStatusOffOrError('Error');
	}

	/**
	 * Test task status will change to 'Error' if execution is longer than expected
	 * @throws Exception
	 */
	public function testSetTaskStatusToError() {
		$this->cleanCronTaskStatus();
		$runner = CronTaskController::create();
		$runner->setQuiet(true);
		$task = new CronTaskTest_TestCron();

		SS_Datetime::set_mock_now('2010-06-20 13:00:10');
		$task->updateTaskStatus(array(
			'LastRun'		=> SS_Datetime::now()->getValue(),
			'Status'		=> 'Running',
			'LastChecked'	=> SS_Datetime::now()->getValue(),
		));

		SS_Datetime::set_mock_now('2010-06-20 13:02:10');
		$runner->runTask($task);
		$this->assertEquals('Running', $task->getStatus());

		SS_Datetime::set_mock_now('2010-06-20 15:52:10');
		$runner->runTask($task);
		$this->assertEquals('Error', $task->getStatus());
	}

	public function testCheckSimultaneousExecution() {
		$this->cleanCronTaskStatus();
		$runner = CronTaskController::create();
		$runner->setQuiet(true);
		$task = new CronTaskTest_TestCron();

		SS_Datetime::set_mock_now('2010-06-20 12:00:10');
		$task->updateTaskStatus(array(
			'LastRun'		=> SS_Datetime::now()->getValue(),
			'Status'		=> 'Running',
			'LastChecked'	=> SS_Datetime::now()->getValue(),
		));
		$this->assertEquals(0, CronTaskTest_TestCron::$times_run);
		SS_Datetime::set_mock_now('2010-06-20 13:00:10');
		$runner->runTask($task);
		$this->assertEquals(0, CronTaskTest_TestCron::$times_run);
	}

	/**
	 * Delete all CronTaskStatus records so every test will start from clean
	 */
	private function cleanCronTaskStatus() {
		foreach(CronTaskStatus::get() as $cronTaskStatus)
			$cronTaskStatus->delete();
	}

	/**
	 * Test CronTaskController::runTask for task with status 'Off' or 'Error'
	 */
	private function runTaskStatusOffOrError($status) {

		$runner = CronTaskController::create();
		$runner->setQuiet(true);
		$task = new CronTaskTest_TestCron();
		$task->updateTaskStatus(array(
			'Status'	=> $status,
		));

		// Assuming first run, match the exact time (seconds are ignored)
		$this->assertEquals(0, CronTaskTest_TestCron::$times_run);
		SS_Datetime::set_mock_now('2010-06-20 13:00:10');
		$runner->runTask($task);
		$this->assertEquals(0, CronTaskTest_TestCron::$times_run);

		// Test that re-requsting the task in the same minute do not retrigger another run
		SS_Datetime::set_mock_now('2010-06-20 13:00:40');
		$runner->runTask($task);
		$this->assertEquals(0, CronTaskTest_TestCron::$times_run);

		// Job prior to next hour mark should not run
		SS_Datetime::set_mock_now('2010-06-20 13:40:00');
		$runner->runTask($task);
		$this->assertEquals(0, CronTaskTest_TestCron::$times_run);

		// Jobs just after the next hour mark should run
		SS_Datetime::set_mock_now('2010-06-20 14:10:00');
		$runner->runTask($task);
		$this->assertEquals(0, CronTaskTest_TestCron::$times_run);

		// Jobs run on the exact next expected date should run
		SS_Datetime::set_mock_now('2010-06-20 15:00:00');
		$runner->runTask($task);
		$this->assertEquals(0, CronTaskTest_TestCron::$times_run);

		// Jobs somehow delayed a whole day should be run
		SS_Datetime::set_mock_now('2010-06-21 13:40:00');
		$runner->runTask($task);
		$this->assertEquals(0, CronTaskTest_TestCron::$times_run);
	}
}


class CronTaskTest_TestCron extends CronTask implements TestOnly {
	public static $times_run = 0;

	/**
	 * When this script is supposed to run the CronTaskController will execute
	 * process().
	 *
	 * @return void
	 */
	public function process() {
		++self::$times_run;
	}

	/**
	 * Return default schedule string
	 *
	 * @return string
	 */
	public function defaultSchedule() {
		return '0 * * * *';
	}

	/**
	 * Return default status of the task
	 *
	 * @return string
	 */
	public function defaultStatus() {
		return 'On';
	}

	/**
	 * Return execution time of a task in seconds
	 *
	 * @return int
	 */
	public function allowedExecutionTime() {
		return 2 * 60 * 60;
	}
}

//class C1ronTaskTest_TestCron implements TestOnly, CronTask {
//
//	public static $times_run = 0;
//
//	public function getSchedule() {
//		// Use hourly schedule
//		return '0 * * * *';
//	}
//
//	public function process() {
//		++self::$times_run;
//	}
//
//}
