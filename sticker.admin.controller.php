<?php
/*! Copyright (C) 2016 BGM STORAGE. All rights reserved. */
/**
 * @class  stickerAdminController
 * @author Huhani (mmia268@dnip.co.kr)
 * @brief  Sticker module admin controller class.
 */

class stickerAdminController extends sticker
{
	function init()
	{
	}

	function procStickerAdminConfig()
	{

		$oModuleController = getController('module');

		$config = Context::getRequestVars();
		getDestroyXeVars($config);
		unset($config->body);
		unset($config->_filter);
		unset($config->error_return_url);
		unset($config->act);
		unset($config->module);
		unset($config->ruleset);

		$config->default_sticker = $config->default_sticker ? $config->default_sticker : "";
		if($config->browser_title){
			$oModuleModel = getModel('module');
			$sticker_info = $oModuleModel->getModuleInfoByMid('sticker');
			$sticker_info->browser_title = $config->browser_title;
			unset($config->browser_title);
			$oModuleController->updateModule($sticker_info);
		}

		$output = $oModuleController->updateModuleConfig('sticker', $config);
		if (!$output->toBool())
		{
			return $output;
		}

		$this->setMessage('success_saved');

		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispStickerAdminSkin');
		$this->setRedirectUrl($returnUrl);
	}

	function procStickerAdminDesign(){

		if(Context::getRequestMethod() == 'GET') return new Object(-1, 'msg_invalid_request');

		$oModuleController = getController('module');
		$oModuleController->updateModuleConfig('sticker', $config);

		$oModuleModel = getModel('module');
		$sticker_info = $oModuleModel->getModuleInfoByMid('sticker');
		if($sticker_info){
			$sticker_info->skin = Context::get('skin');
			$sticker_info->mskin = Context::get('mskin');
			$sticker_info->layout_srl = Context::get('layout_srl');
			$sticker_info->mlayout_srl = Context::get('mlayout_srl');

			$oModuleController->updateModule($sticker_info);
		}

		$this->setMessage('success_saved');

		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispStickerAdminDesign');
		$this->setRedirectUrl($returnUrl);

	}

	function procStickerAdminUpdate(){

		$sticker_srl = Context::get('sticker_srl');
		$config = Context::getRequestVars();
		getDestroyXeVars($config);
		unset($config->body);
		unset($config->_filter);
		unset($config->error_return_url);
		unset($config->act);
		unset($config->module);
		unset($config->ruleset);

		$oStickerModel = getModel('sticker');
		$oSticker = $oStickerModel->getSticker($sticker_srl);
		if(!$oSticker){
			return new Object(-1,'msg_invalid_sticker');
		}

		$config->start_hour = $config->start_hour ? intval($config->start_hour) : 0;
		$config->start_minute = $config->start_minute ? intval($config->start_minute) : 0;
		$config->start_second = $config->start_second ? intval($config->start_second) : 0;

		$config->end_hour = $config->end_hour ? intval($config->end_hour) : 0;
		$config->end_minute = $config->end_minute ? intval($config->end_minute) : 0;
		$config->end_second = $config->end_second ? intval($config->end_second) : 0;

		$start_date = null;
		if($config->start_date &&
			strlen($config->start_date) == 8 &&
			checkdate(substr($config->start_date, 4, 2), substr($config->start_date, -2), substr($config->start_date, 0, 4)) &&
			($config->start_hour >= 0 && $config->start_hour < 24) &&
			($config->start_minute >= 0 && $config->start_minute < 60) &&
			($config->start_second >= 0 && $config->start_second < 60)
		){
			$start_date = $config->start_date . (strlen($config->start_hour) == 1 ? ('0'.$config->start_hour) : $config->start_hour) . (strlen($config->start_minute) == 1 ? ('0'.$config->start_minute) : $config->start_minute) . (strlen($config->start_second) == 1 ? ('0'.$config->start_second) : $config->start_second);
		}


		$end_date = null;
		if($config->end_date &&
			strlen($config->end_date) == 8 &&
			checkdate(substr($config->end_date, 4, 2), substr($config->end_date, -2), substr($config->end_date, 0, 4)) &&
			($config->end_hour >= 0 && $config->end_hour < 24) &&
			($config->end_minute >= 0 && $config->end_minute < 60) &&
			($config->end_second >= 0 && $config->end_second < 60)
		){
			$end_date = $config->end_date . (strlen($config->end_hour) == 1 ? ('0'.$config->end_hour) : $config->end_hour) . (strlen($config->end_minute) == 1 ? ('0'.$config->end_minute) : $config->end_minute) . (strlen($config->end_second) == 1 ? ('0'.$config->end_second) : $config->end_second);
		}

		$sequence = getNextSequence();
		$logged_info = Context::get('logged_info');

		$title = $config->title ? $config->title : $oSticker->title;
		$content = $config->content ? $config->content : $oSticker->content;
		$status = array('PUBLIC', 'CHECK', 'PAUSE', 'STOP');

		$args = new stdClass();
		$args->sticker_srl = $config->sticker_srl;
		$args->title = cut_str(htmlspecialchars(strip_tags($title), ENT_QUOTES, 'UTF-8', false), 100);
		$args->tag = cut_str(htmlspecialchars(strip_tags($config->tag), ENT_QUOTES, 'UTF-8', false), 250);
		$args->content = removeHackTag($content);

		if($config->readed_count_e && $config->readed_count_e === 'Y'){
			$args->readed_count = $config->readed_count ? intval($config->readed_count) : 0;
		}
		if($config->bought_count_e && $config->bought_count_e === 'Y'){
			$args->bought_count = $config->bought_count ? intval($config->bought_count) : 0;
		}
		if($config->used_count_e && $config->used_count_e === 'Y'){
			$args->used_count = $config->used_count ? intval($config->used_count) : 0;
		}

		$args->start_date = $start_date;
		$args->end_date = $end_date;
		$args->price = $config->price ? intval($config->price) : 0;
		$args->buy_limit = $config->buy_limit ? intval($config->buy_limit) : 0;
		$args->exptime = $config->exptime && $config->exptime != 0 ? intval($config->exptime) : null;

		$args->last_update = date('YmdHis');
		$args->last_updater = $logged_info->nick_name;
		$args->list_order = $sequence * -1;
		$args->status = in_array($config->status, $status) ? $config->status : "PUBLIC";

		$output = executeQuery('sticker.updateStickerAdmin', $args);
		if (!$output->toBool()) {
			return $output;
		}

		$this->setMessage('success_saved');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispStickerAdminStickerView', 'sticker_srl', $sticker_srl);
		$this->setRedirectUrl($returnUrl);

	}

