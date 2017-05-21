<?php
/**
 * UserFrosting (http://www.userfrosting.com)
 *
 * @link      https://github.com/userfrosting/UserFrosting
 * @copyright Copyright (c) 2013-2016 Alexander Weissman
 * @license   https://github.com/userfrosting/UserFrosting/blob/master/licenses/UserFrosting.md (MIT License)
 */
namespace UserFrosting\Sprinkle\Demo;

use UserFrosting\Sprinkle\Demo\ServicesProvider\ServicesProvider;
use UserFrosting\Sprinkle\Core\Initialize\Sprinkle;

/**
 * Bootstrapper class for the 'demo' sprinkle.
 *
 * @author Alex Weissman (https://alexanderweissman.com)
 */
class Demo extends Sprinkle
{
    /**
     * Register services.
     */
    public function init()
    {
        $serviceProvider = new ServicesProvider();
        $serviceProvider->register($this->ci);
    }
}
