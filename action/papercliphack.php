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
define('__REGISTER_ORDER__', array(
    'invitationCode'=> 0,
    'username' => 2,
    'email' => 3,
    'pass' => 4,
    'passchk' => 5,
    'fullname' => 6
));


class action_plugin_clipauth_papercliphack extends DokuWiki_Action_Plugin
{
    private $pdo;
    private $settings;
    // Some constants relating to the pagination of personal centre
    private $editperpage;
    private $replyperpage;
    private $editRecordNum;
    private $replyRecordNum;
    // The order in the result of HTML register output form

    private $order;

    public function __construct()
    {
	// paperclip server, getConf() not working on this fucking server
        $this->editperpage = 5;
        $this->replyperpage = 5;
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
    }

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     *
     * @return void
     */
    public function register(Doku_Event_Handler $controller)
    {

        $controller->register_hook('COMMON_WIKIPAGE_SAVE', 'AFTER', $this, 'handle_common_wikipage_save');
        $controller->register_hook('TPL_CONTENT_DISPLAY', 'BEFORE', $this, 'handle_tpl_content_display');
        $controller->register_hook('HTML_REGISTERFORM_OUTPUT', 'BEFORE', $this, 'modifyRegisterForm');
//        $controller->register_hook('MENU_ITEMS_ASSEMBLY', 'AFTER', $this, 'handle_menu_items_assembly');
//        $controller->register_hook('HTML_REGISTERFORM_OUTPUT', 'AFTER', $this, 'handle_html_registerform_output');
//        $controller->register_hook('HTML_LOGINFORM_OUTPUT', 'AFTER', $this, 'handle_html_loginform_output');
//        $controller->register_hook('HTML_UPDATEPROFILEFORM_OUTPUT', 'AFTER', $this, 'handle_html_updateprofileform_output');
   
    }

    public function modifyRegisterForm(Doku_Event $event, $param)
    {
        // Combined with the order of form
        // If the order of form changed, this function must change
        //    'invitationCode'=> 0,
        //    'username' => 2,
        //    'email' => 3,
        //    'pass' => 4,
        //    'passchk' => 5,
        //    'fullname' => 6

        $registerFormContent =& $event->data->_content;
        $this->insertRegisterElements($registerFormContent);

        echo '';
    }

    private function insertRegisterElements(&$registerFormContent)
    {
        // Invitation Code
        $registerFormContent[__REGISTER_ORDER__['invitationCode']]['maxlength'] = $this->getConf('invitationCodeLen');
        $registerFormContent[__REGISTER_ORDER__['invitationCode']]['minlength'] = $this->getConf('invitationCodeLen');
        // Username
        $registerFormContent[__REGISTER_ORDER__['username']]['maxlength'] = $this->getConf('usernameMaxLen');
        // E-mail

        // Password
        $registerFormContent[__REGISTER_ORDER__['pass']]['minlength'] = $this->getConf('passMinLen');
        $registerFormContent[__REGISTER_ORDER__['pass']]['maxlength'] = $this->getConf('passMaxLen');
        // Password Check
        $registerFormContent[__REGISTER_ORDER__['passchk']]['minlength'] = $this->getConf('passMinLen');
        $registerFormContent[__REGISTER_ORDER__['passchk']]['maxlength'] = $this->getConf('passMaxLen');
        // Realname
        $registerFormContent[__REGISTER_ORDER__['fullname']]['maxlength'] = $this->getConf('fullnameMaxLen');


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
        <a class='paperclip__editlog__link' href='/doku.php?id=$pageid&do=edit' target='_blank'>继续编辑</a>
        <a class='paperclip__editlog__link' href='/doku.php?id=$pageid' target='_blank'>查看当前条目</a>
        <div class='paperclip__editlog__index'>
            索引：<span class='paperclip__link'>$indexForShow</span>
        </div>
    </div>
</div> 
        ";
    }

