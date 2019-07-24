<?php
    /**
     * Created by PhpStorm.
     * User: hanfeng
     * Date: 2018/10/15
     * Time: 14:54
     */
    class Rsa
    {
        private static $key='';
        public function __construct($key)
        {
            self::$key = $key;
        }

        /**
         * RSA加密
         * @author LiuFajun
         * @param string $str
         * @return bool|string
         */
        static function encrypt($str = '')
        {
            try {
                if (!extension_loaded('openssl')) {
                    return false;
                }

//                if (!file_exists(self::$key_path)) {
//                    return false;
//                }

                $publicKey=self::$key;
//                if (!($publicKey = @file_get_contents(self::$key_path))) {
//                    return false;
//                }

                $publicKey = openssl_pkey_get_public($publicKey);

                $_ecncryptStr = '';
                if (!openssl_public_encrypt($str, $_ecncryptStr, $publicKey)) {
                    echo "bbbb";
                    return false;
                }

                return $_ecncryptStr;

            } catch (\Exception $e) {
                return false;
            }
        }

        /**
         * RSA角密
         * @author LiuFajun
         * @param string $encryptData
         * @return bool|string
         */
        static function decrypt($decryptData = '')
        {
            try {
                if (!extension_loaded('openssl')) {
                    return false;
                }

                if (!file_exists(APPConf::$private_key_path)) {
                    return false;
                }

                if (!($privateKey = @file_get_contents(APPConf::$private_key_path))) {
                    return false;
                }

                $privateKey = openssl_pkey_get_private($privateKey);
                $_decryptStr = '';
                if (!openssl_private_decrypt($decryptData, $_decryptStr, $privateKey)) {
                    return false;
                }

                return $_decryptStr;

            } catch (\Exception $e) {
                return false;
            }
        }

        public static function urlsafeB64Encode($input)
        {
            return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
        }

        public static function urlsafeB64Decode($input)
        {
            $remainder = strlen($input) % 4;
            if ($remainder) {
                $padlen = 4 - $remainder;
                $input .= str_repeat('=', $padlen);
            }
            return base64_decode(strtr($input, '-_', '+/'));
        }
    }
