<?php
/**
 * Created by PhpStorm.
 * User: leo
 * Date: 2019/2/27
 * Time: 5:48 PM
 */
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

function commonDivEnd(): string {
    return '</div>';
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
// Admin
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

function adminNoEditLog(): string {
    return '<br>您还没有编辑记录<br>';
}

function adminNoReply(): string {
    return '<br>您还没有收到回复<br>';
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


