<?php
require_once __DIR__ . '/Bootstrap.php';

/**
 * 给 Typecho 添加 Livephoto 的支持
 * 
 * @package LivePhotoKit
 * @author Sora Jin
 * @version 1.0.0
 * @link https://jcl.moe
 */
class LivePhotoKit_Plugin implements Typecho_Plugin_Interface {
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate() {
        Typecho_Plugin::factory('Widget_Abstract_Contents')->excerptEx = array('LivePhotoKit_Plugin', 'parse');
        Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array('LivePhotoKit_Plugin', 'parse');
        Typecho_Plugin::factory('Widget_Archive')->header = array('LivePhotoKit_Plugin', 'outputHeader');
        Typecho_Plugin::factory('Widget_Archive')->footer = array('LivePhotoKit_Plugin', 'outputFooter');
        
        Bootstrap::fetch ("https://api.aim.moe/Counter/Plugin", [
            'siteName' => $GLOBALS['options']->title,
            'siteUrl' => $GLOBALS['options']->siteUrl,
            'plugin' => 'LivephotoKit',
            'version' => Plugin_Const::VERSION
        ], 'POST');
    }
    
    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate() {
        $data = Bootstrap::fetch ("https://api.aim.moe/Counter/Plugin?siteName=" . $GLOBALS['options']->title . '&siteUrl=' . $GLOBALS['options']->siteUrl . '&plugin=LivephotoKit');
        $data = json_decode ($data, true);
        Bootstrap::fetch ("https://api.aim.moe/Counter/Plugin/" . $data[0]->pid, 'DELETE');
    }
    
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config (Typecho_Widget_Helper_Form $form) {
        $mode = new Typecho_Widget_Helper_Form_Element_Radio('mode', 
            [
                '0' => '未开启', 
                '1' => '已开启'
            ], 0, '是否开启 Pjax', '');
        $form->addInput($mode->addRule('enum', _t('必须选择一个哦～'), array(0, 1)));
        $height = new Typecho_Widget_Helper_Form_Element_Text('height', NULL,  '480', _t('Livephoto框的高度'), _t('单位: px'));
        $form->addInput($height->addRule('required', _t('您必须填写一个正确的高度')));
    }
    
    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}
    
    /**
     * 插件实现方法
     *
     * @access public
     * @return void
     */
    public static function parse ($text, $widget, $lastResult) {
        if ('feed' == $widget->parameter->type) {
            $text = empty($lastResult) ? $text : $lastResult;
            $text = preg_replace("/\[Livephoto(.*?)\\/\](<br>|<br \/>)*/is", ' &lt 访问完整版以查看 Livephoto &gt ', $text);
            return trim($text);
        }
        $text = empty($lastResult) ? $text : $lastResult;
        
        if ($widget instanceof Widget_Archive) {
            if (false === strpos($text, '[')) {
                return $text;
            }
            $pattern = self::get_shortcode_regex(array('Livephoto'));
            $text = preg_replace_callback("/$pattern/", array('LivePhotoKit_Plugin', 'parseCallback'), $text);
        }

        return $text;
    }

    public static function parseCallback($matches) {
        $_cfg = $GLOBALS['options']->plugin('LivePhotoKit');

        // allow [[player]] syntax for escaping the tag
        if ($matches[1] == '[' && $matches[6] == ']') {
            return substr($matches[0], 1, -1);
        }
        //还原转义后的html
        $attr = htmlspecialchars_decode ($matches[3]);
        $atts = self::shortcode_parse_atts ($attr);
        $id = md5 (md5 ($_SERVER['HTTP_HOST'] . $atts['video'] . $atts['photo'] . time()));

        $ret = '<div id="' . $id . '" class="livePhoto" data-live-photo data-photo-src="' . $atts['photo'] .'" data-video-src="' . $atts['video'] .'" style="height: ' . $_cfg->height . 'px"></div>';
        $ret .= <<< EOF
<script>
    'use strict';
    let e_{$id} = document.getElementById('{$id}');
    LivePhotosKit.Player(e_{$id});
    e_{$id}.addEventListener('photoload', (ev) => {
        console.log('PHOTO_LOADED');
    })
    
    e_{$id}.addEventListener('videoload', (ev) => {
        console.log('VIDEO_LOADED');
    })
    
    e_{$id}.addEventListener('canplay', (ev) => {
        console.log('LIVE_PHOTO_RD');
    })
    
    e_{$id}.addEventListener('error', (ev) => {
        if (typeof ev.detail.errorCode === 'number') {
            switch (ev.detail.errorCode) {
            case LivePhotosKit.Errors.IMAGE_FAILED_TO_LOAD:
                console.log('IMAGE_FAILED_TO_LOAD');
                break;
            case LivePhotosKit.Errors.VIDEO_FAILED_TO_LOAD:
                console.log('VIDEO_FAILED_TO_LOAD');
                break;
            }
        } else {
            console.error(ev.detail.error);
        }
        console.log(ev);
    })
</script>
EOF;
        return $ret;
    }

    public static function outputHeader() {
        echo <<<EOF
<style>
.livePhoto {
    margin: 0 auto;
    margin-bottom: 10px;
}
</style>     
EOF;
    }
    

    public static function outputFooter() {
        $_cfg = $GLOBALS['options']->plugin('LivePhotoKit');

        echo '<script src="https://cdn.apple-livephotoskit.com/lpk/1/livephotoskit.js"></script>' . PHP_EOL;
        if ($_cfg->mode == 1) {
            echo <<<EOF
<script>
'use strict';
$(document).on('pjax:complete', function() {
    if ($('.livePhoto').length > 0) {
        let livePhotos = document.getElementsByClassName('livePhoto');
        for(let i = 0; i < livePhotos.length; ++i){
            let e = livePhotos[i];
            LivePhotosKit.createPlayer(e);
            e.addEventListener('photoload', (ev) => {
                console.log('PHOTO_LOADED');
            })
            
            e.addEventListener('videoload', (ev) => {
                console.log('VIDEO_LOADED');
            })
            
            e. addEventListener('canplay', (ev) => {
                console.log('LIVE_PHOTO_RD');
            })
            
            e.addEventListener('error', (ev) => {
                if (typeof ev.detail.errorCode === 'number') {
                    switch (ev.detail.errorCode) {
                    case LivePhotosKit.Errors.IMAGE_FAILED_TO_LOAD:
                        console.log('IMAGE_FAILED_TO_LOAD');
                        break;
                    case LivePhotosKit.Errors.VIDEO_FAILED_TO_LOAD:
                        console.log('VIDEO_FAILED_TO_LOAD');
                        break;
                    }
                } else {
                    console.error(ev.detail.error);
                }
                console.log(ev);
            })
        }
    }
});
</script>     
EOF;
        }
    }

    /**
     * Retrieve all attributes from the shortcodes tag.
     *
     * The attributes list has the attribute name as the key and the value of the
     * attribute as the value in the key/value pair. This allows for easier
     * retrieval of the attributes, since all attributes have to be known.
     *
     * @link https://github.com/WordPress/WordPress/blob/master/wp-includes/shortcodes.php
     * @since 2.5.0
     *
     * @param string $text
     * @return array|string List of attribute values.
     *                      Returns empty array if trim( $text ) == '""'.
     *                      Returns empty string if trim( $text ) == ''.
     *                      All other matches are checked for not empty().
     */
    private static function shortcode_parse_atts($text) {
        $atts = array();
        $pattern = '/([\w-]+)\s*=\s*"([^"]*)"(?:\s|$)|([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)|([\w-]+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|(\S+)(?:\s|$)/';
        $text = preg_replace("/[\x{00a0}\x{200b}]+/u", " ", $text);
        if (preg_match_all($pattern, $text, $match, PREG_SET_ORDER)) {
            foreach ($match as $m) {
                if (!empty($m[1]))
                    $atts[strtolower($m[1])] = stripcslashes($m[2]);
                elseif (!empty($m[3]))
                    $atts[strtolower($m[3])] = stripcslashes($m[4]);
                elseif (!empty($m[5]))
                    $atts[strtolower($m[5])] = stripcslashes($m[6]);
                elseif (isset($m[7]) && strlen($m[7]))
                    $atts[] = stripcslashes($m[7]);
                elseif (isset($m[8]))
                    $atts[] = stripcslashes($m[8]);
            }
            // Reject any unclosed HTML elements
            foreach ($atts as &$value) {
                if (false !== strpos($value, '<')) {
                    if (1 !== preg_match('/^[^<]*+(?:<[^>]*+>[^<]*+)*+$/', $value)) {
                        $value = '';
                    }
                }
            }
        } else {
            $atts = ltrim($text);
        }
        return $atts;
    }
    /**
     * Retrieve the shortcode regular expression for searching.
     *
     * The regular expression combines the shortcode tags in the regular expression
     * in a regex class.
     *
     * The regular expression contains 6 different sub matches to help with parsing.
     *
     * 1 - An extra [ to allow for escaping shortcodes with double [[]]
     * 2 - The shortcode name
     * 3 - The shortcode argument list
     * 4 - The self closing /
     * 5 - The content of a shortcode when it wraps some content.
     * 6 - An extra ] to allow for escaping shortcodes with double [[]]
     *
     * @link https://github.com/WordPress/WordPress/blob/master/wp-includes/shortcodes.php
     * @since 2.5.0
     *
     *
     * @param array $tagnames List of shortcodes to find. Optional. Defaults to all registered shortcodes.
     * @return string The shortcode search regular expression
     */
    private static function get_shortcode_regex($tagnames = null) {
        $tagregexp = join('|', array_map('preg_quote', $tagnames));
        // WARNING! Do not change this regex without changing do_shortcode_tag() and strip_shortcode_tag()
        // Also, see shortcode_unautop() and shortcode.js.
        return
            '\\['                              // Opening bracket
            . '(\\[?)'                           // 1: Optional second opening bracket for escaping shortcodes: [[tag]]
            . "($tagregexp)"                     // 2: Shortcode name
            . '(?![\\w-])'                       // Not followed by word character or hyphen
            . '('                                // 3: Unroll the loop: Inside the opening shortcode tag
            . '[^\\]\\/]*'                   // Not a closing bracket or forward slash
            . '(?:'
            . '\\/(?!\\])'               // A forward slash not followed by a closing bracket
            . '[^\\]\\/]*'               // Not a closing bracket or forward slash
            . ')*?'
            . ')'
            . '(?:'
            . '(\\/)'                        // 4: Self closing tag ...
            . '\\]'                          // ... and closing bracket
            . '|'
            . '\\]'                          // Closing bracket
            . '(?:'
            . '('                        // 5: Unroll the loop: Optionally, anything between the opening and closing shortcode tags
            . '[^\\[]*+'             // Not an opening bracket
            . '(?:'
            . '\\[(?!\\/\\2\\])' // An opening bracket not followed by the closing shortcode tag
            . '[^\\[]*+'         // Not an opening bracket
            . ')*+'
            . ')'
            . '\\[\\/\\2\\]'             // Closing shortcode tag
            . ')?'
            . ')'
            . '(\\]?)';                          // 6: Optional second closing brocket for escaping shortcodes: [[tag]]
    }
}
