<?php

namespace Gregwar\Captcha;


use \Exception;
use OzdemirBurak\Iris\Color\Rgb;

/**
 * Builds a new captcha image
 * Uses the fingerprint parameter, if one is passed, to generate the same image
 *
 * @author Gregwar <g.passault@gmail.com>
 * @author Jeremy Livingston <jeremy.j.livingston@gmail.com>
 */
class CaptchaBuilder implements CaptchaBuilderInterface
{
	/**
	 * @var array
	 */
	protected $fingerprint = array();

	/**
	 * @var bool
	 */
	protected $useFingerprint = false;

	/**
	 * @var array
	 */
	protected $textColor = array();

	/**
	 * @var array
	 */
	protected $lineColor = null;

	/**
	 * @var array
	 */
	protected $backgroundColor = null;

	/**
	 * @var array
	 */
	protected $backgroundImages = array();

	/**
	 * @var resource
	 */
	protected $contents = null;

	/**
	 * @var string
	 */
	protected $phrase = null;

	/**
	 * @var PhraseBuilderInterface
	 */
	protected $builder;

	/**
	 * @var bool
	 */
	protected $distortion = true;

	/**
	 * The maximum number of lines to draw in front of
	 * the image. null - use default algorithm
	 */
	protected $maxFrontLines = 9;

	/**
	 * The maximum number of lines to draw behind
	 * the image. null - use default algorithm
	 */
	protected $maxBehindLines = 5;

	/**
	 * The maximum angle of char
	 */
	protected $maxAngle = 8;

	/**
	 * The maximum offset of char
	 */
	protected $maxOffset = 5;

	/**
	 * Is the interpolation enabled ?
	 *
	 * @var bool
	 */
	protected $interpolation = true;

	/**
	 * Ignore all effects
	 *
	 * @var bool
	 */
	protected $ignoreAllEffects = false;

	/**
	 * Adds the website URL on top of the image in the selected corner
	 *
	 * @var bool
	 */

	protected $addUrlText = true;

	/**
	 *  Holds server name
	 *
	 * @var string
	 */

	private $url;

	/**
	 * Allowed image types for the background images
	 *
	 * @var array
	 */
	protected $allowedBackgroundImageTypes = array('image/png', 'image/jpeg', 'image/gif');


	/**
	 * The image contents
	 */
	public function getContents()
	{
		return $this->contents;
	}

	/**
	 * Enable/Disables the interpolation
	 *
	 * @param $interpolate bool  True to enable, false to disable
	 *
	 * @return CaptchaBuilder
	 */
	public function setInterpolation($interpolate = true)
	{
		$this->interpolation = $interpolate;

		return $this;
	}

	/**
	 * Temporary dir, for OCR check
	 */
	public $tempDir = 'temp/';

	public function __construct($phrase = null, PhraseBuilderInterface $builder = null)
	{
		if ( $builder === null ) {
			$this->builder = new PhraseBuilder;
		} else {
			$this->builder = $builder;
		}

		$this->url = $url ?? $_SERVER['HTTP_HOST'];

		$this->phrase = is_string($phrase) ? $phrase : $this->builder->build($phrase);
	}

	/**
	 * Setting the phrase
	 */
	public function setPhrase($phrase)
	{
		$this->phrase = (string)$phrase;
	}

	/**
	 * Enables/disable distortion
	 */
	public function setDistortion($distortion)
	{
		$this->distortion = (bool)$distortion;

		return $this;
	}

	public function setMaxBehindLines($maxBehindLines)
	{
		$this->maxBehindLines = $maxBehindLines;

		return $this;
	}

	public function setMaxFrontLines($maxFrontLines)
	{
		$this->maxFrontLines = $maxFrontLines;

		return $this;
	}

	public function setMaxAngle($maxAngle)
	{
		$this->maxAngle = $maxAngle;

		return $this;
	}

	public function setMaxOffset($maxOffset)
	{
		$this->maxOffset = $maxOffset;

		return $this;
	}

	/**
	 * Gets the captcha phrase
	 */
	public function getPhrase()
	{
		return $this->phrase;
	}

