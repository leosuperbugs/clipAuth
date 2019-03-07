<?php
/**
 * Created by PhpStorm.
 * User: leo
 * Date: 2019/2/27
 * Time: 5:48 PM
 */
use \dokuwiki\Form\Form;
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



// Common
// Used in multiple parts
/**
 * Unit in navigation bar
 *
 * @param $isSelected
 * @param $href
 * @param $navbarContent
 * @return string
 */
function commonNavbar($isSelected, $href, $navbarContent): string {
    return "<div class='paperclip__selfinfo__navbar $isSelected'>
                <a href= '$href'>$navbarContent</a>
            </div>";
}

/**
 * @return string
 */
function commonDivEnd(): string {
    return '</div>';
}

/**
 * Page nav bar
 *
 * @param $content
 * @param $additionalParam
 * @return string
 */
function commonPageJump($content, $additionalParam): string {
    $html = "<td class='paperclip__pagejump'>
             <form action='/doku.php' method='get'>
             <input type='text' class='paperclip__pagejump__input' name='page' required>
             <input type='hidden' name='show' value=$content>";

    foreach ($additionalParam as $param => $value) {
        $html .= "<input type='hidden' name=$param value=$value>";
    }
    $html .= "<input type='submit' class='paperclip__pagejump__button' value='跳转'>
    </form>
    </td>
    </tr>
    </table>
    </div>";
    return $html;
}

/**
 * Print a new cell in the page num table
 * @param $page
 */
function commonPrintOnePagenum ($page, $content, $additionalParam = []) {
    $addiQuery = '';
    foreach ($additionalParam as $param => $value) {
        $addiQuery .= "&$param=$value";
    }
    print "<td class='paperclip__pagenum'>
                <a href='/doku.php?show=$content&page=$page$addiQuery' class='paperclip__pagehref'>
                    $page
                </a>
           </td>";
}

/**
 * Print the present page num in the table
 * @param $page
 */
function commonPrintPresentPagenum ($page) {
    print "<td class='paperclip__pagenum__nohref'>$page</td>";
}

/**
 * Print the ... in the table
 */
function commonPrintEllipsis () {
    print "<td class='paperclip__pagenum__nohref'>...</td>";
}

/**
 * @param $start
 * @param $end The range includes the end
 */
function commonPrintPageFromRange($start, $end, $content, $additionalParam = []) {
    if ($start > $end) return;

    for ($i = $start; $i <= $end; $i++) {
        commonPrintOnePagenum($i, $content, $additionalParam);
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
function commonPaginationNumber($sum, $page, $content, $additionalParam = []) {
    // check some exception
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
                commonPrintPageFromRange(1, $left, $content, $additionalParam);
            }
        } else {
            // The table should look like:
            // 1 ... 4 5
            commonPrintOnePagenum(1, $content, $additionalParam);
            commonPrintEllipsis();
            commonPrintPageFromRange($left - 1, $left, $content, $additionalParam);
        }
        //print centre part
        commonPrintPresentPagenum($page);
        //print right part
        $right = $sum - $page;
        if ($right <= 4) {
            if ($right > 0) {
                commonPrintPageFromRange($page + 1, $sum, $content, $additionalParam);
            }
        } else {
            // The table should look like:
            // 7 8 ... 10
            commonPrintPageFromRange($page + 1, $page + 2, $content, $additionalParam);
            commonPrintEllipsis();
            commonPrintOnePagenum($sum, $content, $additionalParam);
        }
        // print the input and jump button
        print commonPageJump($content, $additionalParam);
    }
}



// Search
/**
 * The title of search result, together with result count
 *
 * @param $prefix
 * @param $counter
 * @param $suffix
 * @return string
 */
function searchResult($prefix, $counter, $suffix): string {
    $html = "<div class='paperclip__qresult'>
                 <p class='paperclip__counter'>{$prefix}{$counter}{$suffix}</p>";
    return $html;
}

