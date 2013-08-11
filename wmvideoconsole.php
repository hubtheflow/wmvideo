<?php
error_reporting(-1);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '1024M');
include_once 'applywatermark.class.php';

$cliParameters = parseArgs($argv);
if(isset($cliParameters['source']) && strlen($cliParameters['source'])>0 
  	&& isset($cliParameters['dest']) && strlen($cliParameters['dest'])>0 
		&& isset($cliParameters['watermark']) && strlen($cliParameters['watermark'])>0){
		$sourceDir=$cliParameters['source'];
		$destinationDir=$cliParameters['dest'];
		$watermarkImage=$cliParameters['watermark'];
		if(isset($cliParameters['frames'])){
			$options['frames'] = explode(',', $cliParameters['frames']);
		}
		if(isset($cliParameters['posx']) && isset($cliParameters['posy'])
		&& is_numeric($cliParameters['posx']) && is_numeric($cliParameters['posy'])){
			$options['posx'] = $cliParameters['posx'];
			$options['posy'] = $cliParameters['posy'];
		}
		if(isset($cliParameters['debug'])){
			$options['debug'] = true;
		}
}
elseif(isset($_GET['source']) && isset($_GET['dest']) 
	&& isset($_GET['watermark'])){
		$sourceDir=$_GET['source'];
		$destinationDir=$_GET['dest'];
		$watermarkImage=$_GET['watermark'];
		if(isset($_GET['frames'])){
			$framesToSave = explode(',', $_GET['frames']);
		}
}
if(file_exists($sourceDir)===false || file_exists($watermarkImage)===false){
	echo 'Usage: php wmvideoconsole.php --source=directory --dest=directory --watermark=file...'.PHP_EOL;;
	echo 'Watermark the video(s)'.PHP_EOL;
	echo PHP_EOL;
	echo '  --source	the directory containing video files to watermark'.PHP_EOL;
	echo '  --dest 	the directory where watermarked video files will be saved'.PHP_EOL;
	echo '  --watermark	PNG image file containing the watermark'.PHP_EOL;;
	echo '  --frames	comma separated frame ids to save as PNG'.PHP_EOL;;
	echo '  --posx	x position of a watermark'.PHP_EOL;;
	echo '  --posy	y position of a watermark'.PHP_EOL;;
	echo '  --debug	show debug information'.PHP_EOL;;
	die();
}
if(substr($destinationDir,-1) !=='/' || substr($destinationDir,-1) !== '\\'){
	$destinationDir=$destinationDir.'/';
}
if(file_exists($destinationDir)===false){
	mkdir($destinationDir);
}
$fileSPLObjects =  new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($sourceDir),
				RecursiveIteratorIterator::CHILD_FIRST
			);
try {
	$options['html'] = false;
	$options['format'] = 'mp4';
	foreach( $fileSPLObjects as $video => $fileSPLObject ) {
		if($fileSPLObject->getFilename()==='.' || $fileSPLObject->getFilename()==='..'){
			continue;
		}
		$original_size=$fileSPLObject->getSize();
		if(file_exists($destinationDir.$fileSPLObject->getFilename())!==false){
			continue; //tmp to skip files which cause segfault
			$result_stat = stat($destinationDir.$fileSPLObject->getFilename());
			if(strlen((string)$original_size)===strlen((string)$result_stat['size'])){
				echo 'skipping already processed file: '.$fileSPLObject->getFilename().PHP_EOL; 
				continue;
			}
		}
		
		echo 'filename: '.$fileSPLObject->getFilename().PHP_EOL;
		$WatermarkVideo = new WatermarkVideo($video, $watermarkImage, $destinationDir.$fileSPLObject->getFilename(), $options);
		echo 'done'.PHP_EOL;
	}
}
catch (UnexpectedValueException $e) {
	printf("Directory [%s] contained a directory we can not recurse into", $sourceDir);
}
function parseArgs($argv){
    array_shift($argv);
    $out = array();
    foreach ($argv as $arg){
        if (substr($arg,0,2) == '--'){
            $eqPos = strpos($arg,'=');
            if ($eqPos === false){
                $key = substr($arg,2);
                $out[$key] = isset($out[$key]) ? $out[$key] : true;
            } else {
                $key = substr($arg,2,$eqPos-2);
                $out[$key] = substr($arg,$eqPos+1);
            }
        } else if (substr($arg,0,1) == '-'){
            if (substr($arg,2,1) == '='){
                $key = substr($arg,1,1);
                $out[$key] = substr($arg,3);
            } else {
                $chars = str_split(substr($arg,1));
                foreach ($chars as $char){
                    $key = $char;
                    $out[$key] = isset($out[$key]) ? $out[$key] : true;
                }
            }
        } else {
            $out[] = $arg;
        }
    }
    return $out;
}

?>
