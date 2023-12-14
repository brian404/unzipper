<?php

define('VERSION', '1.0');

$timestart = microtime(TRUE);
$GLOBALS['status'] = array();

$unzipper = new Unzipper;
if (isset($_POST['dounzip'])) {
  $archive = isset($_POST['zipfile']) ? strip_tags($_POST['zipfile']) : '';
  $destination = isset($_POST['extpath']) ? strip_tags($_POST['extpath']) : '';
  $unzipper->prepareExtraction($archive, $destination);
}

if (isset($_POST['dozip'])) {
  $zippath = !empty($_POST['zippath']) ? strip_tags($_POST['zippath']) : '.';
  $zipfile = 'zipper-' . date("Y-m-d--H-i") . '.zip';
  Zipper::zipDir($zippath, $zipfile);
}

$timeend = microtime(TRUE);
$time = round($timeend - $timestart, 4);

class Unzipper {
  public $localdir = '.';
  public $zipfiles = array();

  public function __construct() {
    if ($dh = opendir($this->localdir)) {
      while (($file = readdir($dh)) !== FALSE) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'zip'
          || pathinfo($file, PATHINFO_EXTENSION) === 'gz'
          || pathinfo($file, PATHINFO_EXTENSION) === 'rar'
        ) {
          $this->zipfiles[] = $file;
        }
      }
      closedir($dh);

      if (!empty($this->zipfiles)) {
        $GLOBALS['status'] = array('info' => '.zip or .gz or .rar files found, ready for extraction');
      } else {
        $GLOBALS['status'] = array('info' => 'No .zip or .gz or rar files found. So only zipping functionality available.');
      }
    }
  }

  public function prepareExtraction($archive, $destination = '') {
    if (empty($destination)) {
      $extpath = $this->localdir;
    } else {
      $extpath = $this->localdir . '/' . $destination;
      if (!is_dir($extpath)) {
        mkdir($extpath);
      }
    }

    if (in_array($archive, $this->zipfiles)) {
      self::extract($archive, $extpath);
    }
  }

  public static function extract($archive, $destination) {
    $ext = pathinfo($archive, PATHINFO_EXTENSION);
    switch ($ext) {
      case 'zip':
        self::extractZipArchive($archive, $destination);
        break;
      case 'gz':
        self::extractGzipFile($archive, $destination);
        break;
      case 'rar':
        self::extractRarArchive($archive, $destination);
        break;
    }
  }

  public static function extractZipArchive($archive, $destination) {
    if (!class_exists('ZipArchive')) {
      $GLOBALS['status'] = array('error' => 'Error: Your PHP version does not support unzip functionality.');
      return;
    }

    $zip = new ZipArchive;

    if ($zip->open($archive) === TRUE) {
      if (is_writeable($destination . '/')) {
        $zip->extractTo($destination);
        $zip->close();
        $GLOBALS['status'] = array('success' => 'Files unzipped successfully');
      } else {
        $GLOBALS['status'] = array('error' => 'Error: Directory not writeable by webserver.');
      }
    } else {
      $GLOBALS['status'] = array('error' => 'Error: Cannot read .zip archive.');
    }
  }

  public static function extractGzipFile($archive, $destination) {
    if (!function_exists('gzopen')) {
      $GLOBALS['status'] = array('error' => 'Error: Your PHP has no zlib support enabled.');
      return;
    }

    $filename = pathinfo($archive, PATHINFO_FILENAME);
    $gzipped = gzopen($archive, "rb");
    $file = fopen($destination . '/' . $filename, "w");

    while ($string = gzread($gzipped, 4096)) {
      fwrite($file, $string, strlen($string));
    }
    gzclose($gzipped);
    fclose($file);

    if (file_exists($destination . '/' . $filename)) {
      $GLOBALS['status'] = array('success' => 'File unzipped successfully.');

      if (pathinfo($destination . '/' . $filename, PATHINFO_EXTENSION) == 'tar') {
        $phar = new PharData($destination . '/' . $filename);
        if ($phar->extractTo($destination)) {
          $GLOBALS['status'] = array('success' => 'Extracted tar.gz archive successfully.');
          unlink($destination . '/' . $filename);
        }
      }
    } else {
      $GLOBALS['status'] = array('error' => 'Error unzipping file.');
    }
  }

  public static function extractRarArchive($archive, $destination) {
    if (!class_exists('RarArchive')) {
      $GLOBALS['status'] = array('error' => 'Error: Your PHP version does not support .rar archive functionality. <a class="info" href="http://php.net/manual/en/rar.installation.php" target="_blank">How to install RarArchive</a>');
      return;
    }

    if ($rar = RarArchive::open($archive)) {
      if (is_writeable($destination . '/')) {
        $entries = $rar->getEntries();
        foreach ($entries as $entry) {
          $entry->extract($destination);
        }
        $rar->close();
        $GLOBALS['status'] = array('success' => 'Files extracted successfully.');
      } else {
        $GLOBALS['status'] = array('error' => 'Error: Directory not writeable by webserver.');
      }
    } else {
      $GLOBALS['status'] = array('error' => 'Error: Cannot read .rar archive.');
    }
  }
}

