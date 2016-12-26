<?php
/*! Copyright (C) 2016 BGM STORAGE. All rights reserved. */
/**
 * @class  stickerController
 * @author Huhani (mmia268@gmail.com)
 * @brief  Sticker module controller class.
 */

class stickerController extends sticker
{
	function init(){
		//직접적으로 sticker모듈이 로딩되었을 때만 적용됨.
		$oStickerModel = getModel('sticker');

		$this->module_config = $oStickerModel->getConfig();
		$this->module_config->start_time = date('YmdHis');
	}

	function triggerDeleteMember(&$obj){
		$member_srl = $obj->member_srl;
		if(!$member_srl){
			return new Object();
		}
		executeQuery('sticker.deleteStickerBuyAllByMemberSrl', $obj);

		return new Object();
	}

	function triggerBeforeModuleInit(&$obj){
		if(!Context::get('is_logged')){
			return new Object();
		}

		$oStickerModel = getModel('sticker');
		$module_config = $oStickerModel->getConfig();

		if($module_config->add_member_menu === "Y"){
			Context::loadLang('./modules/sticker/lang/lang.xml');

			$oMemberController = getController('member');
			$oMemberController->addMemberMenu('dispStickerMylist', 'cmd_sticker_mypage');
		}

		return new Object();
	}

	function triggerMemberMenu(&$obj){
		$member_srl = Context::get('target_srl');
		$mid = Context::get('cur_mid');

		if(!$member_srl || !$mid) {
			return new Object();
		}

		$logged_info = Context::get('logged_info');

		$oModuleModel = getModel('module');
		$columnList = array('module');
		$cur_module_info = $oModuleModel->getModuleInfoByMid($mid, 0, $columnList);

		if($cur_module_info->module != 'sticker'){
			return new Object();
		}

		if($member_srl == $logged_info->member_srl){
			$member_info = $logged_info;
		} else {
			$oMemberModel = getModel('member');
			$member_info = $oMemberModel->getMemberInfoByMemberSrl($member_srl);
		}

		if(!$member_info->user_id){
			return new Object();
		}

		$url = getUrl('', 'mid', 'sticker', 'search_target', 'nick_name', 'search_keyword', $member_info->nick_name);
		$oMemberController = getController('member');
		$oMemberController->addMemberPopupMenu($url, 'cmd_view_own_sticker', '');

		return new Object();
	}

	function triggerBeforeInsertDocument(&$obj){

		$oStickerModel = getModel('sticker');
		$module_config = $oStickerModel->getConfig();

		if($module_config->use != "Y"){
			return new Object();
		}

		$content = $obj->content;
		$content = preg_replace('/{@sticker:[0-9]+\|[0-9]+}/i', "", $content);

		$obj->content = $content;
	}

	function triggerBeforeUpdateDocument(&$obj){

		$oStickerModel = getModel('sticker');
		$module_config = $oStickerModel->getConfig();

		if($module_config->use != "Y"){
			return new Object();
		}

		$content = $obj->content;
		$content = preg_replace('/{@sticker:[0-9]+\|[0-9]+}/i', "", $content);

		$obj->content = $content;
	}

	function triggerBeforeInsertComment(&$obj){

		$oStickerModel = getModel('sticker');
		$module_config = $oStickerModel->getConfig();

		if($module_config->use != "Y"){
			return new Object();
		}

		$logged_info = Context::get('logged_info');

		$member_srl = $logged_info ? $logged_info->member_srl : 0;
		$content = html_entity_decode($obj->content);
		preg_match('/{@sticker:([0-9]+)\|([0-9]+)}/i', $content, $match);
		if(!empty($match)){
			$checkFake = $this->_checkFakeSticker($match[1], $match[2], $member_srl);
			if(!$checkFake){
				return new Object(-1,'invalid sticker');
			}

			$isUsable = $this->_checkUsableSticker($match[1]);
			if(!$isUsable){
				return new Object(-1,'disalbe sticker');
			}

			if($module_config->cmt_max_sticker_count != 0){
				$writeStickeCount = $oStickerModel->getCommentSticekrCountByDocumentSrl($obj->document_srl, $member_srl);
				if($writeStickeCount >= intval($module_config->cmt_max_sticker_count)){
					return new Object(-1,'msg_exceed_wrote_sticker_count');
				}
			}


			$obj->content = "{@sticker:".$match[1]."|".$match[2]."}";

			$this->_increaseStickerUsedCount($match[1], $match[2], $member_srl);
		} else {
			$isHiddenSticker = $this->_checkStickerInContent($content);
			if($isHiddenSticker){
				return new Object(-1,'invalid sticker');
			}
		}

	}

	function triggerBeforeUpdateComment(&$obj){

		$oStickerModel = getModel('sticker');
		$module_config = $oStickerModel->getConfig();

		if($module_config->use != "Y"){
			return new Object();
		}

		$logged_info = Context::get('logged_info');

		if($module_config->cmt_allow_modify === "N" && (!$logged_info || ($logged_info && !$logged_info->is_admin) )){
			$oCommentModel = getModel('comment');
			$oComment = $oCommentModel->getComment($obj->comment_srl);

			if($oComment && $oComment->isExists()){
				if(preg_match('/{@sticker:[0-9]+\|[0-9]+}/i', $oComment->content)){
					return new Object(-1,'msg_invalid_update_comment');
				}
			}
		}

		$member_srl = $logged_info ? $logged_info->member_srl : 0;
		$content = html_entity_decode($obj->content);
		preg_match('/{@sticker:([0-9]+)\|([0-9]+)}/i', $content, $match);
		if(!empty($match)){
			$checkFake = $this->_checkFakeSticker($match[1], $match[2], $member_srl);
			if(!$checkFake){
				return new Object(-1,'invalid sticker');
			}
			if($module_config->cmt_max_sticker_count != 0){
				$writeStickeCount = $oStickerModel->getCommentSticekrCountByDocumentSrl($obj->document_srl, $member_srl);
				if($writeStickeCount > intval($module_config->cmt_max_sticker_count)){
					return new Object(-1,'msg_exceed_wrote_sticker_count');
				}
			}

			$obj->content = "{@sticker:".$match[1]."|".$match[2]."}";
		} else {
			$isHiddenSticker = $this->_checkStickerInContent($content);
			if($isHiddenSticker){
				return new Object(-1,'invalid sticker');
			}
		}

	}


	function triggerBeforeDisplay(&$obj){
		if(!Context::get('document_srl')){
			return new Object();
		}

		$temp_output = preg_replace_callback('/<!--BeforeComment\(([0-9]+),([0-9]+)\)-->.*{@sticker:([0-9]+)\|([0-9]+)}.*<!--AfterComment\([0-9]+,[0-9]+\)-->/', array($this, 'stickerCommentCallback'), $obj);
		if($temp_output){
			$obj = $temp_output;
		}
	}

