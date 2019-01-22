<?php
/**
 * DokuWiki Plugin papercliphack (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Tongyu Nie <marktnie@gmail.com>
 */
include dirname(__FILE__).'/../aliyuncs/aliyun-php-sdk-core/Config.php';
use Green\Request\V20180509 as Green;

require dirname(__FILE__).'/../vendor/autoload.php';

use \dokuwiki\Form\Form;
use \dokuwiki\Ui;
use \dokuwiki\paperclip;
use Caxy\HtmlDiff\HtmlDiff;

include dirname(__FILE__).'/../paperclipDAO.php';


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
define('__CLIP__TITLE__', 3);
define('__CLIP__FULLTEXT__', 4);
// for admin console
define('__CLIP__ALLEDIT__', 5);
define('__CLIP__ALLCOM__', 6);
define('__CLIP__ADMIN__', 7);

define('__NAVBARSETTING__', array('最近编辑', '评论/回复', '设置', '条目名称搜索', '条目内容搜索', '词条更新日志', '用户评论日志', '管理'));
define('__HREFSETTING__', array('editlog', 'comment', 'setting', 'title', 'fulltext', 'alledit', 'allcom', 'admin'));
// The position of the metadata in the register form
define('__REGISTER_ORDER__', array(
    'invitationCode'=> 2,
    'username' => 6,
    'email' => 9,
    'pass' => 12,
    'passchk' => 15,
    'fullname' => 18
));
define('__MUTED__', 'muted');
define('__NUKED__', 'nuked');

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

    // paperclip's own DAO
    private $dao;

    public function __construct()
    {
        $this->editperpage = $this->getConf('editperpage');
        $this->replyperpage = $this->getConf('commentperpage');

        $this->dao = new dokuwiki\paperclip\paperclipDAO();

        require  dirname(__FILE__).'/../settings.php';
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
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     *
     * @return void
     */
    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook(
            'ACTION_ACT_PREPROCESS',
            'BEFORE', $this,
            'handle_action_act_preprocess'
        );
        $controller->register_hook(
            'COMMON_WIKIPAGE_SAVE',
            'AFTER', $this,
            'handle_common_wikipage_save'
        );
        $controller->register_hook(
            'TPL_CONTENT_DISPLAY',
            'BEFORE',
            $this,
            'handle_tpl_content_display'
        );
        $controller->register_hook(
            'HTML_REGISTERFORM_OUTPUT',
            'BEFORE',
            $this,
            'modifyRegisterForm'
        );
        $controller->register_hook(
            'HTML_EDITFORM_OUTPUT',
            'AFTER',
            $this,
            'modifyEditFormAfter'
        );
        $controller->register_hook(
            'HTML_EDITFORM_OUTPUT',
            'BEFORE',
            $this,
            'modifyEditFormBefore'
        );
        $controller->register_hook(
            'TPL_ACT_RENDER',
            'AFTER',
            $this,
            'handle_parser_metadata_render',
            array(),
            -PHP_INT_MAX
        );
        $controller->register_hook(
            'TPL_CONTENT_DISPLAY',
            'BEFORE',
            $this,
            'clearWayForShow'
        );
        $controller->register_hook(
            'AJAX_CALL_UNKNOWN',
            'BEFORE',
            $this,'ajaxHandler'
        );

    }

    public function ajaxHandler(Doku_Event $event, $param)
    {
        if ($_POST['call']==='paperclip') {
            global $INFO;
            global $USERINFO;

            // Check user identity here
            $INFO = pageinfo();
            if(!$INFO['isadmin']) return;

            // Change user identity
            if ($_POST['muteTime'] == '0') {
                $this->dao->setIdentity($_POST['userID'], __NUKED__);
            } else {
                $this->dao->setIdentity($_POST['userID'], __MUTED__);
            }

            // Make mute record
            $this->dao->addMuteRecord($_POST['userID'], $_POST['muteTime'], $_POST['identity'], implode(',',$USERINFO['grps']));

            $event->preventDefault();
            // Still need to mute the

        } elseif ($_POST['call']=='clip_submit') {
            global $_REQUEST;
            $editcontent = $_REQUEST['wikitext'];
            $res = $this->_filter($editcontent);
            if (!$res) {
                echo 'false';
            }else{
                echo 'true';
            }
        }
    }

    public function clearWayForShow(Doku_Event $event, $param)
    {
        global $_GET;
        $show = $_GET['show'];
        if ($show) {
            $event->data = '';
        }
    }

    private function showEditorNames() {
        global $ACT, $ID;
        global $_GET;
        $show = $_GET['show'];

        if ($ACT === 'show' && isset($ID) && !$show) {
            return true;
        } else {
            return false;
        }
    }

    public function handle_parser_metadata_render(Doku_Event $event, $param) {
        global $ID;

        if ($this->showEditorNames()) {
            // Append the author history here
            $editorTitle = $this->getConf('editors');

            $editorList = '';
            $count = $this->dao->getEditorNames($editorList);

            echo "<h1>$editorTitle<div class='paperclip__editbtn__wrapper'><span>$count 人</span></div></h1>";
            echo "<p>$editorList</p>";
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
     * @param Doku_Event $event
     * @param $param
     */
    public function modifyEditFormAfter(Doku_Event& $event, $param)
    {
        print '<noscript>您的浏览器未启用脚本，请启用后重试！</noscript>';
    }

    /**
     * @param Doku_Event $event
     * @param $param
     */
    public function modifyEditFormBefore(Doku_Event& $event, $param)
    {
        $event->data->_hidden['call'] = 'clip_submit';
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
        $editor = $INFO['client'];
        $htmlDiff = new HtmlDiff($event->data['oldContent'], $event->data['newContent']);
        $content = $htmlDiff->build();
        $content = '<?xml version="1.0" encoding="UTF-8"?><div>'.$content.'</div>';

        $dom = new DOMDocument;
        $editSummary = '';
        if ($dom->loadXML($content)) {
            $xpath = new DOMXPath($dom);
            $difftext = $xpath->query('ins |del');

            foreach ($difftext as $wtf) {
                $nodeName = $wtf->nodeName;
                $editSummary .= "<$nodeName>".$wtf->nodeValue."</$nodeName>";
            }
        }
        $result = $this->dao->insertEditlog($pageid, $editSummary, $editor);
        if (!$result) {
            echo 'wikipage_save: failed to add editlog into DB';
        }
    }

    private function processPageID($pageid, &$indexForShow) {
        $indexArray = explode(':', $pageid);
        $mainPageName = $indexArray[count($indexArray) - 1];
        $indexForShow = array_reverse($indexArray);
        $indexForShow = implode('</span>-<span class="paperclip__link">', $indexForShow);
        return $mainPageName;
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
            $mainPageName = $this->processPageID($pageid, $indexForShow);
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
            最后的编辑时间为 $time .
        </div>
    </div>
    <p class='paperclip__editlog__sum'>
        详情： $summary
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
            $mainPageName = $this->processPageID($pageid, $indexForShow);
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

        $num = $this->dao->countRow(array('editor' => $username), $this->settings['editlog']);

        return $num;
    }

    /**
     * Count the total replies
     * @param $username
     * @return int
     */
    private  function countReplyForName($username) {
        if (isset($this->replyRecordNum)) return $this->replyRecordNum;

        $num = $this->dao->countRow(array('parentname' => $username), $this->settings['comment']);

        return $num;
    }

    /**
     * Return legal pagenum, turn the out-range ones to in-range
     */
    private function checkPagenum($pagenum, $count, $username) {

        if (!isset($pagenum)) return 1;

        $maxnum = ceil($count / $this->editperpage);
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
     * Print the head nav bar of admin console
     *
     * @param $highlight
     */
    private function printAdminHeader($highlight) {
        if ($highlight >= __CLIP__ALLEDIT__ && $highlight <= __CLIP__ADMIN__) {
            $hrefSetting = __HREFSETTING__;
            print "<div class='paperclip__selfinfo__header'>";
            $this->printNavbar(__CLIP__ALLEDIT__, $highlight, "/doku.php?show={$hrefSetting[__CLIP__ALLEDIT__]}&page=1");
            $this->printNavbar(__CLIP__ALLCOM__, $highlight, "/doku.php?show={$hrefSetting[__CLIP__ALLCOM__]}&page=1");
            $this->printNavbar(__CLIP__ADMIN__, $highlight, "/doku.php?show={$hrefSetting[__CLIP__ADMIN__]}&page=1");

            print "</div>";

        }
    }

    /**
     * Check and round the limit of query
     *
     * @param $limit Original limit
     * @param $count Total entries count
     * @param $offset
     * @return mixed
     */
    private function roundLimit($limit, $count, $offset) {
        $columnsLeft = $count - $offset;
        return $limit < $columnsLeft ? $limit : $columnsLeft;
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
        global $USERINFO, $conf, $INFO;
        $username = $INFO['client'];
        $count = $this->countEditForName($username);
        $pagenum = $this->checkPagenum($pagenum, $count, $username);
        $offset = ($pagenum - 1) * $this->editperpage;
        $countPage = $this->editperpage;
        $countPage = $this->roundLimit($countPage, $count, $offset);

        $statement = $this->dao->getEditlog($username,$offset,$countPage);
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
        global $USERINFO, $conf, $INFO;
        $username = $INFO['client'];
        $count = $this->countReplyForName($username);
        $pagenum = $this->checkPagenum($pagenum, $count, $username);
        $offset = ($pagenum - 1) * $this->replyperpage;
        $countPage = $this->replyperpage;
        $countPage = $this->roundLimit($countPage, $count, $offset);

        $statement = $this->dao->getComment($username, $offset, $countPage);

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
        $html .= "<a href='/doku.php?id=$id' target='_blank'>$pageTitle</a>";
        // Last edittion time and index
        $html .= "<div class='paperclip__searchmeta'>";
        // Last modification time
        if ($countInText > 0) {
            $html .= "<span>{$countInText}{$this->getLang('matches')}</span>";
        }
        $html .= "<span class='paperclip__lastmod'>{$lang['lastmod']}{$time}</span>";
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
        $pagenum = $this->checkPagenum($pagenum, $counter, '');

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
        $counter = count($fullTextResults);
        $pagenum = $_GET['page'];
        $pagenum = $this->checkPagenum($pagenum, $counter, '');

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
     * Get a form which can be used to adjust/refine the search
     *
     * @param string $query
     *
     * @return string
     */
    protected function getSearchFormHTML($query)
    {
        global $lang, $ID, $INPUT;

        $searchForm = (new Form(['method' => 'get'], true))->addClass('search-results-form');
        $searchForm->setHiddenField('do', 'search');
        $searchForm->setHiddenField('id', $ID);
        $searchForm->setHiddenField('sf', '1');
        if ($INPUT->has('min')) {
            $searchForm->setHiddenField('min', $INPUT->str('min'));
        }
        if ($INPUT->has('max')) {
            $searchForm->setHiddenField('max', $INPUT->str('max'));
        }
        if ($INPUT->has('srt')) {
            $searchForm->setHiddenField('srt', $INPUT->str('srt'));
        }
        $searchForm->addFieldsetOpen()->addClass('search-form');
        $searchForm->addTextInput('q')->val($query)->useInput(false);
        $searchForm->addButton('', $lang['btn_search'])->attr('type', 'submit');

        $searchForm->addFieldsetClose();

        return $searchForm->toHTML();
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

        echo "<div class='paperclip__search'>";
        echo "<div class='paperclip__srchhead'>";
        echo "<div class='paperclip__srchrslt'>{$this->getLang('searchResult')}</div>";
        echo "<div class='paperclip__floatright'>";
        echo $this->getSearchFormHTML($QUERY);
        echo "<p class='paperclip__srchhint'>{$this->getLang('searchHint')}</p></div>";
        echo '</div>';


        if ($show === 'title' || !isset($show)) {
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
        echo '</div>';
    }

    /**
     * Return true if the identity is an admin
     *
     * @param $identity
     * @return bool
     */
    private function checkIdentityIsAdmin($identity) {
        $identities = explode(',', $identity);
        if (in_array('admin', $identities)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Print the first line of user
     * @param $id
     * @param $time
     * @param $userID
     */
    private function printAdminProcess($id, $time, $userID, $identity) {
        global $INFO;
        $isRecordEditorAdmin = $this->checkIdentityIsAdmin($identity);

        print "<div class='paperclip__adminProcess' >
                    <span>{$this->getLang('id')}$id</span>
                    <span>{$this->getLang('time')}$time</span>";
        if($isRecordEditorAdmin) {
            print $this->getLang('cantban');
        } else {
            print "<form  id='$id'>
                        <select name='muteTime'>
                            <option value='1'>禁言1天</option>
                            <option value='7'>禁言7天</option>
                            <option value='30'>禁言30天</option>
                            <option value='0'>拉黑用户</option>
                        </select>
                        <input type='hidden' name='userID' value='$userID'>
                        <input type='hidden' name='call' value='paperclip'>
                        <input type='hidden' name='identity' value='{$INFO['client']}'>

                        <input type='submit' value='{$this->getLang('process')}'>

                    </form>";
                  }
        print "</div>";
    }

    private function printUserInfo($realname, $editorid, $mailaddr, $identity) {
        print "<div class='paperclip__editorInfo'>";
        print "<span>{$this->getLang('editor')}$realname</span>";
        print "<span>{$this->getLang('editorID')}$editorid</span>";
        print "<span>{$this->getLang('mailaddr')}$mailaddr</span>";
        print "</div>";

        print "<div class='paperclip__userState'>";
        print "<span>{$this->getLang('userIdentity')}$identity</span>";
        print "</div>";

    }

    private function adminEditUnit($editData) {
        $id = $editData['id'];
        $time = $editData['time'];

        print "<div class='paperclip__adminEditUnit'>";
        print "<hr class='paperclip__editlog__split'>";
        $this->printAdminProcess($editData['editlogid'], $editData['time'], $editData['editorid'], $editData['identity']);
        $this->printUserInfo($editData['realname'], $editData['editorid'], $editData['mailaddr'], $editData['identity']);
        $this->editUnit($editData, true);


        print "</div>";

    }

    private function adminCommentUnit($commentData) {

        print "<div class='paperclip__adminEditUnit'>";
        print "<hr class='paperclip__editlog__split'>";
        $this->printAdminProcess($commentData['hash'], $commentData['time'], $commentData['userid'], $commentData['identity']);
        $this->printUserInfo($commentData['realname'], $commentData['userid'], $commentData['mailaddr'], $commentData['identity']);
        $this->editUnit($commentData, true);


        print "</div>";
    }




    /**
     * A wrapper of checking if the action is to admin the site
     * !!! NOT FOR IDENTITY!!
     *
     * @param $show
     * @param $ACT
     * @return bool
     */
    private function isAdmin($show, $ACT) {
        return ($show === 'alledit' || $show === 'allcom' || $show === 'admin');
    }


    private function showAdminContent() {
        $show = $_GET['show'];
        $pagenum = $_GET['page'];
        if (!isset($pagenum)) {
            $pagenum = 1;
        }
        global $ACT, $INFO;

        // Need something to check the identity here
        // Need Fix !
        if(!$INFO['isadmin']) return;

        if ($show === 'admin') {
            // Normal
            $admin = new dokuwiki\Ui\Admin();
            $admin->show();
        }
        else {
            echo "<div class='paperclip__admin'>";

            if ($show === 'alledit'){
                // Showing the edit history for admins
                // For admins only, show full edit history
                $this->printAdminHeader(__CLIP__ALLEDIT__);

                // Get editlog count and do the calculation
                $countFullEditlog = $this->dao->countRow('','editlog');
                $pagenum = $this->checkPagenum($pagenum, $countFullEditlog, '');
                $offset = ($pagenum - 1) * $this->editperpage;
                $countPage = $this->editperpage;
                $countPage = $this->roundLimit($countPage, $countFullEditlog, $offset);

                $statement = $this->dao->getEditlogWithUserInfo($offset, $countPage);

                while (($result = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                    // Processing the result of editlog, generating a row of log
                    $this->adminEditUnit($result);
                }

                if ($statement->rowCount() === 0) {
                    echo '<br>还没有编辑记录<br>';
                }

                $sum = ceil($countFullEditlog / $this->editperpage);
                $this->paginationNumber($sum, $pagenum, 'alledit');

            } else if ($show === 'allcom') {
                // Showing the comment history for admins
                $this->printAdminHeader(__CLIP__ALLCOM__);

                // Get comment count and do the calculation
                $countFullEditlog = $this->dao->countRow('', 'comment');
                $pagenum = $this->checkPagenum($pagenum, $countFullEditlog, '');
                $offset = ($pagenum - 1) * $this->editperpage;
                $countPage = $this->editperpage;
                $countPage = $this->roundLimit($countPage, $countFullEditlog, $offset);

                $statement = $this->dao->getCommentWithUserInfo($offset, $countPage);


                while (($result = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                    // Processing the result of editlog, generating a row of log
                    $this->adminCommentUnit($result);
                }

                if ($statement->rowCount() === 0) {
                    echo '<br>还没有编辑记录<br>';
                }

                $sum = ceil($countFullEditlog / $this->editperpage);
                $this->paginationNumber($sum, $pagenum, 'allcom');

            }

            echo "</div>";

        }

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
        global $USERINFO, $conf, $QUERY, $INFO;
        $username = $INFO['client'];
        $pagenum = $_GET['page'];

        if ($show === 'editlog') {
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
        } else if ($show === 'setting') {
            print "<div class='paperclip__selfinfo'>";
            $this->setting();
            print "</div>";
        }
        else if ($QUERY) {
            $this->showSearchResult();
        }
        else if ($this->isAdmin($show, $ACT)) {
            $this->showAdminContent();
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
    public function handle_action_act_preprocess(Doku_Event $event, $param)
    {
      global $_GET;

      $mail = $_GET['mail'];
      $code = $_GET['verify'];

      // verification code
      if ($code && $mail) {

        // retrieve data
        $result = $this->dao->getUserDataByEmail($mail);

        if ($result === false) { // invalid $mail

          header('Location: doku.php?id=start&do=register');
        } else if ($result['verifycode'] !== $code) { //invalid $verifycode

          header('Location: doku.php');
        } else { // valid input

          function filter_cb( $var ) {
            global $conf;
            return $var !== $conf['defaultgroup'];
          }

          // modify grps
          $result['grps'][] = 'user';

          // filter @ALL (default group)
          $grps =  implode(",", array_filter($result['grps'], "filter_cb"));

          // modify db
          $this->dao->setUserGroup($result['id'], $grps);

          //redirect
          header('Location: doku.php');
        }
      }


    }

    /**
     * filter edit
     *
     * @return bool
     */
    protected function _filter($edit){
        date_default_timezone_set("PRC");
        $ak = parse_ini_file(dirname(__FILE__)."/../aliyun.ak.ini");
        $iClientProfile = DefaultProfile::getProfile("cn-shanghai", $ak["accessKeyId"], $ak["accessKeySecret"]); // TODO
        DefaultProfile::addEndpoint("cn-shanghai", "cn-shanghai", "Green", "green.cn-shanghai.aliyuncs.com");
        $client = new DefaultAcsClient($iClientProfile);
        $request = new Green\TextScanRequest();
        $request->setMethod("POST");
        $request->setAcceptFormat("JSON");
        $task1 = array('dataId' =>  uniqid(),
            'content' => $edit
        );
        
        $request->setContent(json_encode(array("tasks" => array($task1),
            "scenes" => array("antispam"))));
        try {
            $response = $client->getAcsResponse($request);
            if(200 == $response->code){
                $taskResults = $response->data;
                foreach ($taskResults as $taskResult) {
                    if(200 == $taskResult->code){
                        $sceneResults = $taskResult->results;
                        foreach ($sceneResults as $sceneResult) {
                            $scene = $sceneResult->scene;
                            $suggestion = $sceneResult->suggestion;
                            //do something
                            if ($suggestion == 'pass')
                                return true;
                            else
                                return false;
                        }
                    }
                }
            }
            return false;
        } catch (Exception $e) {
            echo $e;
        }
    }

}