/**
 * Get a form which can be used to adjust/refine the search
 *
 * @param string $query
 *
 * @return string
 */
function searchFormHTML($query)
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
 * A single search result
 *
 * @param $id :pageid
 * @param $countInText
 * @param $highlight
 * @param $time
 * @param $passedLang :Some languages from $this->getLang()
 * @return string
 */
function searchMeta($id, $countInText, $highlight, $time, $passedLang): string {
    global $lang;

    // Comment part of search title result and search fulltext result
    $goldspanPrefix = "<span class='paperclip__link'>";
    $spanSuffix = "</span>";
    $wikiIndex = $id;
    $wikiIndex = explode(':', $wikiIndex);
    $pageTitle = array_pop($wikiIndex);
    $wikiIndex = implode("$spanSuffix-$goldspanPrefix", $wikiIndex);
    $wikiIndex = $goldspanPrefix.$wikiIndex.$spanSuffix;

    $html = "<div class='paperclip__qtitle'>";

    // Title
    $html .= "<a href='/doku.php?id=$id' target='_blank'>$pageTitle</a>";
    // Last edittion time and index
    $html .= "<div class='paperclip__searchmeta'>";
    // Last modification time
    if ($countInText > 0) {
        $html .= "<span>{$countInText}{$passedLang['matches']}</span>";
    }
    $html .= "<span class='paperclip__lastmod'>{$lang['lastmod']}{$time}</span>";
    $html .= "<span>{$passedLang['index']}{$wikiIndex}</span>";
    $html .= "</div>";

    // Snippet
    $resultBody = ft_snippet($id, $highlight);
    $html .= "<p>{$resultBody}{$passedLang['ellipsis']}</p>";
    $html .= "</div>";
    return $html;
}

/**
 * Head part of search result
 *
 * @param $searchResult
 * @param $searchHint
 * @return string
 */
function searchHead($searchResult, $searchHint): string {
    global $QUERY;

    $html = "<div class='paperclip__search'>
    <div class='paperclip__srchhead'>
    <div class='paperclip__srchrslt'>{$searchResult}</div>
    <div class='paperclip__floatright'>";
    $html .= searchFormHTML($QUERY);
    $html .= "<p class='paperclip__srchhint'>{$searchHint}</p></div></div>";
    return $html;
}



// Entries
/***
 * 编辑人员 xx人
 * foo's name, bar's name...
 *
 * @param $editorTitle
 * @param $count
 * @param $editorList
 * @return string
 */
function entryEditorCredit($editorTitle, $count, $editorList): string {
    return "<h1>$editorTitle
                <div class='paperclip__editbtn__wrapper'>
                    <span>$count 人</span>
                </div>
            </h1>
            <p>$editorList</p>";
}



// Personal
/**
 * Personal info nav bar
 *
 * @return string
 */
function personalInfoNavbarHeader(): string {
    return "<div class='paperclip__selfinfo__header'>";
}

/**
 * Personal info setting page
 *
 * @return string
 */
function personalSetting(): string {
    global $USERINFO;
    $username = $USERINFO['name'];
    $mail = $USERINFO['mail'];

    return "
        <div class='paperclip__settings'>
        <div class='paperclip__settings__title'>个人信息</div>
        <div class='paperclip__settings__info'>用户名：$username&nbsp|&nbsp登录邮箱：$mail</div>
            <a href='/doku.php?do=profile' target='_blank' class='paperclip__settings__update'>前往更改</a>
        </div>
        ";
}

/**
 * Head part of self info division
 * @return string
 */
function personalInfo(): string {
    return "<div class='paperclip__selfinfo'>";
}



// Admin
/**
 * Beginning of paperclip__admin
 *
 * @return string
 */
function adminHead(): string {
    return "<div class='paperclip__admin'>";
}
/**
 * id in admin pages
 * xxxx-xxxx-xxxx
 *
 * @return string
 */
function adminPageidGlue(): string {
    return '</span>-<span class="paperclip__link">';
}

