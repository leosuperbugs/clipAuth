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

// for personal center
// show = xxx
define('__CLIP__EDIT__', 0);
define('__CLIP__COMMENT__', 1);
define('__CLIP__SETTING__', 2);
// for search result
// result = xxx
define('__CLIP__TITLE__', 3);
define('__CLIP__FULLTEXT__', 4);

define('__NAVBARSETTING__', array('最近编辑', '评论/回复', '设置', '条目名称搜索', '条目内容搜索'));
define('__HREFSETTING__', array('editlog', 'comment', 'setting', 'title', 'fulltext'));
// The position of the metadata in the register form
define('__REGISTER_ORDER__', array(
    'invitationCode'=> 2,
    'username' => 6,
    'email' => 9,
    'pass' => 12,
    'passchk' => 15,
    'fullname' => 18
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
        $this->editperpage = $this->getConf('editperpage');
        $this->replyperpage = $this->getConf('commentperpage');
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
        $controller->register_hook('TPL_ACT_RENDER', 'AFTER', $this, 'handle_parser_metadata_render');
//        $controller->register_hook('MENU_ITEMS_ASSEMBLY', 'AFTER', $this, 'handle_menu_items_assembly');
//        $controller->register_hook('HTML_REGISTERFORM_OUTPUT', 'AFTER', $this, 'handle_html_registerform_output');
//        $controller->register_hook('HTML_LOGINFORM_OUTPUT', 'AFTER', $this, 'handle_html_loginform_output');
//        $controller->register_hook('HTML_UPDATEPROFILEFORM_OUTPUT', 'AFTER', $this, 'handle_html_updateprofileform_output');
   
    }

    private function showEditorNames() {
        global $ACT, $ID;
        if ($ACT === 'show' && isset($ID)) {
            return true;
        } else {
            return false;
        }
    }

    public function handle_parser_metadata_render(Doku_Event $event, $param) {
        global $ID;
        if ($this->showEditorNames()) {
            // Append the author history here
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
                $editorTitle = $this->getConf('editors');

                echo "<h1>$editorTitle<div class='paperclip__editbtn__wrapper'><span>$count 人</span></div></h1>";
                echo "<p>$editorList</p>";
            }
            catch (PDOException $e) {
                echo $e->getMessage();
            }
        }
    }
    /**
     * @param Doku_Event $event
     * @param $param
     */
    public function modifyRegisterForm(Doku_Event $event, $param)
    {
        $registerFormContent =& $event->data->_content;
        $this->insertRegisterElements($registerFormContent);
    }

    /**
     * @param $registerFormContent
     *
     * Modify the metadata in register form
     */
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
        $replier= $replyData['username'];
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
    private function printNavbar ($content, $highlight, $href) {
        $isSelected = '';
        $navbarContent = __NAVBARSETTING__[$content];

        if ($content === $highlight) {
            $isSelected = 'paperclip__selfinfo__selectednav';
        }
        print "<div class='paperclip__selfinfo__navbar $isSelected'>
<a href= '$href'>$navbarContent</a></div>";
    }

    /**
     * Print the content of header part, switching according to $highlight
     * @param $highlight
     */
    private function printSelfinfoHeader($highlight) {
        if ($highlight >= __CLIP__EDIT__ && $highlight <= __CLIP__SETTING__) {
            $hrefSetting = __HREFSETTING__;
            print "<div class='paperclip__selfinfo__header'>";
            $this->printNavbar(__CLIP__EDIT__, $highlight, "/doku.php?show={$hrefSetting[__CLIP__EDIT__]}&page=1&id=start");
            $this->printNavbar(__CLIP__COMMENT__, $highlight, "/doku.php?show={$hrefSetting[__CLIP__COMMENT__]}&page=1&id=start\"");
            $this->printNavbar(__CLIP__SETTING__, $highlight, "/doku.php?show={$hrefSetting[__CLIP__SETTING__]}&page=1&id=start\"");

            print "</div>";
        }
    }

    /**
     * Print the head nav bar of search result
     *
     * @param $highlight
     */
    private function printSearchHeader($highlight) {
        if ($highlight >= __CLIP__TITLE__ && $highlight <= __CLIP__FULLTEXT__) {
            global $QUERY;
            $hrefSetting = __HREFSETTING__;
            print "<div class='paperclip__selfinfo__header'>";
            $this->printNavbar(__CLIP__TITLE__, $highlight, "/doku.php?q=$QUERY&show={$hrefSetting[__CLIP__TITLE__]}&page=1");
            $this->printNavbar(__CLIP__FULLTEXT__, $highlight, "/doku.php?q=$QUERY&show={$hrefSetting[__CLIP__FULLTEXT__]}&page=1");
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
        $this->printSelfinfoHeader(__CLIP__EDIT__);
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
        $this->printSelfinfoHeader(__CLIP__COMMENT__);

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
        $this->printSelfinfoHeader(__CLIP__SETTING__);

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
    private function printOnePagenum ($page, $content, $additionalParam = []) {
        $addiQuery = '';
        foreach ($additionalParam as $param => $value) {
            $addiQuery .= "&$param=$value";
        }
        print "<td class='paperclip__pagenum'><a href='/doku.php?show=$content&page=$page$addiQuery' class='paperclip__pagehref'>$page</a></td>";
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
    private function printPageFromRange($start, $end, $content, $additionalParam = []) {
        if ($start > $end) return;

        for ($i = $start; $i <= $end; $i++) {
            $this->printOnePagenum($i, $content, $additionalParam);
        }
    }

    /**
     * Print out a table to show the page number like:
     * 1 ... 4 5 6 7 8 ... 100
     *
     * @param $sum total page number
     * @param $page
     * @param $content
     */
    private function paginationNumber($sum, $page, $content, $additionalParam = []) {
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
                    $this->printPageFromRange(1, $left, $content, $additionalParam);
                }
            } else {
                // The table should look like:
                // 1 ... 4 5
                $this->printOnePagenum(1, $content, $additionalParam);
                $this->printEllipsis();
                $this->printPageFromRange($left - 1, $left, $content, $additionalParam);
            }
            //print centre part
            $this->printPresentPagenum($page);
            //print right part
            $right = $sum - $page;
            if ($right <= 4) {
                if ($right > 0) {
                    $this->printPageFromRange($page + 1, $sum, $content, $additionalParam);
                }
            } else {
                // The table should look like:
                // 7 8 ... 10
                $this->printPageFromRange($page + 1, $page + 2, $content, $additionalParam);
                $this->printEllipsis();
                $this->printOnePagenum($sum, $content, $additionalParam);
            }
            // print the input and jump button
            print "
            <td class='paperclip__pagejump'>
            <form action='/doku.php' method='get'>
            <input type='text' class='paperclip__pagejump__input' name='page' required>
            <input type='hidden' name='show' value=$content>";
            foreach ($additionalParam as $param => $value) {
                print "<input type='hidden' name=$param value=$value>";
            }
            print "<input type='submit' class='paperclip__pagejump__button' value='跳转'>
            </form>
            </td>";
            print "</tr></table></div>";
        }
    }


    /**
     *
     * Display the content of each cell in search result
     *
     * @param $id
     * @param $countInText
     * @param $highlight
     * @param $html
     */
    private function showMeta($id, $countInText, $highlight, &$html) {
        global $lang;

        // Comment part of search title result and search fulltext result
        $goldspanPrefix = "<span class='paperclip__link'>";
        $spanSuffix = "</span>";

        $html .= "<div class='paperclip__qtitle'>";
        $mtime = filemtime(wikiFN($id));
        $time = date_iso8601($mtime);
        $wikiIndex = $id;
        $wikiIndex = explode(':', $wikiIndex);
        $pageTitle = array_pop($wikiIndex);
        $wikiIndex = implode("$spanSuffix-$goldspanPrefix", $wikiIndex);

        $wikiIndex = $goldspanPrefix.$wikiIndex.$spanSuffix;

        // Title
        $html .= "<a href='/doku.php?id=$id'>$pageTitle</a>";
        // Last edittion time and index
        $html .= "<div class='paperclip__searchmeta'>";
        // Last modification time
        if ($countInText > 0) {
            $html .= "<span>{$countInText}{$this->getLang('matches')}</span>";
        }
        $html .= "<span>{$lang['lastmod']}{$time}</span>";
        $html .= "<span>{$this->getLang('index')}{$wikiIndex}</span>";
        $html .= "</div>";

        // Snippet
        $resultBody = ft_snippet($id, $highlight);
        $html .= "<p>{$resultBody}{$this->getLang('ellipsis')}</p>";
        $html .= "</div>";

    }

    /**
     *
     * Do the pagination for the search results
     *
     * @param $fullTextResults
     * @return array
     */
    private function cutResultInPages($fullTextResults) {
        global $_GET;
        $pagenum = $_GET['page'];

        // pagination
        $counter = count($fullTextResults);
        $pagenum = $this->checkPagenum($pagenum, $counter, "");
        // some vars to make my life easier
        $editperpage = $this->editperpage;
        $resultLeft = $counter - ($pagenum - 1) * $editperpage;

        $fullTextResults = array_slice($fullTextResults, ($pagenum - 1) * $editperpage, $editperpage < $resultLeft ? $editperpage : $resultLeft );

        return $fullTextResults;
    }

    /**
     * Display the content of page title search result
     *
     * @param $pageLookupResults
     * @param $highlight
     */
    private function showSearchOfPageTitle($pageLookupResults, $highlight)
    {
        // May be confusing here,
        // $highlight here is used to mark the highlighted part of search result
        // Not to indicate the highlighted nav bar

        global $QUERY;
        $counter = count($pageLookupResults);
        $pagenum = $_GET['page'];

        $pageLookupResults = $this->cutResultInPages($pageLookupResults);

        $this->printSearchHeader(__CLIP__TITLE__);

        $html = "";
        $html .= "<div class='paperclip__qresult'>";
        $html .= "<p class='paperclip__counter'>{$this->getLang('countPrefix')}{$counter}{$this->getLang('countSuffix')}</p>";

        foreach ($pageLookupResults as $id => $title) {
            $this->showMeta($id, 0, $highlight, $html);
        }

        $html .= "</div>";
        echo $html;

        $sum = ceil($counter / $this->editperpage);
        $this->paginationNumber($sum, $pagenum, 'title', array('q' => $QUERY));

    }


    /**
     *
     * Display the content of page fulltext search result
     *
     * @param $fullTextResults
     * @param $highlight
     */
    private function showSearchOfFullText($fullTextResults, $highlight)
    {
        // May be confusing here,
        // $highlight here is used to mark the highlighted part of search result
        // Not to indicate the highlighted nav bar

        global $QUERY, $_GET;
        $pagenum = $_GET['page'];
        $counter = count($fullTextResults);

        $fullTextResults = $this->cutResultInPages($fullTextResults);

        $this->printSearchHeader(__CLIP__FULLTEXT__);

        $html = "";
        $html .= "<div class='paperclip__qresult'>";
        $html .= "<p class='paperclip__counter'>{$this->getLang('countPrefix')}{$counter}{$this->getLang('countSuffix')}</p>";

        foreach ($fullTextResults as $id => $countInText) {
            $this->showMeta($id, $countInText, $highlight, $html);
        }

        $html .= "</div>";
        echo $html;

        $sum = ceil($counter / $this->editperpage);
        $this->paginationNumber($sum, $pagenum, 'fulltext', array('q' => $QUERY));
    }

    /**
     * Show the search result in two sections
     *
     */
    private function showSearchResult() {
        // Display the search result
        global $_GET, $INPUT, $QUERY, $ACT;
        $show = $_GET['show'];

        $after = $INPUT->str('min');
        $before = $INPUT->str('max');
        if ($show === 'title' || $ACT === 'search') {
            // Display the result of title searching
            $pageLookupResults = ft_pageLookup($QUERY, true, useHeading('navigation'), $after, $before);
            $this->showSearchOfPageTitle($pageLookupResults, array());
        }
        elseif ($show === 'fulltext') {
            // Display the result of fulltext searching
            $highlight = array();
            $fullTextResults = ft_pageSearch($QUERY, $highlight, $INPUT->str('srt'), $after, $before);
            $this->showSearchOfFullText($fullTextResults, $highlight);
        }
        exit;
    }


    /**
     *
     * Dispatching inside this plugin
     * Most of them are for customization
     *
     * @param Doku_Event $event
     * @param $param
     */
    public function handle_tpl_content_display(Doku_Event $event, $param)
    {
        // Dispatch the customized behavior based on _GET
        global $_GET, $ACT;
        $show = $_GET['show'];
        global $USERINFO, $conf, $QUERY;
        $username = $USERINFO['name'];
        $pagenum = $_GET['page'];
        // For search result

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
        } else if (isset($QUERY)) {
            $this->showSearchResult();
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

