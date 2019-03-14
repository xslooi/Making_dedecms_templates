/**
 * 操作JS文件
 */

// ========================================================================
// 函数区
function ShowMsg(msg, type){
    var msgBox = $("#msgBoxContainer");

    if(!!msg){
        if(!!type){
            switch(type){
                case 'success':
                    type = 'green';
                    break;
                case 'error':
                    type = 'red';
                    break;

            }
        }else{
            type = 'red';
        }
    }else{
        msg = '暂无操作';
        if(!type){
            type = 'lightslategray';
        }
    }

    var msgHtml = '<b style="color: '+ type +';">' + msg + '</b>';
    msgBox.html(msgHtml);
    //提示后自动还原
    setTimeout(function () {$("#msgBoxContainer").html('<b style="color: lightslategray;">暂无操作</b>');}, 1500);
}


function customCopy(returnData){
    // 自动复制
    var clipboard = new ClipboardJS('.btn', {
        text: function(trigger){
            return returnData;
        }
    });

    clipboard.on('success', function(e) {
        ShowMsg('替换且复制成功，请直接粘贴（CTRL + V）使用！', 'success');
        $("#sourcebox").val('');
        $("#sourcebox").focus();
    });

    clipboard.on('error', function(e) {
        ShowMsg('复制失败');
    });

    clipboard = null;
}

// ========================================================================

// 页面开始执行
$(function () {

    //清空输入
    $("#sourcebox").val('');
    $("#sourcebox").focus();

    //监听所有button的点击事件
    $("button").click(function () {

        var sourceCode = $("#sourcebox").val();
        var platform = $("input[name='platform']:checked").val();
        var tagName = $(this).val();

        if(!tagName){
            $("#sourcebox").focus();
            return -1;
        }

        if(!sourceCode && !!tagName.indexOf('cmd_')){
            $("#sourcebox").focus();
            ShowMsg('请输入源代码！');
            return -1;
        }

        //ajax 后台处理
        $.ajax({
            url: 'api.php',
            type: 'POST',
            data: {sc: sourceCode, pf: platform, tn: tagName},
            dataType: 'json',
            async: false,
            beforeSend: function(xhr){
                ShowMsg('正在转换中，请稍等。。。', 'success');
            },
            success: function (data) {
                if(data.state == 1){
                    $("#sourcebox").val(data.data);
                    customCopy(data.data);
                }else if(data.state == 0){
                    ShowMsg(data.msg, 'success');
                }
                else{
                    ShowMsg(data.msg);
                }
            },
            error: function (xhr) {

            }
        });

    });

    //固定的复制内容-元素属性目标值复制
    var clipboard = new ClipboardJS('.fixed');

    clipboard.on('success', function(e) {
        ShowMsg('代码复制成功，请直接粘贴（CTRL + V）使用！', 'success');
        $("#sourcebox").val('');
        $("#sourcebox").focus();
    });

    clipboard.on('error', function(e) {
        ShowMsg('复制失败');
    });

    //固定内容复制-HTML id 元素内text
    var clipboard_hmtl = new ClipboardJS('.fixed_html', {
        target: function(trigger) {
            return document.getElementById("fixed_html_" + trigger.value);
        }
    });

    clipboard_hmtl.on('success', function(e) {
        ShowMsg('代码复制成功，请直接粘贴（CTRL + V）使用！', 'success');
        $("#sourcebox").val('');
        $("#sourcebox").focus();
    });

    clipboard_hmtl.on('error', function(e) {
        ShowMsg('复制失败');
    });
});