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

    public function getEditForm($id = null, $fields = null) {
        $form = parent::getEditForm($id, $fields);

        if($this->modelClass == 'CronTaskStatus') {
            $gridField = $form->Fields()->fieldByName($this->sanitiseClassName($this->modelClass));

            $config = $gridField->getConfig()->removeComponentsByType('GridFieldDeleteAction')
                ->removeComponentsByType('GridFieldAddNewButton')
                ->removeComponentsByType('GridFieldGroupOperations');
        }

        return $form;
    }
}