	/**
	 * Returns true if the given phrase is good
	 */
	public function testPhrase($phrase)
	{
		return ($this->builder->niceize($phrase) == $this->builder->niceize($this->getPhrase()));
	}

	/**
	 * Instantiation
	 */
	public static function create($phrase = null)
	{
		return new self($phrase);
	}

	/**
	 * Sets the text color to use
	 */
	public function setTextColor($r, $g, $b)
	{
		$this->textColor = array($r, $g, $b);

		return $this;
	}

	/**
	 * Sets the background color to use
	 */
	public function setBackgroundColor($r, $g, $b)
	{
		$this->backgroundColor = array($r, $g, $b);

		return $this;
	}

	public function setLineColor($r, $g, $b)
	{
		$this->lineColor = array($r, $g, $b);

		return $this;
	}

	/**
	 * Sets the ignoreAllEffects value
	 *
	 * @param bool $ignoreAllEffects
	 *
	 * @return CaptchaBuilder
	 */
	public function setIgnoreAllEffects($ignoreAllEffects)
	{
		$this->ignoreAllEffects = $ignoreAllEffects;

		return $this;
	}

	/**
	 * Sets the list of background images to use (one image is randomly selected)
	 */
	public function setBackgroundImages(array $backgroundImages)
	{
		$this->backgroundImages = $backgroundImages;

		return $this;
	}

	/**
	 * Draw lines over the image
	 */
	protected function drawLine($image, $width, $height, $pallet)
	{
		if ( $this->rand(0, 1) ) { // Horizontal
			$Xa = $this->rand(0, $width / 2);
			$Ya = $this->rand(0, $height);
			$Xb = $this->rand($width / 2, $width);
			$Yb = $this->rand(0, $height);
		} else { // Vertical
			$Xa = $this->rand(0, $width);
			$Ya = $this->rand(0, $height / 2);
			$Xb = $this->rand(0, $width);
			$Yb = $this->rand($height / 2, $height);
		}

		if ( $this->lineColor === null ) {
			$rgb = $this->getCol($image, $Xa, $Ya);
			$red = $this->getCol($image, $Xa, $Ya);
			$green = $this->rand(100, 255);
			$blue = $this->rand(100, 255);
		} else {
			$red = $this->lineColor[0];
			$green = $this->lineColor[1];
			$blue = $this->lineColor[2];
		}

		if ( $pallet === null ) {
			$pallet = imagecolorallocate($image, $red, $green, $blue);
		} else {
			if ( is_array($pallet) || is_object($pallet) ) {
				$i = 0;
				foreach ( $pallet as $key => $val ) if ( $i < 29 ) {
					foreach ( $val as $v ) {
						$r = $pallet[$i][$i][0];
						$g = $pallet[$i][$i][1];
						$b = $pallet[$i][$i][2];
						$b = $pallet[$i][$i][2];

						$tcol = $this->colorDistort($image, $r, $g, $b, 60);
						imagesetthickness($image, $this->rand(1, 5));
						imageline($image, $Xa, $Ya, $Xb, $Yb, $tcol);

					}

				}
			}
		}
		imagesetthickness($image, $this->rand(1, 3));
		imageline($image, $Xa, $Ya, $Xb, $Yb, $tcol);
	}

	/**
	 * Apply some post effects
	 */
	protected function postEffect($image)
	{
		if ( !function_exists('imagefilter') ) {
			return;
		}

		if ( $this->backgroundColor != null || $this->textColor != null ) {
			return;
		}

		// Negate ?
		if ( $this->rand(0, 1) == 0 ) {
			imagefilter($image, IMG_FILTER_NEGATE);
		}

		// Edge ?
		if ( $this->rand(0, 10) == 0 ) {
			imagefilter($image, IMG_FILTER_EDGEDETECT);
		}

		// Contrast
		imagefilter($image, IMG_FILTER_CONTRAST, $this->rand(-50, 10));

		// Colorize
		if ( $this->rand(0, 5) == 0 ) {
			imagefilter($image, IMG_FILTER_COLORIZE, $this->rand(-80, 50), $this->rand(-80, 50), $this->rand(-80, 50));
		}
	}

