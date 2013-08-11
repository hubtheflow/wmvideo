<?php
class WatermarkVideo{
  public $originalVideo;
	public $resultingVideo;
	private $watermark;
	private $saveFrames;
	public $options;
	public $frames;

	/**
	 * Apply a logo to the video
	 * @param file string - The path to the video
	 * @param image string - The path to the image with logo
	 * @param result string - The path to save the procesed video file
	 * @param posx int - relative x position to place the watermark at
	 * @param posy int - relative y position to place the watermark at
	 * @param options array - times array - time where to apply logo, if not set will apply logo to a every frame, or an array of times, frames array - An array of frame ids to save as PNG images,
	 * debug boolean - if set true will print debug information
	 * @return boolean - True if a video frame was read; false otherwise.
	*/
	public function __construct($file, $image, $result, $options){
		$this->originalVideo['filename'] = $file;
		$this->watermark['filename'] = $image;
		$this->options = $options;
		if(isset($this->options['frames']) && is_array($this->options['frames'])!==false){
			$this->saveFrames=$this->options['frames'];
		}
		if(isset($this->options['html']) && $this->options['html']!==false){
			define('LINE_BREAK', '<br/>');
		}
		else{
			define('LINE_BREAK', PHP_EOL);
		}
		$this->originalVideo['av_file'] = av_file_open($this->originalVideo['filename'], 'r');
		$this->originalVideo['info'] = av_file_stat($this->originalVideo['av_file']);
		//print_r($this->options);
		if(!isset($this->options['format']) || 
			($this->options['format'] !== 'mp4' && $this->options['format'] !== 'webm' && $this->options['format'] !== 'ogg') && $this->options['format'] !== 'avi'){
			if($this->originalVideo['info']['format'] === 'mp4' || $this->originalVideo['info']['format'] === 'webm' || $this->originalVideo['info']['format'] === 'ogg' || $this->originalVideo['info']['format'] === 'avi'){
				$this->options['format'] = $this->originalVideo['info']['format'];
			}
			else{
				$this->options['format'] = 'mp4';
			}
		}
		$path_parts = pathinfo($result);
		$this->resultingVideo['filename'] = $path_parts['dirname'].'/'.$path_parts['filename'].'.'.$this->options['format'];
		//print_r($this->resultingVideo['filename']);
		if($this->options['html']===true){
			echo '<div class="tabcontent debug"><pre>';	
		}
		if(isset($options['debug']) && $options['debug']===true){
			print_r($this->originalVideo['info']);
		}
		if($this->options['html']===true){
			echo '</pre></div>';
		}
		if($this->options['html']===true){
		echo '<div class="tabcontent selected info">';
		}
		echo 'duration: '.$this->originalVideo['info']['duration'].LINE_BREAK;
		echo 'dimensions: '.$this->originalVideo['info']['streams']['video']['width'].'x'.$this->originalVideo['info']['streams']['video']['height'].LINE_BREAK;
		echo 'codec: '.$this->originalVideo['info']['streams']['video']['codec'].LINE_BREAK;
		echo 'bitrate: '.$this->originalVideo['info']['bit_rate'].LINE_BREAK;
		if(isset($this->saveFrames)){
			echo 'will save frames '.implode(', ', $this->saveFrames).' as PNG images'.LINE_BREAK;
		}
		echo "the resulting video will be in ".$this->options['format'].' format'.LINE_BREAK;
		//print_r($this->originalVideo['info']);
		$this->loadWatermark();
		if(!isset($this->options['posx']) || !isset($this->options['posx'])){
			$this->options['posx'] = 0;
			$this->options['posy'] = 0;
		}
		$this->calculateWatermarkPosition($this->options['posx'], $this->options['posy']);
		if($this->options['html']===true){
			echo '</div>';
		}
		$this->applyWatermark();
	}
	/**
	 * Apply the watermark to the video
	*/
	private function applyWatermark(){
		if(isset($this->originalVideo['info']['streams']['video']) && is_array($this->originalVideo['info']['streams']['video'])){
			$this->resultingVideo['av_file']  = av_file_open($this->resultingVideo['filename'], 'w', array('format' => $this->options['format']));
			$this->openStreams();
			if(isset($this->originalVideo['streams']['video'])){

				$this->processFrames();
				$this->closeVideoStreams();
				if(isset($this->originalVideo['streams']['audio'])){

					$this->processAudio();
					$this->closeAudioStreams();
				}
			}
			$result = av_file_close($this->resultingVideo['av_file'] );

		}
		$result = av_file_close($this->originalVideo['av_file']);
	}
	/**
	 * Open original and destination video and audio streams
	*/
	private function openStreams(){
			//open video stream for reading
			$this->originalVideo['streams']['video'] = av_stream_open($this->originalVideo['av_file'], 'video', $this->originalVideo['info']['streams']['video']);
			//open video stream for writing
			$resultingVideoOptions=$this->originalVideo['info']['streams']['video'];
			$resultingVideoOptions['gop']=1;
			$this->resultingVideo['streams']['video'] = av_stream_open($this->resultingVideo['av_file'] , 'video', $resultingVideoOptions);
			if(isset($this->originalVideo['info']['streams']['audio']) && is_array($this->originalVideo['info']['streams']['audio'])){
				//open audio stream for reading
				$this->originalVideo['streams']['audio'] = av_stream_open($this->originalVideo['av_file'], 'audio', $this->originalVideo['info']['streams']['audio']);
				//open audio stream for writing
				$this->resultingVideo['streams']['audio'] = av_stream_open($this->resultingVideo['av_file'] , 'audio', $this->originalVideo['info']['streams']['audio']);
			}
	}
	/**
	 * Close original and destination video streams
	*/
	public function closeVideoStreams(){	
		$result = av_stream_close($this->originalVideo['streams']['video']);
		$result = av_stream_close($this->resultingVideo['streams']['video']);
	}
	
