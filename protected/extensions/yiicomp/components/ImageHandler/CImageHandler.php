<?php
/**
 * Image handler
 * @author Yaroslav Pelesh aka Tokolist http://tokolist.com
 * @author Kosenka https://github.com/kosenka
 * @link https://github.com/tokolist/yii-components
 * @version 2.0
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 */

class CImageHandler extends CApplicationComponent
{
	private $driver = null;
	private $originalImage = null;
	private $format = 0;
	private $width = 0;
	private $height = 0;
	private $mimeType = '';
	private $fileName = '';

	const IMG_GIF = 1;
	const IMG_JPEG = 2;
	const IMG_PNG = 3;

	const CORNER_LEFT_TOP = 1;
	const CORNER_RIGHT_TOP = 2;
	const CORNER_LEFT_BOTTOM = 3;
	const CORNER_RIGHT_BOTTOM = 4;
	const CORNER_CENTER = 5;
	const CORNER_CENTER_TOP = 6;
	const CORNER_CENTER_BOTTOM = 7;
	const CORNER_LEFT_CENTER = 8;
	const CORNER_RIGHT_CENTER = 9;
	const CORNER_TILE = 10;

	const FLIP_HORIZONTAL = 1;
	const FLIP_VERTICAL = 2;
	const FLIP_BOTH = 3;


	public function getImage()
	{
		return $this->image;
	}

	public function getFormat()
	{
		return $this->format;
	}

	public function getWidth()
	{
		return $this->width;
	}

	public function getHeight()
	{
		return $this->height;
	}

	public function getMimeType()
	{
		return $this->mimeType;
	}

	public function __destruct()
	{
		$this->freeImage();
	}
	
	public function __construct($driver, $driverOptions=array()) {
		if(empty($driver))
		{
			throw new Exception('Invalid driver name');
		}
		
		$driverClassName = "C{$driver}ImageHandlerDriver";
		require "drivers\\$driverClassName.php";
		$this->driver = new $driverClassName($this);
		
		foreach($driverOptions as $option => $value)
		{
			$this->driver->{$option} = $value;
		}
		
		return $this;
	}

	private function freeImage()
	{
		$this->driver->freeImage();
	}

	private function checkLoaded()
	{
		Yii::log('CImageHandler::checkLoaded: ', "trace", "system.*");

		if(!$this->driver->checkLoaded())
		{
			throw new Exception('Load image first');
		}
	}

	private function loadImage($file)
	{
		Yii::log('CImageHandler::loadImage: '.$file, "trace", "system.*");

		$result = array();

		if ($imageInfo = @getimagesize($file))
		{
			$result['width'] = $imageInfo[0];
			$result['height'] = $imageInfo[1];
			$result['mimeType'] = $imageInfo['mime'];
			$result['format'] = $imageInfo[2];
			$result['image'] = $this->driver->loadImage($file, $result['format']);
			
			return $result;
		}
		else
		{
			throw new Exception('Invalid image file');
		}
	}

	protected function initImage($image = false)
	{
		Yii::log('CImageHandler::initImage: ', "trace", "system.*");
		
		if($image === false)
		{
			$image = $this->originalImage;
		}

		$this->width = $image['width'];
		$this->height = $image['height'];
		$this->mimeType = $image['mimeType'];
		$this->format = $image['format'];

		$this->driver->initImage($image);
	}

	public function load($file)
	{
		Yii::log('CImageHandler::load: '.$file, "trace", "system.*");

		$this->freeImage();

		if(($this->originalImage = $this->loadImage($file)))
		{
			$this->initImage();
			$this->fileName = $file;
			return $this;
		}
		else
		{
			return false;
		}
	}

	public function reload()
	{
		Yii::log('CImageHandler::reload: ', "trace", "system.*");

		$this->driver->checkLoaded();
		$this->driver->initImage();

		return $this;
	}

