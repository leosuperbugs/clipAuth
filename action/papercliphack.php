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

use \dokuwiki\Ui;
use \dokuwiki\paperclip;
use Caxy\HtmlDiff\HtmlDiff;

include dirname(__FILE__).'/../paperclipDAO.php';

// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}

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
define('__OKCODE__', 200);

//admin search conditions field
define('__USERTABLEALIAS__', 'us');
define('__COMMENTABLEALIAS__', 'com');
define('__CONDITIONS__', array(
    'conTime' => 'time',
    'conUsername' => 'username',
    'conEditor' => 'editor',
    'conUserid' => 'id',
    'conComment' => 'comment',
    'conSummary' => 'summary',
    'conIdentity' => 'identity',
));
define('__TOOLBAR__', array(
    'bold' => 0,
    'italic' => 1,
    'underline' => 2,
    'mono' => 3,
    'strike' => 4,
    'hequal' => 5,
    'hminus' => 6,
    'hplus' => 7,
    'h' => 8,
    'h1' => 0,
    'h2' => 1,
    'h3' => 2,
    'h4' => 3,
    'h5' => 4,
    'innerlink' => 9,
    'outlink' => 10,
    'ol' => 11,
    'ul' => 12,
    'hr' => 13,
    'pic' => 14,
    'smiley' => 15,
    'chars' => 16,
    'sig' => 17
));
define('__TOOLBAR__STATE__', array(
    'no' => 1,
    'yes' => 2
));
class action_plugin_clipauth_papercliphack extends DokuWiki_Action_Plugin
{
//    private $pdo;
    private $settings;
    // Some constants relating to the pagination of personal centre
    private $editperpage;
    private $replyperpage;
    private $editRecordNum;
    private $replyRecordNum;
    // The order in the result of HTML register output form
    private $redis;
    private $toolbarflag;
    // paperclip's own DAO
    private $dao;
    public function __construct()
    {
        require  dirname(__FILE__).'/../settings.php';
        $this->editperpage = $this->getConf('editperpage');
        $this->replyperpage = $this->getConf('commentperpage');

        $this->redis = new \Redis();
        $this->redis->connect($this->settings['rhost'], $this->settings['rport']);
        $this->redis->auth($this->settings['rpassword']);

        // For js cache
        $this->toolbarflag = $this->redis->get('toolbarflag');
        if (!$this->toolbarflag)
            $this->toolbarflag = __TOOLBAR__STATE__['no'];

        $this->dao = new dokuwiki\paperclip\paperclipDAO();
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
            'ACTION_ACT_PREPROCESS',
            'BEFORE', $this,
            'handle_extlogin_redirect'
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
        $controller->register_hook(
            'HTML_LOGINFORM_OUTPUT',
            'BEFORE',
            $this, 'login_form_handler'
        );
        $controller->register_hook(
            'COMMON_USER_LINK',
            'AFTER',
            $this,
            'modify_user_link'
        );
        $controller->register_hook(
            'JS_SCRIPT_LIST',
            'BEFORE',
            $this,'jsList'
        );
        $controller->register_hook(
            'CSS_STYLES_INCLUDED',
            'BEFORE',
            $this,'cssList'
        );
        $controller->register_hook(
            'TOOLBAR_DEFINE',
            'AFTER',
            $this,'toolbarlist',
            array()
        );
        if ($this->toolbarflag == __TOOLBAR__STATE__['no']) {
            $controller->register_hook(
                'JS_CACHE_USE',
                'BEFORE',
                $this,'jshandler'
            );
        }
        
    }

    public function modify_user_link(Doku_Event &$event, $param) {
        $data = &$event->data;
        if ($this->dao) {
            $info = $this->dao->getUserDataCore($data['username']);
            if ($info) {
                $data['userlink'] = $info['name'];
            }
        }
    }

