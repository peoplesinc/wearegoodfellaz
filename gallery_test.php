<?php
$event = $_GET["event"];

 class Event {
        // only data member
        var $name;
		var $path;

        // constructor
        function Event($name = "", $path="") {
            $this->name = $name;
			$this->path = $path;
        }

        // getter/accessor
        function getName() {
            return $this->name;
        }

		function getPath(){
			return $this->path;
		}
    }

    // construct events
	$little_black_dress = new Event('Little Black Dress | 1/21 @ Select Lounge','images/event/little_black_dress/');
 	$xii = new Event('XII | 1/6 @ Select Lounge','images/event/xii/');
	$naughty_n_nice = new Event('Naughty and Nice | 12/17 @ Select Lounge','images/event/naughty_n_nice/');
    $midnight = new Event('Midnight | 12/2 @ Select Lounge','images/event/midnight/');
    $milestone = new Event('Milestone | 11/4 @ Select Lounge','images/event/milestone/');
    $morgan = new Event('Morgan Homecoming | 10/8 @ Select Lounge','images/event/morgan_hc/');
    $venus_vs_mars = new Event('Venus Vs Mars | 8/20 @ Select Lounge','images/event/venus_vs_mars/');
    $my_first = new Event('My First | 7/1 @ Select Lounge','images/event/my_first/');
    $summer_salute = new Event('Summer Salute | 9/2 @ Select Lounge','images/event/summer_salute/');	
    $pastel_paradise = new Event('Pastel Paradise | 8/5 @ Select Lounge','images/event/pastel_paradise/');			
	
	$events['lbd'] =  $little_black_dress;
	$events['xii'] =  $xii;
	$events['naughty_n_nice'] =  $naughty_n_nice;
	$events['midnight'] =  $midnight;
	$events['milestone'] =  $milestone;
	$events['morgan_hc'] =  $morgan;
	$events['venus_vs_mars'] =  $venus_vs_mars;
	$events['my_first'] =  $my_first;
	$events['summer_salute'] =  $summer_salute;
	$events['pastel_paradise'] =  $pastel_paradise;



$directory = $events[$event]->getPath();

//Dynamically generate a list of iamges
if ($handle = opendir($directory."/full")) {
    /* This is the correct way to loop over the directory. */
    while (false !== ($file = readdir($handle))) {
		 if ($file != "." && $file != "..") { 
		 ?>
		 <a href='<?php print $directory."full/".$file; ?>' ><img src='<?php print  $directory."thumbs/".$file; ?>' alt="Right click the image to save. Click the X or hit escape to exit full screen." title="<?php print $events[$event]->getName() ?>"/></a>
		<?php
		 }
    }


    closedir($handle);
}
?>