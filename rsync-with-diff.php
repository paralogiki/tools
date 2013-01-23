#!/usr/bin/php
<?
$colorize=true;
$rsync='/usr/bin/rsync';
$diff='/usr/bin/diff';
$wc='/usr/bin/wc';
$cut='/usr/bin/cut';
$grep='/bin/grep';
$exclude_file='/tmp/'.time().'-exclude';

if (!isset($argv[1])||!isset($argv[2])) {
  print "Usage: {$_SERVER['PHP_SELF']} sourcedir targetdir\n";
  die;
}

if (!is_dir($argv[1])) die("sourcedir is not a directory\n");
if (!is_dir($argv[2])) die("sourcedir is not a directory\n");
if ($argv[1]==$argv[2]) die("sourcedir is the same as targetdir\n");
$sourcedir=rtrim($argv[1], '/');
$targetdir=rtrim($argv[2], '/');
if ($sourcedir==''||$targetdir=='') die("sourcedir and targetdir cannot be /");
if ($sourcedir==$targetdir) die("sourcedir is the same as targetdir\n");

# get possible update list
echo "Gathering rsync list from "._color($sourcedir, 'green')."->"._color($targetdir, 'yellow')."\n";
$cmd="$rsync -an --list-only $sourcedir/ $targetdir/ | $cut -c 44- | $grep -v '^\.$'";
$list=array();
exec($cmd,$list);
$dir_count=0;
$file_count=0;
$diffsables=array();
$nondiffables=array();
if (count($list)>0) {
  print "Listing files\n";
  foreach($list as $file) {
    print "\t$file";
    if (is_dir($sourcedir.'/'.$file)) {
      $dir_count++;
      print " "._color('[dir]', 'yellow');
    } else {
      $file_count++;
      $ext='';
      if (substr_count($file, '.')>0) {
        $ext=substr($file,strrpos($file, '.')+1);
        if (diffable($ext)) {
          $diffsables[]=$file;
          print " "._color('[diffable]', 'green');
        } else {
          $nondiffables[]=$file;
          print " "._color('[nondiff]', 'blue');
        }
      }   
    }   
    print "\n";
  }
}

print "Found $file_count files, $dir_count directories, ".count($diffsables)." diffables\n";

if (count($diffsables)>0) {
  print "Offering diff files\n";

  foreach($diffsables as $file) {
    if (file_exists($targetdir.'/'.$file)) {
      $diff_count=exec("$diff $sourcedir/$file $targetdir/$file | $wc -l");
      if ($diff_count=="0") {
        print "$sourcedir/$file matches $targetdir/$file\n";
      } else {
        print _color("$sourcedir/$file", 'green').' differs '._color("$targetdir/$file", 'yellow')."\n";
        passthru("$diff $sourcedir/$file $targetdir/$file");
        print "Accept $sourcedir/$file? ("._color("N", 'red')."=No/[^N]=Yes): ";
        $input = trim(fgets(STDIN));
        if ($input=='n'||$input=='N') {
          addexclude($file);
        }
      }
    } else {
      print "$file does not exist in $targetdir/\n";
    }
  }
}
### TODO combine diffables/non-diffables
if (count($nondiffsables)>0) {
  print "Offering non-diff files\n";

  foreach($nondiffsables as $file) {
    if (file_exists($targetdir.'/'.$file)) {
      $diff_count=exec("$diff --speed-large-files $sourcedir/$file $targetdir/$file | $wc -l");
      if ($diff_count=="0") {
        print "$sourcedir/$file matches $targetdir/$file\n";
      } else {
        print "$sourcedir/$file differs $targetdir/$file\n";
        print "Accept $sourcedir/$file? (N=No/[^N]=Yes): ";
        $input = trim(fgets(STDIN));
        if ($input=='n'||$input=='N') {
          addexclude($file);
        }
      }
    } else {
      print "$file does not exist in $targetdir/\n";
    }
  }
}

print "Sync files\n";

$cmd="$rsync -av";
if (file_exists($exclude_file)&&filesize($exclude_file)>0) $cmd.=" --exclude-from=$exclude_file";
$cmd.=" $sourcedir/ $targetdir/";
passthru($cmd);

if (file_exists($exclude_file)) unlink($exclude_file);

#functions
function diffable($ext) {
  # figure out a better way to do this
  $a=array('php'=>1, 'html'=>1, 'txt'=>1);
  if (isset($a[$ext])) return true;
  return false;
}
function addexclude($file) {
  global $exclude_file;
  file_put_contents($exclude_file, $file, FILE_APPEND);
}
function _color($text, $color='') {
  global $colorize;

  $return='';
  if (!$colorize) return $return;

  switch($color) {
    case 'red': $color='1;31'; break;
    case 'green': $color='1;32'; break;
    case 'yellow': $color='1;33'; break;
    case 'blue': $color='1;34'; break;
    default: $color='';
  }

  if ($color<>'') $return.="\033[0;{$color}m";
  $return.=$text;
  if ($color<>'') $return.="\033[0m";

  return $return;
}
?>
