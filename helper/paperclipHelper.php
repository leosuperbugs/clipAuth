<?php
/**
 * Author: Mark
 * Email: marktnie@gmail.com
 *
 * Date: 2019/1/18
 * Time: 8:55 PM
 */
if(!defined('DOKU_INC')) die();

/**
 * Help the Auth Plugin
 * Class ExtLoginHelper
 */
class helper_plugin_clipauth_paperclipHelper extends DokuWiki_Plugin {

    public function wechatLoginLink($state) {
        $wechatLink = $this->getConf('wechatlink')
            . '?appid=' . $this->getConf('wechatAppId')
            . '&redirect_uri=' . $this->getConf('wechatRediURI')
            . '&response_type=' . $this->getConf('wechatRespType')
            . '&scope=' . $this->getConf('wechatScope')
            . '&state=' . 'test'
            . '#wechat_redirect';
        return $wechatLink;
    }

    /**
     * To get the user info from Wechat
     *
     * @param $code
     */
    public function getWechatInfo($code) {

    }

    /**
     * To get the user info from Weibo
     *
     * @param $code
     */
    public function getWeiboInfo($code) {

    }

}