<?php
    /**
     * @class wiki
     * @author haneul (haneul0318@gmail.com)
     * @brief  wiki 모듈의 high class
     **/

    class wiki extends ModuleObject {


		static $omitting_characters = array('/&/', '/\//', '/,/', '/ /');
		static $replacing_characters = array('', '', '', '_');

		static function makeEntryName($matches)
		{
			$answer->is_alias_link = false;

			$matches[0] = trim($matches[0]);

			$names = explode('|', $matches[1]);
			foreach ($names as $key => $entry_name)
			{
				$names[$key] = trim($entry_name);
			}
			$processed_names = array();
			foreach ($names as $key => $entry_name)
			{
				$entry_name = wiki::beautifyEntryName($entry_name);
				$processed_names[] = $entry_name;
			}

			if(count($names) == 2)
			{
				$answer->is_alias_link = true;
				$answer->printing_name = $names[1];
				$answer->link_entry = $processed_names[0];
			}
			else
			{
				$answer->printing_name = $names[0];
				$answer->link_entry = $processed_names[0];
			}
			return $answer;
		}

		static function beautifyEntryName($entry_name)
		{
			$entry_name = strip_tags($entry_name);
			$entry_name = html_entity_decode($entry_name);
			$entry_name = preg_replace(wiki::$omitting_characters, wiki::$replacing_characters, $entry_name);
			$entry_name = preg_replace('/[_]+/', '_', $entry_name);

			return $entry_name;			
		}

        function moduleInstall() {
            return new Object();
        }

        /**
         * @brief 설치가 이상이 없는지 체크하는 method
         **/
        function checkUpdate() {
			$flag = false;
			$flag = $this->_hasOldStyleAliases();
            return $flag;
        }

        function moduleUpdate() {
			$this->_updateOldStyleAliases();
            return new Object(0, 'success_updated');
        }

		function moduleUninstall() {
			return new Object();
		}

        /**
         * @brief 캐시 파일 재생성
         **/
        function recompileCache() {
            FileHandler::removeFilesInDir(_XE_PATH_."files/cache/wiki");
        }


		function _hasOldStyleAliases()
		{
			// Wiki 모듈의 modules_srl 을 모두 구함.
			$output = executeQueryArray('wiki.getAllWikiList', null);
			$wiki_srls = array();
			if(count($output->data))
			{
				foreach($output->data as $key => $module_instance)
				{
					$wiki_srls[] = $module_instance->module_srl;
				}
			}
			$args->wiki_srls = $wiki_srls;

			$output = executeQueryArray('wiki.checkOldStyleAliases', $args);
			if(count($output->data))
			{
				$omitting_characters = array('&','//', ',', ' ');

				foreach($output->data as $key => $doc_alias)
				{
					if($doc_alias->alias_title == 'Front Page') continue;
					foreach($omitting_characters as $key => $char)
					{	
						debugPrint($doc_alias->alias_title);
						debugPrint(strpos($doc_alias->alias_title, $char));
						if(strpos($doc_alias->alias_title, $char)) return true;
					}
				}
			}
			return false;
		}

		function _updateOldStyleAliases()
		{
			// Wiki 모듈의 modules_srl 을 모두 구함.
			$output = executeQueryArray('wiki.getAllWikiList', null);
			$wiki_srls = array();
			if(count($output->data))
			{
				foreach($output->data as $key => $module_instance)
				{
					$wiki_srls[] = $module_instance->module_srl;
				}
			}
			$args->wiki_srls = $wiki_srls;

			$output = executeQueryArray('wiki.checkOldStyleAliases', $args);

			if(count($output->data))
			{
				foreach($output->data as $key => $doc_alias)
				{
					$omitting_characters = array('&','//', ',', ' ');
					if($doc_alias->alias_title == 'Front Page') break;
					foreach($omitting_characters as $key => $char)
					{	
						if(strpos($doc_alias->alias_title, $char))
						{
							unset($args);
							$args->alias_srl = $doc_alias->alias_srl;
							$args->alias_title = wiki::beautifyEntryName($doc_alias->alias_title);
							$output = executeQuery('wiki.updateDocumentAlias', $args);
							break;
						}
					}
				}
			}
		}

    }
?>