	function stickerCommentCallback($matches){
		$output = $this->_getStickerComment($matches[4]);
		$part = "";
		if(!empty($output->data)){
			$data = $output->data;
			$file_name = substr($data->file_name, 0, strrpos($data->file_name, "."));
//!!!S
			if(!$_COOKIE['txtmode']){
				$part = '<!--BeforeComment('.$matches[1].','.$matches[2].')--><div class="comment_'.$matches[1].'_'.$matches[2].' xe_content"><a href="/?mid=sticker&sticker_srl='.$data->sticker_srl.'" title="'.$data->title.'" style="display:block;background-image:url('.$data->url.');background-size:cover;background-position:50% 50%;width:140px !important;height:140px !important;border-radius:3px;" alt="'.$file_name.'"></a></div><!--AfterComment('.$matches[1].','.$matches[2].')-->';
			} else {
				$part = '<!--BeforeComment('.$matches[1].','.$matches[2].')--><div class="txtmode comment_'.$matches[1].'_'.$matches[2].' xe_content"><p style="margin:1em;">데이터 절약 모드 작동중<BR><a href="/?mid=sticker&sticker_srl='.$data->sticker_srl.'" target="_blank" style="color:#777;">('.$data->title.')</a></p></div><!--AfterComment('.$matches[1].','.$matches[2].')-->';
			}
//!!!E

		} else {
			$delete_msg = $this->_getStickerDeleteMsg();
			$part = '<!--BeforeComment('.$matches[1].','.$matches[2].')--><div class="comment_'.$matches[1].'_'.$matches[2].' xe_content">'.$delete_msg.'</div><!--AfterComment('.$matches[1].','.$matches[2].')-->';
		}
		return $part;
	}

	function procStickerCommentInsert(){

	}

	function procStickerBuy(){
		$sticker_srl = Context::get('sticker_srl');
		$logged_info = Context::get('logged_info');
		$member_srl = $logged_info->member_srl;

		if(!$logged_info || !$sticker_srl){
			return new Object(-1,'msg_invalid_access');
		}

		if(!$this->grant->buy){
			return new Object(-1,'msg_access_denied');
		}

		$oStickerModel = getModel('sticker');
		$sticker = $oStickerModel->getSticker($sticker_srl);
		if(!$sticker){
			return new Object(-1,'msg_invalid_sticker');
		}

		$start_date = (int)$sticker->start_date;
		$end_date = (int)$sticker->end_date;
		$date = (int)$this->module_config->start_time;
		$bought_count = $sticker->bought_count;
		$buy_limit = $sticker->buy_limit;
		$status = $sticker->status;
		if($buy_limit > 0 && $bought_count >= $buy_limit){
			return new Object(-1,'msg_sold_out_sticker');
		}
		if(($start_date && $date < $start_date) ||
			($end_date && $date > $end_date)
		){
			return new Object(-1,'msg_not_sale_date');
		}
		if($status != "PUBLIC"){
			return new Object(-1,'msg_not_sale_sticker');
		}

		$checkBuySticker = $oStickerModel->checkBuySticker($member_srl, $sticker_srl);
		if($checkBuySticker){
			return new Object(-1,'msg_already_bought_sticker');
		}

		$isDefaultSticker = $this->_checkDefaultSticker($sticker_srl);
		if($isDefaultSticker){
			return new Object(-1,'msg_default_sticker');
		}

		$buyCount = $this->_getStickerBuyCount($member_srl);
		if($this->module_config->buy_limit != 0 && $buyCount >= $this->module_config->buy_limit){
			return new Object(-1,'msg_exceed_bougth_count');
		}

		if(!$this->grant->free){
			$oPointModel = getModel('point');
			$point = intval($oPointModel->getPoint($member_srl));

			if($sticker->price > $point){
				return new Object(-1,'msg_not_enough_point');
			}

			$this->_setBuyMemberPoint($sticker->member_srl, $logged_info->member_srl, $sticker->price);
		}

		$date = $this->module_config->start_time;
		$sequence = getNextSequence();
		$expdate = $sticker->exptime ? date("YmdHis", mktime(date('H') + $sticker->exptime, date('i'), date('s'), date('m'), date('d'), date('Y'))) : null;

		$args = new stdClass();
		$args->idx = $sequence;
		$args->member_srl = $member_srl;
		$args->sticker_srl = $sticker_srl;
		$args->use_point = $sticker->price;
		$args->expdate = $expdate;
		$args->ipaddress = $_SERVER['REMOTE_ADDR'];
		$args->list_order = $sequence * -1;
		$args->regdate = $date;
		$output = executeQuery('sticker.insertBuyStickerInfo', $args);
		if (!$output->toBool())	{
			return new Object(-1,'msg_fail_buy_sticker');
		}

		$checkBuyHistoryToday = $this->_checkBuyStickerToday($member_srl, $sticker_srl);
		if($sticker->member_srl != $member_srl && $checkBuyHistoryToday === 0){
			$this->_increaseStickerBuyCount($sticker_srl);
		}

		$args->type = "buySticker";
		$this->insertStickerLog($args);

		$this->setMessage('success_buy_sticker');

	}

	function procStickerBuyOrderChange(){
		$sticker_srl = Context::get('sticker_srl');
		$logged_info = Context::get('logged_info');
		$move = Context::get('move');
		$member_srl = $logged_info->member_srl;
		$date = $this->module_config->start_time;

		if(!$logged_info || !$sticker_srl || !$move || !($move && in_array($move, array('up', 'down'))) ) {
			return new Object(-1,'msg_invalid_access');
		}

		$args = new stdClass();
		$args->member_srl = $member_srl;
		$args->sticker_srl = $sticker_srl;
		$args->date = $date;
		$output = executeQuery('sticker.getStickerBuy', $args);
		if( !$output->toBool() || empty($output->data) ){
			return new Object(-1,'msg_invalid_sticker');
		}
		if(is_array($output->data)){
			return new Object(-1,'msg_multiple_useable_same_sticker');
		}
		$list_order = $output->data->list_order;

		$args1 = new stdClass();
		$args1->member_srl = $member_srl;
		if($move == 'up'){
			$args1->order_up = $list_order;
			$args1->order_type = 'desc';
		} else {
			$args1->order_down = $list_order;
			$args1->order_type = 'asc';
		}

		$args1->list_count = 1;
		$args1->page = 1;
		$args1->date = $date;
		$output = executeQuery('sticker.getStickerOrder', $args1);
		if( !$output->toBool() || empty($output->data) ){
			return new Object(-1,'msg_invalid_access');
		}
		$getStickerObj = current($output->data);
		$swap_sticker_srl = $getStickerObj->sticker_srl;
		$swap_list_order = $getStickerObj->list_order;

		if(!isset($swap_sticker_srl) || !isset($swap_list_order)){
			return new Object(-1,'msg_exception_process');
		}

		$args2 = new stdClass();
		$args2->member_srl = $member_srl;
		$args2->sticker_srl = $sticker_srl;
		$args2->list_order = $list_order;
		$args2->swap_list_order = $swap_list_order;
		$output = executeQuery('sticker.updateStickerBuyOrder', $args2);

		$args3 = new stdClass();
		$args3->member_srl = $member_srl;
		$args3->sticker_srl = $swap_sticker_srl;
		$args3->list_order = $swap_list_order;
		$args3->swap_list_order = $list_order;
		$output = executeQuery('sticker.updateStickerBuyOrder', $args3);

		$this->setMessage('success_moved');
	}

