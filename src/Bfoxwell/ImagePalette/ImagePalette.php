<?php
/**
 * This file is part of the ImagePalette package.
 *
 * (c) Brian Foxwell <brian@foxwell.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bfoxwell\ImagePalette;
require_once('ColorUtil.php');

use Bfoxwell\ImagePalette\Exception\UnsupportedFileTypeException;
use Imagick;

/**
 * Class ImagePalette
 *
 * Gets the prominent colors in a given image. To get common color matching, all pixels are matched
 * against a white-listed color palette.
 *
 * @package bfoxwell\ImagePalette
 */
class ImagePalette implements \IteratorAggregate
{
    /**
     * File or Url
     * @var string
     */
    protected $file;
    
    /**
     * Loaded Image
     * @var object
     */
    protected $loadedImage;
    
    /**
     * Process every Nth pixel
     * @var int
     */
    protected $precision;

    /**
     * Width of image
     * @var integer
     */
    protected $width;

    /**
     * Height of image
     * @var integer
     */
    protected $height;

    /**
     * Number of colors to return
     * @var integer
     */
    protected $paletteLength;

    /**
     * Colors Whitelist
     * @var array
     */
    protected $whiteList = array(
        /**
         * all combinations of 0,3,6,9,c,f
         * total of 6*6*6 = 216 colors
         */
        0x000, 0x030, 0x060, 0x090, 0x0c0, 0x0f0,
        0x003, 0x033, 0x063, 0x093, 0x0c3, 0x0f3,
        0x006, 0x036, 0x066, 0x096, 0x0c6, 0x0f6,
        0x009, 0x039, 0x069, 0x099, 0x0c9, 0x0f9,
        0x00c, 0x03c, 0x06c, 0x09c, 0x0cc, 0x0fc,
        0x00f, 0x03f, 0x06f, 0x09f, 0x0cf, 0x0ff,
        
        0x300, 0x330, 0x360, 0x390, 0x3c0, 0x3f0,
        0x303, 0x333, 0x363, 0x393, 0x3c3, 0x3f3,
        0x306, 0x336, 0x366, 0x396, 0x3c6, 0x3f6,
        0x309, 0x339, 0x369, 0x399, 0x3c9, 0x3f9,
        0x30c, 0x33c, 0x36c, 0x39c, 0x3cc, 0x3fc,
        0x30f, 0x33f, 0x36f, 0x39f, 0x3cf, 0x3ff,
        
        0x600, 0x630, 0x660, 0x690, 0x6c0, 0x6f0,
        0x603, 0x633, 0x663, 0x693, 0x6c3, 0x6f3,
        0x606, 0x636, 0x666, 0x696, 0x6c6, 0x6f6,
        0x609, 0x639, 0x669, 0x699, 0x6c9, 0x6f9,
        0x60c, 0x63c, 0x66c, 0x69c, 0x6cc, 0x6fc,
        0x60f, 0x63f, 0x66f, 0x69f, 0x6cf, 0x6ff,
        
        0x900, 0x930, 0x960, 0x990, 0x9c0, 0x9f0,
        0x903, 0x933, 0x963, 0x993, 0x9c3, 0x9f3,
        0x906, 0x936, 0x966, 0x996, 0x9c6, 0x9f6,
        0x909, 0x939, 0x969, 0x999, 0x9c9, 0x9f9,
        0x90c, 0x93c, 0x96c, 0x99c, 0x9cc, 0x9fc,
        0x90f, 0x93f, 0x96f, 0x99f, 0x9cf, 0x9ff,
        
        0xc00, 0xc30, 0xc60, 0xc90, 0xcc0, 0xcf0,
        0xc03, 0xc33, 0xc63, 0xc93, 0xcc3, 0xcf3,
        0xc06, 0xc36, 0xc66, 0xc96, 0xcc6, 0xcf6,
        0xc09, 0xc39, 0xc69, 0xc99, 0xcc9, 0xcf9,
        0xc0c, 0xc3c, 0xc6c, 0xc9c, 0xccc, 0xcfc,
        0xc0f, 0xc3f, 0xc6f, 0xc9f, 0xccf, 0xcff,
        
        0xf00, 0xf30, 0xf60, 0xf90, 0xfc0, 0xff0,
        0xf03, 0xf33, 0xf63, 0xf93, 0xfc3, 0xff3,
        0xf06, 0xf36, 0xf66, 0xf96, 0xfc6, 0xff6,
        0xf09, 0xf39, 0xf69, 0xf99, 0xfc9, 0xff9,
        0xf0c, 0xf3c, 0xf6c, 0xf9c, 0xfcc, 0xffc,
        0xf0f, 0xf3f, 0xf6f, 0xf9f, 0xfcf, 0xfff,
        
        // additional colors (old)
        0xea4c88, 0x77cc33, 0xE7D8B1,
        0xFDADC7, 0x424153, 0xABBCDA, 0xF5DD01
    );
    
