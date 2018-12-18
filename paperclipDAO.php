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
        $cond = $this->conditionsToString($cond);
        try {
            $sql = 'select count(*) from '.$this->settings[$tablename]. ' where '.$cond;
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
     * Get the editlog for the users by page
     *
     * @param $username
     * @param $offset
     * @param $countPage
     * @return bool|null|\PDOStatement
     */
    public function getEditlog($username, $offset, $countPage) {
        $sql = 'select * from '.$this->settings['editlog'].' where editor=:editor order by id DESC limit :offset ,:count';
        try {
            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(':editor', $username);
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



}