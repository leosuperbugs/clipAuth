<?php
namespace dokuwiki\paperclip;

use Doctrine\DBAL\Driver\PDOException;
use PDO;

/**
 * Created by PhpStorm.
 * User: leo
 * Date: 2018/12/12
 * Time: 7:17 PM
 */

class paperclipDAO
{
    private $settings;
    private $pdo;

    public function __construct()
    {
        require dirname(__FILE__).'/settings.php';

        $dsn = "mysql:host=".$this->settings['host'].
            ";dbname=".$this->settings['dbname'].
            ";port=".$this->settings['port'].
            ";charset=".$this->settings['charset'];

        try {
            $this->pdo = new PDO($dsn, $this->settings['username'], $this->settings['password']);
        } catch ( PDOException $e) {
            echo $e->getMessage();
            exit;
        }
    }

    /**
     * Add record of mute execution
     *
     * @param $userid
     * @param $mutedays
     * @param $prevIdentity
     * @param $operator
     * @return bool
     */
    public function addMuteRecord($userid, $mutedays, $prevIdentity, $operator) {
        $sql = "insert into {$this->settings['mutelog']}
                  (recordid, id, time, mutedates, identity, operator)
                values
                  (null, :id, null, :mutedays, :prevIdentity, :operator)";
        try {
            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(":id", $userid);
            $statement->bindValue(":mutedays", $mutedays);
            $statement->bindValue(":prevIdentity", $prevIdentity);
            $statement->bindValue(":operator", $operator);
            $result = $statement->execute();

            return $result;
        } catch (\PDOException $e) {
            echo "add mute record error";
            echo $e->getMessage();
        }

    }

    /**
     * Modify table auth_username
     *
     * @param $id
     * @param $username
     * @param $encodedPass
     * @return bool
     */
    public function addAuthUsername($id, $username, $encodedPass) {
        try {
            $sql = "insert into {$this->settings['auth_username']} (id, username, password)
            values 
            (:id, :username, :password)";
            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(':id', $id, PDO::PARAM_INT);
            $statement->bindValue(':username', $username);
            $statement->bindValue(':password', $encodedPass);

            $result = $statement->execute();
            return $result;
        } catch (\PDOException $e) {
            echo 'add auth username';
            echo $e->getMessage();
            return false;
        }
    }

    public function addAuthOAuth($data, $third_party, $id = null) {
        // provide id when binding
        if ($id) {
            // binding
            // should use the original name of the dokuwiki account
            $username = $data['username'];
        } else {
            // not binding
            $username = $data['open_id'];
            $id = $this->getUserID($data['open_id']);
        }
        $openid = $data['open_id'];
        $sql = "insert into {$this->settings['auth_oauth']} (
                    id, username, third_party, credential, 
                    refresh_token, union_id, open_id, create_time, refresh_time)
                values (
                    :id, :username, :third_party, :accessToken, 
                    :refreshToken, :union_id, :open_id, null, null)";

        $statement = $this->pdo->prepare($sql);

        $statement->bindValue(":id", $id, PDO::PARAM_INT);
        $statement->bindValue(":username", $username);
        $statement->bindValue(":third_party", $third_party);
        $statement->bindValue(":accessToken", $data['accessToken']);
        $statement->bindValue(":refreshToken", $data['refreshToken']);
        $statement->bindValue(":union_id", $data['union_id']);
        $statement->bindValue(":open_id", $openid);

        $result = $statement->execute();