class Zipper {
  private static function folderToZip($folder, &$zipFile, $exclusiveLength) {
    $handle = opendir($folder);

    while (FALSE !== $f = readdir($handle)) {
      if ($f != '.' && $f != '..' && $f != basename(__FILE__)) {
        $filePath = "$folder/$f";
        $localPath = substr($filePath, $exclusiveLength);

        if (is_file($filePath)) {
          $zipFile->addFile($filePath, $localPath);
        } elseif (is_dir($filePath)) {
          $zipFile->addEmptyDir($localPath);
          self::folderToZip($filePath, $zipFile, $exclusiveLength);
        }
      }
    }
    closedir($handle);
  }

  public static function zipDir($sourcePath, $outZipPath) {
    $pathInfo = pathinfo($sourcePath);
    $parentPath = $pathInfo['dirname'];
    $dirName = $pathInfo['basename'];

    $z = new ZipArchive();
    $z->open($outZipPath, ZipArchive::CREATE);
    $z->addEmptyDir($dirName);
    if ($sourcePath == $dirName) {
      self::folderToZip($sourcePath, $z, 0);
    } else {
      self::folderToZip($sourcePath, $z, strlen("$parentPath/"));
    }
    $z->close();

    $GLOBALS['status'] = array('success' => 'Successfully created archive ' . $outZipPath);
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>File Unzipper + Zipper</title>
  <style>
    body {
      font-family: 'Arial', sans-serif;
      line-height: 1.6;
      background-color: #f8f9fa;
      margin: 0;
      padding: 0;
    }

    header {
      background-color: #343a40;
      color: white;
      text-align: center;
      padding: 20px;
      margin-bottom: 20px;
    }

    h1 {
      margin: 0;
    }

    fieldset {
      border: 1px solid #ced4da;
      border-radius: 5px;
      padding: 20px;
      margin: 20px auto;
      background-color: #fff;
      max-width: 600px;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }

    legend {
      font-size: 1.2rem;
      color: #495057;
      margin-bottom: 10px;
    }

    label {
      display: block;
      margin: 10px 0;
      font-size: 0.9rem;
      color: #495057;
    }

    select,
    input[type="text"] {
      width: 100%;
      padding: 8px;
      border: 1px solid #ced4da;
      border-radius: 5px;
      box-sizing: border-box;
      margin-bottom: 15px;
    }

    .info {
      font-size: 0.8rem;
      color: #6c757d;
      margin-top: 5px;
    }

    .submit {
      background-color: #007bff;
      color: white;
      font-size: 15px;
      padding: 10px 20px;
      border: 0;
      border-radius: 5px;
      cursor: pointer;
      transition: background-color 0.3s;
    }

    .submit:hover {
      background-color: #0056b3;
    }

    .status {
      margin: 0;
      margin-bottom: 20px;
      padding: 15px;
      font-size: 0.9rem;
      background: #d4edda;
      border: 1px solid #c3e6cb;
      border-radius: 5px;
      color: #155724;
      max-width: 600px;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }

    .status--ERROR {
      background-color: #f8d7da;
      color: #721c24;
      border-color: #f5c6cb;
    }

    .status--SUCCESS {
      background-color: #d4edda;
      color: #155724;
      border-color: #c3e6cb;
    }

    .small {
      font-size: 0.7rem;
      font-weight: normal;
      color: #6c757d;
    }

    .version {
      font-size: 0.8rem;
      text-align: center;
      color: #6c757d;
    }
  </style>
</head>
<body>
  <header>
    <h1>File Unzipper + Zipper</h1>
  </header>

  <div class="status status--<?php echo strtoupper(key($GLOBALS['status'])); ?>">
    Status: <?php echo reset($GLOBALS['status']); ?><br/>
    <span class="small">Processing Time: <?php echo $time; ?> seconds</span>
  </div>

  <form action="" method="POST">
    <fieldset>
      <legend>Archive Unzipper</legend>
      <label for="zipfile">Select .zip or .rar archive or .gz file you want to extract:</label>
      <select name="zipfile" size="1">
        <?php foreach ($unzipper->zipfiles as $zip) {
          echo "<option>$zip</option>";
        }
        ?>
      </select>
      <label for="extpath">Extraction path (optional):</label>
      <input type="text" name="extpath" />
      <p class="info">Enter extraction path without leading or trailing slashes (e.g. "mypath"). If left empty current directory will be used.</p>
      <input type="submit" name="dounzip" class="submit" value="Unzip Archive"/>
    </fieldset>

    <fieldset>
      <legend>Archive Zipper</legend>
      <label for="zippath">Path that should be zipped (optional):</label>
      <input type="text" name="zippath" />
      <p class="info">Enter path to be zipped without leading or trailing slashes (e.g. "zippath"). If left empty current directory will be used.</p>
      <input type="submit" name="dozip" class="submit" value="Zip Archive"/>
    </fieldset>
  </form>

  <p class="version">Unzipper version: <?php echo VERSION; ?></p>
</body>
</html>