	/**
	 * Writes the phrase on the image
	 */
	protected function writePhrase($image, $phrase, $font, $width, $height, $pallet = null)
	{
		$length = mb_strlen($phrase);
		if ( $length === 0 ) {
			return \imagecolorallocate($image, 0, 0, 0);
		}

		$size = $width / $length - $this->rand(0, 3) - 1;
		$box = \imagettfbbox($size, 0, $font, $phrase);
		$textWidth = $box[2] - $box[0];
		$textHeight = $box[1] - $box[7];
		$x = ($width - $textWidth) / 2;
		$y = ($height - $textHeight) / 2 + $size;
		if ( $pallet == null ) {
			$textColor = array($this->rand(0, 150), $this->rand(0, 150), $this->rand(0, 150));
		}
		$j = 0;
		if ( is_array($pallet) || is_object($pallet) ) {
			foreach ( $pallet as $key => $val ) if ( $j < $length ) {
				// Write the letters one by one, with random angle
				for ( $i = 0; $i < $length; $i++ ) if ( $i < $length ) {
					$r = $pallet[$i][$i][0];
					$g = $pallet[$i][$i][1];
					$b = $pallet[$i][$i][2];

					$col = $this->colorDistort($image, $r, $g, $b, 180);

					$symbol = mb_substr($phrase, $i, 1);
					$box = \imagettfbbox($size, 0, $font, $symbol);
					$w = $box[2] - $box[0];
					$angle = $this->rand(-$this->maxAngle, $this->maxAngle);
					$offset = $this->rand(-$this->maxOffset, $this->maxOffset);
					\imagettftext($image, $size, $angle, $x, $y + $offset, $col, $font, $symbol);
					$x += $w;
					$j++;
				}
			}
		}

		return $col;
	}

	/**
	 * Try to read the code against an OCR
	 */
	public function isOCRReadable()
	{
		if ( !is_dir($this->tempDir) ) {
			@mkdir($this->tempDir, 0755, true);
		}

		$tempj = $this->tempDir . uniqid('captcha', true) . '.jpg';
		$tempp = $this->tempDir . uniqid('captcha', true) . '.pgm';

		$this->save($tempj);
		shell_exec("convert $tempj $tempp");
		$value = trim(strtolower(shell_exec("ocrad $tempp")));

		@unlink($tempj);
		@unlink($tempp);

		return $this->testPhrase($value);
	}

	public function addUrl($image, $width, $height, $font, $pallet)
	{
		$symbol = $this->url;
		$length = mb_strlen($symbol);
		$size = 18;
		$angle = 0;
		$padding = 5;
		$x = 0;
		$y = $height;
		$z = mt_rand(0, 15);
		$r = $pallet[$z][$z][0];
		$g = $pallet[$z][$z][1];
		$b = $pallet[$z][$z][2];
		$col = $this->colorDistort($image, $r, $g, $b, 360);
		$rgb = \imagecolorsforindex($image, $col);
		$rgb = (new Rgb('rgb(' . $rgb['red'] . ',' . $rgb['green'] . ',' . $rgb['blue'] . ')'));
		if ( $rgb->isLight() ) {
			$rgb->Shade(100);
		}else{
			$rgb->tint(100);
		}
		$rcol = \imagecolorallocate($image, $rgb->red(), $rgb->green(), $rgb->blue());
		$type_space = imagettfbbox($size, 0, $font, $symbol);
		$type_width = abs($type_space[4] - $type_space[0]) + $padding;
		\imagefilledrectangle($image , $x, ($y - $size) - $padding, $type_width, $y, $rcol );
		\imagettftext($image, $size, $angle, $x, $y, $col, $font, $symbol);

		return $image;
	}

	/**
	 * Builds while the code is readable against an OCR
	 */
	public function buildAgainstOCR($width = 150, $height = 40, $font = null, $fingerprint = null)
	{
		do {
			$this->build($width, $height, $font, $fingerprint);
		} while ( $this->isOCRReadable() );
	}

