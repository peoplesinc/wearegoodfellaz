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
   $events['taaday'] =  new Event("TaaDay | 12/14 @ Select Lounge",'images/event/taaday/');
   $events['noir'] =  new Event("Noir | 12/7 @ Select Lounge",'images/event/noir/');
   $events['black_friday'] =  new Event("Black Friday | 11/23 @ Select Lounge",'images/event/black_friday/');
   $events['remy_crusade'] =  new Event("Remy CRUsade | 11/16 @ Select Lounge",'images/event/remy_crusade/');
   $events['cuffed'] =  new Event("Cuffed | 11/9 @ Select Lounge",'images/event/cuffed/');
   $events['golden_standard'] =  new Event("Golden Standard with Mehgan James | 11/2 @ Select Lounge",'images/event/golden_standard/');
   $events['gentleman_affair'] =  new Event("Gentleman's affair| Friday 10/26 @ Select Lounge",'images/event/gentleman_affair/');
   $events['rosa_acosta'] =  new Event("Friday Night Lights with Rosa Acosta | Friday 10/19 @ Select Lounge",'images/event/rosa_acosta/');
   $events['exhibitw'] =  new Event("Exhibit Wednesdays | Wednesdays @ Museum Lounge",'images/event/exhibit_wednesdays/');
	$events['fiesta'] =  new Event("Fiesta | 10/5 @ Select Lounge",'images/event/fiesta/');
	$events['royal_flush'] =  new Event("Royal Flush | 9/21 @ Select Lounge",'images/event/royal_flush/');
	$events['finesse'] =  new Event("Finesse | 9/14 @ Select Lounge",'images/event/finesse/');	
	$events['80s_vs_90s'] =  new Event("80s Babies vs 90s babies | 9/7 @ Select Lounge",'images/event/80s_vs_90s/');	
	$events['kings_and_queens'] =  new Event("Kings and Queens | 8/31 @ Select Lounge",'images/event/kings_and_queens/');   
   $events['the_professional'] =  new Event("Polished | The Professional| 8/24 @ Select Lounge",'images/event/the_professional/');
	$events['body_language'] =  new Event("Body Language| 8/17 @ Select Lounge",'images/event/body_language/');
	$events['luau_2'] =  new Event("Luau 2 | 8/3 @ Select Lounge",'images/event/luau_2/');   
   $events['tight_dresses'] =  new Event("Sexy Dresses &amp; Stilettos | 7/27 @ Select Lounge",'images/event/tight_dresses/');	
	$events['inked_up'] =  new Event("Inked Up | 7/20 @ Select Lounge",'images/event/inked_up/');	
	$events['carnival'] =  new Event("Euphoria | 7/13 @ Select Lounge",'images/event/carnival/');		
	$events['stars_and_stripes'] =  new Event("Stars &amp; Stripes | 7/6 @ Select Lounge",'images/event/stars_and_stripes/');	
	$events['fifth_element'] =  new Event("5th Element | 6/29 @ Select Lounge",'images/event/fifth_element/');	
	$events['spring_finale'] =  new Event("Spring Finale | 6/22 @ Select Lounge",'images/event/spring_finale/');
	$events['nicest_men'] =  new Event("The Nicest Men | 6/15 @ Select Lounge",'images/event/nicest_men/');
	$events['luau'] =  new Event("The Luau | 6/1 @ Select Lounge",'images/event/luau/');
	$events['higher_learning'] =  new Event("Torrey Smith's Preakness Party | 5/18 @ Select Lounge",'images/event/higher_learning/');
	$events['remy_launch'] =  new Event("C'est la Vie | 5/4 @ Select Lounge",'images/event/remy_launch/');
	$events['taurus_special'] =   new Event('Taurus Special | 4/20 @ Select Lounge','images/event/taurus_special/');
	$events['hardball'] =  new Event('Hardball | 4/6 @ Select Lounge','images/event/hardball/');
	$events['black_enterprise'] = new Event('Black Enterprise | 4/1 @ World Trade Center','images/event/black_enterprise/');
	$events['refresh'] =   new Event('Refresh | 3/16 @ Select Lounge','images/event/refresh/');
	$events['pardi_gras'] =  new Event('Pardi Gras | 3/2 @ Select Lounge','images/event/pardi_gras/');
	$events['coppin_hc'] = new Event('Coppin Homecoming | 2/18 @ Select Lounge','images/event/coppin_hc/');
	$events['royal'] =  new Event('Royal | 2/3 @ Select Lounge','images/event/royal/');
	$events['lbd'] =  new Event('Little Black Dress | 1/21 @ Select Lounge','images/event/little_black_dress/');
	$events['xii'] = new Event('XII | 1/6 @ Select Lounge','images/event/xii/');
	$events['naughty_n_nice'] =  new Event('Naughty and Nice | 12/17 @ Select Lounge','images/event/naughty_n_nice/');
	$events['midnight'] =  new Event('Midnight | 12/2 @ Select Lounge','images/event/midnight/');
	$events['milestone'] =  new Event('Milestone | 11/4 @ Select Lounge','images/event/milestone/');
	$events['morgan_hc'] = new Event('Morgan Homecoming | 10/8 @ Select Lounge','images/event/morgan_hc/');
	$events['venus_vs_mars'] =  new Event('Venus Vs Mars | 8/20 @ Select Lounge','images/event/venus_vs_mars/');
	$events['my_first'] =  new Event('My First | 7/1 @ Select Lounge','images/event/my_first/');
	$events['summer_salute'] =  new Event('Summer Salute | 9/2 @ Select Lounge','images/event/summer_salute/');
	$events['pastel_paradise'] =  new Event('Pastel Paradise | 8/5 @ Select Lounge','images/event/pastel_paradise/');



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
