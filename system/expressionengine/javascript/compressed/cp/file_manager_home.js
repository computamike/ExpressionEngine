/*!
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2003 - 2010, EllisLab, Inc.
 * @license		http://expressionengine.com/user_guide/license.html
 * @link		http://expressionengine.com
 * @since		Version 2.0
 * @filesource
 */

$.tablesorter.addParser({id:"filesize",is:function(){return false},format:function(c){c=c.replace(/[^0-9.]/g,"");return c*1E3},type:"numeric"});
$(document).ready(function(){function c(a){var d,b;d=$("#file_information_hold");b=$("#file_information_header");b.removeClass("closed");b.addClass("open");d.slideDown("fast");d.html('<p style="text-align: center;"><img src="'+EE.THEME_URL+'images/indicator.gif" alt="'+EE.lang.loading+'" /><br />'+EE.lang.loading+"...</p>");$.get(EE.BASE+"&C=content_files&M=file_info",{file:a},function(e){d.html(e)})}function h(a){$("#progress").html('<span class="notice">'+a+"</span>")}function g(){$("td.fancybox a").unbind("click").fancybox({showEditLink:true}).click(function(){c($(this).attr("rel"))});
$(".toggle").unbind("click").click(function(){$(this).parent().parent().toggleClass("selected")});$(".mainTable td").unbind("click").click(function(a){if(a.ctrlKey||a.metaKey){$(this).parent().toggleClass("selected");$(this).parent().find(".file_select :checkbox").attr("checked")?$(this).parent().find(".file_select :checkbox").attr("checked",""):$(this).parent().find(".file_select :checkbox").attr("checked","true")}})}$(".mainTable").tablesorter({headers:{1:{sorter:"filesize"},4:{sorter:false},5:{sorter:false},
6:{sorter:false}},widgets:["zebra"],sortList:[[0,0]]});$("#file_tools").show();$("#download_selected").css("display","block");$("#showToolbarLink a").toggle(function(){$("#file_manager_tools").hide();$("#showToolbarLink a span").text(EE.lang.show_toolbar);$("#showToolbarLink").animate({marginRight:"20"});$("#file_manager_holder").animate({marginRight:"10"})},function(){$("#showToolbarLink a span").text(EE.lang.hide_toolbar);$("#showToolbarLink").animate({marginRight:"314"});$("#file_manager_holder").animate({marginRight:"300"},
function(){$("#file_manager_tools").show()})});$("#file_manager_tools h3 a").toggle(function(){$(this).parent().next("div").slideUp();$(this).toggleClass("closed")},function(){$(this).parent().next("div").slideDown();$(this).toggleClass("closed")});$("#file_manager_list h3").toggle(function(){document.cookie="exp_hide_upload_"+$(this).next().attr("id")+"=true";$(this).next().slideUp();$(this).toggleClass("closed")},function(){document.cookie="exp_hide_upload_"+$(this).next().attr("id")+"=false";$(this).next().slideDown();
$(this).toggleClass("closed")});$("#file_manager_tools h3.closed").next("div").hide();$("#file_manager_tools h3.closed a").click();$("input[type=file]").ee_upload({url:EE.BASE+"&C=content_files&M=upload_file&is_ajax=true",onStart:function(){$("#progress").html('<p><img src="'+EE.THEME_URL+'images/indicator.gif" alt="'+EE.lang.loading+'" />'+EE.lang.uploading_file+"</p>").show();dir_id=$("#upload_dir").val();return{upload_dir:dir_id}},onComplete:function(a,d,b){if(typeof a=="object")if(a.success){var e=
"#dir_id_"+b.upload_dir;$.get(EE.BASE+"&C=content_files&ajax=true&directory="+b.upload_dir+"&enc_path="+a.enc_path,function(i){var f=$("<div />");f.append(i);f=f.find("tbody tr");$(e+" tbody").append(f);$(e+" tbody .no_files_warning").parent().remove();$(e+" table").trigger("update");$("table").trigger("sorton",[[[0,0]]]);g(f);$("#progress").html(a).slideUp("slow")},"html")}else h(a.error)}});$("#download_selected a").click(function(){var a=$("#files_form").attr("action");$("#files_form").attr("action",
a.replace(/delete_files_confirm/,"download_files"));$("#files_form").submit();return false});$("a#email_files").click(function(){alert("not yet functional");return false});$("#delete_selected_files a").click(function(){var a=$("#files_form").attr("action");$("#files_form").attr("action",a.replace(/download_files/,"delete_files_confirm"));$("#files_form").submit();return false});$(".toggle_all").toggle(function(){$(this).closest("table").find("tbody tr").addClass("selected");$(this).closest("table").find("input.toggle").attr("checked",
true)},function(){$(this).closest("table").find("tbody tr").removeClass("selected");$(this).closest("table").find("input.toggle").attr("checked",false)});$("input.toggle").each(function(){this.checked=false});g()});