	public function resize($toWidth, $toHeight, $proportional = true)
	{
		Yii::log('CImageHandler::resize: ', "trace", "system.*");

		$this->driver->checkLoaded();

		$toWidth = $toWidth !== false ? $toWidth : $this->width;
		$toHeight = $toHeight !== false ? $toHeight : $this->height;

		if($proportional)
		{
			$newHeight = $toHeight;
			$newWidth = round($newHeight / $this->height * $this->width);
			if($newWidth > $toWidth)
			{
				$newWidth = $toWidth;
				$newHeight = round($newWidth / $this->width * $this->height);
			}
		}
		else
		{
			$newWidth = $toWidth;
			$newHeight = $toHeight;
		}
		
		$this->driver->resize($toWidth, $toHeight);
		
		return $this;
	}

	public function thumb($toWidth, $toHeight, $proportional = true)
	{
		Yii::log('CImageHandler::thumb: ', "trace", "system.*");

		$this->driver->checkLoaded();

		if($toWidth !== false)
			$toWidth = min($toWidth, $this->width);

		if($toHeight !== false)
			$toHeight = min($toHeight, $this->height);


		$this->resize($toWidth, $toHeight, $proportional);

		return $this;
	}

	public function watermark($watermarkFile, $offsetX, $offsetY, $corner = self::CORNER_RIGHT_BOTTOM, $zoom = false)
	{
		Yii::log('CImageHandler::watermark: ', "trace", "system.*");

		$this->driver->checkLoaded();

		if($wImg = $this->loadImage($watermarkFile))
		{
			$posX = 0;
			$posY = 0;

			$watermarkWidth = $wImg['width'];
			$watermarkHeight = $wImg['height'];

			if($zoom !== false)
			{
				$dimension = round(max($this->width, $this->height) * $zoom);

				$watermarkHeight = $dimension;
				$watermarkWidth = round($watermarkHeight / $wImg['height'] * $wImg['width']);

				if($watermarkWidth > $dimension)
				{
					$watermarkWidth = $dimension;
					$watermarkHeight = round($watermarkWidth / $wImg['width'] * $wImg['height']);
				}
			}


			switch ($corner)
			{
				case self::CORNER_LEFT_TOP:
					$posX = $offsetX;
					$posY = $offsetY;
					break;
				case self::CORNER_RIGHT_TOP:
					$posX = $this->width - $watermarkWidth - $offsetX;
					$posY = $offsetY;
					break;
				case self::CORNER_LEFT_BOTTOM:
					$posX = $offsetX;
					$posY = $this->height - $watermarkHeight - $offsetY;
					break;
				case self::CORNER_RIGHT_BOTTOM:
					$posX = $this->width - $watermarkWidth - $offsetX;
					$posY = $this->height - $watermarkHeight - $offsetY;
					break;
				case self::CORNER_CENTER:
					$posX = floor(($this->width - $watermarkWidth) / 2);
					$posY = floor(($this->height - $watermarkHeight) / 2);
					break;
				case self::CORNER_CENTER_TOP:
					$posX = floor(($this->width - $watermarkWidth) / 2);
					$posY = $offsetY;
					break;
				case self::CORNER_CENTER_BOTTOM:
					$posX = floor(($this->width - $watermarkWidth) / 2);
					$posY = $this->height - $watermarkHeight - $offsetY;
					break;
				case self::CORNER_LEFT_CENTER:
					$posX = $offsetX;
					$posY = floor(($this->height - $watermarkHeight) / 2);
					break;
				case self::CORNER_RIGHT_CENTER:
					$posX = $this->width - $watermarkWidth - $offsetX;
					$posY = floor(($this->height - $watermarkHeight) / 2);
					break;
				case self::CORNER_TILE:
					break;
				default:
					throw new Exception('Invalid $corner value');
			}
			
			$this->driver->watermark();
			
			return $this;
		}
		else
		{
			return false;
		}
	}