	/**
	 * Close original and destination audio streams
	*/
	public function closeAudioStreams(){
		if(isset($this->originalVideo['info']['streams']['audio']) && is_array($this->originalVideo['info']['streams']['audio'])){			
			$result = av_stream_close($this->originalVideo['streams']['audio']);
			$result = av_stream_close($this->resultingVideo['streams']['audio']);
		}	
	}
	
	/**
	 * Copy video frames to destination stream with the watermark applied
	*/
	private function processFrames(){
		//create a empty gd image dimensioned as video
		if(isset($this->saveFrames)){
			$saveFrames=$this->saveFrames;
		}
		$frame=imagecreatetruecolor($this->originalVideo['info']['streams']['video']['width'],$this->originalVideo['info']['streams']['video']['height']);
		//load watermark from an image specified
		$percents=array(90,80,70,60,50,40,30,20,10);
		$complete=10;
		if($this->options['html']!==true){
			echo 'processing video 0% ';
		}
		$frameId=0;
		while(av_stream_read_image($this->originalVideo['streams']['video'], $frame, $time)!==false){
			++$frameId;
			$tm = round(($time / $this->originalVideo['info']['duration'])*100);
			//calculate completion
			if($tm==$complete){
				$complete=array_pop($percents);
				if(isset($complete)){
					//echo $complete.'% ';
					if($this->options['html']===true){
						echo '<div class="progress-video" data-progressbar="set-'.$complete.'"></div>';
					}
					else{
						echo $complete.'% ';
					}
					flush();
				}
			}
			/*if(isset($this->saveFrames)){
				$frameToSave=array_pop($this->saveFrames);
				if($frameId==$frameToSave){
					//echo LINE_BREAK.'saving frame '.$frameId.LINE_BREAK;
					//imagepng($frame, $this->result.'-frame-'.$frameId.'.orig.png'); 
					array_push($this->saveFrames,$frameToSave);
				}
				else{
					array_push($this->saveFrames,$frameToSave);
				}
			}*/
			$this->imagecopymerge_alpha($frame, $this->watermark['image'], $this->watermark['posx'], $this->watermark['posy'], 0, 0, $this->watermark['width'] , $this->watermark['height'] ,100);
			if(isset($saveFrames)){
				$frameToSave=array_shift($saveFrames);
				if($frameId==$frameToSave){
					//echo LINE_BREAK.'saving frame '.$frameId.LINE_BREAK;
					$frameFile=$this->resultingVideo['filename'].'_frame-'.$frameId.'.png';
					$this->frames[$frameId]=$frameFile;
					imagepng($frame, $frameFile);
					if($this->options['html']===true){
						$this->showFrame(basename($this->resultingVideo['filename']).'_frame-'.$frameId.'.png', $frameId);
					}
				}
				else{
					array_unshift($saveFrames,$frameToSave);
				}
			}
			//write frame to destination stream
			av_stream_write_image($this->resultingVideo['streams']['video'], $frame, $time);
		}
		if($this->options['html']===true){
			echo '<div class="progress-video" data-progressbar="set-100"></div>';
			echo '</div>';
		}
		else{
			echo '100% '.LINE_BREAK;
		}
	}
	/**
	 * Copy audio samples to destination stream
	*/
	private function processAudio(){
		$percents=array(90,80,70,60,50,40,30,20,10);
		$complete=10;
		if($this->options['html']!==true){
			echo 'processing audio 0% ';
		}
		while(av_stream_read_pcm($this->originalVideo['streams']['audio'], $samples, $time)!==false){
			//echo 'processing audio stream sample'.$time.LINE_BREAK;
			av_stream_write_pcm($this->resultingVideo['streams']['audio'], $samples, $time);
			$tm = round(($time / $this->originalVideo['info']['duration'])*100);
			if($tm==$complete){
				$complete=array_pop($percents);
				if(isset($complete)){
					if($this->options['html']===true){
						echo '<div class="progress-audio" data-progressbar="set-'.$complete.'"></div>';
					}
					else{
						echo $complete.'% ';
					}
					flush();
				}
			}
			//echo 'processed audio stream sample'.$time.LINE_BREAK;
		}
		if($this->options['html']===true){
			echo '<div class="progress-audio" data-progressbar="set-100"></div>';
		}
		else{
			echo '100%'.LINE_BREAK;
		}
	}
	
