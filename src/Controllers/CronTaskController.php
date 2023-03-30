<?php

namespace SilverStripe\CronTask\Controllers;

use Cron\CronExpression;
use DateTime;
use Exception;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Convert;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\CronTask\CronTaskStatus;
use SilverStripe\CronTask\Interfaces\CronTask;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

/**
 * This is the controller that finds, checks and process all crontasks
 *
 * The default route to this controller is 'dev/cron'
 *
 */
class CronTaskController extends Controller
{
    /**
     * Tell the controller how noisy it may be
     *
     * @var int A number from 0 to 2
     */
    protected $verbosity = 1;

    /**
     * Tell the controller how noisy it may be
     *
     * @param int $verbosity An integer from 0 to 2. 0 = no output, 1 = normal, 2 = debug
     */
    public function setVerbosity($verbosity)
    {
        $this->verbosity = (int) $verbosity;
    }

    /**
     * Checks for cli or admin permissions and include the library
     *
     * @throws Exception
     */
    public function init()
    {
        parent::init();

        // Unless called from the command line, we need ADMIN privileges
        if (!Director::is_cli() && !Permission::check('ADMIN')) {
            Security::permissionFailure();
        }

        // set quiet flag based on CLI parameter
        if ($this->getRequest()->getVar('quiet')) {
            $this->setVerbosity(0);
        }
        if ($this->getRequest()->getVar('debug')) {
            $this->setVerbosity(2);
        }
    }

    /**
     * Determine if a task should be run
     *
     * @param CronTask $task
     * @param CronExpression $cron
     */
    public function isTaskDue(CronTask $task, CronExpression $cron)
    {
        // Get last run status
        $status = CronTaskStatus::get_status(get_class($task));

        // If the cron is due immediately, then run it
        $now = new DateTime(DBDatetime::now()->getValue());
        if ($cron->isDue($now)) {
            if (empty($status) || empty($status->LastRun)) {
                return true;
            }
            // In case this process is invoked twice in one minute, supress subsequent executions
            $lastRun = new DateTime($status->LastRun);
            return $lastRun->format('Y-m-d H:i') != $now->format('Y-m-d H:i');
        }

        // If this is the first time this task is ever checked, no way to detect postponed execution
        if (empty($status) || empty($status->LastChecked)) {
            return false;
        }

        // Determine if we have passed the last expected run time
        $nextExpectedDate = $cron->getNextRunDate($status->LastChecked);
        return $nextExpectedDate <= $now;
    }

    /**
     * Default controller action
     *
     * @param HTTPRequest $request
     */
    public function index(HTTPRequest $request)
    {
        // Show more debug info with ?debug=1
        $isDebug = (bool)$request->getVar('debug');

        // Check each task
        $tasks = ClassInfo::implementorsOf(CronTask::class);
        if (empty($tasks)) {
            $this->output(_t(self::class . '.NO_IMPLEMENTERS', 'There are no implementators of CronTask to run'), 2);
            return;
        }
        foreach ($tasks as $subclass) {
            $task = Injector::inst()->create($subclass);
            // falsey schedule = don't run task
            if ($task->getSchedule()) {
                $this->runTask($task, $isDebug);
            }
        }
    }

    /**
     * Checks and runs a single CronTask
     *
     * @param CronTask $task
     */
    public function runTask(CronTask $task)
    {
        $cron = CronExpression::factory($task->getSchedule());
        $isDue = $this->isTaskDue($task, $cron);
        // Update status of this task prior to execution in case of interruption
        CronTaskStatus::update_status(get_class($task), $isDue);
        if ($isDue) {
            $this->output(_t(self::class . '.WILL_START_NOW', '{task} will start now.', ['task' => get_class($task)]));
            $task->process();
        } else {
            $this->output(
                _t(
                    self::class . '.WILL_RUN_AT',
                    '{task} will run at {time}.',
                    ['task' => get_class($task), 'time' => $cron->getNextRunDate()->format('Y-m-d H:i:s')]
                ),
                2
            );
        }
    }

    /**
     * Output a message to the browser or CLI
     *
     * @param string $message
     */
    public function output($message, $minVerbosity = 1)
    {
        if ($this->verbosity < $minVerbosity) {
            return;
        }
        $timestamp = DBDatetime::now()->Rfc2822();
        if (Director::is_cli()) {
            echo $timestamp . ' - ' . $message . PHP_EOL;
        } else {
            echo Convert::raw2xml($timestamp . ' - ' . $message) . '<br />' . PHP_EOL;
        }
    }
}
