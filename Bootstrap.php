<?php
    define('__COMMENT2TELEGRAM_PLUGIN_ROOT__', __DIR__);
    
    require_once __COMMENT2TELEGRAM_PLUGIN_ROOT__ . '/lib/Const.php';
    
    $GLOBALS['options'] = Helper::options();

    class Bootstrap {
        public static function fetch ($url, $postdata = null, $method = 'GET') {
            $ch = curl_init ();
            curl_setopt ($ch, CURLOPT_URL, $url);
            switch ($method) {
                case 'GET':

                    break;
                case 'POST':
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postdata));
                    break;
                case 'PUT':
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
                    break;
                case 'DELETE':
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                    break;
            }
            curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
            $re = curl_exec ($ch);
            curl_close ($ch);
            
            return $re;
        }   
    }
