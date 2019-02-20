<?php
/**
 * DokuWiki Plugin clipauth (Auth Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Tongyu Nie <marktnie@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}

define("__SEC__DAY__", 86400);
define("__MUTED__", 'muted');
define("__NUKED__", 'nuked');


class auth_plugin_clipauth_paperclipAuth extends DokuWiki_Auth_Plugin
{

//    var $pdo;
    var $settings;
    var $dao;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct(); // for compatibility


        $this->cando['addUser']     = true; // can users be added?
        $this->cando['delUser']     = true; // can Users be deleted?
        $this->cando['modLogin']    = true; // can login names be changed?
        $this->cando['modPass']     = true; // can passwords be changed?
        $this->cando['modName']     = true; // can real names be changed?
        $this->cando['modMail']     = true; // can emails be changed?
        $this->cando['modGroups']   = true; // can groups be changed?
        $this->cando['getUsers']    = true; // can a (filtered) list of users be retrieved?
        $this->cando['getUserCount']= true; // can the number of users be retrieved?
        $this->cando['getGroups']   = true; // can a list of available groups be retrieved?
        $this->cando['external']    = true; // does the module do external auth checking?
        $this->cando['logout']      = true; // can the user logout again? (eg. not possible with HTTP auth)

        $this->dao = new dokuwiki\paperclip\paperclipDAO();

        $this->success = true;
    }


    /**
     * Log off the current user [ OPTIONAL ]
     */
    // {
    // }

    /**
     * @param string $user
     * @param bool   $sticky
     * @param string $servicename
     * @param int    $validityPeriodInSeconds optional, per default 1 Year
     */
    private function setUserCookie($user, $sticky, $servicename, $validityPeriodInSeconds = 31536000) {
        global $USERINFO;
        global $auth;

        $cookie = base64_encode($user).'|'.((int) $sticky).'|'.base64_encode('oauth').'|'.base64_encode($servicename);
        $cookieDir = empty($conf['cookiedir']) ? DOKU_REL : $conf['cookiedir'];
        $time      = $sticky ? (time() + $validityPeriodInSeconds) : 0;
        setcookie(DOKU_COOKIE,$cookie, $time, $cookieDir, '',($conf['securecookie'] && is_ssl()), true);

        $_SERVER['REMOTE_USER'] = $user;
        $_SESSION[DOKU_COOKIE]['auth']['user'] = $user;
//                    $_SESSION[DOKU_COOKIE]['auth']['pass'] = $pass;
        $_SESSION[DOKU_COOKIE]['auth']['info'] = $USERINFO;
        $USERINFO = $this->dao->getUserDataCore($user);
    }

    /**
     * Do all authentication [ OPTIONAL ]
     *
     * @param   string $user   Username
     * @param   string $pass   Cleartext Password
     * @param   bool   $sticky Cookie should not expire
     *
     * @return  bool             true on successful auth
     */
    public function trustExternal($user, $pass, $sticky = false)
    {

        global $USERINFO;
        global $conf;
        $sticky ? $sticky = true : $sticky = false; //sanity check


        if (isset($_COOKIE[DOKU_COOKIE])) {
            // Check users' cookies
            list($cookieuser, $cookiesticky, $auth, $servicename) = explode('|', $_COOKIE[DOKU_COOKIE]);
            $cookieuser = base64_decode($cookieuser, true);
            $auth = base64_decode($auth, true);
            $servicename = base64_decode($servicename, true);

            if ($USERINFO = $this->dao->getUserDataCore($cookieuser)) {
                $_SERVER['REMOTE_USER'] = $USERINFO['name'];
                return $USERINFO;
            }
        }

        if (empty($user)) {
            // do the checking here
            $isExtLogin = $_GET['ext'];
            // login
            if ($isExtLogin) {
                // External login
                // Import helper
                $hlp = $this->loadHelper('clipauth_paperclipHelper');

                // Handle the wechat login
                if ($isExtLogin === $this->getConf('wechat')) {
                    $code = $_GET['code'];

                    // Varify the session
                    // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
                    // Should be enabled in the future
                if (empty($_GET['state']) || ($_GET['state'] !== rtrim($_SESSION['oauth2state'], '#wechat_redirect'))) {

                    unset($_SESSION['oauth2state']);
                    exit('Invalid state');

                }

                    // Get user data from wechat
                    // First, get access token from code
                    $authOAuthData = [];
                    $accessToken = $hlp->getAccessToken();
                    $values = $accessToken->getValues();
                    $authOAuthData['accessToken'] = $accessToken->getToken();
                    $authOAuthData['refreshToken'] = $accessToken->getRefreshToken();
                    $authOAuthData['open_id'] = $values['openid'];
                    $authOAuthData['union_id'] = $values['unionid'];

//                    $username = '';
                    // Check if user have already registered
                    if ($username = $this->dao->getOAuthUserByOpenid($this->getConf('wechat'), $values['openid'])) {
                        // Set user info
                        $USERINFO = $this->dao->getUserDataCore($authOAuthData['open_id']);
                        $realname = $USERINFO['name'];

                        $_SERVER['REMOTE_USER'] = $realname;
                        $this->setUserCookie($authOAuthData['open_id'], $sticky, $this->getConf('wechat'));


                    }
                    else {
                        // Not registered
                        // get user info
                        $userinfo = $hlp->getWechatInfo($authOAuthData['accessToken'], $values['openid']);
                        $authOAuthData['username'] = $userinfo['nickname'];
                        $username = $userinfo['nickname'];

                        // set default group if no groups specified
                        $grps = array($conf['defaultgroup']);
                        $grps = join(',', $grps);

                        // this function is not finished
                        $addUserCoreResult = $this->dao->addUserCore(
                            $authOAuthData['open_id'],
                            null,
                            $authOAuthData['username'],
                            null,
                            $grps,
                            null
                        );
                        $addAuthOAuthResult = $this->dao->addAuthOAuth($authOAuthData, $this->getConf('wechat'));

                        // Set cookies
                        $this->setUserCookie($authOAuthData['open_id'], $sticky, $this->getConf('wechat'));
                    }

                    return true;

                }
                // Handle the weibo login
                elseif ($isExtLogin === $this->getLang('weibo')) {

                }
            }
        }
        return auth_login($user, $pass, $sticky);
    }

    private function isInPrison($record) {
        $time = $record['time'];
        $sentence = $record['mutedates'];
        $mutedTimeInSeconds = strtotime($time);
        $timeNow = strtotime('now');
        $timeElapsed = $timeNow - $mutedTimeInSeconds;

        if ($timeElapsed > __SEC__DAY__ * $sentence) {
            // Not in prison
            return false;
        } else {
            // Still in prison
            return true;
        }

    }

    private function checkUserStatus($userinfo, &$userPrevId) {
        // get the mute record for every user
        $statement = $this->dao->getMuteRecord($userinfo['id']);
        if ($statement->rowCount() === 0) {
            return true;
        } else {
            while (($result = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                if ($this->isInPrison($result)) {
                    // Should not be released
                    return false;
                }
                $userPrevId = $result['identity'];
            }
            return true;
        }
    }

    /**
     * Check user+password
     *
     * May be ommited if trustExternal is used.
     *
     * @param   string $user the user name
     * @param   string $pass the clear text password
     *
     * @return  bool
     */
    public function checkPass(&$user, $pass)
    {
        // FIXME implement password check
        if (!$user || !$pass) {
            return false;
        } else {
            // enable user to use their mail address to login
            if (filter_var($user, FILTER_VALIDATE_EMAIL)) {
                $userinfo = $this->getUserDataByEmail($user);
                $user = $userinfo['user'];
            } else {
                $userinfo = $this->getUserData($user);

            }

            // begin to verify
            if (in_array(__NUKED__, $userinfo['grps'])) {
                // block nuked users and save the muted users
                return false;
            }
            elseif (in_array(__MUTED__, $userinfo['grps'])) {
                // Only for muted users
                $prevID = 'user';
                if ($this->checkUserStatus($userinfo, $prevID)) {
                    // change user status;
                    $this->dao->setUserIdentity($userinfo['id'], $prevID);
                    $userinfo = $this->getUserData($user);
                }
            }

            if ($userinfo !== false) {
                return auth_verifyPassword($pass, $userinfo['pass']);
            } else {
                return false;
            }
        }
    }

    /**
     * Return user info
     *
     * Returns info about the given user needs to contain
     * at least these fields:
     *
     * name string  full name of the user
     * mail string  email addres of the user
     * grps array   list of groups the user is in
     *
     * @param   string $user          the user name
     * @param   bool   $requireGroups whether or not the returned data must include groups
     *
     * @return  array  containing user data or false
     */
    public function getUserData($user, $requireGroups=true)
    {
        return $this->dao->getUserData($user);
    }

    private function getUserDataByEmail($email)
    {
        return $this->dao->getUserDataByEmail($email);

    }

    /**
     * TODO: Add verficationCode support ==> add mailing server && add additional column in db to store verificationcode
     * Create a new User [implement only where required/possible]
     *
     * Returns false if the user already exists, null when an error
     * occurred and true if everything went well.
     *
     * The new user HAS TO be added to the default group by this
     * function!
     *
     * Set addUser capability when implemented
     *
     * @param  string     $user
     * @param  string     $pass
     * @param  string     $name
     * @param  string     $mail
     * @param  null|array $grps
     *
     * @return bool|null
     */
    public function createUser($user, $pass, $name, $mail, $grps = null)
    {
        global $conf;

        // validate email again to avoid client bypassing input tag validation check
        if (filter_var($mail, FILTER_VALIDATE_EMAIL) === false) {
            echo 'Invalid Email';
            return false;
        }

        // check if the email has been registerd
        if ($this->getUserDataByEmail($mail) !== false) {
            return false;
        }

        // check if the user already exist
        if ($this->getUserData($user) !== false) {
            return false;
        }

        $invitation = $pass['invitation'];
        // if the user does not exist
        // check the invitation code
        if ($conf['needInvitation'] == 0) {
            $result = $this->dao->checkInvtCode($invitation);
            // the code should be valid and haven't been used
            if ($result === false || $result['isUsed'] == 1) {
                // return false as user has already been registered
                 return false;
            }
            $pass = $pass['pass'];
        }

        // encrypt password
        $pass = auth_cryptPassword($pass);

        // generate verfication code with random 48 bytes and convert them to hex
        // the actual length would be 48 * 2 = 96
        $verficationCode = bin2hex(openssl_random_pseudo_bytes(48));

        // set default group if no groups specified
        if(!is_array($grps)) $grps = array($conf['defaultgroup']);
        $grps = join(',', $grps);

        $result = $this->dao->addUser($user, $pass, $name, $mail, $grps, $verficationCode);

        if ($result === true) {
            if ($conf['needInvitation'] == 0) {
                $this->dao->setInvtCodeToInvalid($invitation);
            }
            return $this->sendVerificationMail($mail, $verficationCode);
        }
        else {
            return null;
        }
    }

    /**
    * WARNING: mailing server has not been configured.
    * Send a verfication e-mail
    *
    * Returns true if the mail was successfully accepted for delivery,
    * false otherwise
    *
    * @param   string   $mail  e-mail address this mail sends to
    * @param   string   $verficationCode verificationcode to be sent
    *
    * @return  bool
     */
    private function sendVerificationMail($mail, $verficationCode)
    {
      $smtp = $this->loadHelper('smtp');

      $link = "https://ipaperclip.net/doku.php?mail=".$mail."&verify=".$verficationCode;
      $type = 'verification';

      $info = array(
        'to'=>$mail,
        'link'=>$link
      );

      try {
        return $smtp->sendMail($info, $type);
      } catch(Exception $e) {
        echo $e->getMessage();
        return false;
      }
    }

    /**
     * Modify user data [implement only where required/possible]
     *
     * Set the mod* capabilities according to the implemented features
     *
     * @param   string $user    nick of the user to be changed
     * @param   array  $changes array of field/value pairs to be changed (password will be clear text)
     *
     * @return  bool
     */
    public function modifyUser($user, $changes)
    {
        if ($this->getUserData($user) !== false) {
            if ($changes['mail']) {
                $mail = $changes['mail'];
                $verficationCode = bin2hex(openssl_random_pseudo_bytes(48));
                $result = $this->sendVerificationMail($mail, $verficationCode);
                if ($result) {
                    $userdata = $this->dao->getUserData($user);
                    $id = $userdata['id'];
                    $this->dao->setIdentity($id, 'unverified');
                }else{
                    return false;
                }
            }
            $result = $this->dao->setUserInfo($user, $changes);
            return $result;
        }

        return false;
    }

    /**
     * Delete one or more users [implement only where required/possible]
     *
     * Set delUser capability when implemented
     *
     * @param   array  $users
     *
     * @return  int    number of users deleted
     */
    public function deleteUsers($users)
    {
        $counter = 0;
        foreach ($users as $user) {
            if ($this->getUserData($user) !== false) {
                $result = $this->dao->deleteUser($user);
                if ($result) $counter += 1;
            }

        }
        return $counter;
    }

    var $fieldToDB = [
        'user' => 'username',
        'name' => 'realname',
        'mail' => 'mailaddr',
        'grps' => 'identity'
    ];
    /**
     * Simple transfer of array
     *
     * @param $result
     * @return array
     */
    private  function  transferResult ($result) {
        return [
            'pass' => $result['password'],
            'name' => $result['realname'],
            'mail' => $result['mailaddr'],
            'id'   => $result['id'],
            'grps' => array_filter(explode(',', $result['identity']))
        ];
    }

    private function processOnefield($filter, $fieldname, &$conditions) {
        if ($filter[$fieldname]) {
            $elements = $filter[$fieldname];
            $elements = explode('|', $elements);
            foreach ($elements as $element) {
                $condition = $this->fieldToDB[$fieldname].' = "'.$element.'"';
                array_push($conditions, $condition);
            }
        };
        return $conditions;
    }

    private function processGrps($filter, $fieldname, &$conditions) {
        // since the identity of user is stored like xxx, xxx
        // the way to match identity should be different
        // as a result I used 'like' to treat the grps field
        if ($filter[$fieldname]) {
            $elements = $filter[$fieldname];
            $elements = explode('|', $elements);
            foreach ($elements as $element) {
                $condition = $this->fieldToDB[$fieldname].' like "%'.$element. '%"';
                array_push($conditions, $condition);
            }
        };
        return $conditions;
    }

    private  function  _filter($filter) {
        $conditions = array();
        $this->processOneField($filter, 'user', $conditions);
        $this->processOneField($filter, 'mail', $conditions);
        $this->processOneField($filter, 'name', $conditions);
        $this->processGrps($filter, 'grps', $conditions);
        return $conditions;
    }

    private  function  _retrieveUsers($filter) {
        $conditions = $this->_filter($filter);

        $statement = $this->dao->getUsers($conditions);

        return $statement;

    }

    private  function _countUsers($filter) {
        $conditions = $this->_filter($filter);

        return $this->dao->countUsers($conditions);
    }
    /**
     * Bulk retrieval of user data [implement only where required/possible]
     *
     * Set getUsers capability when implemented
     *
     * @param   int   $start  index of first user to be returned
     * @param   int   $limit  max number of users to be returned, 0 for unlimited
     * @param   array $filter array of field/pattern pairs, null for no filter
     *
     * @return  array list of userinfo (refer getUserData for internal userinfo details)
     */
    public function retrieveUsers($start = 0, $limit = 0, $filter = null)
    {
        $statement = $this->_retrieveUsers($filter);
        $results = array();
        while (($result = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $results[$result['username']] = $this->transferResult($result);
        }

        return $results;
    }

    /**
     * Return a count of the number of user which meet $filter criteria
     * [should be implemented whenever retrieveUsers is implemented]
     *
     * Set getUserCount capability when implemented
     *
     * @param  array $filter array of field/pattern pairs, empty array for no filter
     *
     * @return int
     */
    public function getUserCount($filter = array())
    {
        $num = $this->_countUsers($filter);
        return $num;
    }

    /**
     * Define a group [implement only where required/possible]
     *
     * Set addGroup capability when implemented
     *
     * @param   string $group
     *
     * @return  bool
     */
    //public function addGroup($group)
    //{
        // FIXME implement
    //    return false;
    //}

    /**
     * Retrieve groups [implement only where required/possible]
     *
     * Set getGroups capability when implemented
     *
     * @param   int $start
     * @param   int $limit
     *
     * @return  array
     */
    public function retrieveGroups($start = 0, $limit = 0)
    {
        return array("admin", "user", "muted", "nuked");
    }

    /**
     * Return case sensitivity of the backend
     *
     * When your backend is caseinsensitive (eg. you can login with USER and
     * user) then you need to overwrite this method and return false
     *
     * @return bool
     */
    public function isCaseSensitive()
    {
        return true;
    }

    /**
     * Sanitize a given username
     *
     * This function is applied to any user name that is given to
     * the backend and should also be applied to any user name within
     * the backend before returning it somewhere.
     *
     * This should be used to enforce username restrictions.
     *
     * @param string $user username
     * @return string the cleaned username
     */
    public function cleanUser($user)
    {
        return $user;
    }

    /**
     * Sanitize a given groupname
     *
     * This function is applied to any groupname that is given to
     * the backend and should also be applied to any groupname within
     * the backend before returning it somewhere.
     *
     * This should be used to enforce groupname restrictions.
     *
     * Groupnames are to be passed without a leading '@' here.
     *
     * @param  string $group groupname
     *
     * @return string the cleaned groupname
     */
    public function cleanGroup($group)
    {
        return $group;
    }

    /**
     * Check Session Cache validity [implement only where required/possible]
     *
     * DokuWiki caches user info in the user's session for the timespan defined
     * in $conf['auth_security_timeout'].
     *
     * This makes sure slow authentication backends do not slow down DokuWiki.
     * This also means that changes to the user database will not be reflected
     * on currently logged in users.
     *
     * To accommodate for this, the user manager plugin will touch a reference
     * file whenever a change is submitted. This function compares the filetime
     * of this reference file with the time stored in the session.
     *
     * This reference file mechanism does not reflect changes done directly in
     * the backend's database through other means than the user manager plugin.
     *
     * Fast backends might want to return always false, to force rechecks on
     * each page load. Others might want to use their own checking here. If
     * unsure, do not override.
     *
     * @param  string $user - The username
     *
     * @return bool
     */
    //public function useSessionCache($user)
    //{
      // FIXME implement
    //}
}
