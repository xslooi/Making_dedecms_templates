<?php
/**
 * 织梦模板制作标签替换接口文件 (UTF-8编码)
 * 作者：xslooi
 * ---------------------------------------------------------
 * 使用说明
 * 1、把原来需要织梦标签包裹的HTML文档直接复制粘贴到源代码输入框内
 * 2、选择并点击标签按钮如：arclist、type等
 * 3、程序直接返回已经解析且复制好的代码直接粘贴即可使用，修改下调用ID
 * 4、常用标签、代码等一键复制
 * ---------------------------------------------------------
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(0);  //不限制 执行时间
date_default_timezone_set('Asia/Shanghai');
header("content-Type: text/javascript; charset=utf-8"); //语言强制
header('Cache-Control:no-cache,must-revalidate');
header('Pragma:no-cache');

//todo 环境检测
//1、PHP版本 默认大于5.3
//2、函数库检测：打开文件夹需要 system 函数

//定义根目录
define('WEB_ROOT', str_replace("\\", '/', dirname(__FILE__)) );
define('INPUT_DIR', WEB_ROOT . '/input/');
define('OUTPUT_DIR', WEB_ROOT . '/output/');
define('VENDOR_DIR', WEB_ROOT . '/vendor/');

//======================================================================================================================
//文件说明区
//======================================================================================================================

$source_code = $_POST['sc'];
$platform = $_POST['pf'];
$tag_name = $_POST['tn'];

//======================================================================================================================
//操作逻辑区
//======================================================================================================================
$response_array = array(
    'state' => 1,
    'msg' => 'ok',
    'data' => 'formatCodeOk',
);

//PHP 版本检测
if(version_compare(PHP_VERSION,'5.3.0','<')){
    $response_array = array(
        'state' => -1,
        'msg' => 'PHP 版本必须大于 5.3.0 !',
        'data' => '',
    );
    exit(json_encode($response_array));
}

if(empty($source_code) && 0 !== stripos($tag_name, 'cmd_')){
    $response_array = array(
        'state' => -1,
        'msg' => '源码不能为空！',
        'data' => '',
    );
    exit(json_encode($response_array));
}

if(empty($tag_name)){
    $response_array = array(
        'state' => -1,
        'msg' => '标签名或者操作名不能为空！',
        'data' => '',
    );
    exit(json_encode($response_array));
}


//基本路由：
if(0 === stripos($tag_name, 'cmd_')){
    execute_cmd($tag_name);
}
elseif(0 === stripos($tag_name, 'analysis_')){
    execute_analysis($tag_name, $source_code);
}
elseif('pc' == $platform){
    format_pc($tag_name, $source_code);
}
elseif('wap' == $platform){
    format_wap($tag_name, $source_code);
}
else{
    $response_array = array(
        'state' => -1,
        'msg' => 'Not Found Platform :' . $platform,
        'data' => '',
    );
    exit(json_encode($response_array));
}

//======================================================================================================================
//函数库区
//======================================================================================================================

/**
 * 分析导航中的中文名称列表目前仅实现提取a标签中 <a>栏目名称</a> 标签中的栏目名称
 * todo 此处想分出一二级分类？暂时没有想到办法
 * @param $analysis
 * @param $source_code
 */
function execute_analysis($analysis, $source_code){
    //初始化变量
    $result = array();
    $state = 2;

    if('analysis_nav' == $analysis){
        $html_code = $source_code;

        //源码整理格式化
        $html_code = strip_tags($html_code, '<a>');

        //a标签内容正则获取
        $matches = array();
        $pattern = '/<a .*?>(.*?)<\/a>/i';
        preg_match_all($pattern, $html_code, $matches);
        if(isset($matches[1][0])){
            foreach($matches[1] as $item){
                if(!empty($item)){
                    $result[] = trim($item);
                }
            }
        }

        //a 标签去除解析失败后，直接匹配源码中的中文
        if(empty($result)){
            $html_code = $source_code;
            //正则直接匹配中文列表
            $matches = array();
            $pattern = '/[\sa-zA-z0-9]*[\x{4e00}-\x{9fa5}]+[\sa-zA-z0-9]*/u';
            preg_match_all($pattern, $html_code, $matches);
            if(isset($matches[0][0])){
                foreach($matches[0] as $item){
                    if(!empty($item)){
                        $result[] = trim($item);
                    }
                }
            }

            $result = array_unique($result);
        }
        $state = 2;
        $msg = '栏目名称解析完成';
    }
    elseif('analysis_navtree' == $analysis){

        $html = $source_code;
        // 格式化源代码
        $html = str_replace(array("\r", "\n", "\t", "&nbsp;"), '', $html);  //去掉换行
        $html = preg_replace('/<script[\s|>][\s\S]*?<\/script>/i', '', $html); //去掉js
        $html = preg_replace('/<style[\s|>][\s\S]*?<\/style>/i', '', $html); //去掉css
        $html = preg_replace('/<!--[\s\S]*?-->/', '', $html); //去掉HTML注释
        $html = preg_replace('/ {2,}/', ' ', $html); //多个空格替换为一个
        $html = str_replace("> <", '><', $html);  //去掉两个标签中间的空格
        $html = trim($html); // 去掉两边的空白

        $pattern_html_tags = '/<[a-zA-Z1-6]+[\s|>]{1}/i'; //匹配所有标签 (用\s包括回车)
        $matches_html_tags = array();
        preg_match_all($pattern_html_tags, $html, $matches_html_tags);

        $htmlTags = array();
        if(isset($matches_html_tags[0][0])) {
            foreach ($matches_html_tags[0] as $item) {
                $htmlTag = str_replace(array('<', '>', ' '), '', $item);
                $htmlTags[] = $htmlTag;
            }
        }

        $uniqueHtmlTags = array_unique($htmlTags);
        if(isset($uniqueHtmlTags[0])){
            foreach($uniqueHtmlTags as $item){
                $html = preg_replace('/<' . $item . '.*?>/', '<' . $item . '>', $html);
            }
        }

        $pattern_replace = '/>([\sa-zA-z0-9]*[\x{4e00}-\x{9fa5}\P{L}]+[\sa-zA-z0-9]*)</u'; //替换中文内容的正则
        $html = preg_replace($pattern_replace, '><button class="fixed" data-clipboard-text="${1}" type="button"> ${1} </button><', $html);

        $result = $html;

        $state = 3;
        $msg = '栏目名称解析完成';
    }
    else{
        $msg = $analysis . '解析完成';
    }
    
    
    $response_array = array(
        'state' => $state,
        'msg' => $msg,
        'data' => $result,
    );

    exit(json_encode($response_array));
}

/**
 * 执行本地服务器命令
 * @param $cmd
 */
function execute_cmd($cmd){
    $result = false;
    $msg = '';

    switch($cmd){
        case 'cmd_open_input':
            $command = escapeshellcmd('start ' . INPUT_DIR);
            if(false !== system($command)){
                $result = true;
                $msg = '打开输入目录';
            }
            break;
        case 'cmd_open_output':
            $command = escapeshellcmd('start ' . OUTPUT_DIR);
            if(false !== system($command)){
                $result = true;
                $msg = '打开输出目录';

            }
            break;
        case 'cmd_clear_input':
            deldir(INPUT_DIR);
            $result = true;
            $msg = '清空输入目录';

            break;
        case 'cmd_clear_output':
            deldir(OUTPUT_DIR);
            $result = true;
            $msg = '清空输出目录';

            break;
        case 'cmd_format_html':
            try{
                $rs = format_html();
            }catch (Exception $e){
                log_record($e);
                $msg = '文件有问题请检查';
                $result = false;
                break;
            }

            if($rs){
                $msg = 'HTML代码格式化';
                $result = true;
            }else{
                $msg = '文件夹为空';
                $result = false;
            }

            break;
        case 'cmd_replace_dedecms':
            $rs = dede_replace();
            if($rs){
                $msg = '织梦头部标签替换';
                $result = true;
            }else{
                $msg = '文件夹为空';
                $result = false;
            }
            break;
        case 'cmd_replace_dedecms_seo':
            $rs = dede_replace_seo();
            if($rs){
                $msg = '织梦头部标签替换SEO';
                $result = true;
            }else{
                $msg = '文件夹为空';
                $result = false;
            }
            break;
        case 'cmd_replace_dedecms_perfect':
            $rs = dede_replace_perfect();
            if($rs){
                $msg = '织梦头部标签替换完美版';
                $result = true;
            }else{
                $msg = '文件夹为空';
                $result = false;
            }
            break;
        case 'cmd_replace_telqq':
            $rs = replace_telqq();
            if($rs){
                $msg = '文件中电话QQ等信息替换';
                $result = true;
            }else{
                $msg = '文件夹为空';
                $result = false;
            }
            break;
        case 'cmd_replace_public':
            $rs = replace_public();
            if($rs){
                $msg = '文件中Public/ 替换为 /Public/';
                $result = true;
            }else{
                $msg = '文件夹为空';
                $result = false;
            }
            break;
        case 'cmd_replace_name':
            $rs = replace_name();
            if($rs){
                $msg = '文件名.html替换为.htm';
                $result = true;
            }else{
                $msg = '文件夹为空';
                $result = false;
            }
            break;
        case 'cmd_remove_bom':
            $rs = remove_bom();
            if($rs){
                $msg = '去除文件BOM头完成';
                $result = true;
            }else{
                $msg = '文件夹为空';
                $result = false;
            }
            break;
        case 'cmd_replace_dedecms_equals':
            $rs = replace_dedecms_equals();
            if($rs){
                $msg = 'dede替换自定义公共HTML标签完成-全等替换';
                $result = true;
            }else{
                $msg = '文件夹为空';
                $result = false;
            }
            break;
        case 'cmd_replace_dedecms_regular':
            $rs = replace_dedecms_regular();
            if($rs){
                $msg = 'dede替换自定义公共HTML标签完成-正则替换';
                $result = true;
            }else{
                $msg = '文件夹为空';
                $result = false;
            }
            break;
        case 'cmd_replace_dedecms_closetag':
            $rs = replace_dedecms_closetag();
            if($rs){
                $msg = 'dede替换自定义公共HTML标签完成-CloseTag';
                $result = true;
            }else{
                $msg = '文件夹为空';
                $result = false;
            }
            break;
        default:
            $msg = $cmd;
            $result = cmd_factory($cmd);

    }


    if($result){
        $response_array = array(
            'state' => 0,
            'msg' => $msg .' Execute Success',
            'data' => '',
        );
    }else{
        $response_array = array(
            'state' => -1,
            'msg' => $msg .' Execute Error',
            'data' => '',
        );
    }

    exit(json_encode($response_array));

}