	function procStickerFileDelete(){

		$sticker_srl = Context::get('sticker_srl');
		$no = Context::get('no');

		$logged_info = Context::get('logged_info');
		if(!$logged_info){
			return new Object(-1,'msg_invalid_access');
		}

		if(!$sticker_srl || !$no){
			return new Object(-1,'msg_unknown_image');
		} else if($no > $this->module_config->maxUploads || $no <= $this->module_config->minUploads){
			return new Object(-1,'msg_invalid_image');
		}

		$output = $this->_getStickerFile($sticker_srl, $no);
		if(empty($output)){
			return new Object(-1,'msg_unknown_image');
		}

		if(!($output->member_srl == $logged_info->member_srl || $this->grant->manager || $logged_info->is_admin === "Y")){
			return new Object(-1,'msg_invalid_access');
		}

		if(!($logged_info->is_admin == 'Y' || $this->grant->manager)){

			$args1 = new stdClass();
			$args1->sticker_srl = $sticker_srl;
			$output1 = executeQuery('sticker.getSticker', $args1);
			if (!$output1->toBool()){

				return $output1;

			}
			if(empty($output1->data)){
				return new Object(-1,'msg_invalid_image');
			}

			$sticker_status = $output1->data->status;
			if($sticker_status == "PUBLIC"){
				if($this->module_config->public_modify != "Y"){
					return new Object(-1, 'msg_modify_denied');
				}
			} else if($sticker_status == "CHECK"){
				if($this->module_config->check_modify != "Y"){
					return new Object(-1, 'msg_modify_denied');
				}
			} else if($sticker_status == "PAUSE"){
				if($this->module_config->pause_modify != "Y"){
					return new Object(-1, 'msg_modify_denied');
				}
			} else if($sticker_status == "STOP"){
				return new Object(-1, 'msg_modify_denied');
			} else {
				return new Object(-1, 'invalid_status_sticker');
			}

			if($this->module_config->limit_modify_buy && $output1->data->bought_count >= $this->module_config->limit_modify_buy){
				return new Object(-1, 'msg_modify_denied');
			}
		}

		$this->_deleteStickerFile($sticker_srl, $output->file_srl);

		$args = new stdClass();
		$args->sticker_srl = $sticker_srl;
		$args->uploaded_count = $this->_getStickerFileCount($sticker_srl);
		$output = executeQuery("sticker.updateStickerUploadedCount", $args);

		$this->setMessage('success_deleted');
	}


	function procStickerDelete(){
		$logged_info = Context::get('logged_info');
		if(!$logged_info){
			return new Object(-1,'msg_invalid_access');
		}

		$sticker_srl = Context::get('sticker_srl');
		$args = new stdClass();
		$args->sticker_srl = $sticker_srl;
		$output = executeQuery('sticker.getSticker', $args);
		if (!$output->toBool()){
			return $output;
		}

		if(empty($output->data)){
			return new Object(-1,'msg_invalid_sticker');
		}
		if(!($logged_info->member_srl == $output->data->member_srl || $this->grant->manager || $logged_info->is_admin === "Y")){
			return new Object(-1,'msg_invalid_access');
		}

		if(!($logged_info->is_admin == 'Y' || $this->grant->manager)){
			$sticker_status = $output->data->status;
			if($sticker_status == "PUBLIC"){
				if($this->module_config->public_delete != "Y"){
					return new Object(-1, 'msg_delete_denied');
				}
			} else if($sticker_status == "CHECK"){
				if($this->module_config->check_delete != "Y"){
					return new Object(-1, 'msg_delete_denied');
				}
			} else if($sticker_status == "PAUSE"){
				if($this->module_config->pause_delete != "Y"){
					return new Object(-1, 'msg_delete_denied');
				}
			} else if($sticker_status == "STOP"){
				return new Object(-1, 'msg_delete_denied');
			} else {
				return new Object(-1, 'invalid_status_sticker');
			}

			if($this->module_config->limit_delete_buy && $output->data->bought_count >= $this->module_config->limit_delete_buy){
				return new Object(-1, 'msg_delete_denied');
			}
		}


		$args->type = "deleteSticker";
		$this->insertStickerLog($args);

		$this->_deleteSticker($sticker_srl);
		$this->_deleteStickerFiles($sticker_srl);
		$this->_deleteStickerBuyByStickerSrl($sticker_srl);

		$this->setMessage('success_deleted');


	}


	function procStickerBuyDelete(){

		$logged_info = Context::get('logged_info');
		if(!$logged_info){
			return new Object(-1,'msg_invalid_access');
		}

		$sticker_srl = Context::get('sticker_srl');

		$oStickerModel = getModel('sticker');
		$is_bougth = $oStickerModel->checkBuySticker($logged_info->member_srl, $sticker_srl);
		if(!$is_bougth){
			return new Object(-1,'sticker was not exist');
		}
		$args = new stdClass();
		$args->member_srl = $logged_info->member_srl;
		$args->sticker_srl = $sticker_srl;
		$args->type = "deleteBuySticker";
		$this->insertStickerLog($args);
		$this->_deleteStickerBuyByMemberSrl($logged_info->member_srl, $sticker_srl);

		$this->setMessage('success_deleted');

	}


