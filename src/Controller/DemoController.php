<?php
/**
 * UserFrosting (http://www.userfrosting.com)
 *
 * @link      https://github.com/userfrosting/UserFrosting
 * @copyright Copyright (c) 2013-2016 Alexander Weissman
 * @license   https://github.com/userfrosting/UserFrosting/blob/master/licenses/UserFrosting.md (MIT License)
 */
namespace UserFrosting\Sprinkle\Demo\Controller;

use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager as Capsule;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use UserFrosting\Fortress\RequestDataTransformer;
use UserFrosting\Fortress\RequestSchema;
use UserFrosting\Fortress\ServerSideValidator;
use UserFrosting\Fortress\Adapter\JqueryValidationAdapter;
use UserFrosting\Sprinkle\Account\Authenticate\Authenticator;
use UserFrosting\Sprinkle\Account\Controller\Exception\SpammyRequestException;
use UserFrosting\Sprinkle\Account\Model\Group;
use UserFrosting\Sprinkle\Account\Model\Role;
use UserFrosting\Sprinkle\Account\Model\User;
use UserFrosting\Sprinkle\Account\Util\Password;
use UserFrosting\Sprinkle\Core\Controller\SimpleController;
use UserFrosting\Sprinkle\Core\Facades\Debug;
use UserFrosting\Sprinkle\Core\Mail\EmailRecipient;
use UserFrosting\Sprinkle\Core\Mail\TwigMailMessage;
use UserFrosting\Sprinkle\Core\Throttle\Throttler;
use UserFrosting\Sprinkle\Core\Util\Captcha;
use UserFrosting\Support\Exception\BadRequestException;
use UserFrosting\Support\Exception\ForbiddenException;
use UserFrosting\Support\Exception\HttpException;

/**
 * Overrides some AccountController methods for the purposes of the demo site.
 *
 * @author Alex Weissman (https://alexanderweissman.com)
 */
class DemoController extends SimpleController
{
    /**
     * Render the account registration page for UserFrosting.
     *
     * This allows new (non-authenticated) users to create a new account for themselves on your website (if enabled).
     * By definition, this is a "public page" (does not require authentication).
     * Request type: GET
     */
    public function pageRegister($request, $response, $args)
    {
        /** @var Config $config */
        $config = $this->ci->config;

        /** @var UserFrosting\Sprinkle\Account\Authenticate\Authenticator $authenticator */
        $authenticator = $this->ci->authenticator;

        // Forward to dashboard if user is already logged in
        // TODO: forward to user's landing page or last visited page
        if ($authenticator->check()) {
            return $response->withRedirect($this->ci->router->pathFor('dashboard'), 302);
        }

        // Get a list of all locales
        $locales = $config['site.locales.available'];

        // Load validation rules
        $schema = new RequestSchema("schema://register.json");
        $validatorRegister = new JqueryValidationAdapter($schema, $this->ci->translator);

        return $this->ci->view->render($response, 'pages/register.html.twig', [
            "locales" => $locales,
            "page" => [
                "validators" => [
                    "register" => $validatorRegister->rules('json', false)
                ]
            ]
        ]);
    }