/**
 * 递归删除一个目录包含子目录和文件 (不包括自身)
 * @param $path
 */
function deldir($path){
    //如果是目录则继续
    if(is_dir($path)){
        //扫描一个文件夹内的所有文件夹和文件并返回数组
        $p = scandir($path);
        foreach($p as $val){
            //排除目录中的.和..
            if($val !="." && $val !=".."){
                //如果是目录则递归子目录，继续操作
                if(is_dir($path.$val)){
                    //子目录中操作删除文件夹和文件
                    deldir($path.$val.'/');
                    //目录清空后删除空文件夹
                    @rmdir($path.$val.'/');
                }else{
                    //如果是文件直接删除
                    unlink($path.$val);
                }
            }
        }
    }
}

/**
 * 电脑站标签格式化
 * @param $tag_name
 * @param $source_code
 */
function format_pc($tag_name, $source_code){
    $pc_tags = array(
        'arclist' => array(
            'tag_start' => "{dede:arclist flag='c,p' typeid='15' row='8' col='' titlelen='60' infolen='' imgwidth='' imgheight='' listtype='' isweight='y' orderby='weight' orderway='' keyword=''}",
            'tag_end' => "{/dede:arclist}",
            'inner_time' => "[field:pubdate function=\"MyDate('Y-m-d',@me)\" /]",
            'inner_title' => '[field:title /]',
            'inner_text' => '[field:info /]',
            'inner_img' => '[field:litpic /]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="[field:arcurl/]" title="[field:fulltitle/]" target="_blank"',
                    ),
                'img' =>
                    array(
                        'attrs' => 'src|alt|title',
                        'replace' => ' src="[field:litpic /]" alt="[field:fulltitle/]" title="[field:fulltitle/]"',
                    ),
            ),
        ),

        'channel' => array(
            'tag_start' => "{dede:channel type='son' row='20' currentstyle=\"<li><a class='thisclass' href='~typelink~'>~typename~</a></li>\"}",
            'tag_end' => "{/dede:channel}",
            'inner_title' => '[field:typename/]',
            'inner_text' => '[field:typename/]',
            'inner_img' => '[field:typelitpic/]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="[field:typelink/]" title="[field:typename/]"',
                )
            ),
        ),

        'type' => array(
            'tag_start' => "{dede:type  typeid='1'}",
            'tag_end' => "{/dede:type}",
            'inner_title' => '[field:typename/]',
            'inner_text' => '[field:typename/]',
            'inner_img' => '[field:typelitpic/]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="[field:typelink/]" title="[field:typename/]"',
                ),
                'img' =>
                    array(
                        'attrs' => 'src|alt|title',
                        'replace' => ' src="[field:typelitpic /]" alt="[field:typename/]" title="[field:typename/]"',
                        ),
            ),
        ),

        'flink' => array(
            'tag_start' => "{dede:flink row='99'}",
            'tag_end' => "{/dede:flink}",
            'inner_title' => '[field:webname/]',
            'inner_text' => '[field:webname/]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="[field:url/]" title="[field:webname/]"',
                ),
                'img' =>
                    array(
                        'attrs' => 'src|alt|title',
                        'replace' => ' src="[field:logo /]" alt="[field:webname/]" title="[field:webname/]"',
                    ),
            ),
        ),

        'list' => array(
            'tag_start' => "{dede:list pagesize='12'  titlelen='60' infolen='200' orderby='weight'}",
            'tag_end' => "{/dede:list}",
            'inner_time' => "[field:pubdate function=\"MyDate('Y-m-d',@me)\" /]",
            'inner_title' => '[field:title /]',
            'inner_text' => '[field:description function=\'cn_substr(@me,300)\'/]',
            'inner_img' => '[field:litpic /]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="[field:arcurl/]" title="[field:fulltitle/]" target="_blank"',
                ),
                'img' =>
                    array(
                        'attrs' => 'src|alt|title',
                        'replace' => ' src="[field:litpic /]" alt="[field:fulltitle/]" title="[field:fulltitle/]"',
                    ),
            ),
        ),

        'index_arclist' => array(
            'tag_start' => "{dede:arclist typeid='32' flag='p' orderby='id' orderway='asc'}",
            'tag_end' => "{/dede:arclist}",
            'inner_time' => "[field:pubdate function=\"MyDate('Y-m-d',@me)\" /]",
            'inner_title' => '[field:title /]',
            'inner_text' => '[field:info /]',
            'inner_img' => '[field:litpic /]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="[field:arcurl/]" title="[field:fulltitle/]" target="_blank"',
                ),
                'img' =>
                    array(
                        'attrs' => 'src|alt|title',
                        'replace' => ' src="[field:litpic /]" alt="[field:fulltitle/]" title="[field:fulltitle/]"',
                    ),
            ),
        ),

        'index_channel' => array(
            'tag_start' => "{dede:channel type='top' row='10' }",
            'tag_end' => "{/dede:channel}",
            'inner_title' => '[field:typename/]',
            'inner_text' => '[field:typename/]',
            'inner_img' => '[field:typelink/]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="[field:typelink/]" title="[field:typename/]"',
                )
            ),
        ),

        'index_channel_typeid' => array(
            'tag_start' => "{dede:channel typeid='1' type='son' row='20'}",
            'tag_end' => "{/dede:channel}",
            'inner_title' => '[field:typename/]',
            'inner_text' => '[field:typename/]',
            'inner_img' => '[field:typelitpic/]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="[field:typelink/]" title="[field:typename/]"',
                )
            ),
        ),

        'likearticle' => array(
            'tag_start' => "{dede:likearticle mytypeid='22' row='20' col='' titlelen='60' infolen='200'}",
            'tag_end' => "{/dede:likearticle}",
            'inner_time' => "[field:pubdate function=\"MyDate('Y-m-d',@me)\" /]",
            'inner_title' => '[field:title /]',
            'inner_text' => '[field:description /]',
            'inner_img' => '[field:litpic /]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="[field:arcurl/]" title="[field:fulltitle/]" target="_blank"',
                ),
                'img' =>
                    array(
                        'attrs' => 'src|alt|title',
                        'replace' => ' src="[field:litpic /]" alt="[field:fulltitle/]" title="[field:fulltitle/]"',
                    ),
            ),
        ),

        'productimagelist' => array(
            'tag_start' => "{dede:productimagelist}",
            'tag_end' => "{/dede:productimagelist}",
            'inner_title' => '[field:text/]',
            'inner_text' => '[field:text/]',
            'inner_img' => '[field:imgsrc/]',
            'inner_tags' => array(
                'img' =>
                    array(
                        'attrs' => 'src|alt|title',
                        'replace' => ' src="[field:imgsrc/]" alt="[field:text/]" title="[field:text/]"',
                    ),
            ),
        ),
    );

    if(isset($pc_tags[$tag_name])){
        $response_array = array(
            'state' => 1,
            'msg' => 'succ',
            'data' => '{'.$tag_name.'}' . $source_code . '{/'.$tag_name.'}',
        );

        $response_array['data'] = replace_tags($pc_tags[$tag_name], $source_code);
    }else{
        $response_array = array(
            'state' => -1,
            'msg' => 'error',
            'data' => 'format_pc Not Exists',
        );
    }

    exit(json_encode($response_array));
}

