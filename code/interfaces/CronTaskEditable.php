<?php
/**
 * By implementing this interface you can define this task as editable
 * and can implement needed logic who can edit it
 *
 */
interface CronTaskEditable {

    /**
     * Check if user can edit this task
     *
     * @param null|Member $member
     * @return mixed
     */
    public function canEdit($member = null);
}