    public function jshandler(Doku_Event $event, $param){
        $event->preventDefault();
    }
    public function toolbarlist(Doku_Event $event, $param){
        $event->data['italic'] = $event->data[__TOOLBAR__['italic']];
        $event->data['italic']['icon'] = '/lib/plugins/clipauth/images/italic.png';
        $event->data['yinyong'] = array(
            'type'   => 'format',
            'title'  => '引用',
            'icon'   => '/lib/plugins/clipauth/images/yinyong.png',
            'open'   => '>',
            'close'   => '',
            'block'  => true
        );
        $event->data['h1'] = $event->data[__TOOLBAR__['h']]['list'][__TOOLBAR__['h1']];
        $event->data['h1']['icon'] = '/lib/plugins/clipauth/images/h1.png';
        $event->data['h2'] = $event->data[__TOOLBAR__['h']]['list'][__TOOLBAR__['h2']];
        $event->data['h2']['icon'] = '/lib/plugins/clipauth/images/h2.png';
        $event->data['h3'] = $event->data[__TOOLBAR__['h']]['list'][__TOOLBAR__['h3']];
        $event->data['h3']['icon'] = '/lib/plugins/clipauth/images/h3.png';
        $event->data['ol'] = $event->data[__TOOLBAR__['ol']];
        $event->data['ol']['icon'] = '/lib/plugins/clipauth/images/ol.png';
        $event->data['ul'] = $event->data[__TOOLBAR__['ul']];
        $event->data['ul']['icon'] = '/lib/plugins/clipauth/images/ul.png';
        $event->data['pic'] = $event->data[__TOOLBAR__['pic']];
        $event->data['pic']['icon'] = '/lib/plugins/clipauth/images/pic.png';
        $event->data['innerlink'] = $event->data[__TOOLBAR__['innerlink']];
        $event->data['innerlink']['icon'] = '/lib/plugins/clipauth/images/innerlink.png';
        $event->data['outlink'] = $event->data[__TOOLBAR__['outlink']];
        $event->data['outlink']['icon'] = '/lib/plugins/clipauth/images/outlink.png';
        $event->data['outlink'] = $event->data[__TOOLBAR__['outlink']];
        $event->data['outlink']['icon'] = '/lib/plugins/clipauth/images/outlink.png';
        $event->data['outlink'] = $event->data[__TOOLBAR__['outlink']];
        $event->data['outlink']['icon'] = '/lib/plugins/clipauth/images/outlink.png';
        $event->data['chars'] = $event->data[__TOOLBAR__['chars']];
        $event->data['chars']['icon'] = '/lib/plugins/clipauth/images/chars.png';
        foreach (__TOOLBAR__ as $k => $v) {
            unset($event->data[$v]);
        }
        $this->toolbarflag = __TOOLBAR__STATE__['yes'];
        $this->redis->set('toolbarflag', $this->toolbarflag);
    } 

    public function jsList(Doku_Event $event, $param){
        $event->data[] = DOKU_INC.'lib/plugins/clipauth/flatpicker.js';
        $event->data[] = DOKU_INC.'lib/plugins/clipauth/zh.js';
    } 

    public function cssList(Doku_Event $event, $param){
        $path = DOKU_INC.'lib/plugins/clipauth/flatpickr.less';
        $event->data['files'][$path] = "lib/plugins/clipauth/";
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
            $res = $this->contentFilter($editcontent);
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

            echo entryEditorCredit($editorTitle, $count, $editorList);
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
        print noScript();
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
        $indexForShow = implode(adminPageidGlue(), $indexForShow);
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
        print adminEditlogUnit($needHide, $mainPageName, $editData, $indexForShow);
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

        print adminReplylogUnit($needHide, $replyData, $indexForShow);
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
        print commonNavbar($isSelected, $href, $navbarContent);
    }