/**
 * 手机站标签格式化
 * @param $tag_name
 * @param $source_code
 */
function format_wap($tag_name, $source_code){
    $wap_tags = array(
        'arclist' => array(
            'tag_start' => "{dede:arclist flag='c,p' typeid='15' row='8' col='' titlelen='60' infolen='' imgwidth='' imgheight='' listtype='' isweight='y' orderby='weight' orderway='' keyword=''}",
            'tag_end' => "{/dede:arclist}",
            'inner_time' => "[field:pubdate function=\"MyDate('Y-m-d',@me)\" /]",
            'inner_title' => '[field:title /]',
            'inner_text' => '[field:info /]',
            'inner_img' => '[field:litpic /]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="/m/view.php?aid=[field:id/]" title="[field:fulltitle/]"',
                ),
                'img' =>
                    array(
                        'attrs' => 'src|alt|title',
                        'replace' => ' src="[field:litpic /]" alt="[field:fulltitle/]" title="[field:fulltitle/]"',
                    ),
            ),
        ),

        'channel' => array(
            'tag_start' => "{dede:channel type='son' row='20' currentstyle=\"<li><a class='thisclass' href='/m/list.php?tid=~id~'>~typename~</a></li>\"}",
            'tag_end' => "{/dede:channel}",
            'inner_title' => '[field:typename/]',
            'inner_text' => '[field:typename/]',
            'inner_img' => '[field:typelitpic/]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="/m/list.php?tid=[field:id /]" title="[field:typename/]"',
                )
            ),
        ),

        'type' => array(
            'tag_start' => "{dede:type  typeid='1'}",
            'tag_end' => "{/dede:type}",
            'inner_title' => '[field:typename/]',
            'inner_text' => '[field:typename/]',
            'inner_img' => '[field:typelitpic/]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="/m/list.php?tid=[field:id /]" title="[field:typename/]"',
                )
            ),
        ),

        'flink' => array(
            'tag_start' => "{dede:flink row='99'}",
            'tag_end' => "{/dede:flink}",
            'inner_title' => '[field:webname/]',
            'inner_text' => '[field:webname/]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="[field:url/]" title="[field:webname/]"',
                ),
                'img' =>
                    array(
                        'attrs' => 'src|alt|title',
                        'replace' => ' src="[field:logo /]" alt="[field:webname/]" title="[field:webname/]"',
                    ),
            ),
        ),

        'list' => array(
            'tag_start' => "{dede:list pagesize='12'  titlelen='60' infolen='200' orderby='weight'}",
            'tag_end' => "{/dede:list}",
            'inner_time' => "[field:pubdate function=\"MyDate('Y-m-d',@me)\" /]",
            'inner_title' => '[field:title /]',
            'inner_text' => '[field:description function=\'cn_substr(@me,300)\'/]',
            'inner_img' => '[field:litpic /]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="/m/view.php?aid=[field:id/]" title="[field:fulltitle/]"',
                ),
                'img' =>
                    array(
                        'attrs' => 'src|alt|title',
                        'replace' => ' src="[field:litpic /]" alt="[field:fulltitle/]" title="[field:fulltitle/]"',
                    ),
            ),
        ),

        'index_arclist' => array(
            'tag_start' => "{dede:arclist typeid='32' flag='p' orderby='id' orderway='asc'}",
            'tag_end' => "{/dede:arclist}",
            'inner_time' => "[field:pubdate function=\"MyDate('Y-m-d',@me)\" /]",
            'inner_title' => '[field:title /]',
            'inner_text' => '[field:info /]',
            'inner_img' => '[field:litpic /]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="/m/view.php?aid=[field:id/]" title="[field:fulltitle/]"',
                ),
                'img' =>
                    array(
                        'attrs' => 'src|alt|title',
                        'replace' => ' src="[field:litpic /]" alt="[field:fulltitle/]" title="[field:fulltitle/]"',
                    ),
            ),
        ),

        'index_channel' => array(
            'tag_start' => "{dede:channel type='top' row='10' }",
            'tag_end' => "{/dede:channel}",
            'inner_title' => '[field:typename/]',
            'inner_text' => '[field:typename/]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="/m/list.php?tid=[field:id /]" title="[field:typename/]"',
                )
            ),
        ),

        'index_channel_typeid' => array(
            'tag_start' => "{dede:channel typeid='1' type='son' row='20'}",
            'tag_end' => "{/dede:channel}",
            'inner_title' => '[field:typename/]',
            'inner_text' => '[field:typename/]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="/m/list.php?tid=[field:id /]" title="[field:typename/]"',
                )
            ),
        ),

        'likearticle' => array(
            'tag_start' => "{dede:likearticle mytypeid='22' row='20' col='' titlelen='60' infolen='200'}",
            'tag_end' => "{/dede:likearticle}",
            'inner_time' => "[field:pubdate function=\"MyDate('Y-m-d',@me)\" /]",
            'inner_title' => '[field:title /]',
            'inner_text' => '[field:description /]',
            'inner_img' => '[field:litpic /]',
            'inner_tags' => array(
                'a' => array(
                    'attrs' => 'href|title|target',
                    'replace' => ' href="/m/view.php?aid=[field:id/]" title="[field:fulltitle/]"',
                ),
                'img' =>
                    array(
                        'attrs' => 'src|alt|title',
                        'replace' => ' src="[field:litpic /]" alt="[field:fulltitle/]" title="[field:fulltitle/]"',
                    ),
            ),
        ),

        'productimagelist' => array(
            'tag_start' => "{dede:productimagelist}",
            'tag_end' => "{/dede:productimagelist}",
            'inner_title' => '[field:text/]',
            'inner_text' => '[field:text/]',
            'inner_img' => '[field:imgsrc/]',
            'inner_tags' => array(
                'img' =>
                    array(
                        'attrs' => 'src|alt|title',
                        'replace' => ' src="[field:imgsrc/]" alt="[field:text/]" title="[field:text/]"',
                    ),
            ),
        ),
    );

    if(isset($wap_tags[$tag_name])){
        $response_array = array(
            'state' => 1,
            'msg' => 'succ',
            'data' => '{'.$tag_name.'}' . $source_code . '{/'.$tag_name.'}',
        );

        $response_array['data'] = replace_tags($wap_tags[$tag_name], $source_code);
    }else{
        $response_array = array(
            'state' => -1,
            'msg' => 'error',
            'data' => 'format_wap Not Exists',
        );
    }

    exit(json_encode($response_array));
}

/**
 * 替换织梦标签使用
 * @param $tags
 * @param $source_code
 * @return mixed|string
 */
