<?php

namespace SilverStripe\CronTask\Tests;

use Cron\CronExpression;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\CronTask\Controllers\CronTaskController;
use SilverStripe\CronTask\CronTaskStatus;
use SilverStripe\CronTask\Controllers\CronTask;
use SilverStripe\Dev\FunctionalTest;

/**
 * @package crontask
 */
class CronTaskControllerTest extends FunctionalTest
{
    /**
     * {@inheritDoc}
     * @var bool
     */
    protected $usesDatabase = true;

    /**
     * {@inheritDoc}
     */
    protected function setUp()
    {
        parent::setUp();
        CronTaskTest\TestCron::$times_run = 0;
    }

    /**
     * Tests CronTaskController::isTaskDue
     */
    public function testIsTaskDue()
    {
        $runner = CronTaskController::create();
        $task = new CronTaskTest\TestCron();
        $cron = CronExpression::factory($task->getSchedule());

        // Assuming first run, match the exact time (seconds are ignored)
        DBDatetime::set_mock_now('2010-06-20 13:00:10');
        $this->assertTrue($runner->isTaskDue($task, $cron));

        // Assume first run, do not match time just before or just after schedule
        DBDatetime::set_mock_now('2010-06-20 13:01:10');
        $this->assertFalse($runner->isTaskDue($task, $cron));
        DBDatetime::set_mock_now('2010-06-20 12:59:50');
        $this->assertFalse($runner->isTaskDue($task, $cron));

        // Mock a run and test that subsequent runs are properly scheduled
        DBDatetime::set_mock_now('2010-06-20 13:30:10');
        CronTaskStatus::update_status('SilverStripe\\CronTask\\Tests\\CronTaskTest\\TestCron', true);

        // Job prior to next hour mark should not run
        DBDatetime::set_mock_now('2010-06-20 13:40:00');
        $this->assertFalse($runner->isTaskDue($task, $cron));

        // Jobs just after the next hour mark should run
        DBDatetime::set_mock_now('2010-06-20 14:10:00');
        $this->assertTrue($runner->isTaskDue($task, $cron));

        // Jobs somehow delayed a whole day should be run
        DBDatetime::set_mock_now('2010-06-21 13:40:00');
        $this->assertTrue($runner->isTaskDue($task, $cron));
    }

    /**
     * Test CronTaskController::runTask
     */
    public function testRunTask()
    {
        $runner = CronTaskController::create();
        $runner->setQuiet(true);
        $task = new CronTaskTest\TestCron();

        // Assuming first run, match the exact time (seconds are ignored)
        $this->assertEquals(0, CronTaskTest\TestCron::$times_run);
        DBDatetime::set_mock_now('2010-06-20 13:00:10');
        $runner->runTask($task);
        $this->assertEquals(1, CronTaskTest\TestCron::$times_run);

        // Test that re-requsting the task in the same minute do not retrigger another run
        DBDatetime::set_mock_now('2010-06-20 13:00:40');
        $runner->runTask($task);
        $this->assertEquals(1, CronTaskTest\TestCron::$times_run);

        // Job prior to next hour mark should not run
        DBDatetime::set_mock_now('2010-06-20 13:40:00');
        $runner->runTask($task);
        $this->assertEquals(1, CronTaskTest\TestCron::$times_run);

        // Jobs just after the next hour mark should run
        DBDatetime::set_mock_now('2010-06-20 14:10:00');
        $runner->runTask($task);
        $this->assertEquals(2, CronTaskTest\TestCron::$times_run);

        // Jobs run on the exact next expected date should run
        DBDatetime::set_mock_now('2010-06-20 15:00:00');
        $runner->runTask($task);
        $this->assertEquals(3, CronTaskTest\TestCron::$times_run);

        // Jobs somehow delayed a whole day should be run
        DBDatetime::set_mock_now('2010-06-21 13:40:00');
        $runner->runTask($task);
        $this->assertEquals(4, CronTaskTest\TestCron::$times_run);
    }


    // normal cron output includes the current date/time - we check for that
    // the exact output here could vary depending on what other modules are installed
    public function testDefaultQuietFlagOutput()
    {
        $this->loginWithPermission('ADMIN');
        $this->expectOutputRegex('#'.SS_Datetime::now()->Format('Y-m-d').'#');
        $this->get('dev/cron?debug=1');
    }

    // with the flag set we want no output
    public function testQuietFlagOnOutput()
    {
        $this->loginWithPermission('ADMIN');
        $this->expectOutputString('');
        $this->get('dev/cron?quiet=1');
    }

}
