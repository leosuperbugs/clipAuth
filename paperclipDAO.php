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
        require_once dirname(__FILE__).'/settings.php';

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
            return 1;
        }

        if ($result === false) return 1;
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
//            $sql = "select @editor=:editor;";
//            $statement = $this->pdo->prepare($sql);
//            $statement->bindValue(':editor', $username);
//            $statement->execute();
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
            from $editlog inner join $users on $editlog.editor = $users.realname";

            if ($conditions) {
                $sql .= " where $conditions";
            }
            $sql .= " order by $editlog.id DESC limit :offset ,:count";

            $statement = $this->pdo->prepare($sql);
            // Be careful about the data_type next time!
            $statement->bindValue(":offset", $offset, PDO::PARAM_INT);
            $statement->bindValue(":count", $countPage, PDO::PARAM_INT);
            $statement->execute();
        } catch (PDOException $e) {
            echo $e->getMessage();
            return false;
        }
        return $statement;
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
        $sql = "select @editor=:editor;";
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

}