function replace_tags($tags, $source_code){
//    1、根据标签匹配里边HTML标签
//    2、替换匹配到的HTML标签
//    3、再替换源码中的HTML标签
//    4、组装返回

    //初始化变量
    $result_str = '';
    $old_html_tags = array();
    $matches = array();
    $replace_html_olds = array();
    $replace_html_news = array();

    //源码整理格式化
    $source_code = trim($source_code);
    $source_code = str_replace("'", '"', $source_code);
    $source_code = str_replace('("', "('", $source_code);
    $source_code = str_replace('")', "')", $source_code);

    //匹配源码中要替换的HTML标签
    $replace_html_tags = array_keys($tags['inner_tags']);
    foreach($replace_html_tags as $item){
        $pattern = '/<' . $item . "\s+.*?" . '>/i';
        preg_match_all($pattern, $source_code, $matches);
        if(isset($matches[0][0])){
            $old_html_tags[$item] = $matches[0];
        }
    }

    //处理替换HTML中的标签
    $oi = 0;
    foreach($old_html_tags as $key=>$value){
        $attrs = array();
        if(!empty($tags['inner_tags'][$key]['attrs'])){
            $attrs = explode('|', $tags['inner_tags'][$key]['attrs']);
        }

        foreach($value as $k=>$v){
            $replace_html_olds[$oi] = $v;

            foreach($attrs as $attr){
                $pattern = '/' . $attr . "[\s]*=[\s]*\".*?\"[\s]*" . '/i';
                $v = preg_replace($pattern, '', $v);
            }

            $v = str_ireplace('<' . $key, '<' . $key . $tags['inner_tags'][$key]['replace'], $v);

            $replace_html_news[$oi] = $v;
            $oi++;
        }

    }

    $result_str .= str_ireplace($replace_html_olds, $replace_html_news, $source_code);

    //todo 2018年12月26日14:05:06  更新算法
    //1、先匹配出所有内部内容 即 匹配内容区 (?<=>)[^<>]+(?=<)
    //2、再在内容区数组里边进行其他匹配
    //3、再替换源码中内容 内部内容用 > < 包裹

    $inner_texts = array();
    $pattern = '/(?<=>)[^<>]+(?=<)/';
    preg_match_all($pattern, $result_str, $matches);

    if(isset($matches[0])){
        foreach($matches[0] as $row){
            if(!is_skip_str($row)){  // 跳过不需要替换的内容
                $inner_texts[] = $row;
            }
        }
    }

    //匹配中文字符-替换标题、描述  todo 纯英文标题暂未考虑
    if(isset($tags['inner_title']) && (0 < count($inner_texts))) {
        $chinese_texts = array(); //再次组装是为了判断他们的长短
        $pattern = '/[\sa-zA-z0-9]*[\x{4e00}-\x{9fa5}]+/u';
        foreach ($inner_texts as $key=>$val) {
             if(preg_match($pattern, $val)){
                 $chinese_texts[] = $val;
                 unset($inner_texts[$key]); //是中文的话 剔除掉 下边时间替换不会错乱
             }
        }

        //todo 此处有BUG 如：第一个标题内容是第二个描述的子串则发生替换错乱 解决方法：添加分割符号
        foreach($chinese_texts as $key=>$value){
            if(isset($chinese_texts[$key-1]) && (strlen(trim($chinese_texts[$key-1])) < strlen(trim($chinese_texts[$key])))){
                $result_str = str_ireplace('>' . $value . '<', '>' . $tags['inner_text'] . '<', $result_str);
            }else{
                $result_str = str_ireplace('>' . $value . '<', '>' . $tags['inner_title'] . '<', $result_str);
            }
        }
    }

    //匹配日期时间并替换
    if(isset($tags['inner_time']) && (0 < count($inner_texts))){
        //替换 年-月-日
        $pattern_year = '/[\s]*\d{2,4}.{2,4}\d{1,2}.{2,4}\d{1,2}[\s]*/';
        //再次替换 年-月
        $pattern_month = '/[\s]*\d{2,4}.{2,4}\d{1,2}[\s]*/';
        //再次替换 日
        $pattern_day = '/[\s]*[0123]{1}\d{1}[\s]*/';

        foreach ($inner_texts as $key=>$val) {
            if(preg_match($pattern_year, $val)){
                $result_str = str_ireplace('>' . $val . '<', '>' . $tags['inner_time'] . '<', $result_str);
                continue;
            }

            if(preg_match($pattern_month, $val)){
                $result_str = str_ireplace('>' . $val . '<', '>' . str_replace('-d', '', $tags['inner_time']) . '<', $result_str);
                continue;
            }

            if(preg_match($pattern_day, $val)){
                $result_str = str_ireplace('>' . $val . '<', '>' .  str_replace('Y-m-', '', $tags['inner_time']) . '<', $result_str);
                continue;
            }
        }
    }

    // 替换background url 里边的图片链接
    if(isset($tags['inner_img'])){
        $pattern = '/url[\s]*\(.*?\)/i';
        preg_match_all($pattern, $result_str, $matches);

        if(isset($matches[0])){
            foreach($matches[0] as $item){
                $result_str = str_ireplace($item, 'url(' . $tags['inner_img'] . ')', $result_str);
            }
        }
    }

    //添加 起始标签、结束标记
    return $tags['tag_start'] . "\r\n" . $result_str . "\r\n" . $tags['tag_end'];
}

/**
 * 格式化HTML文档
 * @return bool
 * @throws \Gajus\Dindent\Exception\InvalidArgumentException
 */
function format_html(){
    $result = true;
    require_once VENDOR_DIR . 'dindent-master/src/Indenter.php';
    require_once VENDOR_DIR . 'dindent-master/src/Exception/DindentException.php';
    require_once VENDOR_DIR . 'dindent-master/src/Exception/InvalidArgumentException.php';
    require_once VENDOR_DIR . 'dindent-master/src/Exception/RuntimeException.php';

    $html_body = '';

    $html_files = get_file_list();  // todo 此函数返回的结果直接是windows gb2312


    if(isset($html_files[0])){
        foreach($html_files as $key=>$value){
            if('.' == $value || '..' == $value){continue;}

            $temp_path = $value;
            if(file_exists($temp_path)){
                $html_body = get_file_content($temp_path);
                if(!empty($html_body)){
                    //编码转换
                    code_convert($html_body);

                    //indent 格式化代码
                    try{
                        $indenter = new \Gajus\Dindent\Indenter();
                        $html_body = $indenter->indent($html_body);
                    }catch (Exception $e){
                        log_record($e);
                        continue;
                    }

                    put_file_content(str_replace(INPUT_DIR, OUTPUT_DIR, $value), $html_body);

                }else{
                    $msg = $value . __FUNCTION__ .  ':: content is Empty !';
                    log_record($msg);
                }
            }else{
                $msg = $value . __FUNCTION__ . ':: file Not found!';
                log_record($msg);
            }
        }
    }else{
        $result = false;
    }

    return $result;
}

/**
 * 替换dedecms头部标签
 * @return bool
 */
function dede_replace(){
    $result = true;

    $html_files = get_file_list(OUTPUT_DIR . '*.html');  // todo 此函数返回的结果直接是windows gb2312

    if(isset($html_files[0])){
        foreach($html_files as $key=>$value){
            if('.' == $value || '..' == $value){continue;}

            $temp_path = $value;
            if(file_exists($temp_path)){
                $html_body = get_file_content($temp_path);
                if(!empty($html_body)){
                    //织梦head内部标签替换
                    multi_replace($html_body);

                    //region 处理页面基本路由 start
                    $file_name = basename(iconv('GB2312', 'UTF-8//IGNORE', $temp_path));

                    if('index.html' == $file_name){
                        $html_body = str_replace('<title>{dede:field.typename /}_{dede:global.cfg_webname/}</title>', '<title>{dede:global.cfg_webname/}</title>', $html_body);
                    }
                    elseif(preg_match('/详情/', $file_name)){
                        $html_body = str_replace('<title>{dede:field.typename /}_{dede:global.cfg_webname/}</title>', '<title>{dede:field.title /}_{dede:global.cfg_webname/}</title>', $html_body);
                    }
                    //endregion

                    put_file_content($value, $html_body);

                }else{
                    $msg = $value . __FUNCTION__ . ':: content is Empty !';
                    log_record($msg);
                }
            }else{
                $msg = $value . __FUNCTION__ .  ':: file Not found!';
                log_record($msg);
            }
        }
    }
    else{
        $result = false;
    }

    return $result;
}

/**
 * 替换dedecms头部标签-内页SEO
 * @return bool
 */
function dede_replace_seo(){
    $result = true;

    $html_files = get_file_list(OUTPUT_DIR . '*.html');  // todo 此函数返回的结果直接是windows gb2312

    if(isset($html_files[0])){
        foreach($html_files as $key=>$value){
            if('.' == $value || '..' == $value){continue;}

            $temp_path = $value;
            if(file_exists($temp_path)){
                $html_body = get_file_content($temp_path);
                if(!empty($html_body)){
                    //织梦head内部标签替换
                    multi_replace_seo($html_body);

                    //region 处理页面基本路由 start
                    $file_name = basename(iconv('GB2312', 'UTF-8//IGNORE', $temp_path));

                    if('index.html' == $file_name){
                        $html_body = str_replace('<title>{dede:field.seotitle /}</title>', '<title>{dede:global.cfg_webname/}</title>', $html_body);
                        $html_body = str_replace('<meta name="keywords" content="{dede:field name=\'keywords\'/}" />', '<meta name="keywords" content="{dede:global.cfg_keywords/}" />', $html_body);
                        $html_body = str_replace('<meta name="description" content="{dede:field name=\'description\'/}" />', '<meta name="description" content="{dede:global.cfg_description/}" />', $html_body);
                    }
                    elseif(preg_match('/详情/', $file_name)){
                        $html_body = str_replace('<title>{dede:field.seotitle /}</title>', '<title>{dede:field.title /}</title>', $html_body);
                    }

                    //endregion

                    put_file_content($value, $html_body);

                }else{
                    $msg = $value . __FUNCTION__ . ':: content is Empty !';
                    log_record($msg);
                }
            }else{
                $msg = $value . __FUNCTION__ .  ':: file Not found!';
                log_record($msg);
            }
        }
    }
    else{
        $result = false;
    }

    return $result;
}

