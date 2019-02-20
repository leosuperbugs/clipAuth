<?php
namespace dokuwiki\paperclip;
/**
 * Created by PhpStorm.
 * User: leo
 * Date: 2019/1/21
 * Time: 6:42 PM
 */

// Abandoned

//class paperclipCache
//{
//    private $settings;
//    private $redis;
//
//
//
//    public function __construct()
//    {
//        require dirname(__FILE__).'/settings.php';
//
//        $this->redis = new \Redis();
//        $this->redis->connect($this->settings['rhost'], $this->settings['rport']);
//        $this->redis->auth($this->settings['rpassword']);
//    }
//
//    public function addStateRecord($session, $randnum) {
//        $this->redis->set($session, $randnum);
//    }
//
//    public function getStateRecord($session) {
//        return $this->redis->get($session);
//    }
//
//    p
//}