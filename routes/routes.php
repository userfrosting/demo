<?php
/**
 * UserFrosting (http://www.userfrosting.com)
 *
 * @link      https://github.com/userfrosting/UserFrosting
 * @license   https://github.com/userfrosting/UserFrosting/blob/master/licenses/UserFrosting.md (MIT License)
 */

/**
 * This route overrides the main `/` route, so that users are taken directly to the registration page.
 */
$app->get('/', 'UserFrosting\Sprinkle\Demo\Controller\DemoController:pageRegister')
    ->setName('register');

$app->group('/account', function () {
    // Redirect to registration page on index
    $this->get('/register', function ($request, $response) {
        $target = $this->router->pathFor('register');
        return $response->withRedirect($target, 301);
    })->setName('register');

    /**
     * This route overrides the `/account/register` route, to create a new group for each demo user.
     */
    $this->post('/register', 'UserFrosting\Sprinkle\Demo\Controller\DemoController:register'); 
});

