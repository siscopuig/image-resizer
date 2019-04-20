<?php

class ResizeImage
{
    public $input;
    public $output;
    public $width;
    public $height;
    public $ext;
    public $img;
    public $base_img;

    function __construct(array $img)
    {
        $this->input = $img['input'];
        $this->output = $img['output'];
        $this->width = $img['width'];
        $this->height = $img['height'];
        $this->ext = $img['ext'];
    }

    function resizer()
    {

        list($w, $h) = getimagesize($this->input);
        $scale_ratio = $w / $h;
        if ($w < $this->width) {
            $this->width = $w;
        }
        if ($h < $this->height) {
            $this->height = $h;
        }
        if (($this->width / $this->height) > $scale_ratio) {
            $this->width = $this->height * $scale_ratio;
        } else {
            $this->height = $this->width / $scale_ratio;
        }

        if ($this->ext == 'jpg') {
            $this->img = imagecreatefromjpeg($this->input);
        } elseif ($this->ext == 'png') {
            $this->img = imagecreatefrompng($this->input);
        } elseif ($this->ext == 'gif') {
            $this->img = imagecreatefromgif($this->input);
        }

        $this->base_img = imagecreatetruecolor($this->width, $this->height);
        imagecopyresampled($this->base_img, $this->img, 0, 0, 0, 0,
            $this->width, $this->height, $w, $h);
        imagejpeg($this->base_img, $this->output, 100);
    }
}

class Options
{

    public $args;
    public $inpath;
    public $output;
    public $width;
    public $height;
    public $is_file;
    public $files = [];
    public $batch = [];
    public $errors = [];
    public $ext_list = ['jpg', 'png', 'gif'];


    function __construct()
    {
        $shortopts = '';       # Initialise
        $shortopts .= "p:";     # Required value
        $shortopts .= "s:";     # Required value
        $shortopts .= "o:";     # Optional value
        $longopts = ['path:', 'size:', 'output:'];
        $this->args = getopt($shortopts, $longopts);
    }

    function processOptions()
    {
        if (count($this->args) !== 3) {
            throw new Exception("Arguments passed are wrong or incomplete!");
        }

        foreach ($this->args as $key => $arg) {
            if ($key === 'p' || $key === 'path') {
                if (is_readable($arg)) {
                    $this->inpath = realpath($arg);
                } else {throw new Exception("Path: $arg not readable, check permissions!");}
                if (is_file($arg))
                    $this->is_file = true;
                if (is_dir($arg))
                    $this->getFilesFromDir();
            } elseif ($key === 's' || $key === 'size') {
                if (strpos($arg, 'x') !== false) {
                    list($width, $height) = explode('x', $arg);
                    if (is_numeric($width) AND is_numeric($height)) {
                        $this->width = intval($width);
                        $this->height = intval($height);
                    } else {throw new Exception("Size: $width or $height value is not numeric");}
                } else {throw new Exception("Size $arg might not have character: x");}
            } elseif ($key === 'o' || $key === 'output') {
                if (is_writable($arg)) {
                    if (is_dir($arg)) {
                        $this->output = realpath($arg);
                    } else {throw new Exception('Output path argument is empty or missing');}
                } else {throw new Exception("Output path: $arg not writable, check directory permissions!");}
            }
        }
    }

    function processFiles()
    {
        # On here can be this 2 cases:
        #   - '/home/sisco/Sites/image-resizer/images/img_1.jpg' -> file
        #   - '/home/sisco/Sites/image-resizer/images'

        if ($this->is_file AND $this->processExtension($this->inpath)) {
            $path = $this->inpath;
            $output = "$this->output/$filename";
            $this->batch[] = $this->getFile($path, $output);
        }

        if ($this->files) {
            foreach ($this->files as $filename) {
                if (!$this->processExtension($filename)) {
                    print "Warning: file $filename extension not accepted";
                    continue;
                }
                $path = "$this->inpath/$filename";
                $output = "$this->output/$filename";
                $this->batch[] = $this->getFile($path, $output);
            }
        }

        foreach ($this->batch as $file) {
            $r = new ResizeImage($file);
            $r->resizer();
        }
    }

    function processExtension($file)
    {
        $exploded = explode('.', $file);
        $this->ext = array_pop($exploded);
        if (!in_array($this->ext, $this->ext_list)) {
            throw new Exception("File: $filename with extension $ext not accepted!");
            return false;
        }
        return true;
    }

    function getFilesFromDir()
    {
        if ($h = opendir($this->inpath)) {
            while (($file = readdir($h)) !== false) {
                if ($file !== '..' AND $file !== '.' AND $file !== '.DS_Store') {
                    $this->files[] .= $file;
                }
            }
            closedir($h);
        }
    }

    function getFile($path, $output)
    {
        return [
            'input'  => $path,
            'output' => $output,
            'width'  => $this->width,
            'height' => $this->height,
            'ext'    => $this->ext
        ];
    }

    function addErrorMsg($error)
    {
        $this->errors[] .= $error;
    }
}

try {
    $re = new Options();
    $re->processOptions();
    $re->processFiles();
} catch (Exception $e) {
    print "Exiting...".  "\n";
    exit("Exception: " . $e->getMessage() . "\n");
}