	/**
	 * Generate the image
	 */
	public function build($width = 150, $height = 40, $font = null, $fingerprint = null)
	{
		if ( null !== $fingerprint ) {
			$this->fingerprint = $fingerprint;
			$this->useFingerprint = true;
		} else {
			$this->fingerprint = array();
			$this->useFingerprint = false;
		}

		if ( $font === null ) {
			$font = __DIR__ . '/Font/captcha' . $this->rand(0, 5) . '.ttf';
		}

		if ( empty($this->backgroundImages) ) {
			// if background images list is not set, use a color fill as a background
			$image = imagecreatetruecolor($width, $height);
			if ( $this->backgroundColor == null ) {
				$bg = imagecolorallocate($image, $this->rand(200, 255), $this->rand(200, 255), $this->rand(200, 255));
			} else {
				$color = $this->backgroundColor;
				$bg = imagecolorallocate($image, $color[0], $color[1], $color[2]);
			}
			$this->background = $bg;
			imagefill($image, 0, 0, $bg);
		} else {
			// use a random background image
			$randomBackgroundImage = $this->backgroundImages[array_rand($this->backgroundImages)];

			$imageType = $this->validateBackgroundImage($randomBackgroundImage);

			$image = $this->createBackgroundImageFromType($randomBackgroundImage, $imageType);

			$pallet = $this->getColorPallet($randomBackgroundImage);
			$this->color = null;
			if ( $image ) {
				$select = mt_rand(0, 10);
				$color = $pallet[$select][$select];
				$this->color = imagecolorallocate($image, $color[0], $color[1], $color[2]);
			}
			$bg = $this->color;
		}

		// Apply effects
		if ( !$this->ignoreAllEffects ) {
			$square = $width * $height;
			$effects = $this->rand($square / 3000, $square / 2000);

			// set the maximum number of lines to draw in front of the text
			if ( $this->maxBehindLines != null && $this->maxBehindLines > 0 ) {
				$effects = min($this->maxBehindLines, $effects);
			}

			if ( $this->maxBehindLines !== 0 ) {
				for ( $e = 0; $e < $effects; $e++ ) {
					$this->drawLine($image, $width, $height, $pallet);
				}
			}
		}

		// Write CAPTCHA text
		$color = $this->writePhrase($image, $this->phrase, $font, $width, $height, $pallet);

		// Apply effects
		if ( !$this->ignoreAllEffects ) {
			$square = $width * $height;
			$effects = $this->rand($square / 3000, $square / 2000);

			// set the maximum number of lines to draw in front of the text
			if ( $this->maxFrontLines != null && $this->maxFrontLines > 0 ) {
				$effects = min($this->maxFrontLines, $effects);
			}

			if ( $this->maxFrontLines !== 0 ) {
				for ( $e = 0; $e < $effects; $e++ ) {
					$this->drawLine($image, $width, $height, $pallet);
				}
			}
		}

		// Distort the image
		if ( $this->distortion && !$this->ignoreAllEffects ) {
			$image = $this->distort($image, $width, $height, $bg);
		}

		// Post effects
		if ( !$this->ignoreAllEffects ) {
			$this->postEffect($image);
		}

		// Add URL text
		if ( $this->addUrlText ) {
			$image = $this->addUrl($image, $width, $height, $font, $pallet);
		}


		$this->contents = $image;

		return $this;
	}

	public function colorDistort($image, $r, $g, $b, $amount)
	{
		$rgb = (new Rgb('rgb(' . $r . ',' . $g . ',' . $b . ')'))->spin($amount);
		$this->lightOrDark($rgb, $amount);
		//$rgb = $rgb->fadeOut(10);

		$col = \imagecolorallocate($image, $rgb->red(), $rgb->green(), $rgb->blue());

		return $col;
	}

	public function lightOrDark($rgb, $amount)
	{
		if ( $rgb->isLight() ) {
			$rgb->shade($amount / 4);
		} else {
			$rgb->tint($amount / 4);
		}
		return $rgb;
	}

