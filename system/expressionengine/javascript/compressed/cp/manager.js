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

(function(a){function r(b,d){b.hasClass("highlightRow")&&b.removeClass("highlightRow");if(b.data(d)){var c=b.data(d).is(":visible");m(b);if(!c){b.addClass("highlightRow");b.data(d).show()}return true}m(b);return false}function m(b,d){if(d)a(b).data(d)&&a(b).data(d).hide();else{m(b,"prefsRow");m(b,"accessRow")}}function v(b,d){b.find("input:radio").each(function(){var c,e;c=a(this).attr("id").split("_");e=c.slice(0,-1).join("_");c=c.slice(-1)[0];a(this).attr({id:e+"_"+d+"_"+c,name:e+"_"+d})})}function w(b,
d){var c=a('<tr class="accessRowHeader"><td colspan="6">'+n+"</td></tr>");c.find("select").each(function(){var e=a(this);switch(this.name){case "template_type":e.val(d.type);break;case "cache":e.val(d.cache);break;case "allow_php":e.val(d.allow_php);break;case "php_parse_location":e.val(d.php_parsing);break}e.attr("name",this.name+"_"+d.id)});c.find(".template_name").val(d.name);d.name==="index"&&c.find(".template_name").attr({readonly:"readonly"});c.find(".refresh").val(d.refresh);c.find(".hits").val(d.hits);
c.data("ajax_ids",{id:d.id,group_id:d.group_id});b.data("prefsRow",c);a(b).addClass("highlightRow");a(b).after(c)}function s(){a(".templateTable .accessTable").find("input:text").unbind("blur.manager_updated").bind("blur.manager_updated",k);a(".templateTable .accessTable").find("input:radio").unbind("click.manager_updated").bind("click.manager_updated",k);a(".templateTable .accessTable").find("select").unbind("change.manager_updated").bind("change.manager_updated",k)}function t(b,d){var c="input:radio[id$=_";
if(d)c="input:radio[id$=_"+d+"_";b.find(".ignore_radio").click(function(){this.value==="y"&&b.find(c+"y]").filter(":not(.ignore_radio)").trigger("click");this.value==="n"&&b.find(c+"n]").filter(":not(.ignore_radio)").trigger("click");a(this).attr("checked","");return false})}function x(b,d,c){var e=a('<tr class="accessRowHeader"><td colspan="6">'+o+"</td></tr>");e.find(".no_auth_bounce").val(c.no_auth_bounce);e.find(".no_auth_bounce").attr({id:"no_auth_bounce_"+b,name:"no_auth_bounce_"+b});e.find(".enable_http_auth").val(c.enable_http_auth);
e.find(".enable_http_auth").attr({id:"enable_http_auth_"+b,name:"enable_http_auth_"+b});v(e,b);a.each(c.access,function(g,f){var i=e.find("#access_"+g+"_"+b+"_y"),j=e.find("#access_"+g+"_"+b+"_n");if(f.access===true){i.attr("checked","checked");j.attr("checked","")}else{j.attr("checked","checked");i.attr("checked","")}});t(e,b);a(d).addClass("highlightRow");a(d).after(e);e.find(".accessTable").tablesorter({widgets:["zebra"]});d.data("accessRow",e)}function y(b){var d,c;if(b.attr("name").substr(0,
14)==="no_auth_bounce"){c=b.attr("name").substr(15)?b.attr("name").substr(15):a("input:hidden[name=template_id]").val();p(b,c,"","no_auth_bounce")}else if(b.attr("name").substr(0,16)==="enable_http_auth"){c=b.attr("name").substr(17)?b.attr("name").substr(17):a("input:hidden[name=template_id]").val();p(b,c,"","enable_http_auth")}else{d=b.attr("name").replace("access_","").split("_");c=d.length<2?a("input:hidden[name=template_id]").val():d[1];p(b,c,d[0],"access")}}function p(b,d,c,e){var g="";switch(e){case "no_auth_bounce":g=
jQuery.param({template_id:d,no_auth_bounce:b.val()});break;case "enable_http_auth":g=jQuery.param({template_id:d,enable_http_auth:b.val()});break;case "access":e=!a(b).closest(".accessTable").length?a(".no_auth_bounce").val():a(b).closest(".accessTable").find(".no_auth_bounce").val();g=jQuery.param({template_id:d,member_group_id:c,new_status:b.val(),no_auth_bounce:e});break}a.ajax({type:"POST",url:EE.access_edit_url,data:"is_ajax=TRUE&XID="+EE.XID+"&"+g,success:function(f){f!==""&&a.ee_notice(f,{duration:3E3,
type:"success"})},error:function(f){f.responseText!==""&&a.ee_notice(f.responseText,{duration:3E3,type:"error"})}})}function k(){var b=a(this).closest(".accessRowHeader"),d,c,e,g,f,i,j,u,q;if(b.length<1)b=a(this).closest(".templateEditorTable");d=b.data("ajax_ids");if(!d){if(a(this).hasClass("ignore_radio"))return false;return y(a(this))}c=d.id;d=d.group_id;e=b.find(".template_name").val();g=b.find(".template_type").val();f=b.find("select[name^=cache]").val();i=b.find(".refresh").val();j=b.find("select[name^=allow_php]").val();
u=b.find("select[name^=php_parse_location]").val();q=b.find(".hits").val();template_size=b.find(".template_size").val();str=jQuery.param({template_id:c,group_id:d,template_name:e,template_type:g,cache:f,refresh:i,hits:q,allow_php:j,php_parse_location:u,template_size:template_size});a.ajax({type:"POST",url:EE.template_edit_url,data:"is_ajax=TRUE&XID="+EE.XID+"&"+str,success:function(l){var h=a("#templateId_"+c);h.text(e);if(h.closest(".templateName").length){h=h.closest(".templateName").next().find("a");
if(h.length){h=h.get(0);h.href=h.href.replace(/\/[^\/]*$/,"/"+e)}}else if(a("#templateViewLink a.submit").length){h=a("#templateViewLink a.submit");if(h.length){h=h.get(0);h.href=h.href.replace(/\/[^\/]*$/,"/"+e)}}a("#template_data").attr("rows",template_size);a("#hitsId_"+c).text(q);l!==""&&a.ee_notice(l,{duration:3E3,type:"success"})},error:function(l){l.responseText!==""&&a.ee_notice(l.responseText,{duration:3E3,type:"error"})}})}var n,o;a(document).ready(function(){var b,d,c;n=a("#prefRowTemplate").html();
o=a("#accessRowTemplate").html();if(!n||!o){b=a("#templateAccess, #templatePreferences");d=a("input:hidden[name=template_id]").val();c=a("input:hidden[name=group_id]").val();a("#templatePreferences").data("ajax_ids",{id:d,group_id:c});t(a("#templateAccess"));b.find("input:text").unbind("blur.manager_updated").bind("blur.manager_updated",k);b.find("input:radio").unbind("click.manager_updated").bind("click.manager_updated",k);b.find("select").unbind("change.manager_updated").bind("change.manager_updated",
k)}else{a("#prefRowTemplate, #accessRowTemplate").remove();EE.manager={showPrefsRow:function(e,g){var f=a(g).parent().parent();if(!r(f,"prefsRow")){w(f,e);s()}return false},showAccessRow:function(e,g,f){f=a(f).parent().parent();if(!r(f,"accessRow")){x(e,f,g);s();f.trigger("applyWidgets")}return false}}}});a(document).ready(function(){if(EE.manager&&EE.manager.warnings){a(".warning_details").hide();a(".toggle_warning_details").click(function(){a(".warning_details").hide();a("#wd_"+this.id.substr(3)).show();
return false});var b=a("#template_data"),d;find_and_replace=function(c,e,g){var f,i="";if(g&&g.length>1)i='<select name="fr_options" id="fr_options"></select>';f='<div style="padding: 5px;"><label>Find:</label> <input name="fr_find" id="fr_find" type="text" value="" /> <label>Replace:</label> <input type="text" name="fr_replace" id="fr_replace" value=""/> '+i+"</div>";f+='<div style="padding: 5px;"><button class="submit" id="fr_find_btn">Find Next</button> <button class="submit" id="fr_replace_btn">Replace</button> <button class="submit" id="fr_replace_all_btn">Replace All</button> <label><input name="fr_replace_closing_tags" id="fr_replace_closing_tags" type="checkbox" /> Include Closing Tags</label></div>';
a.ee_notice(f,{type:"custom",open:true,close_on_click:false});a("#fr_find").val(c);a("#fr_replace").val(e);a("#fr_replace_closing_tags").attr("checked","");if(i!==""){a("#fr_options").append(a(g));a("#fr_options").click(function(){a("#fr_find").val(a(this).val());a("#fr_find_btn").click()})}c&&a("#fr_find_btn").click()};a("#fr_find_btn").live("click",function(){var c=a("#fr_find").val();d=b.selectNext(c).scrollToCursor()});a("#fr_replace_btn").live("click",function(){var c=a("#fr_find").val(),e=a("#fr_replace").val();
d.getSelectedText()===c&&d.replaceWith(e)});a("#fr_replace_all_btn").live("click",function(){var c=a("#fr_find").val(),e=a("#fr_replace").val();if(jQuery.trim(c)!==""){b.val(b.val().split(c).join(e));if(a("#fr_replace_closing_tags").attr("checked")){if(c[0]==="{"&&c.substr(0,2)!=="{/")c="{/"+c.substr(1);if(e[0]==="{"&&e.substr(0,2)!=="{/")e="{/"+e.substr(1);jQuery.trim(c)!==""&&b.val(b.val().split(c).join(e))}}});a(".find_and_replace").click(function(){var c=this.id.substr(8),e="{exp:"+c,g="{exp:"+
EE.manager.warnings[c].suggestion,f=EE.manager.warnings[c].full_tags,i=Array(new Option(e,e));if(f&&f.length>1)for(var j=0;j<f.length;j++){c="{"+f[j]+"}";i.push(new Option(c,c))}if(g==="{exp:")g="";find_and_replace(e,g,i);return false})}})})(jQuery);