	function procStickerAdminDelete(){

		$sticker_srl = Context::get('sticker_srl');
		$oStickerModel = getModel('sticker');
		$oSticker = $oStickerModel->getSticker($sticker_srl);
		if(!$oSticker){
			return new Object(-1,'msg_invalid_sticker');
		}

		$oStickerController = getController('sticker');
		$oStickerController->_deleteSticker($sticker_srl);
		$oStickerController->_deleteStickerFiles($sticker_srl);
		$oStickerController->_deleteStickerBuyByStickerSrl($sticker_srl);

		$this->setMessage('success_deleted');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispStickerAdminStickerList');
		$this->setRedirectUrl($returnUrl);

	}

	function procStickerAdminBuyUpdate(){
		$idx = Context::get('idx');
		$config = Context::getRequestVars();
		getDestroyXeVars($config);

		$args = new stdClass();
		$args->idx = $idx;
		$output = executeQuery('sticker.getStickerBuyByIdx', $args);
		if (!$output->toBool()) {
			return $output;
		}
		if(empty($output->data)){
			return new Object(-1,'msg_invalid_buy_sticker');
		}

		$expdate_hour = $config->expdate_hour ? intval($config->expdate_hour) : 0;
		$expdate_minute = $config->expdate_minute ? intval($config->expdate_minute) : 0;
		$expdate_second = $config->expdate_second ? intval($config->expdate_second) : 0;

		$expdate = null;
		if($config->expdate &&
			strlen($config->expdate) == 8 &&
			checkdate(substr($config->expdate, 4, 2), substr($config->expdate, -2), substr($config->expdate, 0, 4)) &&
			($expdate_hour >= 0 && $expdate_hour < 24) &&
			($expdate_minute >= 0 && $expdate_minute < 60) &&
			($expdate_second >= 0 && $expdate_second < 60)
		){
			$expdate = $config->expdate . (strlen($expdate_hour) == 1 ? ('0'.$expdate_hour) : $expdate_hour) . (strlen($expdate_minute) == 1 ? ('0'.$expdate_minute) : $expdate_minute) . (strlen($expdate_second) == 1 ? ('0'.$expdate_second) : $expdate_second);
		}

		$use_point = $config->use_point ? intval($config->use_point) : 0;
		$args1 = new stdClass();
		$args1->idx = $idx;
		$args1->expdate = $expdate;
		$args1->use_point = $use_point;
		if($config->used_count_e && $config->used_count_e == "Y"){
			$args1->used_count = $config->used_count ? intval($config->used_count) : 0;
		}

		$output1 = executeQuery('sticker.updateStickerBuyInfo', $args1);
		if (!$output1->toBool()) {
			return $output1;
		}

		$this->setMessage('success_saved');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispStickerAdminBuyInfo', 'idx', $idx);
		$this->setRedirectUrl($returnUrl);

	}

	function procStickerAdminBuyDelete(){
		$idx = Context::get('idx');
		$args = new stdClass();
		$args->idx = $idx;

		$output = executeQuery('sticker.getStickerBuyByIdx', $args);
		if (!$output->toBool()) {
			return $output;
		}
		if(empty($output->data)){
			return new Object(-1,'msg_invalid_buy_sticker');
		}

		executeQuery('sticker.deleteStickerBuyByIdx', $args);

		$this->setMessage('success_deleted');
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispStickerAdminBuyList');
		$this->setRedirectUrl($returnUrl);

	}

}

/* End of file sticker.admin.controller.php */
/* Location: ./modules/sticker/sticker.admin.controller.php */