        return $result;
    }

    /**
     * Get record from table auth_oauth
     * @param $third_party
     * @param $openid
     * @return mixed
     */
    public function getOAuthUserByOpenid($third_party, $openid) {
        try {
            $sql = "select username from {$this->settings['auth_oauth']} where third_party=:third_party and open_id=:openid";
            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(':third_party', $third_party);
            $statement->bindValue(':openid', $openid);
            $statement->execute();

            $result = $statement->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                return $result['username'];
            } else {
                return false;
            }
        } catch (\PDOException $e) {
            echo 'get oauth user by openid';
            echo $e->getMessage();
            return false;
        }
    }

    /**
     * Get record from table auth_oauth using user's id
     *
     * @param $third_party
     * @param $id
     * @return bool
     */
    public function getOAuthUserById($third_party, $id) {
        try {
            $sql = "select username from {$this->settings['auth_oauth']} where third_party=:third_party and id=:id";
            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(':third_party', $third_party);
            $statement->bindValue(':id', $id, PDO::PARAM_INT);
            $statement->execute();

            $result = $statement->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                return $result['username'];
            } else {
                return false;
            }

        } catch (\PDOException $e) {
            echo 'get oauth user by id';
            echo $e->getMessage();
            return false;
        }
    }

    /**
     * Only add user to table userinfo (user or users2 in test env)
     * Used with OAuth
     *
     * @param $user
     * @param $pass
     * @param $name
     * @param $mail
     * @param $grps
     * @param $verficationCode
     * @return bool
     */
    public function addUserCore($user, $pass, $name, $mail, $grps, $verficationCode) {
        try {
            $sql = "insert into ".$this->settings['usersinfo'].
                " (id, username, realname, mailaddr, identity, verifycode, password)
            values
                (null, :user, :name, :mail, :grps, :vc, null)";
            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(':user', $user);
            $statement->bindValue(':name', $name);
            $statement->bindValue(':mail', $mail);
            $statement->bindValue(':grps', $grps);
            $statement->bindValue(':vc', $verficationCode);

            $result = $statement->execute();
            return $result;
        } catch (\PDOException $e) {
            echo 'addUser';
            echo $e->getMessage();
            return false;
        }
    }
    /**
     * Add user information to database
     * Username login only
     *
     * @param $user
     * @param $pass
     * @param $name
     * @param $mail
     * @param $grps
     * @param $verficationCode
     * @return bool
     */
    public function addUser($user, $pass, $name, $mail, $grps, $verficationCode) {
        try {
            // create the user in database
//             "(id, username, password, realname, mailaddr, identity, verifycode)
//            (null, :user, :pass, :name, :mail, :grps, :vc)";
            $result = $this->addUserCore($user, $pass, $name, $mail, $grps, $verficationCode);

            // Add user password into auth table
            $id = $this->getUserID($user);
            $addAuthResult = $this->addAuthUsername($id, $user, $pass);

            return ($result && $addAuthResult);

        } catch (\PDOException $e) {
            echo 'addUser';
            echo $e->getMessage();
            return false;
        }
    }

    /**
     * Save users' edit log into DB
     *
     * @param $pageid   the name of page
     * @param $summary  editing sum
     * @param $editor   username
     * @return bool
     */
    public function insertEditlog($pageid, $summary, $editor) {
        $sql = 'insert into '.$this->settings['editlog'].' (id, pageid, time, summary, editor)
            values
                (null, :pageid, null, :summary, :editor)';
        try {
            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(':pageid', $pageid);
            $statement->bindValue(':summary', $summary);
            $statement->bindValue(':editor', $editor);
            $result = $statement->execute();

            return $result;
        }
        catch (PDOException $e){
            echo $e->getMessage();
            return false;
        }
    }

    /**
     * Check the invitation code
     * Return the boolean result
     *
     * @param $invitation
     * @return bool|mixed
     */
    public function checkInvtCode($invitation) {
        try {
            $sql = 'select * from '.$this->settings['invitationCode'].' where invitationCode = :code';
            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(':code', $invitation);
            $statement->execute();
            $result = $statement->fetch(PDO::FETCH_ASSOC);

            return $result;
        } catch (\PDOException $e) {
            echo $invitation;
            echo $e->getMessage();
            return false;
        }
    }

    /**
     * Get password from auth_username
     *
     * @param $user
     * @return bool
     */
    public function getUserPassword($user) {
        try {
            $sql = "select password from {$this->settings['auth_username']} where username=:user";
            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(':username', $user);
            $statement->execute();

            $result = $statement->fetch(PDO::FETCH_ASSOC);

            if ($result == false) {
                return false;
            }

            return $result['password'];
        } catch (\PDOException $e) {
            echo 'get auth username';
            echo $e->getMessage();
            return false;
        }
    }

    /**
     * For user registration
     *
     * @param $user
     * @return mixed
     */
    private function getUserID($user) {
        $sql = "select id from {$this->settings['usersinfo']} where username=:user";
        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':user', $user);
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result['id'];

    }

    /**
     * Only select data from userinfo (users)
     * @param $user
     * @return array|bool
     */
    public function getUserDataCore($user) {
        $userinfo = $this->settings['usersinfo'];

        $sql = "select 
                id,
                username,
                realname,
                mailaddr,
                identity
                from $userinfo 
                where username = :username";

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':username', $user);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
//        $result['password'] = $this->getUserPassword($user);

        if ($result == false) {
            return false;
        }

        $userinfo = $this->transferResult($result);
        return $userinfo;
    }

    /**
     * Get user info from username
     *
     * @param $user
     * @return bool
     */
    public function getUserData($user, $isPassNeeded=true) {
        if ($isPassNeeded) {
            $userinfo = $this->settings['usersinfo'];
            $authUsername = $this->settings['auth_username'];

            $sql = "select 
                $userinfo.id,
                $userinfo.username,
                $userinfo.realname,
                $userinfo.mailaddr,
                $authUsername.password,
                $userinfo.identity
                from $userinfo 
                
                inner join $authUsername on $userinfo.id = $authUsername.id
                where $userinfo.username = :username";

            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(':username', $user);
            $statement->execute();

            $result = $statement->fetch(PDO::FETCH_ASSOC);
//        $result['password'] = $this->getUserPassword($user);

            if ($result == false) {
                return false;
            }

            $userinfo = $this->transferResult($result);
            $userinfo['pass'] = $result['password'];

            return $userinfo;
        } else {
            return $this->getUserDataCore($user);
        }
    }

    /**
     * Core function to get userinfo
     * @param $email
     * @return array|bool
     */
    public function getUserDataByEmailCore($email) {
        $userinfo = $this->settings['usersinfo'];

        $sql = "select 
                id,
                username,
                realname,
                mailaddr,
                identity
                from $userinfo 
                where mailaddr = :email";

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':email', $email);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        if ($result == false) {
            return false;
        }

        $userinfo = $this->transferResult($result);
        return $userinfo;
    }

    /**
     * Get user info from email address
     * @param $email
     * @return bool
     */
    public function getUserDataByEmail($email) {
        $userinfo = $this->settings['usersinfo'];
        $authUsername = $this->settings['auth_username'];

        $sql = "select 
                $userinfo.id,
                $userinfo.username,
                $userinfo.realname,
                $userinfo.mailaddr,
                $authUsername.password,
                $userinfo.identity
                from $userinfo 
                inner join $authUsername on $userinfo.id = $authUsername.id
                where $userinfo.mailaddr = :email";

//        $sql = 'select * from '.$this->settings['usersinfo'].' where mailaddr = :email';
        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':email', $email);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        if ($result == false) {
            return false;
        }

        $name = $result['username'];
        $userinfo = $this->transferResult($result);
        $userinfo['user'] = $name;
        $userinfo['pass'] = $result['password'];

        return $userinfo;
    }

    /**
     * Get the number of people who have contributed to a page
     *
     * Pass the result of editors' names by $editorList
     *
     * @param $editorList
     * @return int
     */
    public function getEditorNames(&$editorList) {
        global $ID;

        $sql = 'select distinct editor from '.$this->settings['editlog'].' where pageid = :pageid group by editor order by max(time) desc';

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(':pageid', $ID);
            $statement->execute();
            $editors = array();
            $count = 0;

            while (($result = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                array_push($editors, $result['editor']);
                $count += 1;
            }
            $editorList = implode(', ', $editors);
            return $count;
        }
        catch (PDOException $e) {
            echo $e->getMessage();
            return 0;
        }
    }

    /**
     * Get user based on conditions
     * @param $conditions
     * @return bool|\PDOStatement
     */
    public function getUsers($conditions) {
        if (count($conditions) > 0) {
            $condArr = implode(' OR ', $conditions);
            $sql = 'select * from '. $this->settings['usersinfo'] . " where ". $condArr;
        } else {
            $sql = 'select * from '. $this->settings['usersinfo'];
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute();

        return $statement;
    }

    /**
     * ?? Is there a better way?
     * @param $cond
     * @return string
     */
    private function conditionsToString($cond) {
        $conditions = array();
        foreach ($cond as $column => $value) {
            $condition = $column . ' = ' ."\"".$value."\" ";
            array_push($conditions, $condition);
        }
        return implode(' AND ', $conditions);
    }

    /**
     * Count the number of result using conditions in $cond
     *
     * @param $cond
     * @param $tablename
     * @return int|mixed count result
     */
    public function countRow($cond, $tablename) {
        if ($cond) {
            $cond = $this->conditionsToString($cond);
        }
        try {
            $sql = 'select count(*) from '.$this->settings[$tablename];
            if ($cond) {
                $sql .= " where $cond";
            }
            $result = $this->pdo->query($sql);
        } catch (PDOException $e) {
            echo $e->getMessage();
            return 0;
        }

        if ($result === false) return 0;
        $num = $result->fetchColumn();

        return $num;
    }

    /**
     * Cut from paperclipAuth.php, maybe duplicate from above
     *
     * @param $conditions String
     * @return int|mixed
     */
    public function countUsers($conditions) {
        if (count($conditions) > 0) {
            $condArr = implode(' OR ', $conditions);
            $sql = 'select count(*) from '. $this->settings['usersinfo'] . " where ". $condArr;
        } else {
            $sql = 'select count(*) from '. $this->settings['usersinfo'];
        }
        $result = $this->pdo->query($sql);
        if ($result === false) return 0;
        $num = $result->fetchColumn();

        return $num;
    }

    /**
     * Data access for editlog admin
     *
     * @param $offset
     * @param $countPage
     * @param string $conditions After the where statement
     * @return bool|\PDOStatement
     */
    public function getEditlogWithUserInfo($offset, $countPage, $conditions='') {
        try {
            $editlog = $this->settings['editlog'];
            $users = $this->settings['usersinfo'];
            $sql = "select
                    $editlog.id as editlogid,
                    $editlog.pageid,
                    $editlog.time,
                    $editlog.summary,
                    $editlog.editor,
                    us.realname,
                    us.id as editorid,
                    us.mailaddr,
                    us.identity
            from $editlog inner join $users as us on $editlog.editor = us.username";
            if ($conditions) {
                $sql .= " where $conditions";
            }
            $sql .= " order by $editlog.id DESC limit :offset ,:count";
            $statement = $this->pdo->prepare($sql);
            // Be careful about the data_type next time!
            $statement->bindValue(":offset", $offset, PDO::PARAM_INT);
            $statement->bindValue(":count", $countPage, PDO::PARAM_INT);
            $statement->execute();
            return $statement;
        } catch (PDOException $e) {
            echo $e->getMessage();
            return false;
        }
    }

    public function getCommentWithUserInfo($offset, $countPage, $conditions="") {
        try {
            $comment = $this->settings['comment'];
            $users = $this->settings['usersinfo'];

            $sql = "select
                    com.hash,
                    com.comment as summary,
                    com.time,
                    com.username as editor,
                    com.pageid,
                    us.realname,
                    us.id as userid,
                    us.mailaddr,
                    us.identity
                    from $comment as com inner join $users as us on com.username = us.username";

            if ($conditions) {
                $sql .= " where $conditions";
            }
            $sql .= " order by com.time DESC limit :offset ,:count";

            $statement = $this->pdo->prepare($sql);
            // Be careful about the data_type next time!
            $statement->bindValue(":offset", $offset, PDO::PARAM_INT);
            $statement->bindValue(":count", $countPage, PDO::PARAM_INT);
            $statement->execute();
            return $statement;

        } catch (PDOException $e) {
            echo $e->getMessage();
            return false;
        }
    }
    /**
     * Get the editlog for the users by page
     *
     * @param $username
     * @param $offset
     * @param $countPage
     * @return bool|null|\PDOStatement
     */
    public function getEditlog($username, $offset, $countPage) {
        $sql = "set @editor=:editor;";
        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':editor', $username);
        $statement->execute();

        $sql = 'select * from '.$this->settings['editlog'].' where @editor is null or editor=@editor order by id DESC limit :offset ,:count';
        try {
            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
            $statement->bindValue(':count', $countPage, PDO::PARAM_INT);
            $r = $statement->execute();

            return $statement;
        }
        catch (PDOException $e){
            echo $e->getMessage();
            return null;
        }

    }

    /**
     * Get comments for the user by page
     *
     * @param $username
     * @param $offset
     * @param $countPage
     * @return bool|\PDOStatement
     */
    public function getComment($username, $offset, $countPage) {
        $sql = 'select * from '.$this->settings['comment'].' where parentname = :parentname order by time DESC limit :offset, :count';
        try {
            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(':parentname', $username);
            $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
            $statement->bindValue(':count', $countPage, PDO::PARAM_INT);
            $r = $statement->execute();

            return $statement;
        }
        catch (PDOException $e){
            echo $e->getMessage();
        }
    }

    /**
     * @param $userID
     * @return bool|\PDOStatement
     */
    public function getMuteRecord($userID) {
        try {
            $sql = "select * from {$this->settings['mutelog']} where id=:userID";
            $statement = $this->pdo->prepare($sql);
            $statement->bindValue("userID", $userID);
            $statement->execute();

            return $statement;
        } catch (\PDOException $e) {
            echo 'getMutedRecord';
            echo $e->getMessage();
        }

    }

    /**
     * Set user identity to new Identity
     *
     * @param $id User ID
     * @param $newIdendity
     * @return bool
     */
    public function setIdentity($id, $newIdendity) {
        $sql = "update {$this->settings['usersinfo']} set identity=:identity where id=:id";
        try {
            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(":identity", $newIdendity);
            $statement->bindValue(":id", $id);

            $result = $statement->execute();
            return $result;
        } catch (\PDOException $e) {
            echo $e->getMessage();
        }
    }

    /**
     * Update invitation code table
     * @param $invitation
     * @return bool
     */
    public function setInvtCodeToInvalid($invitation) {
        try {
            // set the invitation code to invalid
            $sql = "update code set isUsed = 1 where invitationCode = :code";
            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(':code', $invitation);
            $result = $statement->execute();
//            return $result;
        } catch (\PDOException $e) {
            echo 'setInvitationCode';
            echo $e->getMessage();
        }

    }

    /**
     * @param $user
     * @param $pass
     * @return bool
     */
