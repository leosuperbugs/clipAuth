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
     * Add user information to database
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
            $sql = "insert into ".$this->settings['usersinfo'].
                "(id, username, password, realname, mailaddr, identity, verifycode)
            values
            (null, :user, :pass, :name, :mail, :grps, :vc)";
            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(':user', $user);
            $statement->bindValue(':pass', $pass);
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
     * Get user info from username
     *
     * @param $user
     * @return bool
     */
    public function getUserData($user) {
        $sql = 'select * from '.$this->settings['usersinfo'].' where username = :username';
        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':username', $user);
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
        $sql = 'select * from '.$this->settings['usersinfo'].' where mailaddr = :email';
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
                $sql .= ' where '.$cond;
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
                    $users.realname,
                    $users.id as editorid,
                    $users.mailaddr,
                    $users.identity
            from $editlog inner join $users on $editlog.editor = $users.username";

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
                    $comment.hash,
                    $comment.comment as summary,
                    $comment.time,
                    $comment.username as editor,
                    $comment.pageid,
                    $users.realname,
                    $users.id as userid,
                    $users.mailaddr,
                    $users.identity
                    from $comment inner join $users on $comment.username = $users.username";

            if ($conditions) {
                $sql .= " where $conditions";
            }
            $sql .= " order by $comment.time DESC limit :offset ,:count";

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
    public function setUserInfoO($user, $pass) {
        try {
            $sql = "update ".$this->settings['usersinfo'] ." set password=:pass where username=:user";
            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(':pass', $pass);
            $statement->bindValue(':user', $user);
            $result = $statement->execute();

            return $result;
        } catch (\PDOException $e) {
            echo 'setUserInfo';
            echo $e->getMessage();
            return false;
        }

    }

    private function checkFirstAppendComma(&$sql, &$notFirst) {
        if ($notFirst) {
            $sql .= ' , ';
        } else {
            $notFirst = true;
        }
    }

    public function setUserInfo($user, $changes) {
        $sql = "update ".$this->settings['usersinfo']." set ";
        $notFirst = false;

        // Process the updated content
        if ($changes['pass']) {
            $this->checkFirstAppendComma($sql, $notFirst);
            $sql .= " password=:pass ";
        }
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
                $pass = auth_cryptPassword($changes['pass']);
                $statement->bindValue(':pass', $pass);
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
     * Delete User
     *
     * @param $user Username
     * @return bool
     */
    public function deleteUser($user) {
        try {
            $sql = "delete from " . $this->settings['usersinfo'] . " where username = :username";
            $statement = $this->pdo-prepare($sql);
            $statement->bindValue(':username', $user);
            $result = $statement->execute();

            return $result;
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
            'pass' => $result['password'],
            'name' => $result['realname'],
            'mail' => $result['mailaddr'],
            'id'   => $result['id'],
            'grps' => array_filter(explode(',', $result['identity'])),
            'verifycode' => $result['verifycode'],
            'resetpasscode' => $result['resetpasscode']
        ];
    }


}
