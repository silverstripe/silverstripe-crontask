<?php


namespace SilverStripe\CronTask\Interfaces;


interface QueuedCronTask extends CronTask
{

    /**
     * Specify the priority of this job.
     * priority().
     *
     * @return int
     */
    public function priority();

}