	/**
	 * Load watermark from a $this->watermark['filename'] file
	*/
	private function loadWatermark(){
		
		list($width, $height) = getimagesize($this->watermark['filename']);
		$image = imagecreatefrompng($this->watermark['filename']);
		imagealphablending($image, true);
		imagesavealpha($image, true);
		if($width === $this->originalVideo['info']['streams']['video']['width'] 
			&& $height === $this->originalVideo['info']['streams']['video']['height']){
			echo 'keeping original watermark size'.LINE_BREAK;
			$this->watermark['width'] = $width;
			$this->watermark['height'] = $height;
			$this->watermark['image'] = $image;
		}
		else{
			echo 'resizing watermark'.LINE_BREAK;
			$this->watermark['width'] = round($this->originalVideo['info']['streams']['video']['width']*0.1);
			$this->watermark['height'] = round($this->originalVideo['info']['streams']['video']['width']*0.1);
			$ratio = $width/$height;

			if ($this->watermark['width']/$this->watermark['height'] > $ratio) {
			   $this->watermark['width'] = round($this->watermark['height']*$ratio);
			} else {
			   $this->watermark['height'] = round($this->watermark['width']/$ratio);
			}
			// Resample
			$image_p = imagecreatetruecolor($this->watermark['width'], $this->watermark['height']);
			//preserve alpha transparency
			imagecolortransparent($image_p, imagecolorallocatealpha($image_p, 0, 0, 0, 127));
			imagealphablending($image_p, false);
			imagesavealpha($image_p, true);
			
			imagecopyresampled($image_p, $image, 0, 0, 0, 0, $this->watermark['width'] , $this->watermark['height'] , $width, $height);
			$this->watermark['image'] = $image_p;
		}
		echo 'watermark dimensions: '.$this->watermark['width'].'x'.$this->watermark['height'].LINE_BREAK;
	}

	/**
	 * Calculate absolute watermark position from its relative position and size
	 * @param posx int - relative x position to place the watermark at
	 * @param posy int - relative y position to place the watermark at
	*/	
	function calculateWatermarkPosition($posx, $posy){
			//if the watermark and video dimensions are equal set watarmark position to 0x 0y
			if($this->watermark['width'] === $this->originalVideo['info']['streams']['video']['width'] 
			&& $this->watermark['height'] === $this->originalVideo['info']['streams']['video']['height']){
				$this->watermark['posx']=0;
				$this->watermark['posy']=0;
			}
			//else calculate absolute watermark position from given relative position and its size
			else{
				if($posx<0){
					 $this->watermark['posx']=$this->originalVideo['info']['streams']['video']['width'] - $this->watermark['width'] + $posx;
				}
				else{
					 $this->watermark['posx']=$posx;
				}
				if($posy<0){
					 $this->watermark['posy']=$this->originalVideo['info']['streams']['video']['height'] - $this->watermark['height'] + $posy;
				}
				else{
					$this->watermark['posy']=$posy;
				}
			}
			echo 'watermark position: '.$this->watermark['posx'].'x '.$this->watermark['posy'].'y'.LINE_BREAK;
	}
	private function showFrame($frameFile,$frameId){
			echo '<div class="tabcontent frame">'.PHP_EOL;
			echo "<img src='./results/".basename($frameFile)."'>".PHP_EOL;
			echo "<a href='./results/".basename($frameFile)."'>frame #".$frameId."</a>".PHP_EOL;
			echo "</div>".PHP_EOL;
			flush();
	}
	
	/** 
	 * PNG ALPHA CHANNEL SUPPORT for imagecopymerge(); 
	 * by Sina Salek 
	 * 
	 * Bugfix by Ralph Voigt (bug which causes it 
	 * to work only for $src_x = $src_y = 0. 
	 * Also, inverting opacity is not necessary.) 
	 * 08-JAN-2011 
	 * 
	 **/ 
    private function imagecopymerge_alpha($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct){ 
        // creating a cut resource 
        $cut = imagecreatetruecolor($src_w, $src_h); 
		
        // copying relevant section from background to the cut resource 
        imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h); 
		
        // copying relevant section from watermark to the cut resource 
        imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h); 
        
        // insert cut resource to destination image 
        imagecopymerge($dst_im, $cut, $dst_x, $dst_y, 0, 0, $src_w, $src_h, $pct); 
    }
}

?>