    /**
     * Colors hits, keys are colors from whiteList
     * @var array
     */
    protected $whiteListHits;
    
    /**
     * Library used
     * Supported are GD and Imagick
     * @var string
     */
    protected $lib;

    /**
     * Constructor
     * @param string $file
     * @param int $precision
     * @param int $paletteLength
     */
    public function __construct($file, $precision = 10, $paletteLength = 5, $overrideLib = null)
    {
        $this->file = $file;
        $this->precision = $precision;
        $this->paletteLength = $paletteLength;
        
        // use provided libname or auto-detect
        $this->lib = $overrideLib ? $overrideLib : $this->detectLib();
        
        $this->whiteList = array_map('Bfoxwell\ImagePalette\ColorUtil::expand', $this->whiteList);
        
        // creates an array with our colors as keys
        $this->whiteListHits = array_fill_keys($this->whiteList, 0);
        
        // go!
        $this->process($this->lib);
        
        // sort color-keyed array by hits
        arsort($this->whiteListHits);
        
        // sort whiteList accordingly
        $this->whiteList = array_keys($this->whiteListHits);
    }


    /**
     * Autodetect and pick a graphical library to use for processing.
     * @param $lib
     * @return string
     */
    protected function detectLib()
    {
        try {
            if (extension_loaded('gd') && function_exists('gd_info')) {
                return 'GD';
                
            } else if(extension_loaded('imagick')) {
                return 'Imagick';
                
            } else if(extension_loaded('gmagick')) {
                return 'Gmagick';
                
            }

            throw new \Exception(
                "Try installing one of the following graphic libraries php5-gd, php5-imagick, php5-gmagick.
            ");

        } catch(\Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }
    
    /**
     * Select a graphical library and start generating the Image Palette
     * @param string $lib
     * @throws \Exception
     */
    protected function process($lib)
    {
        try {
            
            $this->{'setWorkingImage' . $lib} ();
            $this->{'setImagesize' . $lib} ();
            
            $this->readPixels();
            
        } catch(\Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }
    
    /**
     * Load and set the working image.
     * @param $image
     * @param string $image
     */
    protected function setWorkingImageGD()
    {
        $extension = pathinfo($this->file, PATHINFO_EXTENSION);
        try {

            switch (strtolower($extension)) {
                case "png":
                    $this->loadedImage = imagecreatefrompng($this->file);
                    break;
                    
                case "jpg":
                case "jpeg":
                    $this->loadedImage = imagecreatefromjpeg($this->file);
                    break;
                    
                case "gif":
                    $this->loadedImage = imagecreatefromgif($this->file);
                    break;
                    
                case "bmp":
                    $this->loadedImage = imagecreatefrombmp($this->file);
                    break;
                    
                default:
                    throw new UnsupportedFileTypeException("The file type .$extension is not supported.");
            }

        } catch (UnsupportedFileTypeException $e) {
            echo $e->getMessage() . "\n";
        }
    }
    
    /**
     * Load and set working image
     *
     * @todo needs work
     * @param $image
     * @param string $image
     * @return mixed
     */
    protected function setWorkingImageImagick()
    {

        $file = file_get_contents($this->file);
        $temp = tempnam("/tmp", uniqid("ImagePalette_",true));
        file_put_contents($temp, $file);

        $this->loadedImage = new Imagick($temp);
    }
    
    /**
     * Load and set working image
     *
     * @todo needs work
     * @param $image
     * @param string $image
     * @return mixed
     */
    protected function setWorkingImageGmagick()
    {
        throw new \Exception("Gmagick not supported");
        return null;
    }
    
    /**
     * Get and set size of the image using GD.
     */
    protected function setImageSizeGD()
    {
        list($this->width, $this->height) = getimagesize($this->file);
    }
    
    /**
     * Get and set size of image using ImageMagick.
     */
    protected function setImageSizeImagick()
    {
        $d = $this->loadedImage->getImageGeometry();
        $this->width  = $d['width'];
        $this->height = $d['height'];
    }
    
    /**
     * For each interesting pixel, add its closest color to the loaded colors array
     * 
     * @return mixed
     */
    protected function readPixels()
    {
        // Row
        for ($x = 0; $x < $this->width; $x += $this->precision) {
            // Column
            for ($y = 0; $y < $this->height; $y += $this->precision) {
                
                list($rgba, $r, $g, $b) = $this->getPixelColor($x, $y);
                
                // transparent pixels don't really have a color
                if (ColorUtil::isTransparent($rgba))
                    continue 1;
                
                $this->whiteListHits[ $this->getClosestColor($r, $g, $b) ]++;
            }
        }
    }
    
    /**
     * Returns an array describing the color at x,y
     * At index 0 is the color as a whole int (may include alpha)
     * At index 1 is the color's red value
     * At index 2 is the color's green value
     * At index 3 is the color's blue value
     * 
     * @param  int $x
     * @param  int $y
     * @return array
     */
    protected function getPixelColor($x, $y)
    {
        return $this->{'getPixelColor' . $this->lib} ($x, $y);
    }
    
    /**
     * Using  to retrive color information about a specified pixel
     * 
     * @see  getPixelColor()
     * @param  int $x
     * @param  int $y
     * @return array
     */
    protected function getPixelColorGD($x, $y)
    {
        $color = imagecolorat($this->loadedImage, $x, $y);
        $rgb = imagecolorsforindex($this->loadedImage, $color);
        
        return array(
            $color,
            $rgb['red'],
            $rgb['green'],
            $rgb['blue']
        );
    }
    
    /**
     * Using  to retrive color information about a specified pixel
     * 
     * @see  getPixelColor()
     * @param  int $x
     * @param  int $y
     * @return array
     */
    protected function getPixelColorImagick($x, $y)
    {
        $rgb = $this->loadedImage->getImagePixelColor($x,$y)->getColor();
        
        return array(
            $this->rgbToColor($rgb['r'], $rgb['g'], $rgb['b']),
            $rgb['r'],
            $rgb['g'],
            $rgb['b']
        );
    }

    protected function getPixelColorGmagick($x, $y)
    {
        throw new \Exception("Gmagick not supported");
        return;
    }
    
    /**
     * Get closest matching color
     * 
     * @param $r
     * @param $g
     * @param $b
     * @return mixed
     */
    protected function getClosestColor($r, $g, $b)
    {
        
        $bestKey = 0;
        $bestDiff = PHP_INT_MAX;
        $whiteListLength = count($this->whiteList);
        
        for ( $i = 0 ; $i < $whiteListLength ; $i++ ) {
            
            // get whitelisted values
            list($wlr, $wlg, $wlb) = ColorUtil::intToRgb($this->whiteList[$i]);
            
            // calculate difference (don't sqrt)
            $diff = pow($r - $wlr, 2)
                  + pow($g - $wlg, 2)
                  + pow($b - $wlb, 2);
            
            // see if we got a new best
            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $bestKey = $i;
            }
        }
        
        return $this->whiteList[$bestKey];
    }
    
    /**
     * Returns the color palette as an array containing
     * an integer for each color
     * 
     * @param  int $paletteLength
     * @return int
     */
    public function getIntColors($paletteLength = null)
    {
        // allow custom length calls
        if (!is_numeric($paletteLength)) {
            $paletteLength = $this->paletteLength;
        }
        
        // take the best hits
        return array_slice($this->whiteList, 0, $paletteLength, true);
    }
    
    /**
     * Returns the color palette as an array containing
     * each color as an array of red, green and blue values
     * 
     * @param  int $paletteLength
     * @return array
     */
    public function getRgbColors($paletteLength = null)
    {
        return array_map(
            'Bfoxwell\ImagePalette\ColorUtil::intToRgb',
            $this->getIntColors($paletteLength)
        );
    }
    
    /**
     * Returns the color palette as an array containing
     * hexadecimal string representations, like '#abcdef'
     * 
     * @param  int $paletteLength
     * @return array
     */
    public function getHexStringColors($paletteLength = null)
    {
        return array_map(
            'Bfoxwell\ImagePalette\ColorUtil::intToHexString',
            $this->getIntColors($paletteLength)
        );
    }
    
    /**
     * Returns the color palette as an array containing
     * decimal string representations, like 'rgb(123,0,20)'
     * 
     * @param  int $paletteLength
     * @return array
     */
    public function getRgbStringColors($paletteLength = null)
    {
        return array_map(
            'Bfoxwell\ImagePalette\ColorUtil::rgbToString',
            $this->getRgbColors($paletteLength)
        );
    }
    
    /**
     * Alias for getHexStringColors for legacy support.
     * 
     * @deprecated  use one of the newer getters
     * @param  int $paletteLength
     * @return array
     */
    public function getColors($paletteLength = null)
    {
        return $this->getHexStringColors($paletteLength);
    }
    
    /**
     * Returns a json encoded version of the palette
     * 
     * @return string
     */
    public function __toString()
    {
        return json_encode($this->getHexStringColors());
    }
    
    /**
     * Convenient getter access as properties
     * 
     * @return  mixed
     */
    public function __get($name)
    {
        $method = 'get' . ucfirst($name);
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        throw new \Exception("Method $method does not exist");
    }
    
    /**
     * Returns the palette for implementation of the IteratorAggregate interface
     * Used in foreach loops
     * 
     * @see  getColors()
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->getColors());
    }
}
