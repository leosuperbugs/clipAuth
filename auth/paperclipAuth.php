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

//require_once $dir . "/../vendor/autoload.php";
//use Doctrine\ORM\Tools\Setup;
//use Doctrine\ORM\EntityManager;
$pdo;

class auth_plugin_clipauth_paperclipAuth extends DokuWiki_Auth_Plugin
{

    var $sql; // the database server

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
//        $this->cando['external']    = true; // does the module do external auth checking?
        $this->cando['logout']      = true; // can the user logout again? (eg. not possible with HTTP auth)
        // connect to the MySQL
        $dir = dirname(__FILE__);
        require_once $dir.'/settings.php';

        $dsn = "mysql:host=".$settings['host'].
            ";dbname=".$settings['dbname'].
            ";port=".$settings['port'].
            ";charset=".$settings['charset'];

        global $pdo;
        try {
            $pdo = new PDO($dsn, $settings['username'], $settings['password']);
        } catch ( PDOException $e) {
            echo "Datebase connection error";
            $this->success = false;
            exit;
        }

        $this->success = true;
    }


    /**
     * Log off the current user [ OPTIONAL ]
     */
    // public function logOff()
    // {
    // }

    /**
     * Do all authentication [ OPTIONAL ]
     *
     * @param   string $user   Username
     * @param   string $pass   Cleartext Password
     * @param   bool   $sticky Cookie should not expire
     *
     * @return  bool             true on successful auth
     */
    //public function trustExternal($user, $pass, $sticky = false)
    //{
        /* some example:

        global $USERINFO;
        global $conf;
        $sticky ? $sticky = true : $sticky = false; //sanity check

        // do the checking here

        // set the globals if authed
        $USERINFO['name'] = 'FIXME';
        $USERINFO['mail'] = 'FIXME';
        $USERINFO['grps'] = array('FIXME');
        $_SERVER['REMOTE_USER'] = $user;
        $_SESSION[DOKU_COOKIE]['auth']['user'] = $user;
        $_SESSION[DOKU_COOKIE]['auth']['pass'] = $pass;
        $_SESSION[DOKU_COOKIE]['auth']['info'] = $USERINFO;
        return true;

        */
    //}

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
    public function checkPass($user, $pass)
    {
        // FIXME implement password check
        $userinfo = $this->getUserData($user);
        if ($userinfo !== false) {
            return auth_verifyPassword($pass, $userinfo['pass']);
        } else {
            return false;
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
        // FIXME implement
        global $pdo;
        $sql = 'select * from  users2 where username = :username';
        $statement = $pdo->prepare($sql);
        $statement->bindValue(':username', $user);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        if ($result == false) {
            return false;
        }

        $userinfo = [
            'pass' => $result['password'],
            'name' => $result['username'],
            'mail' => $result['mailaddr'],
            'grps' => array_filter(explode(',', $result['identity']))
        ];

        return $userinfo;
    }

    /**
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
//         FIXME implement
        
        return null;
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
    //public function modifyUser($user, $changes)
    //{
        // FIXME implement
    //    return false;
    //}

    /**
     * Delete one or more users [implement only where required/possible]
     *
     * Set delUser capability when implemented
     *
     * @param   array  $users
     *
     * @return  int    number of users deleted
     */
    //public function deleteUsers($users)
    //{
        // FIXME implement
    //    return false;
    //}

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
    //public function retrieveUsers($start = 0, $limit = 0, $filter = null)
    //{
        // FIXME implement
    //    return array();
    //}

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
    //public function getUserCount($filter = array())
    //{
        // FIXME implement
    //    return 0;
    //}

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
    //public function retrieveGroups($start = 0, $limit = 0)
    //{
        // FIXME implement
    //    return array();
    //}

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

