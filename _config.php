<?php

if(!defined('CRONTASK_MODULE_PATH')) {
	define('CRONTASK_MODULE_PATH', dirname(__DIR__));
}

SiteConfig::add_extension('SiteConfigCronTaskStatusExtension');