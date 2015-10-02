SilverStripe CronTask
==========================

This SilverStripe module provides developers the possibility to define tasks 
that can be triggered by cron jobs or other time based scripts.

Installing
----------

Add the following to your project's composer.json:

	{
		"require": {
			"silverstripe/crontask": "*"
		}
	}

Run `composer update` (this will also install needed 3rd party libs in ./vendor)

Usage
-----

Implement the `CronTask` interface on a new or already existing class:

	class TestCron implements CronTask {

		/**
		 * 
		 * @return string
		 */
		public function getSchedule() {
			return "5 * * * *";
		}

		/**
		 * 
		 * @return void
		 */
		public function process() {
			echo 'hello';
		}
	}


Then execute the crontask controller by sake

	./framework/sake dev/cron

_Note_: Run `./framework/sake dev/cron flush=1` to make SilverStripe aware of 
your new class.


The getSchedule method
----------------------

The crontask controller expects that the getSchedule returns a string as a cron 
expression. 

Some examples:

- `* * * * *` - every time 
- `*/5 * * * *` - every five minute (00:05, 00:10, 00:15 etc)
- `0 1 * * *` - every day at 01:00 
- `0 0 2 * *` - the 2nd of every month at 00:00
- `0 0 0 ? 1/2 FRI#2 *` - Every second Friday of every other month at 00:00

The process method
----------------------

The process method might have some of the logic it in, or setup and execute 
other more complicated background processes.


Note
----

Observe that the crontask module don't scheduling. This means if the total time
of all `process` calls takes more than one minute, it might start another 
process that interferes with the previously running process. 


Code that mimics [Ouroboros](http://en.wikipedia.org/wiki/Ouroboros) is often a 
bad idea.


CRON Expressions
----------------

A CRON expression is a string representing the schedule for a particular command to execute.  The parts of a CRON schedule are as follows:

    *    *    *    *    *    *
    -    -    -    -    -    -
    |    |    |    |    |    |
    |    |    |    |    |    + year [optional]
    |    |    |    |    +----- day of week (0 - 7) (Sunday=0 or 7)
    |    |    |    +---------- month (1 - 12)
    |    |    +--------------- day of month (1 - 31)
    |    +-------------------- hour (0 - 23)
    +------------------------- min (0 - 59)

For more information about what cron expression is allowed, see the 
[Cron-Expression](http://mtdowling.com/blog/2012/06/03/cron-expressions-in-php/) 
post from the creator of the 3rd party library.


Thanks
------

Thanks to [Michael Dowling](http://mtdowling.com/blog/2012/06/03/cron-expressions-in-php/) 
for doing the actual job of parsing cron expressions. 

This module is just a thin wrapper around his code.