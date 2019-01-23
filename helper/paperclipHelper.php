<?php
/**
 * This helper is for external login
 * Providing some of the most commonly used functions for Wechat and Weibo login
 *
 * Author: Mark
 * Email: marktnie@gmail.com
 *
 * Date: 2019/1/18
 * Time: 8:55 PM
 */
if(!defined('DOKU_INC')) die();

use \dokuwiki\paperclip;

define("__STATE__SPLTR__", "+");

/**
 * Help the Auth Plugin
 * Class ExtLoginHelper
 */
class helper_plugin_clipauth_paperclipHelper extends DokuWiki_Plugin {

    private $redis;
    private $settings;

    public function __construct()
    {
        // No default constructor
//        parent::__construct();

        require dirname(__FILE__).'/../settings.php';

        $this->redis = new \Redis();
        $this->redis->connect($this->settings['rhost'], $this->settings['rport']);
        $this->redis->auth($this->settings['rpassword']);
    }

    /**
     * Avoid CSFR
     *
     * @return string
     */
    private function generateState() {
        // RNG
        $random = rand(0, 10000);
        $session = session_id();

        // Save session and rng to redis
        $this->redis->set($session, $random);

        // return state
        return $session . __STATE__SPLTR__ . $random;
    }

    /**
     * Compose wechat login link
     *
     * @param $state
     * @return string
     */
    public function wechatLoginLink() {
        $state = $this->generateState();
        $uri = $this->getConf('wechatRediURI');
        $uri = urlencode($uri);

        $wechatLink = $this->getConf('wechatlink')
            . '?appid=' . $this->getConf('wechatAppId')
            . '&redirect_uri=' . $uri
            . '&response_type=' . $this->getConf('wechatRespType')
            . '&scope=' . $this->getConf('wechatScope')
            . '&state=' . $state
            . '#wechat_redirect';
        return $wechatLink;
    }

    /**
     * Compose wechat access token link
     *
     * @param $code
     * @return string
     */
    public function wechatTokenURL($code) {
        $url = $this->getConf('wechatTokenURL')
            . '?appid=' . $this->getConf('wechatAppId')
            . '&secret=' . $this->getConf('wechatSecret')
            . '&code=' . $code
            . '&grant_type=authorization_code';
        return $url;
    }

    /**
     * Check if the state param is valid
     *
     * @param $state
     * @return bool
     */
    public function checkState($state) {
        $sessionAndRN = explode(__STATE__SPLTR__, $state);
        $session = $sessionAndRN[0];
        $randonNum = $sessionAndRN[1];

        if ($session && $randonNum) {
            $cachedRandonNum = $this->redis->get($session);
            if ($cachedRandonNum == $randonNum) {
                return true;
            }
        }
        return false;
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