/**
 * 替换dedecms头部标签-完美版-解决内部标签为空问题
 * @return bool
 */
function dede_replace_perfect(){
    $result = true;

    $html_files = get_file_list(OUTPUT_DIR . '*.html');  // todo 此函数返回的结果直接是windows gb2312

    if(isset($html_files[0])){
        foreach($html_files as $key=>$value){
            if('.' == $value || '..' == $value){continue;}

            $temp_path = $value;
            if(file_exists($temp_path)){
                $html_body = get_file_content($temp_path);
                if(!empty($html_body)){
                    //先把head标签中的属性替换为空
                    multi_replace_perfet($html_body);

                    $matches = array();
                    // 确定使用HTML4/5  <meta charset="UTF-8"> <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
                    $html_type = 'html4';
                    $pattern_html4 = "/<meta[\s]+http-equiv=\"Content-Type\"[\s]+content=\"text\/html;[\s]+charset.*/i";
                    $pattern_html42 = "/<meta[\s]+content=\"text\/html;[\s]+charset[\s]*=[\s]*utf-8[\s]*\"[\s]+http-equiv[\s]*=[\s]*\"Content-Type\"[\s]*\/?>/i";
                    $pattern_html5 = "/<meta[\s]+charset[\s]*=[\s]*\"[\s]*utf-8[\s]*\"[\s]*\/?>/i";

                    //TODO 暂不处理 有两个声明的文档
                    if(preg_match($pattern_html4, $html_body, $matches) || preg_match($pattern_html42, $html_body, $matches)){
                        $html_type = 'html4';
                        $html_body = str_replace($matches[0], '', $html_body);
                    }
                    elseif(preg_match($pattern_html5, $html_body, $matches)){
                        $html_type = 'html5';
                        $html_body = str_replace($matches[0], '', $html_body);
                    }

                    // 替换head 标签后边，注意里边可能有属性
                    preg_match("/<head.*?>/i", $html_body, $matches);

                    $head_meta = $matches[0] . "\r\n";
                    if('html4' == $html_type){
                        $head_meta .= '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />' . "\r\n";
                    }
                    else{
                        $head_meta .= '<meta charset="utf-8">' . "\r\n";
                    }

                    // todo 这里可以加一个判断这些标题离顶部有几个空格
                    $head_meta .= '<title>{dede:field.seotitle /}</title>' . "\r\n";
                    $head_meta .= '<meta name="keywords" content="{dede:field name=\'keywords\'/}" />' . "\r\n";
                    $head_meta .= '<meta name="description" content="{dede:field name=\'description\'/}" />' . "\r\n";
                    $head_meta .= '<meta name="author" content="xslooi"/>' . "\r\n";

                    $html_body = str_replace($matches[0], $head_meta, $html_body);

                    //region 处理页面基本路由 start
                    $file_name = basename(iconv('GB2312', 'UTF-8//IGNORE', $temp_path));

                    if('index.html' == $file_name){
                        $html_body = str_replace('<title>{dede:field.seotitle /}</title>', '<title>{dede:global.cfg_webname/}</title>', $html_body);
                        $html_body = str_replace('<meta name="keywords" content="{dede:field name=\'keywords\'/}" />', '<meta name="keywords" content="{dede:global.cfg_keywords/}" />', $html_body);
                        $html_body = str_replace('<meta name="description" content="{dede:field name=\'description\'/}" />', '<meta name="description" content="{dede:global.cfg_description/}" />', $html_body);
                    }
                    elseif(preg_match('/详情/', $file_name)){
                        $html_body = str_replace('<title>{dede:field.seotitle /}</title>', '<title>{dede:field.title /}</title>', $html_body);
                    }

                    //endregion

                    put_file_content($value, $html_body);

                }else{
                    $msg = $value . __FUNCTION__ . ':: content is Empty !';
                    log_record($msg);
                }
            }else{
                $msg = $value . __FUNCTION__ .  ':: file Not found!';
                log_record($msg);
            }
        }
    }
    else{
        $result = false;
    }

    return $result;
}

/**
 * 替换dedecms include 公共文件标签——全等判定
 * TODO 此方法局限性：比如头部有当前项则每个页面当前项属性位置不一样就不能替换
 * @return bool
 */
function replace_dedecms_equals(){
    $result = true;

    $html_files = get_file_list(OUTPUT_DIR . '*.html');  // todo 此函数返回的结果直接是windows gb2312
    $html_common = array();
    $html_templets = array();

    if(isset($html_files[0])){
        foreach($html_files as $key=>$value){
            if('.' == $value || '..' == $value){continue;}
            $file_name = basename($value);
            $file_name = substr($file_name, 0, -5);

            if(preg_match('/^[A-Z]+/', $file_name)){
                $html_common[] = $value;
            }
            else{
                $html_templets[] = $value;
            }
        }

        if(isset($html_common[0])){
            foreach($html_common as $item){
                $common_html =  get_file_content($item);
                $file_name = basename($item);
                $file_name = substr($file_name, 0, -5);
                $html_replace = "{dede:include filename='{$file_name}.htm'/}";

                foreach($html_templets as $value){
                    $html_body = get_file_content($value);

                    $html_body = str_replace($common_html, $html_replace, $html_body);

                    put_file_content($value, $html_body);
                }
            }
        }

    }
    else{
        $result = false;
    }

    return $result;
}

/**
 * 替换dedecms include 公共文件标签——正则判定
 * TODO 此方法局限性：匹配全标签正则，比如头部有当前项则每个页面当前项标签位置不一样就不能替换
 * @return bool
 */
function replace_dedecms_regular(){
    $result = true;

    $html_files = get_file_list(OUTPUT_DIR . '*.html');  // todo 此函数返回的结果直接是windows gb2312
    $html_common = array();
    $html_templets = array();

    if(isset($html_files[0])){
        foreach($html_files as $key=>$value){
            if('.' == $value || '..' == $value){continue;}
            $file_name = basename($value);
            $file_name = substr($file_name, 0, -5);

            if(preg_match('/^[A-Z]+/', $file_name)){
                $html_common[] = $value;
            }
            else{
                $html_templets[] = $value;
            }
        }

        if(isset($html_common[0])){
            foreach($html_common as $item){
                $common_html =  get_file_content($item);
                $common_html_tags = analysis_html($common_html);
                $common_html_regular = get_html_pattern($common_html_tags);

                // 创建替换字符
                $file_name = basename($item);
                $file_name = substr($file_name, 0, -5);
                $html_replace = "{dede:include filename='{$file_name}.htm'/}";

                foreach($html_templets as $value){
                    $html_body = get_file_content($value);

                    $html_body = preg_replace($common_html_regular, $html_replace, $html_body, 1);

                    put_file_content($value, $html_body);
                }
            }
        }

    }
    else{
        $result = false;
    }

    return $result;
}

/**
 * 替换dedecms include 公共文件标签——标签闭合判断
 * TODO 此方法局限性：代码片段为从头开始查找，则footer里边的代码段也是从头查找（会产生很大歧义）如：从<div开始；从<script 、 <style 开始等等
 * @return bool
 */