	function procStickerInsert(){
		if( !(extension_loaded('gd') && function_exists('gd_info')) ){
			return new Object(-1,'GD_library_is_not_installed');
		}

		$oModuleModel = getModel('module');
		$module_info = $oModuleModel->getModuleInfoByMid("sticker");

		$obj = Context::getRequestVars();

		$title = Context::get('title');
		$content = Context::get('content');

		//$file_info = $_FILES['sticker_file'];
		//debugPrint($file_info);
		//debugPrint($obj);

		$sticker_srl = Context::get('sticker_srl');
		$logged_info = Context::get('logged_info');

		if(!$logged_info){
			return new Object(-1,'msg_invalid_access');
		}

		if($sticker_srl){

			$args = new stdClass();
			$args->sticker_srl = $sticker_srl;
			$output = executeQueryArray('sticker.getSticker', $args);

			if (!$output->toBool()) {
				return $output;
			}

			if(!empty($output->data)){

				if(!($logged_info->member_srl == $output->data[0]->member_srl || $this->grant->manager || $logged_info->is_admin === "Y")){
					return new Object(-1,'msg_invalid_access');
				}

				if(!$this->grant->upload){
					return new Object(-1,'msg_access_denied');
				}

				if(!($logged_info->is_admin == 'Y' || $this->grant->manager)){
					$sticker_status = $output->data[0]->status;
					if($sticker_status == "PUBLIC"){
						if($this->module_config->public_modify != "Y"){
							return new Object(-1, 'msg_modify_denied');
						}
					} else if($sticker_status == "CHECK"){
						if($this->module_config->check_modify != "Y"){
							return new Object(-1, 'msg_modify_denied');
						}
					} else if($sticker_status == "PAUSE"){
						if($this->module_config->pause_modify != "Y"){
							return new Object(-1, 'msg_modify_denied');
						}
					} else if($sticker_status == "STOP"){
						return new Object(-1, 'msg_modify_denied');
					} else {
						return new Object(-1, 'invalid_status_sticker');
					}

					if($this->module_config->limit_modify_buy && $output->data[0]->bought_count >= $this->module_config->limit_modify_buy){
						return new Object(-1, 'msg_modify_denied');
					}
				}

				return $this->_updateSticker($obj, $output->data[0]);
			}
		}

		if(!$this->grant->upload){
			return new Object(-1,'msg_access_denied');
		}

		// 제목 유무 체크
		if(!$obj->title){
			return new Object(-1,'unknown title');
		}

		//빈 내용인지 체크
		if(!$obj->content){
			return new Object(-1,'unknown content');
		}

		//포인트 체크
		if($this->module_config->minPoint==$this->module_config->maxPoint){
			$obj->price = $this->module_config->minPoint;
		} else {

			if($obj->price > $this->module_config->maxPoint || $obj->price < $this->module_config->minPoint){
				return new Object(-1,'point error');
			}

		}

		//파일 체크
		//파일이 존재하지 않을 시
		if(!$obj->sticker_main_file || !$obj->sticker_file){
			return new Object(-1,'file is not exist');
		} else { //존재 할 때 파일 갯수와 용량, 확장자 체크

			$sticker_count = count($obj->sticker_file);
			$sticker_accu_size = 0;
			$sticker_mime_type = array('image/jpeg', 'image/gif', 'image/png');
			$file_size = $this->module_config->file_size << 10;
			$file_size_all = $this->module_config->file_size_all << 10;

			if($sticker_count < $this->module_config->minUploads){
				return new Object(-1,'file_is_not_enough');
			}

			if($sticker_count > $this->module_config->maxUploads){
				return new Object(-1,'file_count_is_over_limit');
			}

			if($obj->sticker_main_file['error'] != 0){
				return new Object(-1,'file transfer error');
			}

			if($obj->sticker_main_file['size'] > $file_size){
				return new Object(-1,'exceed file size');
			}

			if(!in_array($obj->sticker_main_file['type'], $sticker_mime_type)){
				return new Object(-1,'unknown file extension');
			}

			foreach($obj->sticker_file as $value){

				if($value['error'] != 0){
					return new Object(-1,'file transfer error');
				}

				$sticker_accu_size += $value['size'];
				if($value['size'] > $file_size){
					return new Object(-1,'exceed file size');
				}
				if($sticker_accu_size > $file_size_all){
					return new Object(-1,'exceed files size');
				}

				if(!in_array($value['type'], $sticker_mime_type)){
					return new Object(-1,'unknown file extension');
				}
			}

		}

		$date = $this->module_config->start_time;
		$sequence = getNextSequence();

		$module_srl = $module_info->module_srl;
		$sticker_srl = $sequence;
		$file_count = 0;

		//sticker_main_file
		$oFileController = getController('file');
		$output = $oFileController->insertFile($obj->sticker_main_file, $module_srl, $sticker_srl, 0, true);
		if (!$output->toBool()) {
			return $output;
		} else {
			$convert = $this->_insertImage($sticker_srl, $output->get('file_srl'), $output->get('uploaded_filename'), $output->get('source_filename'), $file_count);

			//정상적인 이미지 파일이 아닐 시
			if($convert === false){
				$this->_deleteStickerFiles($sticker_srl);
				return new Object(-1,'unknown file extension');
			} else if($convert === 2){
				$this->_deleteStickerFiles($sticker_srl);
				return new Object(-1,'image size is too small');
			} else if($convert === 3){
				$this->_deleteStickerFiles($sticker_srl);
				return new Object(-1,'image resolution is too big');
			}
		}

		//sticker_file
		foreach($obj->sticker_file as $value){
			$output = $oFileController->insertFile($value, $module_srl, $sticker_srl, 0, true);
			if (!$output->toBool()) {
				return $output;
			} else {
				$convert = $this->_insertImage($sticker_srl, $output->get('file_srl'), $output->get('uploaded_filename'), $output->get('source_filename'), $file_count);

				//정상적인 이미지 파일이 아닐 시
				if($convert === false){
					$this->_deleteStickerFiles($sticker_srl);
					return new Object(-1,'unknown file extension');
				} else if($convert === 2){
					$this->_deleteStickerFiles($sticker_srl);
					return new Object(-1,'image size is too small');
				} else if($convert === 3){
					$this->_deleteStickerFiles($sticker_srl);
					return new Object(-1,'image resolution is too big');
				}
			}

		}

		$end_date = null;
		if($this->module_config->sale_end_date){
			$end_date = date("YmdHis", mktime(date('H'), date('i'), date('s'), date('m'), date('d')+$this->module_config->sale_end_date, date('Y')));
		}
		$exptime = $this->module_config->use_date && $this->module_config->use_date > 0 ? $this->module_config->use_date : null;
		$buy_limit = $this->module_config->sale_limit && $this->module_config->sale_limit > 0 ? $this->module_config->sale_limit : null;

		//insert sticker document
		$args = new stdClass();
		$args->sticker_srl = $sticker_srl;
		$args->member_srl = $logged_info->member_srl;
		$args->nick_name = $logged_info->nick_name;
		$args->category_srl = 0;
		$args->title = cut_str(trim(htmlspecialchars(strip_tags($obj->title), ENT_QUOTES, 'UTF-8', false)), 100);
		$args->tag = cut_str(htmlspecialchars(strip_tags($obj->tags), ENT_QUOTES, 'UTF-8', false), 150);
		$args->content = removeHackTag($obj->content);
		$args->uploaded_count = $file_count;
		$args->end_date = $end_date;
		$args->price = $obj->price;
		$args->buy_limit = $buy_limit;
		$args->exptime = $exptime;
		$args->ipaddress = $_SERVER['REMOTE_ADDR'];
		$args->last_update = $date;
		$args->last_updater = $logged_info->nick_name;
		$args->list_order = $sequence * -1;
		$args->regdate = $date;
		$args->status = $this->module_config->before_test == "N" ? "PUBLIC" : "CHECK";

		$output = executeQuery('sticker.insertSticker', $args);
		if (!$output->toBool())	{
			return $output;
		}
		$this->_updateFileStatus($sticker_srl); //sicker_srl;

		if($this->module_config->upload_charge > 0 && !$this->grant->manager){
			$oPointController = getController('point');
			$oPointController->setPoint($logged_info->member_srl, $this->module_config->upload_charge, 'minus');
		}

		$args->type = "insertSticker";
		unset($args->content);
		$this->insertStickerLog($args);

		$this->setMessage('success_saved');

		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'mid', 'sticker', 'sticker_srl', $sticker_srl);

