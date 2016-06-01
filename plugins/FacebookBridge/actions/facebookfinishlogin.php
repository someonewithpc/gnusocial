<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Login or register a local user based on a Facebook user
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Plugin
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010-2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class FacebookfinishloginAction extends Action
{
    private $fbuid       = null; // Facebook user ID
    private $fbuser      = null; // Facebook user object (JSON)
    private $accessToken = null; // Access token provided by Facebook JS API

    function prepare(array $args = array()) {
        parent::prepare($args);

        // Check cookie for a valid access_token

        if (isset($_COOKIE['fb_access_token'])) {
            $this->accessToken = $_COOKIE['fb_access_token'];
        }

        if (empty($this->accessToken)) {
            $this->clientError(_m("Unable to authenticate you with Facebook."));
        }

        $graphUrl = 'https://graph.facebook.com/me?access_token=' . urlencode($this->accessToken);
        $this->fbuser = json_decode(file_get_contents($graphUrl));

        if (empty($this->fbuser)) {
            // log badness

            list($proxy, $ip) = common_client_ip();

            common_log(
                LOG_WARNING,
                    sprintf(
                        'Failed Facebook authentication attempt, proxy = %s, ip = %s.',
                         $proxy,
                         $ip
                    ),
                    __FILE__
            );

            $this->clientError(
                // TRANS: Client error displayed when trying to connect to Facebook while not logged in.
                _m('You must be logged into Facebook to register a local account using Facebook.')
            );
        }

        $this->fbuid  = $this->fbuser->id;
        // OKAY, all is well... proceed to register
        return true;
    }

    function handle()
    {
        parent::handle();

        if (common_is_real_login()) {

            // This will throw a client exception if the user already
            // has some sort of foreign_link to Facebook.

            $this->checkForExistingLink();

            // Possibly reconnect an existing account

            $this->connectUser();

        } else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->handlePost();
        } else {
            $this->tryLogin();
        }
    }

    function checkForExistingLink() {

        // User is already logged in, are her accounts already linked?

        $flink = Foreign_link::getByForeignID($this->fbuid, FACEBOOK_SERVICE);

        if (!empty($flink)) {

            // User already has a linked Facebook account and shouldn't be here!

            $this->clientError(
                // TRANS: Client error displayed when trying to connect to a Facebook account that is already linked
                // TRANS: in the same StatusNet site.
                _m('There is already a local account linked with that Facebook account.')
            );
       }

       $cur = common_current_user();
       $flink = Foreign_link::getByUserID($cur->id, FACEBOOK_SERVICE);

       if (!empty($flink)) {

            // There's already a local user linked to this Facebook account.

            $this->clientError(
                // TRANS: Client error displayed when trying to connect to a Facebook account that is already linked
                // TRANS: in the same StatusNet site.
                _m('There is already a local account linked with that Facebook account.')
            );
        }
    }

    function handlePost()
    {
        $token = $this->trimmed('token');

        // CSRF protection
        if (!$token || $token != common_session_token()) {
            $this->showForm(
                // TRANS: Client error displayed when the session token does not match or is not given.
                _m('There was a problem with your session token. Try again, please.')
            );
            return;
        }

        if ($this->arg('create')) {

            if (!$this->boolean('license')) {
                $this->showForm(
                    // TRANS: Form validation error displayed when user has not agreed to the license.
                    _m('You cannot register if you do not agree to the license.'),
                    $this->trimmed('newname')
                );
                return;
            }

            // We has a valid Facebook session and the Facebook user has
            // agreed to the SN license, so create a new user
            $this->createNewUser();

        } else if ($this->arg('connect')) {

            $this->connectNewUser();

        } else {

            $this->showForm(
                // TRANS: Form validation error displayed when an unhandled error occurs.
                _m('An unknown error has occured.'),
                $this->trimmed('newname')
            );
        }
    }

    function showPageNotice()
    {
        if ($this->error) {

            $this->element('div', array('class' => 'error'), $this->error);

        } else {

            $this->element(
                'div', 'instructions',
                sprintf(
                    // TRANS: Form instructions for connecting to Facebook.
                    // TRANS: %s is the site name.
                    _m('This is the first time you have logged into %s so we must connect your Facebook to a local account. You can either create a new local account, or connect with an existing local account.'),
                    common_config('site', 'name')
                )
            );
        }
    }

    function title()
    {
        // TRANS: Page title.
        return _m('Facebook Setup');
    }

    function showForm($error=null, $username=null)
    {
        $this->error = $error;
        $this->username = $username;

        $this->showPage();
    }

    function showPage()
    {
        parent::showPage();
    }

    /**
     * @todo FIXME: Much of this duplicates core code, which is very fragile.
     * Should probably be replaced with an extensible mini version of
     * the core registration form.
     */
    function showContent()
    {
        if (!empty($this->message_text)) {
            $this->element('p', null, $this->message);
            return;
        }

        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_settings_facebook_connect',
                                          'class' => 'form_settings',
                                          'action' => common_local_url('facebookfinishlogin')));
        $this->elementStart('fieldset', array('id' => 'settings_facebook_connect_options'));
        // TRANS: Fieldset legend.
        $this->element('legend', null, _m('Connection options'));
        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        $this->element('input', array('type' => 'checkbox',
                                      'id' => 'license',
                                      'class' => 'checkbox',
                                      'name' => 'license',
                                      'value' => 'true'));
        $this->elementStart('label', array('class' => 'checkbox', 'for' => 'license'));
        // TRANS: %s is the name of the license used by the user for their status updates.
        $message = _m('My text and files are available under %s ' .
                     'except this private data: password, ' .
                     'email address, IM address, and phone number.');
        $link = '<a href="' .
                htmlspecialchars(common_config('license', 'url')) .
                '">' .
                htmlspecialchars(common_config('license', 'title')) .
                '</a>';
        $this->raw(sprintf(htmlspecialchars($message), $link));
        $this->elementEnd('label');
        $this->elementEnd('li');
        $this->elementEnd('ul');

        $this->elementStart('fieldset');
        $this->hidden('token', common_session_token());
        $this->element('legend', null,
                       // TRANS: Fieldset legend.
                       _m('Create new account'));
        $this->element('p', null,
                       // TRANS: Form instructions.
                       _m('Create a new user with this nickname.'));
        $this->elementStart('ul', 'form_data');

        // Hook point for captcha etc
        Event::handle('StartRegistrationFormData', array($this));

        $this->elementStart('li');
        // TRANS: Field label.
        $this->input('newname', _m('New nickname'),
                     ($this->username) ? $this->username : '',
                     // TRANS: Field title.
                     _m('1-64 lowercase letters or numbers, no punctuation or spaces.'));
        $this->elementEnd('li');

        // Hook point for captcha etc
        Event::handle('EndRegistrationFormData', array($this));

        $this->elementEnd('ul');
        // TRANS: Submit button to create a new account.
        $this->submit('create', _m('BUTTON','Create'));
        $this->elementEnd('fieldset');

        $this->elementStart('fieldset');
        $this->element('legend', null,
                       // TRANS: Fieldset legend.
                       _m('Connect existing account'));
        $this->element('p', null,
                       // TRANS: Form instructions.
                       _m('If you already have an account, login with your username and password to connect it to your Facebook.'));
        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        // TRANS: Field label.
        $this->input('nickname', _m('Existing nickname'));
        $this->elementEnd('li');
        $this->elementStart('li');
        // TRANS: Field label.
        $this->password('password', _m('Password'));
        $this->elementEnd('li');
        $this->elementEnd('ul');
        // TRANS: Submit button to connect a Facebook account to an existing StatusNet account.
        $this->submit('connect', _m('BUTTON','Connect'));
        $this->elementEnd('fieldset');

        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    function message($msg)
    {
        $this->message_text = $msg;
        $this->showPage();
    }

    function createNewUser()
    {
        if (!Event::handle('StartRegistrationTry', array($this))) {
            return;
        }

        if (common_config('site', 'closed')) {
            // TRANS: Client error trying to register with registrations not allowed.
            $this->clientError(_m('Registration not allowed.'));
        }

        $invite = null;

        if (common_config('site', 'inviteonly')) {
            $code = $_SESSION['invitecode'];
            if (empty($code)) {
                // TRANS: Client error trying to register with registrations 'invite only'.
                $this->clientError(_m('Registration not allowed.'));
            }

            $invite = Invitation::getKV($code);

            if (empty($invite)) {
                // TRANS: Client error trying to register with an invalid invitation code.
                $this->clientError(_m('Not a valid invitation code.'));
            }
        }

        try {
            $nickname = Nickname::normalize($this->trimmed('newname'), true);
        } catch (NicknameException $e) {
            $this->showForm($e->getMessage());
            return;
        }

        $args = array(
            'nickname' => $nickname,
            'fullname' => $this->fbuser->name,
            'homepage' => $this->fbuser->website,
            'location' => $this->fbuser->location->name
        );

        // It's possible that the email address is already in our
        // DB. It's a unique key, so we need to check
        if ($this->isNewEmail($this->fbuser->email)) {
            $args['email']           = $this->fbuser->email;
            if (isset($this->fuser->verified) && $this->fuser->verified == true) {
                $args['email_confirmed'] = true;
            }
        }

        if (!empty($invite)) {
            $args['code'] = $invite->code;
        }

        $user   = User::register($args);
        $result = $this->flinkUser($user->id, $this->fbuid);

        if (!$result) {
            // TRANS: Server error displayed when connecting to Facebook fails.
            $this->serverError(_m('Error connecting user to Facebook.'));
        }

        // Add a Foreign_user record
        Facebookclient::addFacebookUser($this->fbuser);

        $this->setAvatar($user);

        common_set_user($user);
        common_real_login(true);

        common_log(
            LOG_INFO,
            sprintf(
                'Registered new user %s (%d) from Facebook user %s, (fbuid %d)',
                $user->nickname,
                $user->id,
                $this->fbuser->name,
                $this->fbuid
            ),
            __FILE__
        );

        Event::handle('EndRegistrationTry', array($this));

        $this->goHome($user->nickname);
    }

    /*
     * Attempt to download the user's Facebook picture and create a
     * StatusNet avatar for the new user.
     */
    function setAvatar($user)
    {
         try {
            $picUrl = sprintf(
                'http://graph.facebook.com/%d/picture?type=large',
                $this->fbuser->id
            );

            // fetch the picture from Facebook
            $client = new HTTPClient();

            // fetch the actual picture
            $response = $client->get($picUrl);

            if ($response->isOk()) {

                // seems to always be jpeg, but not sure
                $tmpname = "facebook-avatar-tmp-" . common_random_hexstr(4);

                $ok = file_put_contents(
                    Avatar::path($tmpname),
                    $response->getBody()
                );

                if (!$ok) {
                    common_log(LOG_WARNING, 'Couldn\'t save tmp Facebook avatar: ' . $tmpname, __FILE__);
                } else {
                    // save it as an avatar

                    $imagefile = new ImageFile(null, Avatar::path($tmpname));
                    $filename = Avatar::filename($user->id, image_type_to_extension($imagefile->preferredType()),
                                                 180, common_timestamp());
                    // Previous docs said 180 is the "biggest img we get from Facebook"
                    $imagefile->resizeTo(Avatar::path($filename, array('width'=>180, 'height'=>180)));

                    // No need to keep the temporary file around...
                    @unlink(Avatar::path($tmpname));

                    $profile   = $user->getProfile();

                    if ($profile->setOriginal($filename)) {
                        common_log(
                            LOG_INFO,
                            sprintf(
                                'Saved avatar for %s (%d) from Facebook picture for '
                                    . '%s (fbuid %d), filename = %s',
                                 $user->nickname,
                                 $user->id,
                                 $this->fbuser->name,
                                 $this->fbuid,
                                 $filename
                             ),
                             __FILE__
                        );

                        // clean up tmp file
                    }

                }
            }
        } catch (Exception $e) {
            common_log(LOG_WARNING, 'Couldn\'t save Facebook avatar: ' . $e->getMessage(), __FILE__);
            // error isn't fatal, continue
        }
    }

    function connectNewUser()
    {
        $nickname = $this->trimmed('nickname');
        $password = $this->trimmed('password');

        if (!common_check_user($nickname, $password)) {
            // TRANS: Form validation error displayed when username/password combination is incorrect.
            $this->showForm(_m('Invalid username or password.'));
            return;
        }

        $user = User::getKV('nickname', $nickname);

        $this->tryLinkUser($user);

        common_set_user($user);
        common_real_login(true);

        // clear out the stupid cookie
        setcookie('fb_access_token', '', time() - 3600); // one hour ago

        $this->goHome($user->nickname);
    }

    function connectUser()
    {
        $user = common_current_user();
        $this->tryLinkUser($user);

        // clear out the stupid cookie
        setcookie('fb_access_token', '', time() - 3600); // one hour ago
        common_redirect(common_local_url('facebookfinishlogin'), 303);
    }

    function tryLinkUser($user)
    {
        $result = $this->flinkUser($user->id, $this->fbuid);

        if (empty($result)) {
            // TRANS: Server error displayed when connecting to Facebook fails.
            $this->serverError(_m('Error connecting user to Facebook.'));
        }
    }

    function tryLogin()
    {
        try {
            $flink = Foreign_link::getByForeignID($this->fbuid, FACEBOOK_SERVICE);
            $user = $flink->getUser();

            common_log(
                LOG_INFO,
                sprintf(
                    'Logged in Facebook user %s as user %d (%s)',
                    $this->fbuid,
                    $user->nickname,
                    $user->id
                ),
                __FILE__
            );

            common_set_user($user);
            common_real_login(true);

            // clear out the stupid cookie
            setcookie('fb_access_token', '', time() - 3600); // one hour ago

            $this->goHome($user->nickname);

        } catch (NoResultException $e) {
            $this->showForm(null, $this->bestNewNickname());
        }
    }

    function goHome($nickname)
    {
        $url = common_get_returnto();
        if ($url) {
            // We don't have to return to it again
            common_set_returnto(null);
        } else {
            $url = common_local_url('all',
                                    array('nickname' =>
                                          $nickname));
        }

        common_redirect($url, 303);
    }

    function flinkUser($user_id, $fbuid)
    {
        $flink = new Foreign_link();

        $flink->user_id     = $user_id;
        $flink->foreign_id  = $fbuid;
        $flink->service     = FACEBOOK_SERVICE;
        $flink->credentials = $this->accessToken;
        $flink->created     = common_sql_now();

        $flink_id = $flink->insert();

        return $flink_id;
    }

    function bestNewNickname()
    {
        try {
            $nickname = Nickname::normalize($this->fbuser->username, true);
            return $nickname;
        } catch (NicknameException $e) {
            // Failed to normalize nickname, but let's try the full name
        }

        try {
            $nickname = Nickname::normalize($this->fbuser->name, true);
            return $nickname;
        } catch (NicknameException $e) {
            // Any more ideas? Nope.
        }

        return null;
    }

    /*
     * Do we already have a user record with this email?
     * (emails have to be unique but they can change)
     *
     * @param string $email the email address to check
     *
     * @return boolean result
     */
     function isNewEmail($email)
     {
         // we shouldn't have to validate the format
         $result = User::getKV('email', $email);

         if (empty($result)) {
             return true;
         }

         return false;
     }
}
