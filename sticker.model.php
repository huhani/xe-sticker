<?php
/*! Copyright (C) 2016 BGM STORAGE. All rights reserved. */
/**
 * @class  stickerModel
 * @author Huhani (mmia268@gmail.com)
 * @brief  Sticker module model class.
 */

class stickerModel extends sticker
{
	function init()
	{
	}

	function getConfig()
	{
		static $config = null;
		if(is_null($config))
		{
			$oModuleModel = getModel('module');
			$config = $oModuleModel->getModuleConfig('sticker');
			if(!$config)
			{
				$config = new stdClass;
			}

			unset($config->body);
			unset($config->_filter);
			unset($config->error_return_url);
			unset($config->act);
			unset($config->module);
		}

		return $config;
	}

	function getCommentStickerList(){

		$logged_info =  Context::get('logged_info');
		$sticker_array = $this->getDefaultSticker();

//debugPrint($stocker_array);
		$defaultStickerCount = count($sticker_array);
		$page = Context::get('page') ? Context::get('page') : 1;
		$date = date('YmdHis');

		$list_count = Mobile::isMobileCheckByAgent() ? 5 : 12;

		if($logged_info){
			$args = new stdClass();
			$args->page = $page;
			$args->list_count = $page == 1 ? ($list_count-$defaultStickerCount) : $list_count;
			$args->page_count = 2;
			$args->order_type = 'asc';
			$args->member_srl = $logged_info->member_srl;
			$args->date = $date;
			$output2 = executeQueryArray('sticker.getStickerMylist', $args);

			$count = page > 1 || $defaultStickerCount == 5 ? $defaultStickerCount : 0;

			if($page > 1){
				unset($sticker_array);
				$sticker_array = array();
				$prev_page = new stdClass();
				$prev_page->page = $page-1;
				$prev_page->list_count = $list_count;
				$prev_page->order_type = 'asc';
				$prev_page->member_srl = $logged_info->member_srl;
				$prev_page->date = $date;
				$output = executeQueryArray('sticker.getStickerMylist', $prev_page);
				$prev_data = $output->data;
				$prev_page_count = count($output->data);

				if($prev_page_count > $list_count-$defaultStickerCount){
					end($prev_data);
					$countMovePos = $defaultStickerCount && $defaultStickerCount - ($list_count - $prev_page_count) > 0 ? $defaultStickerCount - ($list_count - $prev_page_count) : $defaultStickerCount;
					for($i=1; $i<$countMovePos; $i++){
						prev($prev_data);
					}
					for($i=$countMovePos; $i>0; $i--){
						$current = current($prev_data);

						$args = new stdClass();
						$args->sticker_srl = $current->sticker_srl;
						$args->no = 0;
						$output1 = executeQuery('sticker.getStickerMainImage', $args);

						$obj = new stdClass();
						$obj->sticker_srl = $current->sticker_srl;
						$obj->title = $current->title;
						$obj->main_image = $output1->data->url;

						if($i !== 1){
							next($prev_data);
						}
						array_push($sticker_array, $obj);
						$count++;
					}
				}
			}


			foreach($output2->data as $key=>$sticker){
				if($count >= $list_count){
					break;
				}
				$args1 = new stdClass();
				$args1->sticker_srl = $sticker->sticker_srl;
				$args1->no = 0;
				$output1 = executeQueryArray('sticker.getStickerMainImage', $args1);
			
				$obj = new stdClass();
				$obj->sticker_srl = $sticker->sticker_srl;
				$obj->title = $sticker->title;
				$obj->main_image = $output1->data[0]->url;
				array_push($sticker_array, $obj);
				$count++;
			}

			//$this->add("page_navigation", $output2->page_navigation);

		}

		$this->add("sticker", $sticker_array);

	}

