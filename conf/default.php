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
$conf['aliregion'] = "cn-shanghai";
$conf['aliFilterUrl'] = "green.cn-shanghai.aliyuncs.com";
$conf['alido'] =  "Green";
$conf['wechat'] = 'wechat';
$conf['weibo'] = 'weibo';
// !!!! parameterize the URL here dam it
$conf['wechatlink'] = 'https://open.weixin.qq.com/connect/qrconnect';
$conf['wechatTokenURL'] = 'https://api.weixin.qq.com/sns/oauth2/access_token';
$conf['wechatUserinfoURL'] = 'https://api.weixin.qq.com/sns/userinfo';
$conf['wechatAppId'] = 'wxff579daeee2f39e7';
$conf['wechatRediURI'] = 'https://ipaperclip.net?ext=wechat';
$conf['wechatRespType'] = 'code';
$conf['wechatScope'] = 'snsapi_login';
$conf['wechatSecret'] = '2edc852df4fb54111b92027cbe46ac9a';
// Weibo
$conf['weibolink'] = 'https://api.weibo.com/oauth2/authorize';
$conf['weiboAppId'] = '2849862109';
$conf['weiboSecret'] = '45c808b9f98130ae19f55d5cb7d00962';
$conf['weiboRediURI'] = 'http://ipaper.com/doku.php?ext=weibo';
$conf['weiboUserinfoURL'] = 'https://api.weibo.com/2/users/show.json';
