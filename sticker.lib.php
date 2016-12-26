<?php
	if(!defined("__ZBXE__")) exit();

use Imagecraft\ImageBuilder;
require_once __DIR__.'/lib/imagecraft/autoload.php';

function resizeGIF($filename, $output_name, $width, $height){

	$filename = __DIR__."/../../" . $filename;
	$output_name = __DIR__."/../../" . $output_name;

	$options = ['engine' => 'php_gd'];
	$builder = new ImageBuilder($options);
	$context = $builder->about();
	if (!$context->isEngineSupported()) {
		return false;
	} else {
		$layer = $builder->addBackgroundLayer();
		$layer->filename($filename);
		$layer->resize($width, $height, 'fill_crop');

		$image = $builder->save();
		if ($image->isValid()) {
			file_put_contents($output_name, $image->getContents());
			return true;
		} else {
			//echo $image->getMessage().PHP_EOL;
		}
	}

	return false;
}
