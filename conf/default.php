<?php
/**
 * Default settings for the clipauth plugin
 *
 * @author Tongyu Nie <marktnie@gmail.com>
 */

//$conf['fixme']    = 'FIXME';
$conf['editperpage']    = 5;
$conf['commentperpage'] = 5;
$conf['needInvitation'] = 0;
$conf['invitationCodeLen'] = 6;
$conf['usernameMaxLen'] = 16;
$conf['passMinLen'] = 8;
$conf['passMaxLen'] = 40;
$conf['fullnameMaxLen'] = 16;
$conf['editors'] = '编辑成员';
$conf['resultperpage'] = 10;
$conf['wechat'] = 'wechat';
$conf['weibo'] = 'weibo';
// !!!! parameterize the URL here dam it
$conf['wechatlink'] = 'https://open.weixin.qq.com/connect/qrconnect';
$conf['wechatAppId'] = 'wxff579daeee2f39e7';
$conf['wechatRediURI'] = 'http://ipaperclip.net?ext=wechat';
$conf['wechatRespType'] = 'code';
$conf['wechatScope'] = 'snsapi_login';
// Weibo
$conf['weibolink'] = '';
