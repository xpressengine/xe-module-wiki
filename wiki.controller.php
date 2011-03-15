<?php
	
	class wikiController extends wiki {

		function init() {
		}

		function procWikiInsertDocument() {
			// document module의 model 객체 생성
			$oDocumentModel = &getModel('document');

			// document module의 controller 객체 생성
			$oDocumentController = &getController('document');

			// 권한 체크
			if(!$this->grant->write_document) return new Object(-1, 'msg_not_permitted');
			$entry = Context::get('entry');

			// 글작성시 필요한 변수를 세팅
			$obj = Context::getRequestVars();
			$obj->module_srl = $this->module_srl;
			if($this->module_info->use_comment != 'N') $obj->allow_comment = 'Y';
			else $obj->allow_comment = 'N';

			// 수정시 nick_name 설정
			if(!$obj->nick_name)
			{
				$logged_info = Context::get('logged_info');
				if($logged_info) $obj->nick_name = $logged_info->nick_name;
				else $obj->nick_name = 'anonymous';
			}

			if($obj->is_notice!='Y'||!$this->grant->manager) $obj->is_notice = 'N';

			settype($obj->title, "string");
			if($obj->title == '') $obj->title = cut_str(strip_tags($obj->content),20,'...');
			//그래도 없으면 Untitled
			if($obj->title == '') $obj->title = 'Untitled';

			// 이미 존재하는 글인지 체크
			$oDocument = $oDocumentModel->getDocument($obj->document_srl, $this->grant->manager);

			// 이미 존재하는 경우 수정
			if($oDocument->isExists() && $oDocument->document_srl == $obj->document_srl) {
				if($oDocument->get('title')=='Front Page') $obj->title = 'Front Page';
				$output = $oDocumentController->updateDocument($oDocument, $obj);

				// 성공적으로 수정되었을 경우 계층구조/ alias 변경
				if($output->toBool()) {
					$oDocumentController->deleteDocumentAliasByDocument($obj->document_srl);
					$oDocumentController->insertAlias($obj->module_srl, $obj->document_srl, $obj->title);
				}
				$msg_code = 'success_updated';

			// 그렇지 않으면 신규 등록
			} else {
				$output = $oDocumentController->insertDocument($obj);
				$msg_code = 'success_registed';
				$obj->document_srl = $output->get('document_srl');
				$oDocumentController->insertAlias($obj->module_srl, $obj->document_srl, $obj->title);
			}

			// 오류 발생시 멈춤
			if(!$output->toBool()) return $output;

			$this->recompileTree($this->module_srl);

			// 결과를 리턴
			$entry = $oDocumentModel->getAlias($output->get('document_srl'));
			if($entry) {
				$site_module_info = Context::get('site_module_info');
				$url = getSiteUrl($site_module_info->document,'','mid',$this->module_info->mid,'entry',$entry);
			} else {
				$url = getSiteUrl($site_module_info->document,'','document_srl',$output->get('document_srl'));
			}
			$this->setRedirectUrl($url);

			// 성공 메세지 등록
			$this->setMessage($msg_code);
		}

		function procWikiInsertComment() {
			// 권한 체크
			if(!$this->grant->write_comment) return new Object(-1, 'msg_not_permitted');

			// 댓글 입력에 필요한 데이터 추출
			$obj = Context::gets('document_srl','comment_srl','parent_srl','content','password','nick_name','nick_name','member_srl','email_address','homepage','is_secret','notify_message');
			$obj->module_srl = $this->module_srl;

			// 원글이 존재하는지 체크
			$oDocumentModel = &getModel('document');
			$oDocument = $oDocumentModel->getDocument($obj->document_srl);
			if(!$oDocument->isExists()) return new Object(-1,'msg_not_permitted');

			// comment 모듈의 model 객체 생성
			$oCommentModel = &getModel('comment');

			// comment 모듈의 controller 객체 생성
			$oCommentController = &getController('comment');

			// comment_srl이 존재하는지 체크
				  // 만일 comment_srl이 n/a라면 getNextSequence()로 값을 얻어온다.
				  if(!$obj->comment_srl) {
				$obj->comment_srl = getNextSequence();
			} else {
				$comment = $oCommentModel->getComment($obj->comment_srl, $this->grant->manager);
			}

			// comment_srl이 없을 경우 신규 입력
			if($comment->comment_srl != $obj->comment_srl) {

				// parent_srl이 있으면 답변으로
				if($obj->parent_srl) {
					$parent_comment = $oCommentModel->getComment($obj->parent_srl);
					if(!$parent_comment->comment_srl) return new Object(-1, 'msg_invalid_request');

					$output = $oCommentController->insertComment($obj);

				// 없으면 신규
				} else {
					$output = $oCommentController->insertComment($obj);
				}

				// 문제가 없고 모듈 설정에 관리자 메일이 등록되어 있으면 메일 발송
				if($output->toBool() && $this->module_info->admin_mail) {
					$oMail = new Mail();
					$oMail->setTitle($oDocument->getTitleText());
					$oMail->setContent( sprintf("From : <a href=\"%s#comment_%d\">%s#comment_%d</a><br/>\r\n%s", $oDocument->getPermanentUrl(), $obj->comment_srl, $oDocument->getPermanentUrl(), $obj->comment_srl, $obj->content));
					$oMail->setSender($obj->user_name, $obj->email_address);

					$target_mail = explode(',',$this->module_info->admin_mail);
					for($i=0;$i<count($target_mail);$i++) {
						$email_address = trim($target_mail[$i]);
						if(!$email_address) continue;
						$oMail->setReceiptor($email_address, $email_address);
						$oMail->send();
					}
				}

			// comment_srl이 있으면 수정으로
			} else {
				$obj->parent_srl = $comment->parent_srl;
				$output = $oCommentController->updateComment($obj, $this->grant->manager);
				$comment_srl = $obj->comment_srl;
			}

			if(!$output->toBool()) return $output;

			$this->setMessage('success_registed');
			$this->add('mid', Context::get('mid'));
			$this->add('document_srl', $obj->document_srl);
			$this->add('comment_srl', $obj->comment_srl);
		}

		function procWikiDeleteDocument() {
			$oDocumentController = &getController('document');
			$oDocumentModel = &getModel('document');

			// 권한 체크
			if(!$this->grant->delete_document) return new Object(-1, 'msg_not_permitted');

			$document_srl = Context::get('document_srl');
			if(!$document_srl) return new Object(-1,'msg_invalid_request');

			$oDocument = $oDocumentModel->getDocument($document_srl);
			if(!$oDocument || !$oDocument->isExists()) return new Object(-1,'msg_invalid_request');
			if($oDocument->get('title')=='Front Page') return new Object(-1,'msg_invalid_request');

			$output = $oDocumentController->deleteDocument($oDocument->document_srl);
			if(!$output->toBool()) return $output;

			$oDocumentController->deleteDocumentAliasByDocument($oDocument->document_srl);
			$this->recompileTree($this->module_srl);

			$tree_args->module_srl = $this->module_srl;
			$tree_args->document_srl = $oDocument->document_srl;
			$output = executeQuery('wiki.deleteTreeNode', $tree_args);

			$site_module_info = Context::get('site_module_info');
			$this->setRedirectUrl(getSiteUrl($site_module_info->domain,'','mid',$this->module_info->mid));
		}

		function procWikiDeleteComment() {
			// check the comment's sequence number 
			$comment_srl = Context::get('comment_srl');
			if(!$comment_srl) return $this->doError('msg_invalid_request');

			// create controller object of comment module 
			$oCommentController = &getController('comment');

			$output = $oCommentController->deleteComment($comment_srl, $this->grant->manager);
			if(!$output->toBool()) return $output;

			$this->add('mid', Context::get('mid'));
			$this->add('page', Context::get('page'));
			$this->add('document_srl', $output->get('document_srl'));
			$this->setMessage('success_deleted');
		}

		function procWikiMoveTree() {
			// 권한 체크
			if(!$this->grant->write_document) return new Object(-1, 'msg_not_permitted');

			// request argument 추출
			$args = Context::gets('parent_srl','target_srl','source_srl');

			// 노드 정보 구함
			$output = executeQuery('wiki.getTreeNode', $args);
			$node = $output->data;
			if(!$node->document_srl) return new Object('msg_invalid_request');

			$args->module_srl = $node->module_srl;
			$args->title = $node->title;
			if(!$args->parent_srl) $args->parent_srl = 0;
			// target이 없으면 부모의 list_order중 최소 list_order를 구함
			if(!$args->target_srl) {
				$list_order->parent_srl = $args->parent_srl;
				$output = executeQuery('wiki.getTreeMinListorder',$list_order);
				if($output->data->list_order) $args->list_order = $output->data->list_order-1;
				// target이 있으면 그 target의 list_order + 1
			} else {
				$t_args->source_srl = $args->target_srl;
				$output = executeQuery('wiki.getTreeNode', $t_args);
				$target = $output->data;

				// target보다 list_order가 크고 부모가 같은 node에 대해서 list_order+2를 해주고 선택된 node에 list_order+1을 해줌
				$update_args->module_srl = $target->module_srl;
				$update_args->parent_srl = $target->parent_srl;
				$update_args->list_order = $target->list_order;
				if(!$update_args->parent_srl) $update_args->parent_srl = 0;
				$output = executeQuery('wiki.updateTreeListOrder', $update_args);
				if(!$output->toBool()) return $output;

				// target을 원위치 (list_order중복 문제로 인하여 1번 더 업데이트를 시도함) <- why?
				/*$restore_args->module_srl = $target->module_srl;
				$restore_args->source_srl = $target->document_srl;
				$restore_args->list_order = $target->list_order;
				$output = executeQuery('wiki.updateTreeNode', $restore_args);
				if(!$output->toBool()) return $output;*/

				$args->list_order = $target->list_order+1;
			}
			if(!$node->is_exists) $output = executeQuery('wiki.insertTreeNode',$args);
			else $output = executeQuery('wiki.updateTreeNode',$args);
			if(!$output->toBool()) return $output;

			if($args->list_order) {
				$doc->document_srl = $args->source_srl;
				$doc->list_order = $args->list_order;
				$output = executeQuery('wiki.updateDocumentListOrder', $doc);
				if(!$output->toBool()) return $output;
			}

			$this->recompileTree($this->module_srl);
		}

		/**
		 * @brief 위키 계층 구조 재생성
		 **/
		function procWikiRecompileTree() {
			if(!$this->grant->write_document) return new Object(-1,'msg_not_permitted');
			return $this->recompileTree($this->module_srl);
		}

		function recompileTree($module_srl) {
			$oWikiModel = &getModel('wiki');
			$list = $oWikiModel->loadWikiTreeList($module_srl);

			$dat_file = sprintf('%sfiles/cache/wiki/%d.dat', _XE_PATH_,$module_srl);
			$xml_file = sprintf('%sfiles/cache/wiki/%d.xml', _XE_PATH_,$module_srl);

			$buff = '';
			$xml_buff = "<root>\n";

			// cache 파일 생성
			foreach($list as $key => $val) {
				$buff .= sprintf('%d,%d,%d,%d,%s%s',$val->parent_srl,$val->document_srl,$val->depth,$val->childs,$val->title,"\n");
				$xml_buff .= sprintf('<node node_srl="%d" parent_srl="%d"><![CDATA[%s]]></node>%s', $val->document_srl, $val->parent_srl, $val->title,"\n");
			}

			$xml_buff .= '</root>';

			FileHandler::writeFile($dat_file, $buff);
			FileHandler::writeFile($xml_file, $xml_buff);

			return new Object();

		}


		/**
		 * @brief 비회원 댓글 수정을 위한 패스워드 확인
		 */
		function procWikiVerificationPassword()
		{
			$password = Context::get('password');
			$comment_srl = Context::get('comment_srl');

			$oMemberModel = &getModel('member');

			if($comment_srl)
			{
				$oCommentModel = &getModel('comment');
				$oComment = $oCommentModel->getComment($comment_srl);
				if(!$oComment->isExists()) return new Object(-1, 'msg_invalid_request');

				if(!$oMemberModel->isValidPassword($oComment->get('password'), $password)) return new Object(-1, 'msg_invalid_password');
				
				$oComment->setGrant();
			}
		}
	}
?>