    /**
     * Print the content of header part, switching according to $highlight
     * @param $highlight
     */
    private function printSelfinfoHeader($highlight) {
        if ($highlight >= __CLIP__EDIT__ && $highlight <= __CLIP__SETTING__) {
            $hrefSetting = __HREFSETTING__;
            print personalInfoNavbarHeader();
            $this->printNavbar(__CLIP__EDIT__, $highlight, "/doku.php?show={$hrefSetting[__CLIP__EDIT__]}&page=1&id=start");
            $this->printNavbar(__CLIP__COMMENT__, $highlight, "/doku.php?show={$hrefSetting[__CLIP__COMMENT__]}&page=1&id=start\"");
            $this->printNavbar(__CLIP__SETTING__, $highlight, "/doku.php?show={$hrefSetting[__CLIP__SETTING__]}&page=1&id=start\"");
            print commonDivEnd();
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
            print personalInfoNavbarHeader();
            $this->printNavbar(__CLIP__TITLE__, $highlight, "/doku.php?q=$QUERY&show={$hrefSetting[__CLIP__TITLE__]}&page=1");
            $this->printNavbar(__CLIP__FULLTEXT__, $highlight, "/doku.php?q=$QUERY&show={$hrefSetting[__CLIP__FULLTEXT__]}&page=1");
            print commonDivEnd();
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
            print personalInfoNavbarHeader();
            $this->printNavbar(__CLIP__ALLEDIT__, $highlight, "/doku.php?show={$hrefSetting[__CLIP__ALLEDIT__]}&page=1");
            $this->printNavbar(__CLIP__ALLCOM__, $highlight, "/doku.php?show={$hrefSetting[__CLIP__ALLCOM__]}&page=1");
            $this->printNavbar(__CLIP__ADMIN__, $highlight, "/doku.php?show={$hrefSetting[__CLIP__ADMIN__]}&page=1");
            print commonDivEnd();
        }
    }

