<?php
/**
 * Options for the clipauth plugin
 *
 * @author Tongyu Nie <marktnie@gmail.com>
 */


//$meta['fixme'] = array('string');
$meta['editperpage']      = array('numericopt');
$meta['commentperpage']   = array('numericopt');
$meta['needInvitation'] = array('onoff');
$meta['invitationCodeLen'] = array('numericopt');
$meta['usernameMaxLen'] = array('numericopt');
$meta['passMinLen'] = array('numericopt');
$meta['passMaxLen'] = array('numericopt');
$meta['fullnameMaxLen'] = array('numericopt');
$meta['editors'] = array('string');
$meta['resultperpage'] = array('numericopt');
$meta['loginCacheTTL'] = array('numericopt');
//$conf['wechatlink'] = '
//  https://open.weixin.qq.com/connect/qrconnect?
//  appid=wxff579daeee2f39e7
//  &redirect_uri=http://ipaperclip.net?ext=wechat
//  &response_type=code
//  &scope=snsapi_login
//  &state=1#wechat_redirect';
$meta['wechatlink'] = array('string');
$meta['wechatTokenURL'] = array('string');
$meta['wechatAppId'] = array('string');
$meta['wechatRediURI'] = array('string');
$meta['wechatRespType'] = array('string');
$meta['wechatScope'] = array('string');
$meta['wechatState'] = array('string');
$meta['wechatSecret'] = array('string');
// Weibo
$meta['weibolink'] = array('string');
