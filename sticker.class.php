<?php
/*! Copyright (C) 2016 BGM STORAGE. All rights reserved. */
/**
 * @class  sticker
 * @author Huhani (mmia268@gmail.com)
 * @brief  Sticker module high class.
 */

class sticker extends ModuleObject
{


	private $triggers = array(
		array( 'member.deleteMember',			'sticker',	'controller',	'triggerDeleteMember',					'after'	),
		array( 'member.getMemberMenu',		'sticker',	'controller',	'triggerMemberMenu',						'after'	),
		array( 'document.insertDocument',	'sticker',	'controller',	'triggerBeforeInsertDocument',      'before'	),
		array( 'document.updateDocument',	'sticker',	'controller',	'triggerBeforeUpdateDocument',      'before'	),
		array( 'comment.insertComment',		'sticker',	'controller',	'triggerBeforeInsertComment',       'before'	),
		array( 'comment.updateComment',		'sticker',	'controller',	'triggerBeforeUpdateComment',       'before'	),
		array( 'moduleHandler.init',			'sticker',	'controller',	'triggerBeforeModuleInit',				'before'	), // member메뉴 추가
		array( 'display',							'sticker',	'controller',	'triggerBeforeDisplay',					'before'	)
	);


	function moduleInstall()
	{
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');

		$sticker_info = $oModuleModel->getModuleInfoByMid('sticker');
		if(!$sticker_info->module_srl) {
			$args = new stdClass();
			$args->mid = 'sticker';
			$args->module = 'sticker';
			$args->browser_title = '스티커';
			$args->site_srl = 0;
			$args->skin = 'default';
			$args->mskin = 'default';
			$args->layout_srl = -1;
			$args->mlayout_srl = -1;
			$oModuleController->insertModule($args);
		}

		$config = new stdClass();
		$config->use = "Y";
		$config->before_test = "N";
		$config->add_member_menu = "N";
		$config->default_sticker = "";
		$config->deleted_sticker = '<i><p style="color: rgb(125, 125, 125);">존재하지 않는 스티커입니다.</p></i>';
		$config->buy_limit = 20;

		$config->minPoint = 100;
		$config->maxPoint = 600;
		$config->returnPoint = 15;
		$config->upload_charge = 0; //업로드 수수료. 단위 point
		$config->sale_end_date = 0;
		$config->use_date = 0;
		$config->sale_limit = 0;
		$config->limit_modify_buy = 0;
		$config->public_modify = "Y";
		$config->check_modify = "Y";
		$config->pause_modify = "Y";
		$config->public_delete = "Y";
		$config->check_delete = "Y";
		$config->pause_delete = "Y";
		$config->limit_delete_buy = 0;

		$config->resizing = "Y";
		$config->maxPx = 200;
		$config->gifResizingIf = "Y";
		$config->target_width = "Y";
		$config->image_quality = 100;
		$config->minUploads = 10;
		$config->maxUploads = 24;
		$config->image_min_width = 160;
		$config->image_min_height = 160;
		$config->file_size = 2048; //KB
		$config->file_size_all = 25000; //KB
		$config->file_ext = "jpg,jpeg,png,gif";
		$config->cmt_allow_modify = "Y";
		$config->cmt_max_sticker_count = 0;

		$oModuleController->insertModuleConfig('sticker', $config);

		foreach ($this->triggers as $trigger) {
			$oModuleController->insertTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
		}

		return new Object();
	}




	function moduleUninstall()
	{
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');

		//트리거 삭제
		foreach ($this->triggers as $trigger)
		{
			$oModuleController->deleteTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
		}

		//페이지 삭제
		$sticker_info = $oModuleModel->getModuleInfoByMid('sticker');
		if($sticker_info->module_srl) {
			$output = $oModuleController->deleteModule($sticker_info->module_srl);
			if(!$output->toBool()) {
				return $output;
			}
		}

		return new Object();

	}




	function checkUpdate()
	{
		$oModuleModel = getModel('module');
		foreach ($this->triggers as $trigger)
		{
			if (!$oModuleModel->getTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]))
			{
				return true;
			}
		}

		return false;
	}

	function moduleUpdate()
	{

		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		foreach ($this->triggers as $trigger)
		{
			if (!$oModuleModel->getTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]))
			{
				$oModuleController->insertTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
			}
		}

		return new Object();
	}

}

/* End of file sticker.class.php */
/* Location: ./modules/sticker/sticker.class.php */