		$this->setRedirectUrl($returnUrl);
	}
	

	function _updateSticker($obj, $sticker){

		// 제목 유무 체크
		if(!$obj->title){
			return new Object(-1,'unknown title');
		}

		//빈 내용인지 체크
		if(!$obj->content){
			return new Object(-1,'unknown content');
		}

		//포인트 체크
		if($this->module_config->minPoint==$this->module_config->maxPoint){
			$obj->price = $this->module_config->minPoint;
		} else {
			if($obj->price > $this->module_config->maxPoint || $obj->price < $this->module_config->minPoint){
				return new Object(-1,'point error');
			}
		}

		$oModuleModel = getModel('module');
		$module_info = $oModuleModel->getModuleInfoByMid("sticker");

		$sticker_srl = $sticker->sticker_srl;

		$args = new stdClass();
		$args->sticker_srl = $sticker_srl;
		$args->order_type = 'asc';
		$output = executeQueryArray('sticker.getFiles', $args);

		$file_size_accm = 0;
		foreach($output->data as $value){
			$file_size_accm += $value->file_size;
		}

		$sticker_file = $output->data;
		$sticker_mime_type = array('image/jpeg', 'image/gif', 'image/png');
		$file_size = $this->module_config->file_size << 10;
		$file_size_all = $this->module_config->file_size_all << 10;

		$module_srl = $module_info->module_srl;
		$date = $this->module_config->start_time;
		$sequence = getNextSequence();

		$oFileController = getController('file');

		if($obj->sticker_main_file){

			$main_image_info = null;
			foreach($sticker_file as $value){
				if($value->no == '0'){
					$main_image_info = $value;
					break;
				}
			}

			if($main_image_info == null){
				$args = new stdClass();
				$args->sticker_srl = $sticker->sticker_srl;
				$args->no = 0;
				$output = executeQuery('sticker.getStickerByNo', $args);
				if (!$output->toBool()) {
					return $output;
				}
				
				$main_image_info = $output->data;
				$main_image_info->file_size = 0;
			}

			if($obj->sticker_main_file['error'] != 0){
				return new Object(-1,'file transfer error');
			}

			if($obj->sticker_main_file['size'] > $file_size){
				return new Object(-1,'exceed file size');
			}

			if($file_size_accm > $file_size_all - $main_image_info->file_size){
				return new Object(-1,'exceed files size');
			}

			$file_size_accm = $file_size_accm - $main_image_info->file_size + $obj->sticker_main_file['size'];

			if(!in_array($obj->sticker_main_file['type'], $sticker_mime_type)){
				return new Object(-1,'unknown file extension');
			}

			//update sticker

			$output = $oFileController->insertFile($obj->sticker_main_file, $module_srl, $sticker_srl, 0, true);
			if (!$output->toBool()) {
				return $output;
			} else {
				$convert = $this->_updateImage($main_image_info, $output->get('file_srl'), $output->get('uploaded_filename'), $output->get('source_filename'));
				if($convert === true){
					$this->_updateFileStatus($sticker_srl);
				} else if($convert == 2){
					$this->_deleteFile($output->get('file_srl'));
					return new Object(-1,'image size is too small');
				} else if($convert === false){
					$this->_deleteFile($output->get('file_srl'));
					return new Object(-1,'unknown file extension');
				} else if($convert === 3){
					$this->_deleteFile($output->get('file_srl'));
					return new Object(-1,'image resolution is too big');
				}
			}

		}

		for($i=1; $i<=$this->module_config->maxUploads; $i++){
			$stk = $obj->{"sticker_file_".$i};
			if($stk){

				if($stk['error'] != 0){
					return new Object(-1,'file transfer error');
				}

				if(!in_array($stk['type'], $sticker_mime_type)){
					return new Object(-1,'unknown file extension');
				}

				$image_info = null;
				// 이미지가 이미 존재하는지 체크
				foreach($sticker_file as $value){
					if($value->no == $i){
						$image_info = $value;
						break;
					}
				}

				//이미 존재한다면 업데이트
				if($image_info){
					if($stk['size'] > $file_size){
						return new Object(-1,'exceed file size');
					}

					$file_size_accm = $file_size_accm - $image_info->file_size + $stk['size'];
					if($file_size_accm > $file_size_all){
						return new Object(-1,'exceed files size');
					}

					$output = $oFileController->insertFile($stk, $module_srl, $sticker_srl, 0, true);

					if (!$output->toBool()) {
						return $output;
					} else {
						$convert = $this->_updateImage($image_info, $output->get('file_srl'), $output->get('uploaded_filename'), $output->get('source_filename'));
						if($convert === true){
							$this->_updateFileStatus($sticker_srl);
						} else if($convert == 2){
							$this->_deleteFile($output->get('file_srl'));
							return new Object(-1,'image size is too small');
						} else if($convert === false){
							$this->_deleteFile($output->get('file_srl'));
							return new Object(-1,'unknown file extension');
						} else if($convert === 3){
							$this->_deleteFile($output->get('file_srl'));
							return new Object(-1,'image resolution is too big');
						}
					}


				//존재하지 않을때
				} else {
					//file모듈에만 없을 수 있으므로 sticker_files테이블 검색
					
					$args = new stdClass();
					$args->sticker_srl = $sticker->sticker_srl;
					$args->no = $i;
					$output = executeQuery('sticker.getStickerByNo', $args);
					if (!$output->toBool()) {
						return $output;
					}
					$image_info = $output->data;

					if($stk['size'] > $file_size){
						return new Object(-1,'exceed file size');
					}

					$file_size_accm += $stk['size'];
					if($file_size_accm > $file_size_all){
						return new Object(-1,'exceed files size');
					}

					//데이터가 존재한다!
					if(!empty($image_info)){
						$image_info->file_size = 0;

						$output = $oFileController->insertFile($stk, $module_srl, $sticker_srl, 0, true);
						if (!$output->toBool()) {
							return $output;
						} else {
							$convert = $this->_updateImage($image_info, $output->get('file_srl'), $output->get('uploaded_filename'), $output->get('source_filename'));
							if($convert === true){
								$this->_updateFileStatus($sticker_srl);
							} else if($convert == 2){
								$this->_deleteFile($output->get('file_srl'));
								return new Object(-1,'image size is too small');
							} else if($convert === false){
								$this->_deleteFile($output->get('file_srl'));
								return new Object(-1,'unknown file extension');
							} else if($convert == 3){
								$this->_deleteFile($output->get('file_srl'));
								return new Object(-1,'image resolution is too big');
							}
						}

					} else { // 없는 데이터. 업데이트가 아닌 새로 업로드

						$output = $oFileController->insertFile($stk, $module_srl, $sticker_srl, 0, true);
						if (!$output->toBool()) {
							return $output;
						} else {
							$convert = $this->_insertImage($sticker_srl, $output->get('file_srl'), $output->get('uploaded_filename'), $output->get('source_filename'), $i, true);

							//정상적인 이미지 파일이 아닐 시
							if($convert === true){
								$this->_updateFileStatus($sticker_srl);
							} else if($convert === false){
								$this->_deleteFile($output->get('file_srl'));
								return new Object(-1,'unknown file extension');
							} else if($convert === 2){
								$this->_deleteFile($output->get('file_srl'));
								return new Object(-1,'image size is too small');
							} else if($convert === 3){
								$this->_deleteFile($output->get('file_srl'));
								return new Object(-1,'image resolution is too big');
							}
						}

					}


				} // if($image_info)

			} // END FOR
		}

		$file_count = $this->_getStickerFileCount($sticker_srl);

		$tag = $this->_checkCorrectTag(cut_str(htmlspecialchars(strip_tags($obj->tags), ENT_QUOTES, 'UTF-8', false), 150));

		//insert sticker document
		$args = new stdClass();
		$args->sticker_srl = $sticker_srl;
		$args->category_srl = 0;
		$args->title = cut_str(htmlspecialchars(strip_tags($obj->title), ENT_QUOTES, 'UTF-8', false), 100);
		$args->tag = $tag;
		$args->content = removeHackTag($obj->content);
		$args->uploaded_count = $file_count;
		$args->price = $obj->price;
		$args->last_update = $date;
		$args->last_updater = $logged_info->nick_name;
		$args->list_order = $sequence * -1;
		$args->status = $sticker->status;

		$output = executeQuery('sticker.updateSticker', $args);
		if (!$output->toBool())	{
			return $output;
		}

		$args->type = "updateSticker";
		unset($args->content);
		$this->insertStickerLog($args);

		$this->setMessage('success_saved');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'mid', 'sticker', 'sticker_srl', $sticker_srl);
		$this->setRedirectUrl($returnUrl);

	}

	//convert and insert
	function _insertImage($sticker_srl, $file_srl, $uploaded_filename, $source_filename, &$file_count, $is_update = false){

		if(!$uploaded_filename){
			return false;
		}

		if(!preg_match("/\.(jpg|jpeg|gif|png)$/i", $uploaded_filename , $file_ext)){
			return false;
		}

		$min_width = $this->module_config->maxPx;
		$min_height = $this->module_config->maxPx;

		//check image size
		$source_fileinfo = getimagesize($uploaded_filename);
		if(!$source_fileinfo){
			return false;
		} else {
			$width = $source_fileinfo[0];
			$height = $source_fileinfo[1];

			//설상 gif파일이라도 가로세로 사이즈가 설정값보다 작으면 업로드 중단
			if($width < $this->module_config->image_min_width || $height < $this->module_config->image_min_height){
				return 2;
			}
			if($width > 4096 || $height > 2160){ // If Image resolution is over the 4k, return upload fail.
				return 3;
			}
			if($file_ext[0] !== '.gif' && $source_fileinfo['mime'] === 'image/gif'){
				$file_ext[0] = '.gif';
				$source_filename_withoutExt = strrpos($source_filename, '.');
				$source_filename = substr($source_filename, 0, $source_filename_withoutExt).'.gif';
			}

			$getImageSizeRate = $width / $height;
			$getImageSizeRate = ($getImageSizeRate > 1.8 || $getImageSizeRate < 0.65) || ($width >= $min_width || $height >= $min_height) ? 'crop' : 'ratio';
		}

		if($this->module_config->resizing == "N"){
			$this->_insertSickerFile($sticker_srl, $file_srl, $source_filename, $uploaded_filename, $is_update ? $file_count : $file_count++);
			return true;
		}

		if($source_fileinfo['mime'] === 'image/gif'){
			$is_larger = $width > $height ? $width : $height;
			$is_smaller = $is_larger === $width ? $height : $width;

			$GIFRatio = $is_smaller / $is_larger;

			if($width <= $this->module_config->maxPx && $height <= $this->module_config->maxPx && $GIFRatio > 0.4){
				$this->_insertSickerFile($sticker_srl, $file_srl, $source_filename, $uploaded_filename, $is_update ? $file_count : $file_count++);
				return true;
			}

			require_once 'sticker.lib.php';

			$filename_path = strrpos($uploaded_filename, '/');
			$random = new Password();
			$output_srl = substr($uploaded_filename, 0, $filename_path+1).$random->createSecureSalt(32, 'hex').'.gif';

			$resize = resizeGIF($uploaded_filename, $output_srl, $min_width, $min_height);
			if($resize){
				$origin_image_size = filesize($uploaded_filename);
				$compressed_image_size = filesize($output_srl);
				if($origin_image_size > $compressed_image_size || $this->module_config->gifResizingIf == "N" || $GIFRatio < 0.4){
					$args = new stdClass();
					$args->file_srl = $file_srl;
					$args->uploaded_filename = $output_srl;
					$args->source_filename = $source_filename;
					$args->file_size = filesize($output_srl);
					$output = executeQuery('sticker.updateFileInfo', $args);
					if($uploaded_filename !== $output_srl && file_exists($uploaded_filename)){
						FileHandler::removeFile($uploaded_filename);
					}

					$this->_insertSickerFile($sticker_srl, $file_srl, $source_filename, $output_srl, $is_update ? $file_count : $file_count++);
					return true;
				} else {

					if($uploaded_filename !== $output_srl && file_exists($output_srl)){
						FileHandler::removeFile($output_srl);
					}

				}
			} else {
				//return false;
			}

			$this->_insertSickerFile($sticker_srl, $file_srl, $source_filename, $uploaded_filename, $is_update ? $file_count : $file_count++);
			return true;
		}

		//set output_filename
		$filename_path = strrpos($uploaded_filename, '/');
		$random = new Password();
		$output_srl = substr($uploaded_filename, 0, $filename_path+1).$random->createSecureSalt(32, 'hex').'.jpg';

		//set source_filename
		$source_filename_withoutExt = strrpos($source_filename, '.');
		$source_filename = substr($source_filename, 0, $source_filename_withoutExt).'.jpg';

		if(FileHandler::createImageFile($uploaded_filename,$output_srl,$min_width,$min_height,'jpg',$getImageSizeRate)) // or crop
		{
			$args = new stdClass();
			$args->file_srl = $file_srl;
			$args->uploaded_filename = $output_srl;
			$args->source_filename = $source_filename;
			$args->file_size = filesize($output_srl);
			$output = executeQuery('sticker.updateFileInfo', $args);
			if($uploaded_filename !== $output_srl && file_exists($uploaded_filename)){
				FileHandler::removeFile($uploaded_filename);
			}

			$this->_insertSickerFile($sticker_srl, $file_srl, $source_filename, $output_srl, $is_update ? $file_count : $file_count++);

		} else {
			return false;
		}

		return true;

	}

	function _updateImage($origin_obj, $file_srl, $uploaded_filename, $source_filename){ // origin file, change, changed, changed

		if(!$file_srl || !$uploaded_filename || !$source_filename){
			return false;
		}

		if(!preg_match("/\.(jpg|jpeg|gif|png)$/i", $uploaded_filename , $file_ext)){
			return false;
		}

		$min_width = $this->module_config->maxPx;
		$min_height = $this->module_config->maxPx;

		//check image size
		$source_fileinfo = getimagesize($uploaded_filename);
		if(!$source_fileinfo){
			return false;
		} else {
			$width = $source_fileinfo[0];
			$height = $source_fileinfo[1];

			if($width < $this->module_config->image_min_width || $height < $this->module_config->image_min_height){
				return 2;
			}
			if($width > 4096 || $height > 2160){
				return 3;
			}
			if($file_ext[0] !== '.gif' && $source_fileinfo['mime'] === 'image/gif'){
				$file_ext[0] = '.gif';
				$source_filename_withoutExt = strrpos($source_filename, '.');
				$source_filename = substr($source_filename, 0, $source_filename_withoutExt).'.gif';
			}

			$getImageSizeRate = $width / $height;
			$getImageSizeRate = ($getImageSizeRate > 1.8 || $getImageSizeRate < 0.65) || ($width >= $min_width || $height >= $min_height) ? 'crop' : 'ratio';
		}

		if($this->module_config->resizing == "N"){
			//sticker_files테이블 갱신전 파일 삭제
			$this->_deleteFile($origin_obj->file_srl);

			// 재등록
			$this->_updateStickerFileInfo($origin_obj->sticker_file_srl, $file_srl, $source_filename, $uploaded_filename, $origin_obj->no);
			return true;
		}

		if($source_fileinfo['mime'] === 'image/gif'){
			$is_larger = $width > $height ? $width : $height;
			$is_smaller = $is_larger === $width ? $height : $width;

			$GIFRatio = $is_smaller / $is_larger;

			if($width <= $this->module_config->maxPx && $height <= $this->module_config->maxPx && $GIFRatio > 0.4){
				$this->_deleteFile($origin_obj->file_srl);
				$this->_updateStickerFileInfo($origin_obj->sticker_file_srl, $file_srl, $source_filename, $uploaded_filename, $origin_obj->no);
				return true;
			}

			require_once 'sticker.lib.php';

			$filename_path = strrpos($uploaded_filename, '/');
			$random = new Password();
			$output_name = substr($uploaded_filename, 0, $filename_path+1).$random->createSecureSalt(32, 'hex').'.gif';

			$resize = resizeGIF($uploaded_filename, $output_name, $min_width, $min_height);
			if($resize){
				$origin_image_size = filesize($uploaded_filename);
				$compressed_image_size = filesize($output_name);
				if($origin_image_size > $compressed_image_size || $this->module_config->gifResizingIf == "N" || $GIFRatio < 0.4){

					$args = new stdClass();
					$args->file_srl = $file_srl;
					$args->uploaded_filename = $output_name;
					$args->source_filename = $source_filename;
					$args->file_size = filesize($output_name);
					$output = executeQuery('sticker.updateFileInfo', $args);
					if($uploaded_filename !== $output_name && file_exists($uploaded_filename)){
						FileHandler::removeFile($uploaded_filename);
					}

					$this->_deleteFile($origin_obj->file_srl);
					$this->_updateStickerFileInfo($origin_obj->sticker_file_srl, $file_srl, $source_filename, $output_name, $origin_obj->no);

					return true;
				} else {
					if($uploaded_filename !== $output_name && file_exists($output_name)){
						FileHandler::removeFile($output_name);
					}
				}
			} else {
				//return false;
			}

			$this->_deleteFile($origin_obj->file_srl);
			$this->_updateStickerFileInfo($origin_obj->sticker_file_srl, $file_srl, $source_filename, $uploaded_filename, $origin_obj->no);
			return true;

		}

		//set output_filename
		$filename_path = strrpos($uploaded_filename, '/');
		$random = new Password();
		$output_srl = substr($uploaded_filename, 0, $filename_path+1).$random->createSecureSalt(32, 'hex').'.jpg';

		//set source_filename
		$source_filename_withoutExt = strrpos($source_filename, '.');
		$source_filename = substr($source_filename, 0, $source_filename_withoutExt).'.jpg';

		if(FileHandler::createImageFile($uploaded_filename,$output_srl,$min_width,$min_height,'jpg',$getImageSizeRate)) // or crop
		{
			$args = new stdClass();
			$args->file_srl = $file_srl;
			$args->uploaded_filename = $output_srl;
			$args->source_filename = $source_filename;
			$args->file_size = filesize($output_srl);
			$output = executeQuery('sticker.updateFileInfo', $args);
			if($uploaded_filename !== $output_srl && file_exists($uploaded_filename)){
				FileHandler::removeFile($uploaded_filename);
			}
			$this->_deleteFile($origin_obj->file_srl);

			$this->_updateStickerFileInfo($origin_obj->sticker_file_srl, $file_srl, $source_filename, $output_srl, $origin_obj->no);

		} else {
			return false;
		}

		return true;

	}

	function _updateStickerFileInfo($sticker_file_srl, $file_srl, $file_name, $url, $no){
		$logged_info = Context::get('logged_info');
		if(!$logged_info){
			return false;
		}

		$args = new stdClass();
		$args->sticker_file_srl = $sticker_file_srl;
		$args->member_srl = $logged_info->member_srl;
		$args->file_srl = $file_srl;
		$args->file_name = cut_str(htmlspecialchars($file_name, ENT_QUOTES, 'UTF-8', false), 60);
		$args->url = $url;
		$args->regdate = $this->module_config->start_time;
		$output = executeQuery('sticker.updateStickerFile', $args);

		return $output;

	}

	function updateReadedCount(&$oSticker){
		if(isCrawler()) return false;
		$sticker_srl = $oSticker->sticker_srl;
		$member_srl = $oSticker->member_srl;
		$logged_info = Context::get('logged_info');
		if($_SESSION['readed_sticker'][$sticker_srl]){
			return false;
		}

		if($oSticker->ipaddress == $_SERVER['REMOTE_ADDR']){
			$_SESSION['readed_sticker'][$sticker_srl] = true;
			return false;
		}

		if($logged_info && $logged_info->member_srl == $member_srl){
			$_SESSION['readed_sticker'][$sticker_srl] = true;
			return false;
		}

		$args = new stdClass;
		$args->sticker_srl = $sticker_srl;
		$output = executeQuery('sticker.updateReadedCount', $args);

		$_SESSION['readed_sticker'][$sticker_srl] = true;
		$oSticker->readed_count += 1;
		
		return true;

	}

	function _insertSickerFile($sticker_srl, $file_srl, $file_name, $url, $no){
		$logged_info = Context::get('logged_info');
		if(!$logged_info){
			return false;
		}

		$args = new stdClass();
		$args->sticker_srl = $sticker_srl;
		$args->sticker_file_srl = $file_srl;
		$args->member_srl = $logged_info->member_srl;
		$args->file_srl = $file_srl;
		$args->file_name = cut_str(htmlspecialchars($file_name, ENT_QUOTES, 'UTF-8', false), 60);
		$args->no = $no; //00 main, 01~ sub
		$args->url = $url;
		$args->regdate = $this->module_config->start_time;
		$output = executeQuery('sticker.insertStickerFile', $args);

		return $output;
	}

	function insertStickerLog($obj, $sequence = false){
		$logged_info = Context::get('logged_info');
		$idx = $sequence ? $sequence : getNextSequence();
		$sticker_srl = $obj->sticker_srl ? $obj->sticker_srl : 0;
		$sticker_file_srl = $obj->sticker_file_srl ? $obj->sticker_file_srl : null;
		$member_srl = $obj->member_srl ? $obj->member_srl : $logged_info ? $logged_info->member_srl : 0;
		$type = $obj->type ? $obj->type : null;
		$comment_srl = $obj->comment_srl ? $obj->comment_srl : null;
		$document_srl = $obj->document_srl ? $obj->document_srl : null;
		$content = $obj->content ? $obj->content : null;
		$point = $obj->point ? $obj->point : $obj->use_point ? $obj->use_point : $obj->price ? $obj->price : null;
		$ipaddress = $obj->ipaddress ? $obj->ipaddress : $_SERVER['REMOTE_ADDR'];
		$regdate = $obj->regdate ? $obj->regdate : date("YmdHis");

		if(!$type){
			return false;
		}

		$args = new stdClass();
		$args->idx = $idx;
		$args->sticker_srl = $sticker_srl;
		$args->sticker_file_srl = $sticker_file_srl;
		$args->member_srl = $member_srl;
		$args->type = $type;
		$args->comment_srl = $comment_srl;
		$args->document_srl = $document_srl;
		$args->content = $content;	// Hack 태그가 심겨져있을 수 있으니 스킨단에서 철저히 확인 후 사용 할 것.
		$args->point = $point;
		$args->ipaddress = $ipaddress;
		$args->regdate = $regdate;
		$output = executeQuery('sticker.insertStickerLog', $args);
		if(!$output->toBool()){
			return $output;
		}

		return true;
	}

	function _setBuyMemberPoint($sticker_member_srl, $member_srl, $price=0){
		$oPointController = getController('point');
		$return_percent = $this->module_config->returnPoint;
		//판매자 포인트 설정
		if($return_percent > 0 && $return_percent <= 100){
			$oPointController->setPoint($sticker_member_srl, $price * $return_percent / 100 , 'add');
		}

		//구매자 포인트 설정
		$oPointController->setPoint($member_srl, $price , 'minus');

		return true;
	}

	function _increaseStickerBuyCount($sticker_srl){
		$args = new stdClass();
		$args->sticker_srl = $sticker_srl;
		$output = executeQuery('sticker.updateBoughtCount', $args);
		return !$output->toBool() ? FALSE : TRUE;
	}

	function _increaseStickerUsedCount($sticker_srl, $sticker_file_srl, $member_srl){
		$args = new stdClass();
		$args->sticker_srl = $sticker_srl;
		$output = executeQuery('sticker.updateStickerUsedCount', $args);

		$args1 = new stdClass();
		$args1->sticker_file_srl = $sticker_file_srl;
		$output = executeQuery('sticker.updateStickerFileUsedCount', $args1);

		if($member_srl != 0){
			$args->member_srl = $member_srl;
			$args->date = date("YmdHis");
			$output = executeQuery('sticker.updateStickerBuyUsedCount', $args);
		}
	}


	function _checkFakeSticker($sticker_srl, $sticker_file_srl, $member_srl){
		$oStickerModel = getModel('sticker');

		$isDefaultSticker = $this->_checkDefaultSticker($sticker_srl);
		if(!$isDefaultSticker){
			$checkBuySticker = $oStickerModel->checkBuySticker($member_srl, $sticker_srl);
			if(!$checkBuySticker){
				return false;
			}
		}

		$args = new stdClass();
		$args->member_srl = $member_srl;
		$args->sticker_file_srl = $sticker_file_srl;
		$output = executeQuery('sticker.getStickerFileByStickerFileSrl', $args);
		return (!$output->toBool() || empty($output->data)) ? FALSE : TRUE;
	}

	function _checkUsableSticker($sticker_srl){
		$args = new stdClass();
		$args->sticker_srl = $sticker_srl;
		$output = executeQuery('sticker.getSticker', $args);
		if (!$output->toBool() || empty($output->data))	{
			return false;
		}
		$sticker_status = $output->data->status;

		return $sticker_status != "STOP" ? TRUE : FALSE;
	}

	function _checkDefaultSticker($sticker_srl){
		$oStickerModel = getModel('sticker');

		$module_config = $oStickerModel->getConfig();
		$default_sticker = explode(',', $module_config->default_sticker);

		foreach($default_sticker as &$value){
			$value = trim($value);
		}

		if(in_array($sticker_srl, $default_sticker)){
			return true;
		}

		return false;
	}

	function _checkCorrectTag($tag){
		$tag = preg_replace(array('/^,?\s+?/', '/(,?\s*?,+|,+)/', '/\s+/'), array('', ',', ' '), $tag);
		$tag = explode(',', $tag);
		$iTagCount = count($tag);
		$new_tag = array();
		for($i = 0; $i<$iTagCount; $i++){
			$is_null = true;
			$new_tag_count = count($new_tag);
		    	for($j = 0; $j<$new_tag_count; $j++){
					$trim_tag = trim($tag[$i]);
					if($tag[$i] && (!$trim_tag || $trim_tag == $new_tag[$j])){
						$is_null = false;
						break;
					}
				}
			if($is_null){
				array_push($new_tag, trim($tag[$i]));
			}
		}

		$sTag = "";
		$new_tag_count = count($new_tag);
		foreach($new_tag as $key=>$value){
			if(!!trim($value)){
				if($sTag){
					$sTag .= ", ";
				}
				$sTag .= $value;
			}
		}

		return $sTag;
	}

	function _checkBuyStickerToday($member_srl = 0, $sticker_srl = 0){
		$args = new stdClass();
		$args->member_srl = $member_srl;
		$args->sticker_srl = $sticker_srl;
		$args->type = "buySticker,buyStickerAdmin";
		$args->date = date("Ymd");
		$output = executeQuery('sticker.getStickerBuyCheckByDate', $args);
		if(!$output->toBool()){
			return false;
		}

		return $output->data->count;
	}

	function _checkStickerInContent($content){
		$content = trim(strip_tags($content));
		if(preg_match('/{@sticker:[0-9]+\|[0-9]+}/i', $content)){
			return true;
		}
		return false;
	}

	function _getStickerFile($sticker_srl, $no){
		$args = new stdClass();
		$args->sticker_srl = $sticker_srl;
		$args->no = $no;
		$output = executeQuery('sticker.getStickerFileByNo', $args);
		return $output->data;
	}

	function _getStickerFileCount($sticker_srl){
		$args = new stdClass();
		$args->sticker_srl = $sticker_srl;
		$output = executeQuery('sticker.getStickerFileCheck', $args);
		return $output->data->count;
	}

	function _getStickerBuyCount($member_srl){
		$args = new stdClass();
		$args->member_srl = $member_srl;
		$output = executeQuery('sticker.getStickerBuyCount', $args);
		return $output->data->count;
	}

	function _getStickerComment($sticker_file_srl){
		$args = new stdClass();
		$args->sticker_file_srl = $sticker_file_srl;
		$output = executeQuery('sticker.getStickerByStickerFileSrl', $args);

		return $output;
	}

	function _getStickerDeleteMsg(){
		$oStickerModel = getModel('sticker');
		$module_config = $oStickerModel->getConfig();
		return $module_config->deleted_sticker;
	}

	function _deleteSticker($sticker_srl){
		$args = new stdClass();
		$args->sticker_srl = $sticker_srl;
		$output = executeQuery('sticker.deleteSticker', $args);
	}

	function _deleteStickerFile($sticker_srl, $file_srl){
		$args = new stdClass();
		$args->sticker_srl = $sticker_srl;
		$args->file_srl = $file_srl;
		$output = executeQuery('sticker.deleteStickerFileByFileSrl', $args);

		$this->_deleteFile($file_srl);
	}

	function _deleteStickerFiles($sticker_srl){ // file_parent_srl
		$oFileController = getController('file');
		$output = $oFileController->deleteFiles($sticker_srl);

		$this->_deleteStickerFilesDB($sticker_srl);
	}

	function _deleteFile($file_srl){
		$oFileController = getController('file');
		$output = $oFileController->deleteFile($file_srl);
	}

	function _deleteTemporaryFile($sticker_main, $sticker_file){

	}

	function _updateFileStatus($sticker_srl){
		$args = new stdClass();
		$args->upload_target_srl = $sticker_srl;
		$args->isvalid = 'Y';
		$output = executeQuery('sticker.updateStickerFileValid', $args);
	}

	function _deleteStickerFilesDB($sticker_srl){
		$args = new stdClass();
		$args->sticker_srl = $sticker_srl;
		executeQuery('sticker.deleteStickerFilesByStickerSrl', $args);
	}

	function _deleteStickerBuyByStickerSrl($sticker_srl){
		$args = new stdClass();
		$args->sticker_srl = $sticker_srl;
		executeQuery('sticker.deleteStickerBuyByStickerSrl', $args);
	}

	function _deleteStickerBuyByMemberSrl($member_srl, $sticker_srl){
		$args = new stdClass();
		$args->member_srl = $member_srl;
		$args->sticker_srl = $sticker_srl;
		$args->date = date("YmdHis");
		$output = executeQuery('sticker.deleteStickerBuyByMemberSrl', $args);

		return $output;
	}

}

/* End of file sticker.controller.php */
/* Location: ./modules/sticker/sticker.controller.php */