	public function hex2rgb($hex)
	{
		$hex = str_replace('#', '', $hex);

		if ( strlen($hex) == 3 ) {
			$r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
			$g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
			$b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
		} else {
			$r = hexdec(substr($hex, 0, 2));
			$g = hexdec(substr($hex, 2, 2));
			$b = hexdec(substr($hex, 4, 2));
		}
		return array($r, $g, $b); // RETURN ARRAY INSTEAD OF STRING
	}

	public function getColorPallet($imageURL, $palletSize = [16, 8])
	{ // GET PALLET FROM IMAGE PLAY WITH INPUT PALLET SIZE
		// SIMPLE CHECK INPUT VALUES
		if ( !$imageURL ) return false;
		$img = imagecreatefromjpeg($imageURL);
		// SCALE DOWN IMAGE
		$imgSizes = getimagesize($imageURL);

		$resizedImg = imagecreatetruecolor($palletSize[0], $palletSize[1]);

		imagecopyresized($resizedImg, $img, 0, 0, 0, 0, $palletSize[0], $palletSize[1], $imgSizes[0], $imgSizes[1]);

		imagedestroy($img);

		//GET COLORS IN ARRAY
		$colors = [];

		for ( $i = 0; $i < $palletSize[1]; $i++ )
			for ( $j = 0; $j < $palletSize[0]; $j++ )
				$colors[] = dechex(imagecolorat($resizedImg, $j, $i));

		imagedestroy($resizedImg);
		//REMOVE DUPLICATES
		$colors = array_unique($colors);
		for ( $k = 0; $k < 30; $k++ ) {
			$myarray[] = array($k => $this->hex2rgb($colors[$k]));
		}

		return $myarray;

	}

	/**
	 * Distorts the image
	 */
	public function distort($image, $width, $height, $bg)
	{
		$contents = imagecreatetruecolor($width, $height);
		$X = $this->rand(0, $width);
		$Y = $this->rand(0, $height);
		$phase = $this->rand(0, 10);
		$scale = 1.1 + $this->rand(0, 10000) / 30000;
		for ( $x = 0; $x < $width; $x++ ) {
			for ( $y = 0; $y < $height; $y++ ) {
				$Vx = $x - $X;
				$Vy = $y - $Y;
				$Vn = sqrt($Vx * $Vx + $Vy * $Vy);

				if ( $Vn != 0 ) {
					$Vn2 = $Vn + 4 * sin($Vn / 30);
					$nX = $X + ($Vx * $Vn2 / $Vn);
					$nY = $Y + ($Vy * $Vn2 / $Vn);
				} else {
					$nX = $X;
					$nY = $Y;
				}
				$nY = $nY + $scale * sin($phase + $nX * 0.2);

				if ( $this->interpolation ) {
					$p = $this->interpolate(
						$nX - floor($nX),
						$nY - floor($nY),
						$this->getCol($image, floor($nX), floor($nY), $bg),
						$this->getCol($image, ceil($nX), floor($nY), $bg),
						$this->getCol($image, floor($nX), ceil($nY), $bg),
						$this->getCol($image, ceil($nX), ceil($nY), $bg)
					);
				} else {
					$p = $this->getCol($image, round($nX), round($nY), $bg);
				}

				if ( $p == 0 ) {
					$p = $bg;
				}

				imagesetpixel($contents, $x, $y, $p);
			}
		}

		return $contents;
	}

	/**
	 * Saves the Captcha to a jpeg file
	 */
	public function save($filename, $quality = 90)
	{
		imagejpeg($this->contents, $filename, $quality);
	}

	/**
	 * Gets the image GD
	 */
	public function getGd()
	{
		return $this->contents;
	}

	/**
	 * Gets the image contents
	 */
	public function get($quality = 90)
	{
		ob_start();
		$this->output($quality);

		return ob_get_clean();
	}

	/**
	 * Gets the HTML inline base64
	 */
	public function inline($quality = 90)
	{
		return 'data:image/jpeg;base64,' . base64_encode($this->get($quality));
	}

	/**
	 * Outputs the image
	 */
	public function output($quality = 90)
	{
		imagejpeg($this->contents, null, $quality);
	}

	/**
	 * @return array
	 */
	public function getFingerprint()
	{
		return $this->fingerprint;
	}