function replace_dedecms_closetag(){
    $result = true;

    $html_files = get_file_list(OUTPUT_DIR . '*.html');  // todo 此函数返回的结果直接是windows gb2312
    $html_common = array();
    $html_templets = array();
    $html_common_tags = array();

    if(isset($html_files[0])){
        foreach($html_files as $key=>$value){
            if('.' == $value || '..' == $value){continue;}
            $file_name = basename($value);
            $file_name = substr($file_name, 0, -5);

            if(preg_match('/^[A-Z]+/', $file_name)){
                $html_common[] = $value;
            }
            else{
                $html_templets[] = $value;
            }
        }

        // 得到替换列表的标签头
        if(isset($html_common[0])){
            foreach($html_common as $item){
                $common_html = get_file_content($item);

                $file_name = basename($item);
                $file_name = substr($file_name, 0, -5);

                while(true){
                    $common_html = trim($common_html);
                    $tag_start = substr($common_html, 0, strpos($common_html, '>') + 1);

                    // 处理注释字段 todo 此处注释需要收集全部注释 xslooi
                    if('<!--' == substr($tag_start, 0, 4)){
                        $tag_start = substr($common_html, 0, strpos($common_html, '-->') + 3);
                        $html_common_tags[$file_name][] = $tag_start;
                        $common_html = substr($common_html, strlen($tag_start));
                    }
                    else{
                        $html_common_tags[$file_name][] = $tag_start;
                        $tag_html_segment = get_closing_tag_html($tag_start, $common_html);
                        $common_html = substr($common_html, strlen($tag_html_segment));
                    }


                    if(false === strpos($common_html, '<') || 6 > strlen($common_html)){
                        break;
                    }
                }

            }
        }

        // 根据标签头数组替换文件
        if(isset($html_templets[0]) && 0 < count($html_common_tags)) {
            foreach ($html_templets as $item) {
                $common_html =  get_file_content($item);

                // 只截取body里边的标签
                if(false !== stripos($common_html, '<body')){
                    $html_body = substr($common_html, stripos($common_html, '<body'));
                    $html_tag_body = substr($html_body, 0, strpos($html_body, '>') + 1);
                    $html_body = substr($html_body, strlen($html_tag_body));
                }

                if(false !== stripos($html_body, '</body>')){
                    $html_body = substr($html_body, 0, stripos($html_body, '</body>'));
                }

                $html_body = trim($html_body);

                // 闭合标签替换开始
                foreach($html_common_tags as $key=>$value){

                    $flag = true;

                    foreach($value as $k=>$v){

                        if('<!--' == substr($v, 0, 4)){
                            $html_common_segment = $v;
                            $v = '';
                            $position = strpos($common_html, $html_common_segment);
                            if(false !== $position){
                                $common_html = substr_replace($common_html, '', $position, strlen($html_common_segment));
                            }
                        }
                        else{
                            $html_common_segment = get_closing_tag_html($v, $html_body);
                        }

                        // 判断位置是否存在
                        $position = false;
                        if(!empty($html_common_segment)){
                            $position = strpos($common_html, $html_common_segment);
                            $offset_length = strlen($html_common_segment);
                        }

                        if(false !== $position){
                            // 如果已经替换过则直接替换为空
                            if(false !== strpos($common_html, "filename='{$key}.htm'")){
                                $flag = false;
                            }

                            if($flag && !empty($v)){
                                $html_replace = "{dede:include filename='{$key}.htm'/}";
                                // $common_html = str_replace($html_common_segment, $html_replace, $common_html); // 有bug会替换所有
                                $common_html = substr_replace($common_html, $html_replace, $position, $offset_length);
                                $flag = false;
                            }
                            else{
                                $common_html = substr_replace($common_html, '', $position, $offset_length);
                            }

                        }

                    }

                }
                // 闭合标签替换结束

                put_file_content($item, $common_html);
            }
        }

    }
    else{
        $result = false;
    }

    return $result;
}

/**
 * 替换HTML内容中 QQ TEL Email 等
 * @return bool
 */
function replace_telqq(){
    $result = true;

    $html_files = get_file_list(OUTPUT_DIR . '*.html');  // todo 此函数返回的结果直接是windows gb2312

    if(isset($html_files[0])){
        foreach($html_files as $key=>$value){
            if('.' == $value || '..' == $value){continue;}

            $temp_path = $value;
            if(file_exists($temp_path)){
                $html_body = get_file_content($temp_path);
                if(!empty($html_body)){
                    //多内容替换
                    multi_replace_telqq($html_body);

                    put_file_content($value, $html_body);

                }else{
                    $msg = $value . __FUNCTION__ . ':: content is Empty !';
                    log_record($msg);
                }
            }else{
                $msg = $value . __FUNCTION__ .  ':: file Not found!';
                log_record($msg);
            }
        }
    }else{
        $result = false;
    }

    return $result;
}

/**
 * 替换HTML内容中 Public/ 为 /Public/
 * @return bool
 */
function replace_public(){
    $result = true;

    $html_files = get_file_list(OUTPUT_DIR . '*.html');  // todo 此函数返回的结果直接是windows gb2312

    if(isset($html_files[0])){
        foreach($html_files as $key=>$value){
            if('.' == $value || '..' == $value){continue;}

            $temp_path = $value;
            if(file_exists($temp_path)){
                $html_body = get_file_content($temp_path);
                if(!empty($html_body)){
                    //多内容替换
                    $html_body = str_replace('"Public/', '"/Public/', $html_body);
                    $html_body = str_replace("'Public/", "'/Public/", $html_body);
                    $html_body = str_replace('(Public/', '(/Public/', $html_body);

                    put_file_content($value, $html_body);

                }else{
                    $msg = $value . __FUNCTION__ . ':: content is Empty !';
                    log_record($msg);
                }
            }else{
                $msg = $value . __FUNCTION__ .  ':: file Not found!';
                log_record($msg);
            }
        }
    }else{
        $result = false;
    }

    return $result;
}

/**
 * 替换HTML内容中 Public/ 为 /Public/
 * @return bool
 */
function replace_name(){
    $result = true;

    $html_files = get_file_list(OUTPUT_DIR . '*.html');  // todo 此函数返回的结果直接是windows gb2312

    if(isset($html_files[0])){
        foreach($html_files as $key=>$value){
            if('.' == $value || '..' == $value){continue;}

            $temp_path = $value;
            if(file_exists($temp_path)){
                rename($temp_path, substr($temp_path, 0, -1));
            }else{
                $msg = $value . __FUNCTION__ .  ':: file Not found!';
                log_record($msg);
            }
        }
    }else{
        $result = false;
    }

    return $result;
}

/**
 * 去除文件BOM头信息
 * @return bool
 */
function remove_bom(){
    $result = true;

    $html_files = get_file_list(OUTPUT_DIR . '*.*');  // todo 此函数返回的结果直接是windows gb2312

    if(isset($html_files[0])){
        foreach($html_files as $key=>$value){
            if('.' == $value || '..' == $value){continue;}
            removeFileBOM($value);
        }
    }else{
        $result = false;
    }

    return $result;
}

/**
 * 根据文件的全路径，去除文件的BOM头
 * @param $filename
 * @return bool
 */
function removeFileBOM($filename) {
    $exists_bom = false;

    $contents = file_get_contents($filename);

    $charset[1] = substr($contents, 0, 1);
    $charset[2] = substr($contents, 1, 1);
    $charset[3] = substr($contents, 2, 1);

    // BOM 的前三个字符的 ASCII 码分别为 239/187/191
    if (ord($charset[1]) == 239 && ord($charset[2]) == 187 && ord($charset[3]) == 191) {
        $rest = substr($contents, 3);
        file_put_contents($filename, $rest);
        $exists_bom = true;
    }

    return $exists_bom;
}


/**
 * 得到某个目录的文件列表
 * @param string $path_pattern
 * @return array|false
 */
function get_file_list($path_pattern=''){
    if(empty($path_pattern)){
        $path_pattern = INPUT_DIR . '*.html';
    }
    return glob($path_pattern);
}

/**
 * 得到文件内容
 * @param $file_path
 * @return false|string
 */
function get_file_content($file_path){
    return file_get_contents($file_path);
}

/**
 * 输出文件内容
 * @param $file_path
 * @param $html_body
 * @return bool|int
 */
function put_file_content($file_path, &$html_body){
    return file_put_contents(str_replace(INPUT_DIR, OUTPUT_DIR, $file_path), $html_body);
}

/**
 * 文件编码转换GB2312 转换为 utf8
 * @param $html_body
 */
function code_convert(&$html_body){
    //TODO 暂时忽略 = 左右两边的空白字符

    //gb2312 转 utf8
    if(false !== stripos($html_body, 'charset="gb2312"', 20) || false !== stripos($html_body, 'charset=gb2312', 20)){
        $html_body = str_ireplace('charset="gb2312"', 'charset="utf-8"', $html_body);
        $html_body = str_ireplace('charset=gb2312', 'charset=utf-8', $html_body);
        $html_body = iconv("gb2312", "utf-8//IGNORE", $html_body);
    }

    //gbk 转 utf8
    if(false !== stripos($html_body, 'charset="gbk"', 20) || false !== stripos($html_body, 'charset=gbk', 20)){
        $html_body = str_ireplace('charset="gbk"', 'charset="utf-8"', $html_body);
        $html_body = str_ireplace('charset=gbk', 'charset=utf-8', $html_body);
        $html_body = iconv("gbk", "utf-8//IGNORE", $html_body);
    }

    //去除错误字符
    $html_body = str_replace('�', '?', $html_body);

}

/**
 * 替换页面 标题、描述、关键字
 * TODO 此函数有bug 如果源网页中没有以上属性则不能替换成功
 * TODO 升级算法：
 * 1、把文档中 title、keywords、description、author、copyright 等属性直接替换为空
 * 2、然后直接都替换到 <meta http-equiv="Content-Type" content="text/html; charset=utf-8" /> 标签后边 则可以解决不存在某个属性的情况
 * @param $html_body
 */