/**
 * Edit log unit in admin page
 *
 * @param $needHide
 * @param $mainPageName
 * @param $editData
 * @param $indexForShow
 * @return string
 */
function adminEditlogUnit($needHide, $mainPageName, $editData, $indexForShow): string {

    $time   = $editData['time'];
    $summary= $editData['summary'];
    $pageid = $editData['pageid'];

    return "
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
</div>";
}

/**
 * Reply log unit in admin page
 *
 * @param $needHide
 * @param $replyData
 * @param $indexForShow
 * @return string
 */
function adminReplylogUnit($needHide, $replyData, $indexForShow): string {

    $pageid = $replyData['pageid'];
    $time   = $replyData['time'];
    $comment= $replyData['comment'];
    $replier= $replyData['username'];
    $hash   = $replyData['hash'];

    return "
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
</div>";
}

/**
 * Search box at the top
 *
 * @param $clip
 * @param $muteChecked
 * @param $nukeChecked
 * @return string
 */
function adminSearchBox($clip, $muteChecked, $nukeChecked): string {
    global $_REQUEST;

    $html = "<div id='adsearchbox'>
                <form id='adminsearch_form' method='post' action=/doku.php?show=".__HREFSETTING__[$clip]." accept-charset='utf-8'>";

    if ($clip == __CLIP__ALLEDIT__) {
        $html .= "<p>
                    词　条：
                    <input type='text' name='summary' value={$_REQUEST['summary']}>
                  </p>";
    }
    elseif ($clip == __CLIP__ALLCOM__) {
        $html .= "<p>
                    评　论：
                    <input type='text' name='comment' value={$_REQUEST['comment']}>
                  </p>";
    }

    $html .= "<p>
                 用户名：
                 <input type='text' name='username' value={$_REQUEST['username']}>
              </p>
              <p>
                 用户ID：
                 <input type='text' name='userid' value={$_REQUEST['userid']}>
              </p>
              <p>
                 时　间：
                 <input name='etime' class='flatpickr' type='text' placeholder='开始时间' title='开始时间' readonly='readonly' style='cursor:pointer; 'value='{$_REQUEST['etime']}'>
                 -- 
                 <input name='ltime' class='flatpickr' type='text' placeholder='结束时间' title='结束时间' readonly='readonly' style='cursor:pointer;' value='{$_REQUEST['ltime']}'>
              </p>
              <p> 
                <input type='radio' name='identity' value='all' checked='checked'>全部用户
                <input type='radio' name='identity' value='muted' {$muteChecked}>禁言用户
                <input type='radio' name='identity' value='nuked' {$nukeChecked}>拉黑用户
              </p>
              <p>
                    <input type='submit' name='admin_submit' value='搜索'>
              </p>";
    $html .= "</form></div>";
    return $html;
}

/**
 * @return string
 */
function adminNoEditLog(): string {
    return '<br>您还没有编辑记录<br>';
}

/**
 * @return string
 */
function adminNoReply(): string {
    return '<br>您还没有收到回复<br>';
}

/**
 * For admins to ban users
 * Head part
 *
 * @param $id
 * @param $idLang
 * @param $time
 * @param $timeLang
 * @return string
 */
function adminProcess($id, $idLang, $time, $timeLang): string {
    return "<div class='paperclip__adminProcess' >
                    <span>{$idLang}$id</span>
                    <span>{$timeLang}$time</span>";
}

/**
 * Form for blacklisting user
 *
 * @param $id
 * @param $userID
 * @param $processLang
 * @return string
 */
function adminProcessForm($id, $userID, $processLang): string {
    global $INFO;
    return "<form  id='$id'>
                <select name='muteTime'>
                    <option value='1'>禁言1天</option>
                    <option value='7'>禁言7天</option>
                    <option value='30'>禁言30天</option>
                    <option value='0'>拉黑用户</option>
                </select>
                <input type='hidden' name='userID' value='$userID'>
                <input type='hidden' name='call' value='paperclip'>
                <input type='hidden' name='identity' value='{$INFO['client']}'>
                <input type='submit' value='{$processLang}'>
            </form>";
}