	/**
	 * Returns a random number or the next number in the
	 * fingerprint
	 */
	protected function rand($min, $max)
	{
		if ( !is_array($this->fingerprint) ) {
			$this->fingerprint = array();
		}

		if ( $this->useFingerprint ) {
			$value = current($this->fingerprint);
			next($this->fingerprint);
		} else {
			$value = mt_rand($min, $max);
			$this->fingerprint[] = $value;
		}

		return $value;
	}

	/**
	 * @param $x
	 * @param $y
	 * @param $nw
	 * @param $ne
	 * @param $sw
	 * @param $se
	 *
	 * @return int
	 */
	protected function interpolate($x, $y, $nw, $ne, $sw, $se)
	{
		list($r0, $g0, $b0) = $this->getRGB($nw);
		list($r1, $g1, $b1) = $this->getRGB($ne);
		list($r2, $g2, $b2) = $this->getRGB($sw);
		list($r3, $g3, $b3) = $this->getRGB($se);

		$cx = 1.0 - $x;
		$cy = 1.0 - $y;

		$m0 = $cx * $r0 + $x * $r1;
		$m1 = $cx * $r2 + $x * $r3;
		$r = (int)($cy * $m0 + $y * $m1);

		$m0 = $cx * $g0 + $x * $g1;
		$m1 = $cx * $g2 + $x * $g3;
		$g = (int)($cy * $m0 + $y * $m1);

		$m0 = $cx * $b0 + $x * $b1;
		$m1 = $cx * $b2 + $x * $b3;
		$b = (int)($cy * $m0 + $y * $m1);

		return ($r << 16) | ($g << 8) | $b;
	}

	/**
	 * @param $image
	 * @param $x
	 * @param $y
	 *
	 * @return int
	 */
	protected function getCol($image, $x, $y, $background = null)
	{
		$L = imagesx($image);
		$H = imagesy($image);
		if ( $x < 0 || $x >= $L || $y < 0 || $y >= $H ) {
			return $background;
		}

		return imagecolorat($image, $x, $y);
	}

	/**
	 * @param $col
	 *
	 * @return array
	 */
	protected function getRGB($col)
	{
		return array(
			(int)($col >> 16) & 0xff,
			(int)($col >> 8) & 0xff,
			(int)($col) & 0xff,
		);
	}


	/**
	 * Validate the background image path. Return the image type if valid
	 *
	 * @param string $backgroundImage
	 *
	 * @return string
	 * @throws Exception
	 */
	protected
	function validateBackgroundImage($backgroundImage)
	{
		// check if file exists
		if ( !file_exists($backgroundImage) ) {
			$backgroundImageExploded = explode('/', $backgroundImage);
			$imageFileName = count($backgroundImageExploded) > 1 ? $backgroundImageExploded[count($backgroundImageExploded) - 1] : $backgroundImage;

			throw new Exception('Invalid background image: ' . $imageFileName);
		}

		// check image type
		$finfo = finfo_open(FILEINFO_MIME_TYPE); // return mime type ala mimetype extension
		$imageType = finfo_file($finfo, $backgroundImage);
		finfo_close($finfo);

		if ( !in_array($imageType, $this->allowedBackgroundImageTypes) ) {
			throw new Exception('Invalid background image type! Allowed types are: ' . join(', ', $this->allowedBackgroundImageTypes));
		}

		return $imageType;
	}

	/**
	 * Create background image from type
	 *
	 * @param string $backgroundImage
	 * @param string $imageType
	 *
	 * @return resource
	 * @throws Exception
	 */
	protected
	function createBackgroundImageFromType($backgroundImage, $imageType)
	{
		switch ( $imageType ) {
			case 'image/jpeg':
				$image = imagecreatefromjpeg($backgroundImage);
				break;
			case 'image/png':
				$image = imagecreatefrompng($backgroundImage);
				break;
			case 'image/gif':
				$image = imagecreatefromgif($backgroundImage);
				break;

			default:
				throw new Exception('Not supported file type for background image!');
				break;
		}

		return $image;
	}
}
