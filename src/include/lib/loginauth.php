<?php
/**
 * 登录验证
 * @copyright (c) Emlog All Rights Reserved
 */

class LoginAuth{

    const LOGIN_ERROR_USER = -1;
    const LOGIN_ERROR_PASSWD = -2;
    const LOGIN_ERROR_AUTHCODE = -3;

    /**
     * 验证用户是否处于登录状态
     */
    public static function isLogin() {
        global $userData;
        $auth_cookie = '';
        if(isset($_COOKIE[AUTH_COOKIE_NAME])) {
            $auth_cookie = $_COOKIE[AUTH_COOKIE_NAME];
        } elseif (isset($_POST[AUTH_COOKIE_NAME])) {
            $auth_cookie = $_POST[AUTH_COOKIE_NAME];
        } else{
            return false;
        }

        if(($userData = self::validateAuthCookie($auth_cookie)) === false) {
            return false;
        }else{
            return true;
        }
    }

    /**
     * 验证密码/用户
     *
     * @param string $username
     * @param string $password
     * @param string $imgcode
     * @param string $logincode
     */
    public static function checkUser($username, $password, $imgcode, $logincode = false) {
        session_start();
        if (trim($username) == '' || trim($password) == '') {
            return false;
        } else {
            $sessionCode = isset($_SESSION['code']) ? $_SESSION['code'] : '';
            $logincode = false === $logincode ? Option::get('login_code') : $logincode;
            if ($logincode == 'y' && (empty($imgcode) || $imgcode != $sessionCode)) {
                return self::LOGIN_ERROR_AUTHCODE;
            }
            $userData = self::getUserDataByLogin($username);
            if (false === $userData) {
                return self::LOGIN_ERROR_USER;
            }
            $hash = $userData['password'];
            if (true === CheckPassword($password, $hash)){
                return true;
            } else{
                return self::LOGIN_ERROR_PASSWD;
            }
        }
    }

    /**
     * 登录页面
     */
    public static function loginPage($errorCode = NULL) {
        Option::get('login_code') == 'y' ?
        $ckcode = "<span>验证码</span>
        <div class=\"val\"><input name=\"imgcode\" id=\"imgcode\" type=\"text\" />
        <img src=\"../include/lib/checkcode.php\" align=\"absmiddle\"></div>" :
        $ckcode = '';
        $error_msg = '';
        if ($errorCode) {
            switch ($errorCode) {
                case self::LOGIN_ERROR_AUTHCODE:
                    $error_msg = '验证错误，请重新输入';
                    break;
                case self::LOGIN_ERROR_USER:
                    $error_msg = '用户名错误，请重新输入';
                    break;
                case self::LOGIN_ERROR_PASSWD:
                    $error_msg = '密码错误，请重新输入';
                    break;
            }
        }
        require_once View::getView('login');
        View::output();
    }

    /**
     * 通过登录名查询管理员信息
     *
     * @param string $userLogin User's username
     * @return bool|object False on failure, User DB row object
     */
    public static function getUserDataByLogin($userLogin) {
        $DB = Database::getInstance();
        if (empty($userLogin)) {
            return false;
        }
        $userData = false;
        if (!$userData = $DB->once_fetch_array("SELECT * FROM ".DB_PREFIX."user WHERE username = '$userLogin'")) {
            return false;
        }
        $userData['nickname'] = htmlspecialchars($userData['nickname']);
        $userData['username'] = htmlspecialchars($userData['username']);
        return $userData;
    }

    /**
     * 写用于登录验证cookie
     *
     * @param int $user_id User ID
     * @param bool $remember Whether to remember the user or not
     */
    public static function setAuthCookie($user_login, $ispersis = false) {
        if ($ispersis) {
            $expiration  = time() + 3600 * 24 * 30 * 12;
        } else {
            $expiration = null;
        }
        $auth_cookie_name = AUTH_COOKIE_NAME;
        $auth_cookie = self::generateAuthCookie($user_login, $expiration);
        setcookie($auth_cookie_name, $auth_cookie, $expiration,'/');
    }

    /**
     * 生成登录验证cookie
     * Edit by Star.Yu
     */
    private static function generateAuthCookie($user_login, $expiration) {
		$data = $user_login . '|' . $expiration . '|' . AUTH_KEY;
		$hash = HashPassword($data);
        $cookie = $user_login . '|' . $expiration . '|' . $hash;
        return $cookie;
    }

    /**
     * 验证cookie
     * Edit by Star.Yu
     */
    private static function validateAuthCookie($cookie = '') {
        if (empty($cookie)) {
            return false;
        }

        $cookie_elements = explode('|', $cookie);
        if (count($cookie_elements) != 3) {
            return false;
        }

        list($username, $expiration, $hmac) = $cookie_elements;

        if (!empty($expiration) && $expiration < time()) {
            return false;
        }
        
		$data = $username . '|' . $expiration . '|' . AUTH_KEY;

        if (!CheckPassword($data,$hmac)) {
            return false;
        }

        $user = self::getUserDataByLogin($username);
        if (!$user) {
            return false;
        }
        return $user;
    }

    /**
     * 生成token，防御CSRF攻击
     */
    public static function genToken() {
        $token_cookie_name = 'EM_TOKENCOOKIE_' . md5(substr(AUTH_KEY, 16, 32) . UID);
        if (isset($_COOKIE[$token_cookie_name])) {
            return $_COOKIE[$token_cookie_name];
        } else {
            $token = HashPassword(UID);
            setcookie($token_cookie_name, $token, 0, '/');
            return $token;
        }
    }

    /**
     * 检查token，防御CSRF攻击
     */
    public static function checkToken(){
        $token = isset($_REQUEST['token']) ? addslashes($_REQUEST['token']) : '';
        if (!CheckPassword(UID,$token)) {
            emMsg('权限不足，token error');
        }
    }
}
