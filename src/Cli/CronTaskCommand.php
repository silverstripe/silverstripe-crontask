<?php

namespace SilverStripe\CronTask\Cli;

use Cron\CronExpression;
use DateTime;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\CronTask\CronTaskStatus;
use SilverStripe\CronTask\Interfaces\CronTask;
use SilverStripe\ORM\FieldType\DBDatetime;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This command finds, checks and processes all crontasks
 * Hidden because there's no reason to run this manually other than for debugging
 */
#[AsCommand('cron-task', 'Runs cron tasks that are scheduled to be run', aliases: ['dev/cron'])]
class CronTaskCommand extends Command
{
    protected function configure(): void
    {
        parent::configure();
        $this->addOption('debug', description: 'Enable debug output');
    }

    /**
     * Determine if a task should be run
     */
    public function isTaskDue(CronTask $task, CronExpression $cron): bool
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('debug')) {
            $output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);
        }

        // Check each task
        $tasks = ClassInfo::implementorsOf(CronTask::class);
        if (empty($tasks)) {
            $this->output(
                _t(
                    CronTaskCommand::class . '.NO_IMPLEMENTERS',
                    'There are no implementators of CronTask to run'
                ),
                $output
            );
            return Command::SUCCESS;
        }
        foreach ($tasks as $subclass) {
            $task = Injector::inst()->create($subclass);
            // falsey schedule = don't run task
            if ($task->getSchedule()) {
                $this->runTask($task, $output);
            }
        }
        return Command::SUCCESS;
    }

    /**
     * Checks and runs a single CronTask
     */
    public function runTask(CronTask $task, OutputInterface $output): void
    {
        $cron = CronExpression::factory($task->getSchedule());
        $isDue = $this->isTaskDue($task, $cron);
        // Update status of this task prior to execution in case of interruption
        CronTaskStatus::update_status(get_class($task), $isDue);
        if ($isDue) {
            $this->output(
                _t(
                    CronTaskCommand::class . '.WILL_START_NOW',
                    '{task} will start now.',
                    ['task' => get_class($task)]
                ),
                $output
            );
            $task->process();
        } else {
            $this->output(
                _t(
                    CronTaskCommand::class . '.WILL_RUN_AT',
                    '{task} will run at {time}.',
                    ['task' => get_class($task), 'time' => $cron->getNextRunDate()->format('Y-m-d H:i:s')]
                ),
                $output,
                OutputInterface::VERBOSITY_VERBOSE
            );
        }
    }

    /**
     * Output a message including the timestamp
     */
    public function output(string $message, OutputInterface $output, int $verbosity = 0): void
    {
        $timestamp = DBDatetime::now()->Rfc2822();
        $output->writeln($timestamp . ' - ' . $message, $verbosity);
    }
}