/**
 * User info at admin page
 *
 * @param $realname
 * @param $editorid
 * @param $mailaddr
 * @param $identity
 * @param $langs
 * @return string
 */
function adminUserInfo($realname, $editorid, $mailaddr, $identity, $langs): string {
    return "
<div class='paperclip__editorInfo'>
    <span>{$langs['editor']}$realname</span>
    <span>{$langs['editorID']}$editorid</span>
    <span>{$langs['mailaddr']}$mailaddr</span>
</div>
<div class='paperclip__userState'>
    <span>{$langs['userIdentity']}$identity</span>
</div>";
}

/**
 * Head part of edit unit
 * @return string
 */
function adminEditUnit(): string {
    return "<div class='paperclip__adminEditUnit'>
    <hr class='paperclip__editlog__split'>";
}



// Edit
/**
* Refuse to show when js has been disabled
*
* @return string
                                            */
function noScript(): string {
     return '<noscript>您的浏览器未启用脚本，请启用后重试！</noscript>';
}

/**
 * Additional content on top of edit page
 *
 * @param $editheader
 * @param $editindex
 * @param $indexForShow
 * @param $title
 * @return string
 */
function editHeader($editheader, $editindex, $indexForShow, $title): string {
    return "<div class='paperclip__edit__header'>{$editheader}
                <div class='paperclip__editlog__index'>
                    {$editindex}：<span class='paperclip__link'>$indexForShow</span>
                </div>
            </div>
            <div class='paperclip__edittitle'>$title</div>";
}

// Login
function loginExternalUI($extloginLang, $wechatLink, $wechatloginLang): string {
    return "
    <div class='paperclip__extlogin'>
        <div class='paperclip__exthead'>
            <div class='paperclip__extlgintitle'>
             {$extloginLang}
            </div> 
        </div>
        <div>
            <div class='paperclip__divhr'></div>
        </div>
        <div class='paperclip__extlkgrp'>
            <a class='paperclip__extlink' target='_blank' id='extlink__wechat' href={$wechatLink}>
                {$wechatloginLang}
            </a>
        </div>
    </div>";
}

/**
 * Modified login form for wechat login binding
 * May be duplicated with inc/html.php html_login()
 * Need some update later
 *
 * @return string
 */
function loginBindWechatForm($slogan, $bind, $skip) {
    global $lang;
    global $ID;
    global $INPUT;

    print '<div class=paperclip__login>'.NL;

    // Add some slogans
    print "<div class=paperclip__bind>{$slogan}</div>";

    // I need another form here
    $skipForm = new Doku_Form(array('id' => 'paperclip__skip'));
    $skipForm->startFieldset('');
    $skipForm->addHidden('skip', 'skip');
    $skipForm->endFieldset();
    html_form('skip', $skipForm);

    $form = new Doku_Form(array('id' => 'paperclip__bind'));
    $form->startFieldset('');
    $form->addElement('<div class="form__wrapper">');
    $form->addHidden('id', $ID);

    // Username or mail address
    $firstline = array(
        form_makeTextField('bind_u', ((!$INPUT->bool('http_credentials')) ? $INPUT->str('u') : ''), '邮箱', 'focus__this', 'block')
    );
    addElementsWithWrap($form, $firstline);

    // Password
    $secondline = array(
        form_makePasswordField('bind_p', $lang['pass'], '', 'block')
    );
    addElementsWithWrap($form, $secondline);

    $form->addElement('</div>');

    $form->addElement('<div class="button__wrapper">');
    $form->addElement(form_makeButton('submit', '', $bind));
    $form->addElement(form_makeButton('cancel', '', $skip, array('form' => 'paperclip__skip')));
    $form->addElement('</div>');
    $form->endFieldset();

    html_form('login', $form);
    print '</div>'.NL;

}