	function getStickerElemList(){
		$sticker_srl = Context::get('sticker_srl');
		$logged_info = Context::get('logged_info');
		$member_srl = $logged_info ? $logged_info->member_srl : 0;

		if(!$sticker_srl){
			return new Object(-1,'invalid_sticker');
		}

		$isDefaultSticker = $this->checkDefaultSticker($sticker_srl);
		if(!$isDefaultSticker){
			if(!$member_srl){
				return new Object(-1,'invalid_sticker');
			}

			$isAccessable = $this->checkBuySticker($member_srl, $sticker_srl);
			if(!$isAccessable){
				return new Object(-1,'invalid_sticker');
			}
		}

		$stickerImageArray = array();

		$args = new stdClass();
		$args->sticker_srl = $sticker_srl;
		$output = executeQuery('sticker.getStickerImage', $args);
		if(!count($output->data)){
			return new Object(-1,'invalid_sticker');
		}
		foreach($output->data as $value){
			$obj = new stdClass();
			$obj->sticker_file_srl = $value->sticker_file_srl;
			//$obj->no = $value->no;
			$name = substr($value->file_name, 0, strrpos($value->file_name, "."));
			$obj->name = htmlspecialchars($name);
			$obj->url = $value->url;
			array_push($stickerImageArray, $obj);
		}

		$this->add("stickerImage", $stickerImageArray);

	}

	function getCommentSticekrCountByDocumentSrl($document_srl = 0, $member_srl = 0){
		$args = new stdClass();
		$args->document_srl = $document_srl;
		$args->member_srl = $member_srl;
		$args->content = "{@sticker:";
		$output = executeQuery('sticker.getCommentStickerByMemberSrl', $args);
		if(!$output->toBool()){
			return false;
		}
		$comments = $output->data;
		$typeComment = gettype($comments);
		$count = 0;

		if($typeComment === 'object'){
			if(preg_match('/{@sticker:[0-9]+\|[0-9]+}/i', $comments->content)){
				$count++;
			}
		} else {
			foreach($comments as $value){
				if(preg_match('/{@sticker:[0-9]+\|[0-9]+}/i', $value->content)){
					$count++;
				}
			}
		}

		return $count;
	}

	function getSticker($sticker_srl){
		$args = new stdClass();
		$args->sticker_srl = $sticker_srl;
		$output = executeQuery('sticker.getSticker', $args);
		return !empty($output->data) ? $output->data : false;
	}

	function getDefaultSticker(){
		$defaultSticker = $this->module_config->default_sticker;
		$sticker = explode(',', $defaultSticker);
		$stickerArray = array();
		foreach($sticker as $key=>$value){
			$value = trim($value);
			$oSticker = $this->getSticker($value);

			if($key < 5 && $oSticker && $oSticker->status != "STOP"){
				$args = new stdClass();
				$args->sticker_srl = $value;
				$args->no = 0;
				$output = executeQuery('sticker.getStickerMainImage', $args);

				$obj = new stdClass();
				$obj->sticker_srl = $oSticker->sticker_srl;
				$obj->title = $oSticker->title;
				$obj->main_image = $output->data->url;

				array_push($stickerArray, $obj);
			}
		}

		return $stickerArray;
	}

	function checkDefaultSticker($sticker_srl){
		$defaultSticker = $this->module_config->default_sticker;
		$sticker = explode(',', $defaultSticker);
		foreach($sticker as $value){
			$value = trim($value);
			if($value == $sticker_srl){
				return true;
				break;
			}
		}

		return false;
	}

	function checkBuySticker($member_srl = 0, $sticker_srl = 0){
		$args = new stdClass();
		$args->member_srl = $member_srl;
		$args->sticker_srl = $sticker_srl;
		$args->date = date("YmdHis");
		$output = executeQuery('sticker.getStickerBuyCheck', $args);
		return (!$output->toBool() || $output->data->count == 0) ? FALSE : TRUE;
	}

}

/* End of file sticker.model.php */
/* Location: ./modules/sticker/sticker.model.php */
