<?php
/**
 * UserFrosting (http://www.userfrosting.com)
 *
 * @link      https://github.com/userfrosting/UserFrosting
 * @copyright Copyright (c) 2013-2016 Alexander Weissman
 * @license   https://github.com/userfrosting/UserFrosting/blob/master/licenses/UserFrosting.md (MIT License)
 */
namespace UserFrosting\Sprinkle\Demo\ServicesProvider;

/**
 *
 * @author Alex Weissman (https://alexanderweissman.com)
 */
class ServicesProvider
{
    /**
     * Register extended user fields services.
     *
     * @param Container $container A DI container implementing ArrayAccess and container-interop.
     */
    public function register($container)
    {
        /**
         * Extend the 'classMapper' service to register model classes.
         *
         * Mappings added: DemoUser
         */
        $container->extend('classMapper', function ($classMapper, $c) {
            $classMapper->setClassMapping('user', 'UserFrosting\Sprinkle\Demo\Model\DemoUser');
            return $classMapper;
        });
    }
}
