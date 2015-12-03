<?php

/**
 * Class CronTaskStatusModelAdmin
 *
 * Model admin  for CronTaskStatus
 * By default it's disabled. To enable it add the following in config.yml
 *  CronTaskStatusModelAdmin:
 *    enabled: true
 */
class CronTaskStatusModelAdmin extends ModelAdmin {

    protected static $managed_models = array('CronTaskStatus');

    protected static $url_segment = 'crontasks';

    protected static $menu_title = 'Cron Tasks';

	private static $menu_icon = 'crontask/images/menu-icons/16x16/clock.png';

    /**
     * Control is it disabled or enabled
     *
     * by default it's disabled
     *
     * @var bool
     */
    protected static $enabled = false;

    /**
     * Alternate check is it enabled or not
     *
     * @return bool
     */
    public function alternateAccessCheck() {
        return self::config()->enabled;
    }

}