function multi_replace(&$html_body){
    //此处正则替换多数标签
    $html_body = preg_replace("/<title>.*?<\/title>/i", "<title>{dede:field.typename /}_{dede:global.cfg_webname/}</title>", $html_body);

    $html_body = preg_replace("/<meta[\s]+name=\"keywords\"[\s]+content=\".*/i", "<meta name=\"keywords\" content=\"{dede:global.cfg_keywords/}\" />", $html_body);
    $html_body = preg_replace("/<meta[\s]+name=\"description\"[\s]+content=\".*/i", "<meta name=\"description\" content=\"{dede:global.cfg_description/}\" />", $html_body);
    $html_body = preg_replace("/<meta[\s]+name=\"author\"[\s]+content=\".*/i", "<meta name=\"author\" content=\"xslooi\"/>", $html_body);
    $html_body = preg_replace("/<meta[\s]+name=\"copyright\"[\s]+content=\".*/i", "<meta name=\"copyright\" content=\"xslooi\"/>", $html_body);
    $html_body = preg_replace("/<meta[\s]+name=\"generator\"[\s]+content=\".*/i", "<meta name=\"generator\" content=\"xslooi\"/>", $html_body);

    //内容在前标签
    $html_body = preg_replace("/<meta[\s]+content=\".*[\s]+name=\"keywords.*/i", "<meta name=\"keywords\" content=\"{dede:global.cfg_keywords/}\" />", $html_body);
    $html_body = preg_replace("/<meta[\s]+content=\".*[\s]+name=\"description.*/i", "<meta name=\"description\" content=\"{dede:global.cfg_description/}\" />", $html_body);
    $html_body = preg_replace("/<meta[\s]+content=\".*[\s]+name=\"author.*/i", "<meta name=\"author\" content=\"xslooi\"/>", $html_body);
    $html_body = preg_replace("/<meta[\s]+content=\".*[\s]+name=\"copyright.*/i", "<meta name=\"copyright\" content=\"xslooi\"/>", $html_body);
    $html_body = preg_replace("/<meta[\s]+content=\".*[\s]+name=\"generator.*/i", "<meta name=\"generator\" content=\"xslooi\"/>", $html_body);

}

/**
 * 替换页面 标题、描述、关键字
 * TODO 此函数有bug 如果源网页中没有以上属性则不能替换成功
 * TODO 升级算法：
 * 1、把文档中 title、keywords、description、author、copyright 等属性直接替换为空
 * 2、然后直接都替换到 <meta http-equiv="Content-Type" content="text/html; charset=utf-8" /> 标签后边 则可以解决不存在某个属性的情况
 * @param $html_body
 */
function multi_replace_seo(&$html_body){
    //此处正则替换多数标签
    $html_body = preg_replace("/<title>.*?<\/title>/i", "<title>{dede:field.seotitle /}</title>", $html_body);

    $html_body = preg_replace("/<meta[\s]+name=\"keywords\"[\s]+content=\".*/i", "<meta name=\"keywords\" content=\"{dede:field name='keywords'/}\" />", $html_body);
    $html_body = preg_replace("/<meta[\s]+name=\"description\"[\s]+content=\".*/i", "<meta name=\"description\" content=\"{dede:field name='description'/}\" />", $html_body);
    $html_body = preg_replace("/<meta[\s]+name=\"author\"[\s]+content=\".*/i", "<meta name=\"author\" content=\"xslooi\"/>", $html_body);
    $html_body = preg_replace("/<meta[\s]+name=\"copyright\"[\s]+content=\".*/i", "<meta name=\"copyright\" content=\"xslooi\"/>", $html_body);
    $html_body = preg_replace("/<meta[\s]+name=\"generator\"[\s]+content=\".*/i", "<meta name=\"generator\" content=\"xslooi\"/>", $html_body);

    //内容在前标签
    $html_body = preg_replace("/<meta[\s]+content=\".*[\s]+name=\"keywords.*/i", "<meta name=\"keywords\" content=\"{dede:field name='keywords'/}\" />", $html_body);
    $html_body = preg_replace("/<meta[\s]+content=\".*[\s]+name=\"description.*/i", "<meta name=\"description\" content=\"{dede:field name='description'/}\" />", $html_body);
    $html_body = preg_replace("/<meta[\s]+content=\".*[\s]+name=\"author.*/i", "<meta name=\"author\" content=\"xslooi\"/>", $html_body);
    $html_body = preg_replace("/<meta[\s]+content=\".*[\s]+name=\"copyright.*/i", "<meta name=\"copyright\" content=\"xslooi\"/>", $html_body);
    $html_body = preg_replace("/<meta[\s]+content=\".*[\s]+name=\"generator.*/i", "<meta name=\"generator\" content=\"xslooi\"/>", $html_body);

}

/**
 * 替换页面 标题、描述、关键字 先替换为空
 * TODO 此函数有bug 如果源网页中没有以上属性则不能替换成功
 * TODO 升级算法：
 * 1、把文档中 title、keywords、description、author、copyright 等属性直接替换为空
 * 2、然后直接都替换到 <meta http-equiv="Content-Type" content="text/html; charset=utf-8" /> 标签后边 则可以解决不存在某个属性的情况
 * @param $html_body
 */
function multi_replace_perfet(&$html_body){
    //此处正则替换多数标签
    $html_body = preg_replace("/<title>.*?<\/title>/i", "", $html_body);

    $html_body = preg_replace("/<meta[\s]+name=\"keywords\"[\s]+content=\".*/i", "", $html_body);
    $html_body = preg_replace("/<meta[\s]+name=\"description\"[\s]+content=\".*/i", "", $html_body);
    $html_body = preg_replace("/<meta[\s]+name=\"author\"[\s]+content=\".*/i", "", $html_body);
    $html_body = preg_replace("/<meta[\s]+name=\"copyright\"[\s]+content=\".*/i", "", $html_body);
    $html_body = preg_replace("/<meta[\s]+name=\"generator\"[\s]+content=\".*/i", "", $html_body);

    //内容在前标签
    $html_body = preg_replace("/<meta[\s]+content=\".*[\s]+name=\"keywords.*/i", "", $html_body);
    $html_body = preg_replace("/<meta[\s]+content=\".*[\s]+name=\"description.*/i", "", $html_body);
    $html_body = preg_replace("/<meta[\s]+content=\".*[\s]+name=\"author.*/i", "", $html_body);
    $html_body = preg_replace("/<meta[\s]+content=\".*[\s]+name=\"copyright.*/i", "", $html_body);
    $html_body = preg_replace("/<meta[\s]+content=\".*[\s]+name=\"generator.*/i", "", $html_body);

}
/**
 * 替换页面中 电话、手机、QQ、备案号等信息为默认无效内容
 * @param $html_body
 */
function multi_replace_telqq(&$html_body){

    //电话、手机、邮箱、QQ、备案号 等 替换为默认无效内容 todo 此处正则纯数字未做验证 如 164685400-4164-008.jpg 会被替换成400 电话
    $replace_patterns = array(
        'beian' => array("/[\x{4e00}-\x{9fa5}]{1}icp备\d{8}-?\d?号?-?\d?/ui", "ICP备12345678号"),
        'email' => array("/[a-zA-Z0-9]+([-_.][a-zA-Z0-9]+)*@([a-zA-Z0-9]+[-.])+([a-z]{2,5})/ims", "123456@qq.com"),
        'tel' => array("/(?<!\d)(086-)?[1-9][0-9]{1,4}-?[1-9][0-9]{4,7}-?[0-9]{3,4}(?!\d+)/", "0371-1234567"),
        'tel400' => array("/(?<!\d)400-?\d{3,4}-?\d{3,4}(?!\d+)/", "400-000-1234"),
        'phone' => array("/(?<!\d)((13[0-9])|(14[5,7,9])|(15[^4])|(18[0-9])|(17[0,1,3,5,6,7,8]))\d{8}(?!\d+)/", "15612346578"),
        'qq' => array("/=[1-9]\d{4,9}(?!\d+)/", "=qq123456"),
    );


    foreach($replace_patterns as $item){
        $html_body = preg_replace($item[0], $item[1], $html_body);
    }

}

/**
 * 是否跳过这个字符串 如：空字符串、中文长度大于6、“更多”关键词
 * @param $string
 * @return bool
 */
function is_skip_str($string){
    $is_skip = false;

    if(preg_match("/^[\s]+$/", $string)){
        $is_skip = true;
        return $is_skip;
    }

    if(6 < mb_strlen($string)){ //文字超过6个直接返回
        return $is_skip;
    }

    $more = array('查看', '详情', '推荐', '详细', '参数', '更多', '全部', '立即', '咨询', 'more');

    foreach($more as $item){
        if(false !== stripos($string, $item)){
            $is_skip = true;
            break;
        }
    }

    return $is_skip;
}

/**
 * 执行命令的工厂函数即二级路由
 * 1、if：转换编码
 * @param $cmd
 * @return bool
 */