	public function flip($mode)
	{
                Yii::log('CImageHandler::flip: ', "trace", "system.*");

		$this->checkLoaded();

                if($this->engine=='GD')
                {
        		$srcX = 0;
        		$srcY = 0;
        		$srcWidth = $this->width;
        		$srcHeight = $this->height;

        		switch ($mode)
        		{
        			case self::FLIP_HORIZONTAL:
        				$srcX = $this->width - 1;
        				$srcWidth = -$this->width;
        				break;
        			case self::FLIP_VERTICAL:
        				$srcY = $this->height - 1;
        				$srcHeight = -$this->height;
        				break;
        			case self::FLIP_BOTH:
        				$srcX = $this->width - 1;
        				$srcY = $this->height - 1;
        				$srcWidth = -$this->width;
        				$srcHeight = -$this->height;
        				break;
        			default:
        				throw new Exception('Invalid $mode value');
        		}

        		$newImage = imagecreatetruecolor($this->width, $this->height);
        		$this->preserveTransparency($newImage);
        		imagecopyresampled($newImage, $this->image, 0, 0, $srcX, $srcY, $this->width, $this->height, $srcWidth, $srcHeight);
        		imagedestroy($this->image);
        		$this->image = $newImage;
        		//dimensions not changed
                }
                else
                {
        		switch ($mode)
        		{
        			case self::FLIP_HORIZONTAL:
                                        $this->engineExec=$this->engineIMConvert." -flop ".$this->fileName." %dest%";
        				break;
        			case self::FLIP_VERTICAL:
                                        $this->engineExec=$this->engineIMConvert." -flip ".$this->fileName." %dest%";
        				break;
        			case self::FLIP_BOTH:
                                        $this->engineExec=$this->engineIMConvert." -flop -flip ".$this->fileName." %dest%";
        				break;
        			default:
        				throw new Exception('Invalid $mode value');
        		}
                }

		return $this;
	}

	public function rotate($degrees)
	{
                Yii::log('CImageHandler::rotate: ', "trace", "system.*");

		$this->checkLoaded();

                if($this->engine=='GD')
                {
        		$degrees = (int) $degrees;
        		$this->image = imagerotate($this->image, $degrees, 0);

        		$this->width = imagesx($this->image);
        		$this->height = imagesy($this->image);
                }
                else
                {
                        $this->engineExec=$this->engineIMConvert." -rotate ".$degrees." ".$this->fileName." %dest%";
                }

		return $this;
	}

	public function crop($width, $height, $startX = false, $startY = false)
	{
                Yii::log('CImageHandler::crop: ', "trace", "system.*");

		$this->checkLoaded();

		$width = (int) $width;
		$height = (int) $height;

		//Centered crop
	 	$startX = $startX === false ? floor(($this->width - $width) / 2) : intval($startX);
		$startY = $startY === false ? floor(($this->height - $height) / 2) : intval($startY);
		//Check dimensions
		$startX = max(0, min($this->width, $startX));
		$startY = max(0, min($this->height, $startY));
		$width = min($width, $this->width - $startX);
		$height = min($height, $this->height - $startY);

                if($this->engine=='GD')
                {
        		$newImage = imagecreatetruecolor($width, $height);

        		$this->preserveTransparency($newImage);

        		imagecopyresampled($newImage, $this->image, 0, 0, $startX, $startY, $width, $height, $width, $height);

        		imagedestroy($this->image);
        		$this->image = $newImage;
        		$this->width = $width;
        		$this->height = $height;
                }
                else
                {
                        $this->engineExec=$this->engineIMConvert." -quiet -strip -crop ".$width."x".$height."+".$startX."+".$startY." ".$this->fileName." %dest%";
                }

		return $this;
	}

