<?php
//获取网站Favicon服务接口
namespace Jerrybendy\Favicon;
/*Favicon 获取类*/
class Favicon
{/*是否使用调试模式，默认不启用false true*/
    public $debug_mode = false;
    //保存传入的参数,其中origin_url保存传入的url参数的原始字符串信息,以及一些额外的参数及暂存的数据
    private $params = array();
    //完整的形如  http://xxx.xxx.com:8888 这样的地址
    private $full_host = '';
    //包含获取到的最终的二进制数据
    private $data = NULL;
    //最后一次请求花费的时间
    private $_last_time_spend = 0;
    //最后一次请求消耗的内存
    private $_last_memory_usage = 0;
    //文件映射，用于在规则匹配时直接返回内容
    private $_file_map = [];
    //默认图标，如果设置了此文件将会在请求失败时返回这个图标
    private $_default_icon = '';
    /**获取网站Favicon并输出
     * @param string $url    输入的网址
     * @param bool   $return 是要求返回二进制内容还是直接显示它*/
    public function getFavicon($url = '', $return = FALSE)
    {
        //验证传入参数
        if (!$url) {
            throw new \InvalidArgumentException(__CLASS__ . ': 网址不能为空', E_ERROR);
        }
        $this->params['origin_url'] = $url;
        //解析URL参数
        $ret = $this->formatUrl($url);
        if (!$ret) {
            throw new \InvalidArgumentException(__CLASS__ . ': 无效的网址', E_WARNING);
        }
        //开始获取图标过程
        $time_start = microtime(TRUE);
        //$this->_log_message('==开始获取图标, ' . $url);
        //获取 favicon bin 数据
        $data = $this->getData();
        //$this->_log_message('√成功获取图标' . PHP_EOL);
        //设置输出Header信息,输出公共头
        if ($data === FALSE && $this->_default_icon) {
            $data = @file_get_contents($this->_default_icon);
        }
        if ($return) {
            return $data;
        } else {
            if ($data !== FALSE) {
                foreach ($this->getHeader() as $header) {
                    @header($header);
                }
                echo $data;
            } else {
                header('Content-type: application/json');
                echo json_encode(array('status' => -1, 'msg' => '未知错误'));
            }
        }
        return NULL;
    }
    //获取输出 header,添加缓存时间
    public function getHeader() {
        return array('X-Robots-Tag: noindex, nofollow','Content-type: image/x-icon','Cache-Control: public, max-age=604800');
    }
    /**设置一组正则表达式到文件的映射，
     * 用于在规则匹配成功时直接从本地或指定的URL处获取内容并返回
     * @param array $map 映射内容必须是   正则表达式  =>  文件路径  的数组，
     *                   文件通过 file_get_contents 函数读取，所以可以是本地文件或网络文件路径，
     *                   但必须要保证对应的文件是一定能被读取的
     */
    public function setFileMap(array $map) {
        $this->_file_map = $map;
        return $this;
    }
    /**@param string $filePath
     * @return $this
     */
    public function setDefaultIcon($filePath) {
        $this->_default_icon = $filePath;
        return $this;
    }
    // 获取最终的Favicon图标数据, 此为该类获取图标的核心函数
    protected function getData()
    { 
        // 尝试匹配映射
        $this->data = $this->_match_file_map();
        //判断data中有没有来自插件写入的内容
        if ($this->data !== NULL) {
            $this->_log_message('【0】映像: ' . $this->full_host);
            return $this->data;
        }
        //从网络获取图标
        //从源网址获取HTML内容并解析其中的LINK标签
        $html = $this->getFile($this->params['origin_url']);
        if ($html && $html['status'] == 'OK') {
            /*
             * 2016-01-31
             * FIX #1
             * 对取到的HTML内容进行删除换行符的处理,避免link信息折行导致的正则匹配失败
             */
            $html = str_replace(array("\n", "\r"), '', $html['data']);
            //匹配完整的LINK标签，再从LINK标签中获取HREF的值
            if (@preg_match('/((<link[^>]+rel=.(icon|shortcut icon|alternate icon|apple-touch-icon)[^>]+>))/i', $html, $match_tag)) {
                if (isset($match_tag[1]) && $match_tag[1] && @preg_match('/href=(\'|\")(.*?)\1/i', $match_tag[1], $match_url)) {
                    if (isset($match_url[2]) && $match_url[2]) {
                        //解析HTML中的相对URL 路径
                        $match_url[2] = $this->filterRelativeUrl(trim($match_url[2]), $this->params['origin_url']);
                        $icon = $this->getFile($match_url[2],true);
                        if ($icon && $icon['status'] == 'OK') {
                            //$this->_log_message("【1】网络: {$match_url[2]}");
                            $this->data = $icon['data'];
                        }
                    }
                }
            }
        }
        if ($this->data != NULL) {
            return $this->data;
        }
        //用来在第一次获取后保存可能的重定向后的地址
        $redirected_url = $html['real_url'];
        //未能从LINK标签中获取图标（可能是网址无法打开，或者指定的文件无法打开，或未定义图标地址）
        //将使用网站根目录的文件代替
        $data = $this->getFile($this->full_host . '/favicon.ico',true);
        if ($data && $data['status'] == 'OK') {
            //$this->_log_message("【2】根目录: {$this->full_host}/favicon.ico");
            $this->data = $data['data'];
        } else {
            //如果直接取根目录文件返回了301或404，先读取重定向，再从重定向的网址获取
            $ret = $this->formatUrl($redirected_url);
            if ($ret) {
                //最后的尝试，从重定向后的网址根目录获取favicon文件
                $data = $this->getFile($this->full_host . '/favicon.ico',true);
                if ($data && $data['status'] == 'OK') {
                    //$this->_log_message("【3】重定向: {$this->full_host}/favicon.ico");
                    $this->data = $data['data'];
                }
            }
        }
        //从其他api获取图像111
        //if ($this->data == NULL) {
        //    $thrurl='https://t3.gstatic.cn/faviconV2?client=SOCIAL&type=FAVICON&fallback_opts=TYPE,SIZE,URL&size=64&url='.$this->full_host;
        //    $icon = file_get_contents($thrurl);
        //    if($icon){   
        //        $this->_log_message("【4】API：https://t3.gstatic.cn/faviconV2?client=SOCIAL&type=FAVICON&fallback_opts=TYPE,SIZE,URL&size=64&url=" . parse_url($this->full_host)['host'] );
        //        $this->data = $icon; 
        //    }
        //}
        //从其他api获取图像222
        if ($this->data == NULL) {
            $thrurl='https://api.iowen.cn/favicon/'.parse_url($this->full_host)['host'].'.png';
            //$arrContextOptions=array("ssl"=>array("verify_peer"=>false,"verify_peer_name"=>false,"allow_self_signed"=>true,),);
            //$icon = file_get_contents($thrurl, false, stream_context_create($arrContextOptions));
            $icon = file_get_contents($thrurl);
            //$data = $this->getFile($thrurl,true, stream_context_create($arrContextOptions));
            if($icon && md5($icon) != "05231fb6b69aff47c3f35efe09c11ba0"){
                $this->_log_message("【5】API：https://api.iowen.cn/favicon/".parse_url($this->full_host)['host'].".png");
                //$this->_log_message("MD5:".md5($icon) . PHP_EOL);
                $this->data = $icon;
            }
        }
        //各个方法都试过了，还是获取不到。。。
        if ($this->data == NULL) {
            $this->_log_message(" × 无法从 " . parse_url($this->full_host)['host'] . " 获取图标");
            return FALSE;
        }
        return $this->data;
    }
    /**解析一个完整的URL中并返回其中的协议、域名和端口部分
     * 同时会设置类中的parsed_url和full_host属性
     */
    public function formatUrl($url)
    {
        //尝试解析URL参数，如果解析失败的话再加上http前缀重新尝试解析
        $parsed_url = parse_url($url);
        if (!isset($parsed_url['host']) || !$parsed_url['host']) {
            //在URL的前面加上http://
            // 添加前缀
            if (!preg_match('/^https?:\/\/.*/', $url))
                $url = 'http://' . $url;
            //解析URL并将结果保存到 $this->url
            $parsed_url = parse_url($url);
            if ($parsed_url == FALSE) {
                return FALSE;
            } else {
                //能成功解析的话就可以设置原始URL为这个添加过http://前缀的URL
                $this->params['origin_url'] = $url;
            }
        }
        $this->full_host = (isset($parsed_url['scheme']) ? $parsed_url['scheme'] : 'http') . '://' . $parsed_url['host'] . (isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '');
        return $this->full_host;
    }
    //把从HTML源码中获取的相对路径转换成绝对路径
    private function filterRelativeUrl($url, $URI = '')
    {
        //STEP1: 先去判断URL中是否包含协议，如果包含说明是绝对地址则可以原样返回
        if (strpos($url, '://') !== FALSE) {
            return $url;
        }
        //STEP2: 解析传入的URI
        $URI_part = parse_url($URI);
        if ($URI_part == FALSE)
            return FALSE;
        $URI_root = $URI_part['scheme'] . '://' . $URI_part['host'] . (isset($URI_part['port']) ? ':' . $URI_part['port'] : '');
        //STEP3: 如果URL以左斜线开头，表示位于根目录
        // 如果URL以 // 开头,表示是省略协议的绝对路径,可以添加协议后返回
        if (substr($url, 0, 2) === '//') {
            return $URI_part['scheme'] . ':' . $url;
        }
        if (strpos($url, '/') === 0) {
            return $URI_root . $url;
        }
        //STEP4: 不位于根目录，也不是绝对路径，考虑如果不包含'./'的话，需要把相对地址接在原URL的目录名上
        $URI_dir = (isset($URI_part['path']) && $URI_part['path']) ? '/' . ltrim(dirname($URI_part['path']), '/') : '';
        if (strpos($url, './') === FALSE) {
            if ($URI_dir != '') {
                return $URI_root . $URI_dir . '/' . $url;
            } else {
                return $URI_root . '/' . $url;
            }
        }
        //STEP5: 如果相对路径中包含'../'或'./'表示的目录，需要对路径进行解析并递归
        //STEP5.1: 把路径中所有的'./'改为'/'，'//'改为'/'
        $url = preg_replace('/[^\.]\.\/|\/\//', '/', $url);
        if (strpos($url, './') === 0)
            $url = substr($url, 2);
        //STEP5.2: 使用'/'分割URL字符串以获取目录的每一部分进行判断
        $URI_full_dir = ltrim($URI_dir . '/' . $url, '/');
        $URL_arr = explode('/', $URI_full_dir);
        // 这里为了解决有些网站在根目录下的文件也使用 ../img/favicon.ico 这种形式的错误,
        // 对这种本来不合理的路径予以通过, 并忽略掉前面的两个点 (没错, 我说的是 gruntjs 的官网)
        if ($URL_arr[0] == '..') {
            array_shift($URL_arr);
        }
        //因为数组的第一个元素不可能为'..'，所以这里从第二个元素可以循环
        $dst_arr = $URL_arr;  //拷贝一个副本，用于最后组合URL
        for ($i = 1; $i < count($URL_arr); $i++) {
            if ($URL_arr[$i] == '..') {
                $j = 1;
                while (TRUE) {
                    if (isset($dst_arr[$i - $j]) && $dst_arr[$i - $j] != FALSE) {
                        $dst_arr[$i - $j] = FALSE;
                        $dst_arr[$i] = FALSE;
                        break;
                    } else {
                        $j++;
                    }
                }
            }
        }
        //STEP6: 组合最后的URL并返回
        $dst_str = $URI_root;
        foreach ($dst_arr as $val) {
            if ($val != FALSE)
                $dst_str .= '/' . $val;
        }
        return $dst_str;
    }
    /**从指定URL获取文件,添加请求内容判断
     * @param string $url
     * @param bool   $isimg 是否为图片
     * @param int    $timeout 超时值，默认为10秒
     * @return string 成功返回获取到的内容，同时设置 $this->content，失败返回FALSE
     */
    private function getFile($url, $isimg = false, $timeout = 5)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        //修复ssl的错误
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        /** @var mixed 只获取500kb的数据，如果目标图片超过500kb，则丢弃 */
        $request_headers = array('Range: bytes=0-512000'); //500 KB
        curl_setopt( $ch, CURLOPT_FORBID_REUSE, true );
        $request_headers[] = 'Connection: close';
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $request_headers );
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);
        //执行重定向获取
        $ret = $this->curlExecFollow($ch, 2);
        if($isimg){
            $mime=curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $mimeArray=explode('/',$mime);
        }
        $arr = array(
            'status'   => 'FAIL',
            'data'     => '',
            'real_url' => ''
        );
        if(!$isimg ||  $mimeArray[0] == 'image'){
            if ($ret != false) {
                $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $arr = array(
                    'status'   => ($status >= 200 && $status <= 299) ? 'OK' : 'FAIL',
                    'data'     => $ret,
                    'real_url' => curl_getinfo($ch, CURLINFO_EFFECTIVE_URL)
                );
            }
            curl_close($ch);
            return $arr;
        }else{
            //$this->_log_message("-->不是图片：{$url}");
            return $arr;
        }
    }
    //使用跟综重定向的方式查找被301/302跳转后的实际地址，并执行curl_exec
    private function curlExecFollow( &$ch, $maxredirect = null) { 
        $mr = $maxredirect === null ? 5 : intval($maxredirect); 
        if (ini_get('open_basedir') == '' && ini_get('safe_mode' == 'Off')) { 
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $mr > 0); 
            curl_setopt($ch, CURLOPT_MAXREDIRS, $mr); 
        } else { 
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); 
            if ($mr > 0) { 
                $newurl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL); 
                $rch = curl_copy_handle($ch); 
                curl_setopt($rch, CURLOPT_HEADER, true); 
                curl_setopt($rch, CURLOPT_NOBODY, true); 
                curl_setopt($rch, CURLOPT_NOSIGNAL, 1);
                curl_setopt($rch, CURLOPT_CONNECTTIMEOUT_MS, 800); 
                curl_setopt($rch, CURLOPT_FORBID_REUSE, false); 
                curl_setopt($rch, CURLOPT_RETURNTRANSFER, true); 
                do { 
                    curl_setopt($rch, CURLOPT_URL, $newurl); 
                    $header = curl_exec($rch); 
                    if (curl_errno($rch)) { 
                        $code = 0; 
                    } else { 
                        $code = curl_getinfo($rch, CURLINFO_HTTP_CODE); 
                        if ($code == 301 || $code == 302) { 
                            preg_match('/Location:(.*?)\n/', $header, $matches); 
                            $newurl = trim(array_pop($matches)); 
                            //这里由于部分网站返回的 Location 的值可能是相对网址, 所以还需要做一步,转换成完整地址的操作
                            $newurl = $this->filterRelativeUrl($newurl, $this->params['origin_url']);
                        } else { 
                            $code = 0; 
                        } 
                    } 
                } while ($code && --$mr); 
                curl_close($rch); 
                if (!$mr) { 
                    if ($maxredirect === null) { 
                        trigger_error('重定向太多。跟随重定向时，libcurl 达到最大数量。', E_USER_WARNING); 
                    } else { 
                        $maxredirect = 0; 
                    } 
                    return false; 
                } 
                curl_setopt($ch, CURLOPT_URL, $newurl); 
            } 
        } 
        return curl_exec($ch); 
    } 
    //在设定的映射条件中循环并尝试匹配每一条规则，在条件匹配时返回对应的文件内容
    private function _match_file_map()
    {
        foreach ($this->_file_map as $rule => $file) {
            if (preg_match($rule, $this->full_host)) {
                return @file_get_contents($file);
            }
        }
        return NULL;
    }
    //如果开启了调试模式，将会在控制台或错误日志中输出一些信息
    private function _log_message($message) {
        if ($this->debug_mode) {
            error_log(date('m/d H:i:s：').$message.PHP_EOL,3, "./my-errors.txt");
        }
    }
}