function cmd_factory($cmd){
    $result = false;

    if('cmd_convert_' == substr($cmd, 0, 12)){
        $iconv_params = substr($cmd, 12);
        $iconv_params = explode('_', $iconv_params);

        $html_files = get_file_list(OUTPUT_DIR . '*.*');  // todo 此函数返回的结果直接是windows gb2312

        if(isset($html_files[0])){
            foreach($html_files as $key=>$value){
                if('.' == $value || '..' == $value){continue;}

                $html_body = get_file_content($value);
                $html_body = iconv($iconv_params[0], $iconv_params[1] . '//IGNORE', $html_body);
                put_file_content($value, $html_body);
            }

            $result = true;
        }else{
            $result = false;
        }
    }

    return $result;
}

/**
 * 返回替换HTML的正则表达式
 * @param $html_tags
 * @return mixed|string
 */
function get_html_pattern($html_tags){
    $html_pattern = str_replace('>', '(.*?)>([^<]*?)', $html_tags);
    //替换 javascript 脚本 和 css 样式内容
    $html_pattern = str_replace('<script(.*?)>([^<]*?)</script(.*?)>', '<script(.*?)>([\s\S]*?)</script(.*?)>', $html_pattern);
    $html_pattern = str_replace('<style(.*?)>([^<]*?)</style(.*?)>', '<style(.*?)>([\s\S]*?)</style(.*?)>', $html_pattern);

    // <body 后边的 和 </body>标签前边的 <script> <style> <link 直接包含
    $html_pattern = str_replace('<body(.*?)>([^<]*?)', '<body(.*?)>([\s\S]*?)', $html_pattern);
    $html_pattern = str_replace('([^<]*?)</body(.*?)>', '([\s\S]*?)</body(.*?)>', $html_pattern);

    // 替换注释
    $html_pattern = str_replace('<!--#--(.*?)>', '<!--(.*?)-->', $html_pattern);

    // 转义 / 字符
    $html_pattern = str_replace('/', '\/', $html_pattern);

    $html_pattern = '/' . substr($html_pattern, 0, strrpos($html_pattern, '>') + 1) . '/i';

    return $html_pattern;
}

/**
 * 分析 HTML 标签列表
 * @param $source_code
 * @return string|string[]|null
 */
function analysis_html($source_code){
    $html = $source_code;
    // 格式化源代码
    $html = str_replace(array("\r", "\n", "\t", "&nbsp;"), '', $html);  //去掉换行
//    $html = preg_replace('/<script[\s|>][\s\S]*?<\/script>/i', '', $html); //去掉js
    $html = preg_replace('/<script[\s|>][\s\S]*?<\/script>/i', '<script></script>', $html); //js 替换为一个 占位标签
//    $html = preg_replace('/<style[\s|>][\s\S]*?<\/style>/i', '', $html); //去掉css
    $html = preg_replace('/<style[\s|>][\s\S]*?<\/style>/i', '<style></style>', $html); //css 也替换为一个占位符
    /*    $html = preg_replace('/<link [\s|>][\s\S]*?>/i', '', $html); //去掉css 链接*/
    $html = preg_replace('/<link [\s|>][\s\S]*?>/i', '<link>', $html); //css 链接 也替换为一个占位符

//    $html = preg_replace('/<!--[\s\S]*?-->/', '', $html); //去掉HTML注释
    $html = preg_replace('/<!--[\s\S]*?-->/', '<!--#-->', $html); //HTML注释 替换为一个占位符
    $html = preg_replace('/ {2,}/', ' ', $html); //多个空格替换为一个
    $html = str_replace("> <", '><', $html);  //去掉两个标签中间的空格
    $html = trim($html); // 去掉两边的空白

//    echo $html;
//    echo "\r\n\r\n\r\n";

    $pattern_html_tags = '/<[a-zA-Z1-6]+[\s|>]{1}/i'; //匹配所有HTML标签 (用\s包括回车) todo 注意javascript 里边也有HTML 标签
    $matches_html_tags = array();
    preg_match_all($pattern_html_tags, $html, $matches_html_tags);

//    var_dump($matches_html_tags);

    $htmlTags = array();
    if(isset($matches_html_tags[0][0])) {
        foreach ($matches_html_tags[0] as $item) {
            $htmlTag = str_replace(array('<', '>', ' '), '', $item);
            $htmlTags[] = $htmlTag;
        }
    }

    $uniqueHtmlTags = array_unique($htmlTags);

    if(isset($uniqueHtmlTags[0])){
        foreach($uniqueHtmlTags as $item){
            // todo xslooi 此处有bug li 会替换 link 、 b 会替换 body 和 br
            $html = preg_replace('/<' . $item . '(?!a|b|c|d|e|f|p|s|u|i|l|m|n|o|r|\/).*?>/i', '<' . $item . '>', $html);
//            echo $item;
//            echo $html;
//            echo "\r\n\r\n\r\n";
//            exit;
        }
    }
//exit;
//    echo $html;
//    exit;
//    $pattern_replace = '/>([\sa-zA-z0-9]*[\x{4e00}-\x{9fa5}\P{L}]+[\sa-zA-z0-9]*)</u'; //替换中文内容的正则
//    $html = preg_replace($pattern_replace, '><button class="fixed" data-clipboard-text="${1}" type="button"> ${1} </button><', $html);

    // 去掉标签内部内容
    $pattern_replace = '/>.*?</'; //替换标签内的所有内容为空
    $html = preg_replace($pattern_replace, '><', $html);

    $result = $html;

    return $result;
}

/**
 * 根据 HTML 开始标签 返回该标签的整段闭合HTML代码
 * TODO 注意此函数未处理 注释中的代码 <!-- --> 脚本代码 样式代码
 * !可能有多字节字符问题
 * 不匹配 </div > 闭合标签中有空格问题
 * @param $tag_start
 * @param $html
 * @return bool|string
 */
function get_closing_tag_html($tag_start, $html){
    if(empty($tag_start) || empty($html)){
        exit(__LINE__ . __FUNCTION__ . ' Parameters Error!');
    }

    //HTML 单闭合标签
    $html_single_tag = array('br', 'hr', 'area', 'base', 'img', 'input', 'link', 'meta', 'basefont', 'param', 'col', 'frame', 'embed');

    $html_fragment = ''; //HTML闭合标签整段代码

    //直接付给body 可能用于 body 内部代码段
    $html_body = $html;

    if(false !== stripos($html, '<body')){
        $html_body = substr($html, stripos($html, '<body'));
    }

    if(false !== stripos($html_body, '</body>')){
        $html_body = substr($html_body, 0, stripos($html_body, '</body>') + 7);
    }

    //如果没有找到开始代码段
    if(stripos($html_body, $tag_start) !== false){
        $tag_name_temp = explode(' ', $tag_start);
        $tag_name = substr($tag_name_temp[0], 1);
        $tag_name = str_replace(array('<', '>'), '', $tag_name);


        $html_start = substr($html_body, strpos($html_body, $tag_start));
        if(in_array($tag_name, $html_single_tag)){
            $html_fragment = substr($html_start, 0, strpos($html_start, '>') + 1);
        }
        else{

            $html_tag_end = '</' . $tag_name . '>';
            $html_tag_end_count = substr_count($html_body, $html_tag_end);

            $html_fragment = substr($html_start, 0, strpos($html_start, $html_tag_end) + strlen($html_tag_end));
            $html_fragment_length = strlen($html_fragment);
            $html_tag_start_count = substr_count($html_fragment, '<' . $tag_name . ' ') + substr_count($html_fragment, '<' . $tag_name . '>');
            $end_count = 1; //标签结束标志

            //遍历HTML 闭合标签代码 找到闭合位置
            for($i=1; $i<$html_tag_end_count; $i++){

                if($html_tag_start_count > $end_count){

                    $html_fragment = substr($html_start, $html_fragment_length);
                    $html_fragment = substr($html_fragment, 0, strpos($html_fragment, $html_tag_end) + strlen($html_tag_end));
                    $html_fragment = substr($html_start, 0, $html_fragment_length + strlen($html_fragment));
                    $html_fragment_length = strlen($html_fragment);
                    $html_tag_start_count = substr_count($html_fragment, '<' . $tag_name . ' ') + substr_count($html_fragment, '<' . $tag_name . '>');
                    $end_count++;
                }
                else{
                    break;
                }
            }
        }

    }

    return $html_fragment;
}

/**
 * 输出错误日志 如：文件为空、 indent 异常等
 * @param $data
 */
function log_record($data){
    $content = "\r\n-----------------------------------------------------------------\r\n";
    $content .= var_export($data, true);
    $content .= "\r\n-----------------------------------------------------------------\r\n";
    file_put_contents('log_record.log', $content, FILE_APPEND);
}