	public function text($text, $fontFile, $size=12, $color=array(0, 0, 0),
		$corner=self::CORNER_LEFT_TOP, $offsetX=0, $offsetY=0, $angle=0, $alpha = 0)
	{
                Yii::log('CImageHandler::text: ', "trace", "system.*");

		$this->checkLoaded();

	        $bBox = imagettfbbox($size, $angle, $fontFile, $text);
	        $textHeight = $bBox[1] - $bBox[7];
	        $textWidth = $bBox[2] - $bBox[0];

		switch ($corner)
		{
			case self::CORNER_LEFT_TOP:
				$posX = $offsetX;
				$posY = $offsetY;
				break;
			case self::CORNER_RIGHT_TOP:
				$posX = $this->width - $textWidth - $offsetX;
				$posY = $offsetY;
				break;
			case self::CORNER_LEFT_BOTTOM:
				$posX = $offsetX;
				$posY = $this->height - $textHeight - $offsetY;
				break;
			case self::CORNER_RIGHT_BOTTOM:
				$posX = $this->width - $textWidth - $offsetX;
				$posY = $this->height - $textHeight - $offsetY;
				break;
			case self::CORNER_CENTER:
				$posX = floor(($this->width - $textWidth) / 2);
				$posY = floor(($this->height - $textHeight) / 2);
				break;
			case self::CORNER_CENTER_TOP:
				$posX = floor(($this->width - $textWidth) / 2);
				$posY = $offsetY;
				break;
			case self::CORNER_CENTER_BOTTOM:
				$posX = floor(($this->width - $textWidth) / 2);
				$posY = $this->height - $textHeight - $offsetY;
				break;
			case self::CORNER_LEFT_CENTER:
				$posX = $offsetX;
				$posY = floor(($this->height - $textHeight) / 2);
				break;
			case self::CORNER_RIGHT_CENTER:
				$posX = $this->width - $textWidth - $offsetX;
				$posY = floor(($this->height - $textHeight) / 2);
				break;
			default:
				throw new Exception('Invalid $corner value');
		}

                if($this->engine=='GD')
                {
        		if($alpha > 0)
        		{
        			$color =  imagecolorallocatealpha($this->image, $color[0], $color[1], $color[2], $alpha);
        		}
        		else
        		{
        			$color = imagecolorallocate($this->image, $color[0], $color[1], $color[2]);
        		}

        		imagettftext($this->image, $size, $angle, $posX, $posY + $textHeight, $color, $fontFile, $text);
                }
                else
                {
                        $hex=$this->RGBToHex($color[0],$color[1],$color[2]);
                        $this->engineExec=$this->engineIMConvert." -quiet -font ".$fontFile." -pointsize ".$size." -draw \"gravity south fill '".$hex."' text ".$posX.",".$posY." '".$text."' \" ".$this->fileName." %dest%";
                }

		return $this;
	}

	public function adaptiveThumb($width, $height, $backgroundColor=array(0, 0, 0))
	{
                Yii::log('CImageHandler::adaptiveThumb: ', "trace", "system.*");

		$this->checkLoaded();

                if($this->engine=='GD')
                {
        		$width = intval($width);
        		$height = intval($height);

        		$widthProportion = $width / $this->width;
        		$heightProportion = $height / $this->height;

        		if ($widthProportion > $heightProportion)
        		{
        			$newWidth = $width;
        			$newHeight = round($newWidth / $this->width * $this->height);
        		}
        		else
        		{
        			$newHeight = $height;
        			$newWidth = round($newHeight / $this->height * $this->width);
        		}
        		$this->resize($newWidth, $newHeight);
        		$this->crop($width, $height);
                }
                else
                {
                        $hex=$this->RGBToHex($backgroundColor[0],$backgroundColor[1],$backgroundColor[2]);
                        $this->engineExec=$this->engineIMConvert." -quiet -strip -define jpeg:size=".$width."x".$height." ".$this->fileName." -thumbnail '".$width."x".$height.">' -background '".$hex."' -gravity center -extent ".$width."x".$height." %dest%";
                }

		return $this;
	}

	public function resizeCanvas($toWidth, $toHeight, $backgroundColor = array(255, 255, 255))
	{
                Yii::log('CImageHandler::resizeCanvas: ', "trace", "system.*");

		$this->checkLoaded();

                if($this->engine=='GD')
                {
        		$newWidth = min($toWidth, $this->width);
        		$newHeight = min($toHeight, $this->height);

        		$widthProportion = $newWidth / $this->width;
        		$heightProportion = $newHeight / $this->height;

        		if($widthProportion < $heightProportion)
        		{
        			$newHeight = round($widthProportion * $this->height);
        		}
        		else
        		{
        			$newWidth = round($heightProportion * $this->width);
        		}

        		$posX = floor(($toWidth - $newWidth) / 2);
        		$posY = floor(($toHeight - $newHeight) / 2);

                        $newImage = imagecreatetruecolor($toWidth, $toHeight);

        		$backgroundColor = imagecolorallocate($newImage, $backgroundColor[0], $backgroundColor[1], $backgroundColor[2]);
        		imagefill($newImage, 0, 0, $backgroundColor);

        		imagecopyresampled($newImage, $this->image, $posX, $posY, 0, 0, $newWidth, $newHeight, $this->width, $this->height);

        		imagedestroy($this->image);

        		$this->image = $newImage;
        		$this->width = $toWidth;
        		$this->height = $toHeight;
                }
                else
                {
                        $hex=$this->RGBToHex($backgroundColor[0],$backgroundColor[1],$backgroundColor[2]);
                        $this->engineExec=$this->engineIMConvert." -quiet -strip -define jpeg:size=".$toWidth."x".$toHeight." ".$this->fileName." -thumbnail '".$toWidth."x".$toHeight.">' -background '".$hex."' -gravity center -extent ".$toWidth."x".$toHeight." %dest%";
                }

		return $this;
	}