    private function replyUnit($replyData, $isFirst) {
        $pageid = $replyData['pageid'];
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
        $time   = $replyData['time'];
        $comment= $replyData['comment'];
        $replier= $replyData['parentname'];
        $hash   = $replyData['hash'];

        print "
<div class='paperclip__reply__unit'>
    <hr class='paperclip__editlog__split $needHide'>
    <div class='paperclip__reply__header'>
        <div class='paperclip__reply__from'>
            \"$replier\"的回复
        </div>
        <div class='paperclip__editlog__time'>
            $time 
        </div>
    </div>
    <p class='paperclip__editlog__sum'>
        $comment
    </p>
    <div class='paperclip__reply__footer'>
        <a class='paperclip__editlog__link' href='/doku.php?id=$pageid&#comment_$hash' target='_blank'>查看详情</a>
        <a class='paperclip__editlog__link' href='/doku.php?id=$pageid&do=show&comment=reply&cid=$hash#discussion__comment_form' target='_blank'>回复</a>
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
     * Count the total replies
     * @param $username
     * @return int
     */
    private  function countReplyForName($username) {
        if (isset($this->replyRecordNum)) return $this->replyRecordNum;

        $sql = 'select count(*) from '.$this->settings['comment']. ' where parentname ='."\"".$username."\"";
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
        //
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

        if ($statement->rowCount() === 0) {
            echo '<br>您还没有编辑记录<br>';
        }
    }

    private function comment($pagenum) {
        // Fix me. Here we out put the content of comment page
        $this->printHeader(__CLIP__COMMENT__);

        // Print the content of replying comment
        global $USERINFO, $conf;
        $username = $USERINFO['name'];
        $count = $this->countReplyForName($username);
        $pagenum = $this->checkPagenum($pagenum, $count, $username);
        $offset = ($pagenum - 1) * $this->replyperpage;
        $countPage = $this->replyperpage;

        $sql = 'select * from '.$this->settings['comment'].' where parentname = :parentname order by time DESC limit :offset, :count';
        try {
            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(':parentname', $username);
            $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
            $statement->bindValue(':count', $countPage, PDO::PARAM_INT);
            $r = $statement->execute();
        }
        catch (PDOException $e){
            echo $e->getMessage();
        }
        $isFirst = true;

        while (($result = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            // Processing the result of editlog, generating a row of log
            $this->replyUnit($result, $isFirst);
            $isFirst = false;
        }

        if ($statement->rowCount() === 0) {
            echo '<br>您还没有收到回复<br>';
        }



    }

    private function setting() {
        // Fix me. Here we out put the content of user setting
        $this->printHeader(__CLIP__SETTING__);

        global $USERINFO;
        $username = $USERINFO['name'];
        $mail = $USERINFO['mail'];
        print
        "
        <div class='paperclip__settings'>
        <div class='paperclip__settings__title'>个人信息</div>
        <div class='paperclip__settings__info'>用户名：$username&nbsp|&nbsp登录邮箱：$mail</div>
        <a href='/doku.php?do=profile' target='_blank' class='paperclip__settings__update'>前往更改</a>
</div>
        ";
    }

    /**
     * Print a new cell in the table
     * @param $page
     */
    private function printOnePagenum ($page, $content) {
        print "<td class='paperclip__pagenum'><a href='/doku.php?show=$content&page=$page' class='paperclip__pagehref'>$page</a></td>";
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
    private function printPageFromRange($start, $end, $content) {
        if ($start > $end) return;

        for ($i = $start; $i <= $end; $i++) {
            $this->printOnePagenum($i, $content);
        }
    }

    /**
     * Print out a table to show the page number like:
     * 1 ... 4 5 6 7 8 ... 100
     *
     * @param $sum total page number
     * @param $page
     */
    private function paginationNumber($sum, $page, $content) {
        // check some exception
        global $USERINFO, $conf;
        $username = $USERINFO['name'];
        $page = $this->checkPagenum($page, $this->countEditForName($username), '');
        if ($sum <= 0 || $page <= 0 || $sum < $page) {
            echo '';
        }
        else {
            print "<div class='paperclip__pagenav'>";
            print "<table id='paperclip__pagetable'>";
            print "<tr>";
            //print left part
            $left = $page - 1; // the left part of pagination list
            if ($left <= 4) {
                if ($left > 0) {
                    $this->printPageFromRange(1, $left, $content);
                }
            } else {
                // The table should look like:
                // 1 ... 4 5
                $this->printOnePagenum(1, $content);
                $this->printEllipsis();
                $this->printPageFromRange($left - 1, $left, $content);
            }
            //print centre part
            $this->printPresentPagenum($page);
            //print right part
            $right = $sum - $page;
            if ($right <= 4) {
                if ($right > 0) {
                    $this->printPageFromRange($page + 1, $sum, $content);
                }
            } else {
                // The table should look like:
                // 7 8 ... 10
                $this->printPageFromRange($page + 1, $page + 2, $content);
                $this->printEllipsis();
                $this->printOnePagenum($sum, $content);
            }
            // print the input and jump button
            print "
<td class='paperclip__pagejump'>
<form action='/doku.php' method='get'>
<input type='text' class='paperclip__pagejump__input' name='page' required>
<input type='hidden' name='show' value=$content> 
<input type='submit' class='paperclip__pagejump__button' value='跳转'>
</form>
</td>";
            print "</tr></table></div>";
        }
    }



    public function handle_tpl_content_display(Doku_Event $event, $param)
    {
        global $_GET, $ACT;
        $show = $_GET['show'];
        global $USERINFO, $conf;
        $username = $USERINFO['name'];
        $pagenum = $_GET['page'];

        if ($ACT === 'profile' || $show === 'editlog') {
            $event->data = '';
            // A little bit wired here, need fix
            $editRecordCount = $this->countEditForName($username);
            $sum = ceil($editRecordCount / $this->editperpage);

            print "<div class='paperclip__selfinfo'>";
            if ($show === 'editlog') {
                $pagenum = $this->checkPagenum($pagenum, $editRecordCount, $username);
                $this->editlog($pagenum);
                $this->paginationNumber($sum, $pagenum, 'editlog');
            } else {
                $this->editlog(1);
                $this->paginationNumber($sum, 1, 'editlog');
//                $this->paginationNumber(10, 6);
            }
            print "</div>";

        } else if ($show === 'comment') {
            print "<div class='paperclip__selfinfo'>";
            $replyRecordCount = $this->countReplyForName($username);
            $sum = ceil($replyRecordCount / $this->replyperpage);

            $pagenum = $this->checkPagenum($pagenum, $replyRecordCount, $username);
            // out putting
            $this->comment($pagenum);
            $this->paginationNumber($sum, $pagenum, 'comment');
            print "</div>";
            exit;
        } else if ($show === 'setting') {
            print "<div class='paperclip__selfinfo'>";
            $this->setting();
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

