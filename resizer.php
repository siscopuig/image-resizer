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

    public $options;
    public $inpath;
    public $output;
    public $width;
    public $height;
    public $is_file;
    public $files = [];
    public $batch = [];
    public $ext_list = ['jpg', 'png', 'gif'];

    function __construct()
    {
        $shortopts = '';       # Initialise
        $shortopts .= "p:";     # Required value
        $shortopts .= "s:";     # Required value
        $shortopts .= "o:";     # Optional value
        $longopts = ['path:', 'size:', 'output:'];
        $this->options = getopt($shortopts, $longopts);
        print_r($this->options);
    }

    function processOptions()
    {
        foreach ($this->options as $key => $value) {

            echo "Key: $key, Value: $value \n";

            if ($key == 'p' OR $key == 'path' AND !empty($value)) {
                if (is_readable($value))
                    $this->inpath = realpath($value);
                else throw new Exception("Path: $value not readable, check permissions!");
                if (is_file($value))
                    $this->is_file = true;
                if (is_dir($value))
                    $this->getFilesFromDir();
            } else {throw new Exception("Path argument -p or --path is missing or empty");}

            if (($key == 's' OR $key == 'size' AND !empty($value))) {
                if (strpos($value, 'x') !== false) {
                    list($width, $height) = explode('x', $value);
                    if (is_numeric($width) AND is_numeric($height)) {
                        $this->width = intval($width);
                        $this->height = intval($height);
                    } else {throw new Exception("Size: $width or $height value is not numeric");}
                } else {throw new Exception("Size $value might not have character: x");}
            } else {throw new Exception("Size argument -s or --size is missing or empty");}

            if ($key == 'o' OR $key == 'output' AND !empty($value)) {
                if (is_writable($value)) {
                    if (is_dir($value)) {
                        $this->output = realpath($value);
                    } else {throw new Exception('Output path argument is empty or missing');}
                } else {throw new Exception("Output path: $value not writable, check directory permissions!");}
            } else {throw new Exception("Output path: $value is not a directory!");}
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
                if ($file !== '..' AND $file !== '.' AND $file
                    !== '.DS_Store') {
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
}

try {
    $re = new Options();
    $re->processOptions();
    $re->processFiles();
} catch (Exception $e) {
    print "Exiting...".  "\n";
    exit("Exception: " . $e->getMessage() . "\n");
}