	public function grayscale()
	{
                Yii::log('CImageHandler::grayscale: ', "trace", "system.*");

                if($this->engine=='GD')
                {
        		$newImage = imagecreatetruecolor($this->width, $this->height);

        		imagecopy($newImage, $this->image, 0, 0, 0, 0, $this->width, $this->height);
        		imagecopymergegray($newImage, $newImage, 0, 0, 0, 0, $this->width, $this->height, 0);

        		imagedestroy($this->image);

        		$this->image = $newImage;
                }
                else
                {
                        $this->engineExec=$this->engineIMConvert." -colorspace Gray ".$this->fileName." %dest%";
                }

		return $this;
	}

	public function show($inFormat = false, $jpegQuality = 75)
	{
		$this->checkLoaded();

		if (!$inFormat)
		{
			$inFormat = $this->format;
		}

		switch ($inFormat)
		{
			case self::IMG_GIF:
				header('Content-type: image/gif');
				imagegif($this->image);
				break;
			case self::IMG_JPEG:
				header('Content-type: image/jpeg');
				imagejpeg($this->image, null, $jpegQuality);
				break;
			case self::IMG_PNG:
				header('Content-type: image/png');
				imagepng($this->image);
				break;
			default:
				throw new Exception('Invalid image format for putput');
		}

		return $this;
	}

	public function save($file = false, $toFormat = false, $jpegQuality = 75, $touch = false)
	{
                Yii::log('CImageHandler::save: ', "trace", "system.*");

		if (empty($file))
		{
			$file = $this->fileName;
		}

		$this->checkLoaded();

                if($this->engine=='GD')
                {
        		if (!$toFormat)
        		{
        			$toFormat = $this->format;
        		}

        		switch ($toFormat)
        		{
        			case self::IMG_GIF:
        				if (!imagegif($this->image, $file))
        				{
        					throw new Exception('Can\'t save gif file');
        				}
        				break;
        			case self::IMG_JPEG:
        				if (!imagejpeg($this->image, $file, $jpegQuality))
        				{
        					throw new Exception('Can\'t save jpeg file');
        				}
        				break;
        			case self::IMG_PNG:
        				if (!imagepng($this->image, $file))
        				{
        					throw new Exception('Can\'t save png file');
        				}
        				break;
        			default:
        				throw new Exception('Invalid image format for save');
        		}
                }
                else
                {
        		if (!$toFormat)
        		{
        			$toFormat = $this->format;
        		}

        		switch ($toFormat)
        		{
        			case self::IMG_GIF:
                                        $format="GIF";
        				break;
        			case self::IMG_JPEG:
                                        $format="JPG";
        				break;
        			case self::IMG_PNG:
                                        $format="PNG";
        				break;
        			default:
        				throw new Exception('Invalid image format for save');
        		}
                        $path_parts = pathinfo($file);
                        $file=$path_parts['dirname'].DIRECTORY_SEPARATOR.$path_parts['filename'].'.'.strtolower($format);
                        $this->engineExec=str_replace('%dest%',' -quality '.$jpegQuality.' '.$format.':'.$file,$this->engineExec);
                        Yii::log('CImageHandler: '.$this->engineExec, "trace", "system.*");
                        exec($this->engineExec);
                        $this->fileName=$file;
                }

		if ($touch && $file != $this->fileName)
		{
			touch($file, filemtime($this->fileName));
		}

		return $this;
	}


}
