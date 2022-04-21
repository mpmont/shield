<?php

namespace Sparks\Shield\Authentication\Actions;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\IncomingRequest;
use Sparks\Shield\Controllers\LoginController;
use Sparks\Shield\Models\UserIdentityModel;

class EmailActivator implements ActionInterface
{
    /**
     * Shows the initial screen to the user telling them
     * that an email was just sent to them with a link
     * to confirm their email address.
     *
     * @return mixed
     */
    public function show()
    {
        $user = auth()->user();

        // Delete any previous activation identities
        $identities = new UserIdentityModel();
        $identities->where('user_id', $user->getAuthId())
            ->where('type', 'email_activate')
            ->delete();

        //  Create an identity for our activation hash
        helper('text');
        $code = random_string('nozero', 6);

        $identities->insert([
            'user_id' => $user->getAuthId(),
            'type'    => 'email_activate',
            'secret'  => $code,
            'name'    => 'register',
            'extra'   => lang('Auth.needVerification'),
        ]);

        // Send the email
        helper('email');
        $email = emailer();
        $email->setFrom(setting('Email.fromEmail'), setting('Email.fromName') ?? '')
            ->setTo($user->getAuthEmail())
            ->setSubject(lang('Auth.emailActivateSubject'))
            ->setMessage(view(setting('Auth.views')['action_email_activate_email'], ['code' => $code]))
            ->send();

        // Display the info page
        echo view(setting('Auth.views')['action_email_activate_show'], ['user' => $user]);
    }

    /**
     * This method is unused.
     *
     * @return mixed
     */
    public function handle(IncomingRequest $request)
    {
        throw new PageNotFoundException();
    }

    /**
     * Verifies the email address and code matches an
     * identity we have for that user.
     *
     * @return mixed
     */
    public function verify(IncomingRequest $request)
    {
        $token    = $request->getVar('token');
        $user     = auth()->user();
        $identity = $user->getIdentity('email_activate');

        // No match - let them try again.
        if ($identity->secret !== $token) {
            $_SESSION['error'] = lang('Auth.invalidActivateToken');

            return view(setting('Auth.views')['action_email_activate_show']);
        }

        // Remove the identity
        $identities = new UserIdentityModel();
        $identities->delete($identity->id);

        // Set the user active now
        $model        = auth()->getProvider();
        $user->active = true;
        $model->save($user);

        // Clean up our session
        unset($_SESSION['auth_action']);

        // Get our login redirect url
        $loginController = new LoginController();

        return redirect()->to($loginController->getLoginRedirect());
    }
}
