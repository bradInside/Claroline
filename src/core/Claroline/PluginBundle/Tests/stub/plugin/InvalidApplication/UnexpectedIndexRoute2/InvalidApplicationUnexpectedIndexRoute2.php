<?php

namespace InvalidApplication\UnexpectedIndexRoute2;

use Claroline\PluginBundle\AbstractType\ClarolineApplication;
use Claroline\PluginBundle\Widget\ApplicationLauncher;

class InvalidApplicationUnexpectedIndexRoute2 extends ClarolineApplication
{
    public function getLaunchers()
    {
        return array(
            new ApplicationLauncher('route_test', 'translation_test', array('ROLE_TEST'))
        );
    }
    
    /**
     * Returned string value cannot be empty.
     */
    public function getIndexRoute()
    {
        return '';
    }
}