//    public function setUserInfoO($user, $pass) {
//        try {
//            $sql = "update ".$this->settings['usersinfo'] ." set password=:pass where username=:user";
//            $statement = $this->pdo->prepare($sql);
//            $statement->bindValue(':pass', $pass);
//            $statement->bindValue(':user', $user);
//            $result = $statement->execute();
//
//            return $result;
//        } catch (\PDOException $e) {
//            echo 'setUserInfo';
//            echo $e->getMessage();
//            return false;
//        }
//
//    }

    private function checkFirstAppendComma(&$sql, &$notFirst) {
        if ($notFirst) {
            $sql .= ' , ';
        } else {
            $notFirst = true;
        }
    }

    /**
     * Set password for table auth_username
     *
     * @param $user
     * @param $pass
     * @return bool
     */
    public function setUsernamePass($user, $pass) {
        try {
            $sql = "update {$this->settings['auth_username']} set password=:password where username=:user";
            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(':user', $user);
            $statement->bindValue(':password', $pass);
            $result = $statement->execute();

            return $result;
        } catch (\PDOException $e) {
            echo 'ser auth username';
            echo $e->getMessage();
            return false;
        }
    }

    public function setUserInfo($user, $changes) {
        $sql = "update ".$this->settings['usersinfo']." set ";
        $notFirst = false;

        // Process the updated content
//        if ($changes['pass']) {
//            $this->checkFirstAppendComma($sql, $notFirst);
//            $sql .= " password=:pass ";
//        }
        if ($changes['mail']) {
            $this->checkFirstAppendComma($sql, $notFirst);
            $sql .= " mailaddr=:mail ";
        }
        if ($changes['name']) {
            $this->checkFirstAppendComma($sql, $notFirst);
            $sql .= " realname=:name ";
        }
        // Can be appended here in the future

        $sql .= " where username=:user";

        try {
            $statement = $this->pdo->prepare($sql);
            // Bind values here
            if ($changes['pass']) {
                // Now password is stored in another table
                $pass = auth_cryptPassword($changes['pass']);
                $this->setUsernamePass($user, $pass);
//                $statement->bindValue(':pass', $pass);
            }
            if ($changes['mail']) {
                $statement->bindValue(':mail', $changes['mail']);
            }
            if ($changes['name']) {
                $statement->bindValue(':name', $changes['name']);
            }
            $statement->bindValue(':user', $user);

            $result = $statement->execute();
            return $result;

        } catch (\PDOException $e) {
            echo 'setUserInfo';
            echo $e->getMessage();
            return false;
        }
    }

    public function setUserIdentity($id, $newIdentity) {
        try {
            $sql = "update ".$this->settings['usersinfo'] ." set identity=:identity where id=:id";
            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(':identity', $newIdentity);
            $statement->bindValue(':id', $id, PDO::PARAM_INT);
            $result = $statement->execute();
            return $result;
        } catch (\PDOException $e) {
            echo 'setUserInfo';
            echo $e->getMessage();
            return false;
        }
    }

    public function setUserGroup($id, $newGroup) {
        try {
            $sql = "update ".$this->settings['usersinfo'] . " set identity=:grps, verifycode=NULL, resetpasscode=NULL where id=:id";

            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(':grps', $newGroup);
            $statement->bindValue(':id', $id, PDO::PARAM_INT);
            $result = $statement->execute();

            return $result;
        } catch (\PDOException $e) {
            echo 'setUserGroup';
            echo $e->getMessage();
            return false;
        }
    }

    /**
     * Return a constant auth table array
     */
    private function getAuthTables() {
        // should be modified if auth tables changed later
        return [
            $this->settings['auth_username'],
            $this->settings['auth_oauth']
        ];
    }

    /**
     * Delete user's data in auth_xxx tables
     *
     * @param $id
     */
    private function deleteAuthData($id) {
        // find all auth table
        $tables = $this->getAuthTables();
        // true if something has been deleted
        // false if nothing is deleted
        $deleteSuccess = false;

        // delete by user id
        foreach ($tables as $table) {
            try {
                $sql = "delete from {$table} where id = :id";
                $statement = $this->pdo->prepare($sql);
                $statement->bindValue(':id', $id);
                $result = $statement->execute();

                $deleteSuccess = $deleteSuccess || $result;
            } catch (\PDOException $e) {
                echo 'delete auth data';
                echo $e->getMessage();
                return false;
            }
        }
        return $deleteSuccess;
    }

    /**
     * Delete User
     *
     * @param $user Username
     * @return bool
     */
    public function deleteUser($user) {
        try {
            $info = $this->getUserDataCore($user);
            $userid = $info['id'];

            $sql = "delete from " . $this->settings['usersinfo'] . " where username = :username";
            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(':username', $user);
            $result = $statement->execute();

            // delete auth tables from this table
            $delAuth = $this->deleteAuthData($userid);

            return $result && $delAuth;
        } catch (\PDOException $e) {
            echo 'deleteUser';
            echo $e->getMessage();
            return false;
        }
    }

    /**
     * Simple transfer of array
     *
     * @param $result
     * @return array
     */
    private  function  transferResult ($result) {
        return [
//            'pass' => $result['password'],
            'name' => $result['realname'],
            'mail' => $result['mailaddr'],
            'id'   => $result['id'],
            'grps' => array_filter(explode(',', $result['identity'])),
            'verifycode' => $result['verifycode'],
            'resetpasscode' => $result['resetpasscode']
        ];
    }


    /**
     * Count the number of EditUserinfo using conditions in $cond
     *
     * @param $cond
     * @return int|mixed count result
     */
    public function countEditUserinfo($con) {
        try {
            $editlog = $this->settings['editlog'];
            $users = $this->settings['usersinfo'];
            $sql = "select
                    $editlog.id from $editlog inner join $users as us on $editlog.editor = us.username";
            if ($con) {
                $sql .= " where $con";
            }
            $statement = $this->pdo->prepare($sql);
            $statement->execute();
            $res = $statement->fetchAll(PDO::FETCH_ASSOC);
            return count($res);
        } catch (PDOException $e) {
            echo $e->getMessage();
            return 0;
        }
    }

    /**
     * Count the number of CommentUserinfo using conditions in $cond
     *
     * @param $cond
     * @return int|mixed count result
     */
    public function countCommentUserinfo($con) {
        try {
            $comment = $this->settings['comment'];
            $users = $this->settings['usersinfo'];
            $sql = "select
                    com.id from $comment as com inner join $users as us on com.username = us.username";
            if ($con) {
                $sql .= " where $con";
            }
            $statement = $this->pdo->prepare($sql);
            $statement->execute();
            $res = $statement->fetchAll(PDO::FETCH_ASSOC);
            return count($res);
        } catch (PDOException $e) {
            echo $e->getMessage();
            return 0;
        }
    }

}
