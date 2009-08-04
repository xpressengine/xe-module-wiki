/**
 * @file   modules/wiki/js/wiki.js
 * @author zero (zero@nzeo.com)
 * @brief  wiki 모듈의 javascript
 **/

/* 글삭제 요청 */
function doDeleteWiki(document_srl) {
    var params = new Array();
    params['mid'] = current_mid;
    params['document_srl'] = document_srl;
    exec_xml('wiki','procWikiDeleteDocument', params);
}

/* 댓글 글쓰기 작성후 */
function completeInsertComment(ret_obj) {
    var error = ret_obj['error'];
    var message = ret_obj['message'];
    var mid = ret_obj['mid'];
    var document_srl = ret_obj['document_srl'];
    var comment_srl = ret_obj['comment_srl'];

    var url = current_url.setQuery('mid',mid).setQuery('document_srl',document_srl).setQuery('act','');
    if(comment_srl) url = url.setQuery('rnd',comment_srl)+"#comment_"+comment_srl;

    location.href = url;
}

/* 댓글 삭제 */
function completeDeleteComment(ret_obj) {
    var error = ret_obj['error'];
    var message = ret_obj['message'];
    var mid = ret_obj['mid'];
    var document_srl = ret_obj['document_srl'];
    var page = ret_obj['page'];

    var url = current_url.setQuery('mid',mid).setQuery('document_srl',document_srl).setQuery('act','');
    if(page) url = url.setQuery('page',page);

    location.href = url;
}