    /**
     * Processes an new account registration request.
     *
     * Processes the request from the form on the registration page, checking that:
     * 1. The honeypot was not modified;
     * 2. The master account has already been created (during installation);
     * 3. Account registration is enabled;
     * 4. The user is not already logged in;
     * 5. Valid information was entered;
     * 6. The captcha, if enabled, is correct;
     * 7. The username and email are not already taken.
     * Automatically sends an activation link upon success, if account activation is enabled.
     * This route is "public access".
     * Request type: POST
     * Returns the User Object for the user record that was created.
     * @todo we should probably throttle this as well to prevent account enumeration, especially since it needs to divulge when a username/email has been used.
     */
    public function register(Request $request, Response $response, $args)
    {
        /** @var MessageStream $ms */
        $ms = $this->ci->alerts;

        /** @var UserFrosting\Sprinkle\Core\Util\ClassMapper $classMapper */
        $classMapper = $this->ci->classMapper;

        /** @var Config $config */
        $config = $this->ci->config;

        // Get POST parameters: user_name, first_name, last_name, email, locale, password, passwordc, captcha, spiderbro, csrf_token
        $params = $request->getParsedBody();

        // Check the honeypot. 'spiderbro' is not a real field, it is hidden on the main page and must be submitted with its default value for this to be processed.
        if (!isset($params['spiderbro']) || $params['spiderbro'] != "http://") {
            throw new SpammyRequestException("Possible spam received:" . print_r($params, true));
        }

        // Security measure: do not allow registering new users until the master account has been created.
        if (!$classMapper->staticMethod('user', 'find', $config['reserved_user_ids.master'])) {
            $ms->addMessageTranslated("danger", "ACCOUNT.MASTER_NOT_EXISTS");
            return $response->withStatus(403);
        }

        // Check if registration is currently enabled
        if (!$config['site.registration.enabled']) {
            $ms->addMessageTranslated("danger", "REGISTRATION.DISABLED");
            return $response->withStatus(403);
        }

        /** @var UserFrosting\Sprinkle\Account\Authenticate\Authenticator $authenticator */
        $authenticator = $this->ci->authenticator;

        // Prevent the user from registering if he/she is already logged in
        if ($authenticator->check()) {
            $ms->addMessageTranslated("danger", "REGISTRATION.LOGOUT");
            return $response->withStatus(403);
        }

        // Load the request schema
        $schema = new RequestSchema("schema://register.json");

        // Whitelist and set parameter defaults
        $transformer = new RequestDataTransformer($schema);
        $data = $transformer->transform($params);

        $error = false;

        // Validate request data
        $validator = new ServerSideValidator($schema, $this->ci->translator);
        if (!$validator->validate($data)) {
            $ms->addValidationErrors($validator);
            $error = true;
        }

        // Check if username or email already exists
        if ($classMapper->staticMethod('user', 'where', 'user_name', $data['user_name'])->first()) {
            $ms->addMessageTranslated("danger", "USERNAME.IN_USE", $data);
            $error = true;
        }

        if ($classMapper->staticMethod('user', 'where', 'email', $data['email'])->first()) {
            $ms->addMessageTranslated("danger", "EMAIL.IN_USE", $data);
            $error = true;
        }

        // Check that locale is valid
        $locales = $config['site.locales.available'];
        if (!array_key_exists($data['locale'], $locales)) {
            $ms->addMessageTranslated("danger", "{$data['locale']} is not a valid locale.");
            $error = true;
        }

        // Check captcha, if required
        if ($config['site.registration.captcha']) {
            $captcha = new Captcha($this->ci->session, $this->ci->config['session.keys.captcha']);
            if (!$data['captcha'] || !$captcha->verifyCode($data['captcha'])) {
                $ms->addMessageTranslated("danger", "CAPTCHA.FAIL");
                $error = true;
            }
        }

        if ($error) {
            return $response->withStatus(400);
        }

        // Remove captcha, password confirmation from object data after validation
        unset($data['captcha']);
        unset($data['passwordc']);

        if ($config['site.registration.require_email_verification']) {
            $data['flag_verified'] = false;
        } else {
            $data['flag_verified'] = true;
        }

        // Hash password
        $data['password'] = Password::hash($data['password']);

        // All checks passed!  log events/activities, create user, and send verification email (if required)
        // Begin transaction - DB will be rolled back if an exception occurs
        Capsule::transaction( function() use ($classMapper, $data, $ms, $config) {
            // Create a new group for the user
            $userName = isset($data['first_name']) ? $data['first_name'] : $data['user_name'];
            $group = $classMapper->createInstance('group', [
                'slug' => "{$data['user_name']}-users",
                'name' => "$userName's Users",
                'description' => "Users managed by $userName's test account.",
                'icon' => 'fa fa-flag'
            ]);

            // Save group and set user's group id
            $group->save();
            $data['group_id'] = $group->id;

            // Create the user
            $user = $classMapper->createInstance('user', $data);

            // Store new user to database
            $user->save();

            // Create activity record
            $this->ci->userActivityLogger->info("User {$user->user_name} registered for a new account.", [
                'type' => 'sign_up',
                'user_id' => $user->id
            ]);

            // Attach roles for 'user' and 'group administrator'
            $defaultRoles = [
                'user' => Role::where('slug', 'user')->first(),
                'group-admin' => Role::where('slug', 'group-admin')->first()
            ];
    
            foreach ($defaultRoles as $slug => $role) {
                if ($role) {
                    $user->roles()->attach($role->id);
                }
            }

            // Verification email
            if ($config['site.registration.require_email_verification']) {
                // Try to generate a new verification request
                $verification = $this->ci->repoVerification->create($user, $config['verification.timeout']);

                // Create and send verification email
                $message = new TwigMailMessage($this->ci->view, "mail/verify-account.html.twig");

                $message->from($config['address_book.admin'])
                        ->addEmailRecipient(new EmailRecipient($user->email, $user->full_name))
                        ->addParams([
                            "user" => $user,
                            "token" => $verification->getToken()
                        ]);

                $this->ci->mailer->send($message);

                $ms->addMessageTranslated("success", "REGISTRATION.COMPLETE_TYPE2", $user->toArray());
            } else {
                // No verification required
                $ms->addMessageTranslated("success", "REGISTRATION.COMPLETE_TYPE1");
            }
        });

        return $response->withStatus(200);
    }
}
