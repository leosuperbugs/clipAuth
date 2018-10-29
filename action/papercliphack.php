<?php
/**
 * DokuWiki Plugin papercliphack (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Tongyu Nie <marktnie@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}

define('__CLIP__EDIT__', 0);
define('__CLIP__COMMENT__', 1);
define('__CLIP__SETTING__', 2);
define('__NAVBARSETTING__', array('最近编辑', '评论/回复', '设置'));
define('__HREFSETTING__', array('editlog', 'comment', 'setting'));

class action_plugin_clipauth_papercliphack extends DokuWiki_Action_Plugin
{
    var $pdo;
    var $settings;
    var $editperpage;
    private $editRecordNum;
    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     *
     * @return void
     */
    public function register(Doku_Event_Handler $controller)
    {
	// paperclip server, getConf() not working on this fucking server
        $this->editperpage = 5;
        require  dirname(__FILE__).'/../settings.php';
        $dsn = "mysql:host=".$this->settings['host'].
            ";dbname=".$this->settings['dbname'].
            ";port=".$this->settings['port'].
            ";charset=".$this->settings['charset'];

        try {
            $this->pdo = new PDO($dsn, $this->settings['username'], $this->settings['password']);
        } catch ( PDOException $e) {
            echo "Datebase connection error";
            exit;
        }

        $controller->register_hook('COMMON_WIKIPAGE_SAVE', 'AFTER', $this, 'handle_common_wikipage_save');
        $controller->register_hook('TPL_CONTENT_DISPLAY', 'BEFORE', $this, 'handle_tpl_content_display');
        $controller->register_hook('MENU_ITEMS_ASSEMBLY', 'AFTER', $this, 'changelink');
//        $controller->register_hook('HTML_REGISTERFORM_OUTPUT', 'AFTER', $this, 'handle_html_registerform_output');
//        $controller->register_hook('HTML_LOGINFORM_OUTPUT', 'AFTER', $this, 'handle_html_loginform_output');
//        $controller->register_hook('HTML_UPDATEPROFILEFORM_OUTPUT', 'AFTER', $this, 'handle_html_updateprofileform_output');
   
    }

    /**
     * [Custom event handler which performs action]
     *
     * Called for event:
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function handle_common_wikipage_save(Doku_Event $event, $param)
    {
        global $INFO;
        $pageid = $event->data['id'];
        $summary = $event->data['summary'];
        $editor = $INFO['userinfo']['name'];
        $sql = 'insert into '.$this->settings['editlog'].' (id, pageid, time, summary, editor)
            values
                (null, :pageid, null, :summary, :editor)';
        try {
            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(':pageid', $pageid);
            $statement->bindValue(':summary', $summary);
            $statement->bindValue(':editor', $editor);
            $result = $statement->execute();
        }
        catch (PDOException $e){
            echo $e->getMessage();
	    exit;
        }
        if ($result === false) {
            echo 'log error';
            exit;
        }


    }

    /**
     * Print a row of edit log unit
     * Author: Tongyu Nie marktnie@gmail.com
     * @param $editData
     *
     */
    private function editUnit($editData, $isFirst) {
        $pageid = $editData['pageid'];
        $needHide = '';
        if ($isFirst === true) $needHide = 'noshow';
        $mainPageName = '';
        $indexForShow = '';
        if ($pageid) {
            $indexArray = explode(':', $pageid);
            $mainPageName = $indexArray[count($indexArray) - 1];
            $indexForShow = array_reverse($indexArray);
            $indexForShow = implode('</span>-<span class="paperclip__link">', $indexForShow);
        }
        $time   = $editData['time'];
        $summary= $editData['summary'];
        $editor = $editData['editor'];

        print "
<div class='paperclip__editlog__unit'>
    <hr class='paperclip__editlog__split $needHide'>
    <div class='paperclip__editlog__header'>
        <div class='paperclip__editlog__pageid'>
           $mainPageName 
        </div>
        <div class='paperclip__editlog__time'>
            你最后的编辑时间为 $time .
        </div>
    </div> 
    <p class='paperclip__editlog__sum'>
        编辑摘要： $summary
    </p>
    <div class='paperclip__editlog__footer'>
        <a class='paperclip__editlog__link' href='/doku.php?id=$pageid&show=edit'>继续编辑</a>
        <a class='paperclip__editlog__link' href='/doku.php?id=$pageid'>查看当前条目</a>
        <div class='paperclip__editlog__index'>
            索引：<span class='paperclip__link'>$indexForShow</span>
        </div>
    </div>
</div> 
        ";
    }
    /**
     * Count the total edit record number, not page number.
     * number
     */
    private function countEditForName($username) {
        if (isset($this->editRecordNum)) return $this->editRecordNum;

        $sql = 'select count(*) from '.$this->settings['editlog']. ' where editor = '."\"".$username."\"";
        $result = $this->pdo->query($sql);

        if ($result === false) return 1;
        $num = $result->fetchColumn();
        return $num;
    }

    /**
     * Return legal pagenum, turn the out-range ones to in-range
     */
    private function checkPagenum($pagenum, $count, $username) {
        global $conf;
        if (!isset($pagenum)) return 1;

        $num = $count;
        $maxnum = ceil($num / $this->editperpage);
        if ($pagenum > $maxnum) {
            $pagenum = $maxnum;
        } elseif ($pagenum < 1) {
            $pagenum = 1;
        }

        return $pagenum;
    }

    /**
     * @param $content Which part of navbar is printed
     * @param $highlight Which part of navbar is selected
     */
    private function printNavbar ($content, $highlight) {
        $isSelected = '';
        $navbarContent = __NAVBARSETTING__[$content];
        $hrefContent = __HREFSETTING__[$content];

        if ($content === $highlight) {
            $isSelected = 'paperclip__selfinfo__selectednav';
        }
        print "<div class='paperclip__selfinfo__navbar $isSelected'>
<a href='/doku.php?show=$hrefContent&page=1&id=start'>$navbarContent</a></div>";
    }

    /**
     * Print the content of header part, switching according to $highlight
     * @param $highlight
     */
    private function printHeader($highlight) {
        if ($highlight >= __CLIP__EDIT__ && $highlight <= __CLIP__SETTING__) {
            print "<div class='paperclip__selfinfo__header'>";
            $this->printNavbar(__CLIP__EDIT__, $highlight);
            $this->printNavbar(__CLIP__COMMENT__, $highlight);
            $this->printNavbar(__CLIP__SETTING__, $highlight);
            print "</div>";
        }
    }

    /**
     * @param $pagenum
     *
     * Print the content of edit log according to the number of page
     */
    private function editlog($pagenum) {
        // Out put the header part
        $this->printHeader(__CLIP__EDIT__);
        // Fix me. Here we out put the content of edit history
        global $USERINFO, $conf;
        $username = $USERINFO['name'];
        $count = $this->countEditForName($username);
        $pagenum = $this->checkPagenum($pagenum, $count, $username);
        $offset = ($pagenum - 1) * $this->editperpage;
        $countPage = $this->editperpage;

        $sql = 'select * from '.$this->settings['editlog'].' where editor=:editor order by id DESC limit :offset ,:count';
        try {
            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(':editor', $username);
            $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
            $statement->bindValue(':count', $countPage, PDO::PARAM_INT);
            $r = $statement->execute();
        }
        catch (PDOException $e){
            echo $e->getMessage();
	    exit;
        }
        $isFirst = true;
        while (($result = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            // Processing the result of editlog, generating a row of log
            $this->editUnit($result, $isFirst);
            $isFirst = false;
        }
    }


    private function comment($pagenum) {
        // Fix me. Here we out put the content of comment page
        $this->printHeader(__CLIP__COMMENT__);
    }

    private function setting() {
        // Fix me. Here we out put the content of user setting

    }

    /**
     * Print a new cell in the table
     * @param $page
     */
    private function printOnePagenum ($page) {
        print "<td class='paperclip__pagenum'><a href='/doku.php?show=editlog&page=$page' class='paperclip__pagehref'>$page</a></td>";
    }

    private function printPresentPagenum ($page) {
        print "<td class='paperclip__pagenum__nohref'>$page</td>";
    }

    /**
     * Print the ... in the table
     */
    private function printEllipsis () {
        print "<td class='paperclip__pagenum__nohref'>...</td>";
    }

    /**
     * @param $start
     * @param $end The range includes the end
     */
    private function printPageFromRange($start, $end) {
        if ($start > $end) return;

        for ($i = $start; $i <= $end; $i++) {
            $this->printOnePagenum($i);
        }
    }

    /**
     * Print out a table to show the page number like:
     * 1 ... 4 5 6 7 8 ... 100
     *
     * @param $sum total page number
     * @param $page
     */
    private function paginationNumber($sum, $page) {
        // check some exception
        global $USERINFO, $conf;
        $username = $USERINFO['name'];
        $page = $this->checkPagenum($page, $this->countEditForName($username), '');
        if ($sum <= 0 || $page <= 0 || $sum < $page) {
            echo 'wrong pagenumber passed to pagination';
        }
        else {
            print "<div class='paperclip__pagenav'>";
            print "<table id='paperclip__pagetable'>";
            print "<tr>";
            //print left part
            $left = $page - 1; // the left part of pagination list
            if ($left <= 4) {
                if ($left > 0) {
                    $this->printPageFromRange(1, $left);
                }
            } else {
                // The table should look like:
                // 1 ... 4 5
                $this->printOnePagenum(1);
                $this->printEllipsis();
                $this->printPageFromRange($left - 1, $left);
            }
            //print centre part
            $this->printPresentPagenum($page);
            //print right part
            $right = $sum - $page;
            if ($right <= 4) {
                if ($right > 0) {
                    $this->printPageFromRange($page + 1, $sum);
                }
            } else {
                // The table should look like:
                // 7 8 ... 10
                $this->printPageFromRange($page + 1, $page + 2);
                $this->printEllipsis();
                $this->printOnePagenum($sum);
            }

            print "</tr></table></div>";
        }
    }
    public function handle_tpl_content_display(Doku_Event $event, $param)
    {
        global $_GET, $ACT;
        $show = $_GET['show'];
        if ($ACT === 'profile' || $show === 'editlog') {
            $event->data = '';
            global $USERINFO, $conf;
            $username = $USERINFO['name'];
            // A little bit wired here, need fix
            $editRecordCount = $this->countEditForName($username);
            $sum = ceil($editRecordCount / $this->editperpage);

            print "<div class='paperclip__selfinfo'>";
            if ($show === 'editlog') {
                $pagenum = $_GET['page'];
                $this->editlog($pagenum);
                $this->paginationNumber($sum, $pagenum);
            } else {
                $this->editlog(1);
                $this->paginationNumber($sum, 1);
//                $this->paginationNumber(10, 6);
            }
            print "</div>";

        } else if ($show === 'comment') {
            print "<div class='paperclip__selfinfo'>";
            $pagenum = $_GET['page'];
            $this->comment($pagenum);
            print "</div>";
            exit;
        } else if ($show === 'setting') {
            print "<div class='paperclip__selfinfo'>";
            echo 'setting';
            print "</div>";
            exit;
        }
    }
    public function handle_html_registerform_output(Doku_Event $event, $param)
    {
    }
    public function handle_html_loginform_output(Doku_Event $event, $param)
    {
    }
    public function handle_html_updateprofileform_output(Doku_Event $event, $param)
    {
    }
    public function changeLink(Doku_Event &$event, $param)
    {
        if($event->data['view'] != 'user') return;
    }

}

