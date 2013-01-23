#!/usr/bin/php
<?
###########################################################
##
## Usage: ./script sourcedir targetdir
##
## Reqs:  command-line PHP, rsync, diff, wc, cut, grep
##
## Local-only rsync script that will first tell you files
## and if the file is a known 'text' type it will show
## the diff of the source and target, for text/binary files
## it still offers to add the file to an exclude list
##
## aurthor keith /at/ paralogiktech /dot/ com
## 
## use it as you please, no guarentees to it's safety
###########################################################

# set to false if you want no color in your ouput
$colorize=true;

# set to true to only do dry run
$dryrun=true;

# TODO test for existance and executable?
$rsync='/usr/bin/rsync';
$diff='/usr/bin/diff';
$wc='/usr/bin/wc';
$cut='/usr/bin/cut';
$grep='/bin/grep';

# is this a unique enough tmp name for an exclude file
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
if ($sourcedir==''||$targetdir=='') die("sourcedir and targetdir cannot be\n");
if ($sourcedir==$targetdir) die("sourcedir is the same as targetdir\n");

# run rsync with --list-only and cut the results into an array
# files have 3 labels
#   [dir] means it is a directory
#   [diffable] means we can safely show you a diff
#   [nondiff] means the file is probably binary
echo "Gathering rsync list from "._color($sourcedir, 'green')."->"._color($targetdir, 'yellow')."\n";
$cmd="$rsync -an --list-only $sourcedir/ $targetdir/ | $cut -c 44- | $grep -v '^\.$'";
$list=array();
exec($cmd,$list);
if (count($list)>0) {
  $files=array();
  $dir_count=0;
  $file_count=0;
  $diffables=0;
  _break();
  print "Listing files\n";
  foreach($list as $file) {
    print "\t$file";
    if (is_dir($sourcedir.'/'.$file)) {
      $dir_count++;
      $files[$file]='dir';
      print " "._color('[dir]', 'yellow');
    } else {
      $file_count++;
      $ext='';
      if (substr_count($file, '.')>0) {
        $ext=substr($file,strrpos($file, '.')+1);
        if (diffable($ext)) {
          $files[$file]='diffable';
          $diffables++;
          print " "._color('[diffable]', 'green');
        } else {
          $files[$file]='nondiff';
          print " "._color('[nondiff]', 'blue');
        }
      }   
    }   
    print "\n";
  }
  _break();
} else {
  die("Nothing to do, bye\n");
}

# if file_count=0 then there's nothing by directories to rsync, no thanks
# walks the files and displays diffs for text files
# user will be prompted with Y/N answer to exclude any file that exists
#  in sourcedir
# TODO give choice to not copy source file when it doesn't exist on target
if ($file_count>0) {
  print "Found $file_count files, $dir_count directories, $diffables diffables\n";
  foreach($files as $file => $type) {
    if (file_exists($targetdir.'/'.$file)) {
      # speed-large-files should pass the file as different on first diff
      # we done't need to know ALL the diffs on a binary files
      $extra_diff_count='';
      if ($type=='nondiff') $extra_diff_count=' --speed-large-files';
      $diff_count=exec("$diff{$extra_diff_count} $sourcedir/$file $targetdir/$file | $wc -l");
      if ($diff_count=="0") {
        print "$sourcedir/$file matches $targetdir/$file\n";
      } else {
        print _color("$sourcedir/$file", 'green').' differs '._color("$targetdir/$file", 'yellow')."\n";
        if ($type=='diffable') {
          _break();
          passthru("$diff $sourcedir/$file $targetdir/$file");
          _break();
        }
        print "Exclude file $sourcedir/$file? ("._color("Y", 'green')."=Yes/[^Y]=No) (Default is NO): ";
        $input = trim(fgets(STDIN));
        if ($input=='y'||$input=='Y') file_put_contents($exclude_file, "$file\n", FILE_APPEND);
      }
    } else {
      print "$file does not exist in $targetdir/\n";
    }
  }
} else {
  die("Nothing to do, bye\n");
}

print "Running rsync\n";

# run the rsync command pass it through to terminal
$cmd="$rsync -av";
if ($dryrun) $cmd.='n';
if (file_exists($exclude_file)&&filesize($exclude_file)>0) $cmd.=" --exclude-from=$exclude_file";
$cmd.=" $sourcedir/ $targetdir/";
if ($dryrun) "rsync cmd=$cmd\n";
passthru($cmd);

if (file_exists($exclude_file)) unlink($exclude_file);

print "Done\n";

#functions

# Checks the extentsion and determines if diffable or not
# TODO figure out a better way to do this
# mime_types maybe or a is_bin function, need to check
function diffable($ext) {
  $a=array('php'=>1, 'html'=>1, 'txt'=>1);
  if (isset($a[$ext])) return true;
  return false;
}
# Adds bash colors to the output
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
# An 80-character break line for readability
function _break() {
  for($x=0;$x<=79;$x++) {
    print "-";
  }
  print "\n";
}
?>