    /**
     * Print the search form of admin console
     * $clip alledit or allcom
     */
    private function printAdminSearchForm($clip) {
        global $_REQUEST;
        $mutechecked = '';
        $nukechecked = '';
        if ($_REQUEST['identity'] == __MUTED__)
            $mutechecked = 'checked="checked"';
        elseif ($_REQUEST['identity'] == __NUKED__)
            $nukechecked = 'checked="checked"';

        print adminSearchBox($clip, $mutechecked, $nukechecked);
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
            echo adminNoEditLog();
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
            echo adminNoReply();
        }
    }

    private function setting() {
        $this->printSelfinfoHeader(__CLIP__SETTING__);
        print personalSetting();
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
        $mtime = filemtime(wikiFN($id));
        $time = date_iso8601($mtime);
        $passedLang = array(
            'matches' => $this->getLang('matches'),
            'index' => $this->getLang('index'),
            'ellipsis' => $this->getLang('ellipsis'));

        $html .= searchMeta($id, $countInText, $highlight, $time, $passedLang);
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

        $html = searchResult($this->getLang('countPrefix'), $counter, $this->getLang('countSuffix'));
        foreach ($pageLookupResults as $id => $title) {
            $this->showMeta($id, 0, $highlight, $html);
        }
        $html .= commonDivEnd();
        echo $html;

        $sum = ceil($counter / $this->editperpage);
        commonPaginationNumber($sum, $pagenum, 'title', array('q' => $QUERY));

    }

    /**
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

        $html = searchResult($this->getLang('countPrefix'), $counter, $this->getLang('countSuffix'));

        foreach ($fullTextResults as $id => $countInText) {
            $this->showMeta($id, $countInText, $highlight, $html);
        }

        $html .= commonDivEnd();
        echo $html;

        $sum = ceil($counter / $this->editperpage);
        commonPaginationNumber($sum, $pagenum, 'fulltext', array('q' => $QUERY));
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

        $searchResult = $this->getLang('searchResult');
        $searchHint = $this->getLang('searchHint');
        print searchHead($searchResult, $searchHint);

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
        echo commonDivEnd();
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

        $idLang = $this->getLang('id');
        $timeLang = $this->getLang('time');
        print adminProcess($id, $idLang, $time, $timeLang);

        if($isRecordEditorAdmin) {
            print $this->getLang('cantban');
        } else {
            print adminProcessForm($id, $userID, $this->getLang('process'));
        }
        print commonDivEnd();
    }

    private function adminUnitlangs() {
        return array(
            'editor' => $this->getLang('editor'),
            'editorID' => $this->getLang('editorID'),
            'mailaddr' => $this->getLang('mailaddr'),
            'userIdentity' => $this->getLang('userIdentity')
        );
    }

    private function adminEditUnit($editData) {
        $langs = $this->adminUnitlangs();
        print $this->adminEditUnit();
        $this->printAdminProcess($editData['editlogid'], $editData['time'], $editData['editorid'], $editData['identity']);
        adminUserInfo($editData['realname'], $editData['editorid'], $editData['mailaddr'], $editData['identity'], $langs);
        $this->editUnit($editData, true);
        print commonDivEnd();

    }

    private function adminCommentUnit($commentData) {
        $langs = $this->adminUnitlangs();
        print $this->adminEditUnit();
        $this->printAdminProcess($commentData['hash'], $commentData['time'], $commentData['userid'], $commentData['identity']);
        adminUserInfo($commentData['realname'], $commentData['userid'], $commentData['mailaddr'], $commentData['identity'], $langs);
        $this->editUnit($commentData, true);
        print commonDivEnd();
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

    private function checkCondition(&$getConditions) {
        if ($getConditions) {
            $getConditions .= "and ";
        }
        return $getConditions;
    }

    private function showAdminContent() {
        $show = $_GET['show'];
        $pagenum = $_GET['page'];
        if (!isset($pagenum)) {
            $pagenum = 1;
        }
        global $INFO;

        if(!$INFO['isadmin']) return;

        if ($show === 'admin') {
            // Normal
            $admin = new dokuwiki\Ui\Admin();
            $admin->show();
        }
        else {
            echo adminHead();
            $getConditions = '';
            $username = $_REQUEST['username'];
            $summary = $_REQUEST['summary'];
            $comment = $_REQUEST['comment'];
            $userid = $_REQUEST['userid'];
            $etime = $_REQUEST['etime'];
            $ltime = $_REQUEST['ltime'];
            $identity = $_REQUEST['identity'];
            //search conditions
            
            if ($userid) {
                $this->checkcondition($getConditions);
                $getConditions .= __USERTABLEALIAS__.".".__CONDITIONS__['conUserid']." = $userid ";
            }


            if ($etime && $ltime) {
                $this->checkcondition($getConditions);
                $getConditions .= __CONDITIONS__['conTime']." >= '{$etime}' and ".__CONDITIONS__['conTime']." <= '{$ltime}' ";

            } elseif ($etime) {
                $this->checkcondition($getConditions);
                $getConditions .= __CONDITIONS__['conTime']." >= '{$etime}' ";

            } elseif ($ltime) {
                $this->checkcondition($getConditions);
                $getConditions .= __CONDITIONS__['conTime']." <= '{$ltime}' ";
            }


            if ($identity && $identity != 'all') {
                $this->checkcondition($getConditions);
                $getConditions .= __CONDITIONS__['conIdentity']." like '{$identity}' ";
            }

            if ($show === 'alledit'){
                
                // Showing the edit history for admins
                // For admins only, show full edit history
                $this->printAdminHeader(__CLIP__ALLEDIT__);
                $this->printAdminSearchForm(__CLIP__ALLEDIT__);
                
                if ($summary) {
                    $this->checkcondition($getConditions);
                    $getConditions .= __CONDITIONS__['conSummary']." like '%{$summary}%' ";
                }
                if ($username) {
                    $this->checkcondition($getConditions);
                    $getConditions .= __CONDITIONS__['conEditor']." like '%{$username}%' ";

                }
                               
                $countFullEditlog = $this->dao->countEditUserInfo($getConditions);
                $pagenum = $this->checkPagenum($pagenum, $countFullEditlog, '');
                $offset = ($pagenum - 1) * $this->editperpage;
                $countPage = $this->editperpage;
                $countPage = $this->roundLimit($countPage, $countFullEditlog, $offset);
                $statement = $this->dao->getEditlogWithUserInfo($offset, $countPage ,$getConditions);
                while (($result = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                    // Processing the result of editlog, generating a row of log
                    $this->adminEditUnit($result);
                }

                if ($statement->rowCount() === 0) {
                    echo adminNoEditLog();
                }
                $sum = ceil($countFullEditlog / $this->editperpage);
                $additionalParam = array(
                    'summary' => $summary,
                    'username' => $username,
                    'userid' => $userid,
                    'etime' => $etime,
                    'ltime' => $ltime,
                    'identity' => $identity
                ); 
                commonPaginationNumber($sum, $pagenum, 'alledit', $additionalParam);

            } else if ($show === 'allcom') {
                // Showing the comment history for admins
                $this->printAdminHeader(__CLIP__ALLCOM__);
                $this->printAdminSearchForm(__CLIP__ALLCOM__);

                if ($comment) {
                    if ($getConditions){
                        $getConditions .= "and ";
                    }
                    $getConditions .= __CONDITIONS__['conComment']." like '%{$comment}%' ";

                }
                if ($username) {
                    if ($getConditions) {
                        $getConditions .= "and ";
                    }
                    $getConditions .= __COMMENTABLEALIAS__.".".__CONDITIONS__['conUsername']." like '%{$username}%' ";
                }
                // Get comment count and do the calculation
                $countFullEditlog = $this->dao->countCommentUserinfo($getConditions, 'comment');
                $pagenum = $this->checkPagenum($pagenum, $countFullEditlog, '');
                $offset = ($pagenum - 1) * $this->editperpage;
                $countPage = $this->editperpage;
                $countPage = $this->roundLimit($countPage, $countFullEditlog, $offset);

                $statement = $this->dao->getCommentWithUserInfo($offset, $countPage, $getConditions);


                while (($result = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                    // Processing the result of editlog, generating a row of log
                    $this->adminCommentUnit($result);
                }

                if ($statement->rowCount() === 0) {
                    echo adminNoEditLog();
                }

                $sum = ceil($countFullEditlog / $this->editperpage);
                $additionalParam = array(
                    'comment' => $comment,
                    'username' => $username,
                    'userid' => $userid,
                    'etime' => $etime,
                    'ltime' => $ltime,
                    'identity' => $identity
                ); 
                commonPaginationNumber($sum, $pagenum, 'allcom', $additionalParam);

            }

            echo commonDivEnd();

        }

    }

    private function printEditPageModification() {
        global $ID;
        $indexForShow = '';
        $this->processPageID($ID, $indexForShow);
        $indexArr = explode(':', $ID);
        $title = $indexArr[count($indexArr)-1];
        $editindex = $this->getLang('editindex');
        $editheader = $this->getLang('editheader');

        print editHeader($editheader, $editindex, $indexForShow, $title);
    }

    private function isPersonalCenter($show) {
       return ($show === 'editlog' || $show === 'comment' || $show === 'setting');
    }

    private function personalInfoCentre($show, $event) {
        global $INFO;
        $pagenum = $_GET['page'];
        $username = $INFO['client'];
        print personalInfo();

        if ($show === 'editlog') {
            $event->data = '';
            // A little bit wired here, need fix
            $editRecordCount = $this->countEditForName($username);
            $sum = ceil($editRecordCount / $this->editperpage);

            if ($show === 'editlog') {
                $pagenum = $this->checkPagenum($pagenum, $editRecordCount, $username);
                $this->editlog($pagenum);
                commonPaginationNumber($sum, $pagenum, 'editlog');
            } else {
                $this->editlog(1);
                commonPaginationNumber($sum, 1, 'editlog');
            }

        } else if ($show === 'comment') {
            $replyRecordCount = $this->countReplyForName($username);
            $sum = ceil($replyRecordCount / $this->replyperpage);

            $pagenum = $this->checkPagenum($pagenum, $replyRecordCount, $username);
            // out putting
            $this->comment($pagenum);
            commonPaginationNumber($sum, $pagenum, 'comment');
        } else if ($show === 'setting') {
            $this->setting();
        }
        print commonDivEnd();
    }

    /**
     * Check if this is a binding existing account redirect
     */
    private function isBind() {
        $bind = $_GET['bind'];
        return ($bind === 'ext');
    }

    private function bindExistingAccount(Doku_Event $event) {
        $slogan = $this->getLang('bindSlogan');
        $bind = $this->getLang('bind');
        $skip = $this->getLang('skip');
        loginBindWechatForm($slogan, $bind, $skip);

        $event->preventDefault();
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
        global $QUERY;

        if ($ACT == 'edit' || $ACT == 'preview') {
            $this->printEditPageModification();
        }
        elseif ($this->isPersonalCenter($show)) {
            $this->personalInfoCentre($show, $event);
        }
        elseif ($QUERY) {
            $this->showSearchResult();
        }
        elseif ($this->isAdmin($show, $ACT)) {
            $this->showAdminContent();
        }
        elseif ($this->isBind()) {
            $this->bindExistingAccount($event);
        }
    }

    public function changeLink(Doku_Event &$event, $param)
    {
        if($event->data['view'] != 'user') return;
    }

    /**
     * Verify user's mail
     * User click to here from links in email
     *
     * Author: Max Qian
     * Modified by Mark T. Nie
     *
     * @param $mail
     * @param $code
     */
    private function mailVerification($mail, $code) {
        // retrieve data
        $result = $this->dao->getUserDataByEmailCore($mail);

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
    /**
     * Author: Max Qian
     * Modified by Mark T. Nie
     *
     * Used to handle the mail verification
     *
     * @param Doku_Event $event
     * @param $param
     */
    public function handle_action_act_preprocess(Doku_Event $event, $param)
    {
      global $_GET;

      $mail = $_GET['mail'];
      $code = $_GET['verify'];

      // verification code
      if ($code && $mail) {
          $this->mailVerification($mail, $code);
      }
    }

    /**
     * Inject some extra UI for external login in the login form
     *
     * @return string
     */
    private function externalLoginUI() {
        $bind = $_GET['bind'];
        if ($bind) {
            // Should not add login form
            // when login form is shown at binding page
            return '';
        } else {
            // Make up the link
            $hlp = $this->loadHelper('clipauth_paperclipHelper');
            $wechatLink = $hlp->getAuthURL();
            $extloginLang = $this->getLang('extlogin');
            $wechatloginLang = $this->getLang('wechatlogin');
            return loginExternalUI($extloginLang, $wechatLink, $wechatloginLang);
        }
    }

    /**
     * filter edit
     *
     * @return bool
     */
    protected function contentFilter($edit){
        date_default_timezone_set("PRC");
        $ak = parse_ini_file(dirname(__FILE__)."/../aliyun.ak.ini");
        $iClientProfile = DefaultProfile::getProfile($this->getConf('aliregion'), $ak["accessKeyId"], $ak["accessKeySecret"]); // TODO
        DefaultProfile::addEndpoint($this->getConf('aliregion'), $this->getConf('aliregion'), $this->getConf('alido'), $this->getConf('aliFilterUrl'));
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
            if(__OKCODE__ == $response->code){
                $taskResults = $response->data;
                foreach ($taskResults as $taskResult) {
                    if(__OKCODE__ == $taskResult->code){
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

    public function login_form_handler(Doku_Event $event, $param) {
        $externalLoginUI = $this->externalLoginUI();
        $event->data->_content[] = $externalLoginUI;
    }

    public function handle_extlogin_redirect(Doku_Event $event, $param) {

    }
}
