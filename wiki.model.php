<?php
    /**
     * @class  wikiModel
     * @author haneul (haneul0318@gmail.com) 
     * @brief  wiki 모듈의 Model class
     **/

    class wikiModel extends module {
        /**
         * @brief 초기화
         **/
        function init() {
        }

        /**
         * @brief 계층구조 추출
         * document_category테이블을 이용해서 위키 문서의 계층 구조도를 그림
         * document_category테이블에 등록되어 있지 않은 경우 depth = 0 으로 하여 신규 생성
         **/
        function getWikiTreeList() {
            $oWikiController = &getController('wiki');

            header("Content-Type: text/xml; charset=UTF-8");
            header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
            header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
            header("Cache-Control: no-store, no-cache, must-revalidate");
            header("Cache-Control: post-check=0, pre-check=0", false);
            header("Pragma: no-cache");

            if(!$this->module_srl) return new Object(-1,'msg_invalid_request');

            $xml_file = sprintf('%sfiles/cache/wiki/%d.xml', _XE_PATH_,$this->module_srl);
            if(!file_exists($xml_file)) $oWikiController->recompileTree($this->module_srl);
            print FileHandler::readFile($xml_file);
            Context::close();
            exit();
        }

        function readWikiTreeCache($module_srl) {
            $oWikiController = &getController('wiki');
            if(!$module_srl) return new Object(-1,'msg_invalid_request');

            $dat_file = sprintf('%sfiles/cache/wiki/%d.dat', _XE_PATH_,$module_srl);
            if(!file_exists($dat_file)) $oWikiController->recompileTree($module_srl);
            $buff = explode("\n", trim(FileHandler::readFile($dat_file)));
            if(!count($buff)) return array();
            foreach($buff as $val) {
                if(!preg_match('/^([0-9]+),([0-9]+),([0-9]+),([0-9]+),(.+)$/i', $val, $m)) continue;
                unset($obj);
                $obj->parent_srl = $m[1];
                $obj->document_srl = $m[2];
                $obj->depth = $m[3];
                $obj->childs = $m[4];
                $obj->title = $m[5];
                $list[] = $obj;
            }
            return $list;
        }

        function loadWikiTreeList($module_srl) {
            // 목록을 구함
            $args->module_srl = $module_srl;
            $output = executeQueryArray('wiki.getTreeList', $args);

            if(!$output->data || !$output->toBool()) return array();

            $list = array();
            $root_node = null;
            foreach($output->data as $node) {
                if($node->title == 'Front Page') {
                    $root_node = $node;
                    $root_node->parent_srl = 0;
                    continue;
                } 
                unset($obj);
                $obj->parent_srl = (int)$node->parent_srl;
                $obj->document_srl = (int)$node->document_srl;
                $obj->title = $node->title;
                $list[$obj->document_srl] = $obj;
            }

            $tree[$root_node->document_srl]->node = $root_node;

            foreach($list as $document_srl => $node) {
                if(!$list[$node->parent_srl]) $node->parent_srl = $root_node->document_srl;
                $tree[$node->parent_srl]->childs[$document_srl] = &$tree[$document_srl];
                $tree[$document_srl]->node = $node;

            }

            $result[$root_node->document_srl] = $tree[$root_node->document_srl]->node;
            $result[$root_node->document_srl]->childs = count($tree[$root_node->document_srl]->childs);

            $this->getTreeToList($tree[$root_node->document_srl]->childs, $result,1);
            return $result;
        }

        function getPrevNextDocument($module_srl, $document_srl) {
            $list = $this->readWikiTreeCache($module_srl);
            if(!count($list)) return array(0,0);

            $prev = $next_srl = $prev_srl = 0;
            $checked = false;
            foreach($list as $key => $val) {
                if($checked) {
                    $next_srl = $val->document_srl; 
                    break;
                }
                if($val->document_srl == $document_srl) {
                    $prev_srl = $prev;
                    $checked = true;
                }
                $prev = $val->document_srl;
            }
            return array($prev_srl, $next_srl);
        }

        function getTreeToList($childs, &$result,$depth) {
            if(!count($childs)) return;
            foreach($childs as $key => $node) {
                $node->node->depth = $depth;
                $node->node->childs = count($node->childs);
                $result[$key] = $node->node;
                if($node->childs) $this->getTreeToList($node->childs, $result,$depth+1);
            }
        }

        function getContributors($document_srl) {
            $oDocumentModel = &getModel('document');
            $oDocument = $oDocumentModel->getDocument($document_srl);
            if(!$oDocument->isExists()) return array();

            $args->document_srl = $document_srl;
            $output = executeQueryArray("wiki.getContributors", $args);
            if($output->data) $list = $output->data;
            else $list = array();

            $item->member_srl = $oDocument->getMemberSrl();
            $item->nick_name = $oDocument->getNickName();
            $contributors[] = $item;
            for($i=0,$c=count($list);$i<$c;$i++) {
                unset($item);
                $item->member_srl = $list[$i]->member_srl;
                $item->nick_name = $list[$i]->nick_name;
                if($item->member_srl == $oDocument->getMemberSrl()) continue;
                $contributors[] = $item;
            }
            return $contributors;
        }
    }
?>
