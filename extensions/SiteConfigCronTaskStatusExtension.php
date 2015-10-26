<?php

/**
 * Class SiteConfigCronTaskStatusExtension
 *
 */
class SiteConfigCronTaskStatusExtension extends DataExtension {

    /**
     * Add GridField for CronTaskStatues DataObject
     *
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields) {
        $fields->addFieldsToTab('Root', array(
            Tab::create('Cron Task Statuses', null,
                new GridField(
                    'CronTaskStatuses',
                    'Cron Task Statuses',
                    CronTaskStatus::get(),
                    GridFieldConfig_RecordEditor::create()
                        ->removeComponentsByType('GridFieldAddNewButton')
                )
            ),
        ));
    }
}