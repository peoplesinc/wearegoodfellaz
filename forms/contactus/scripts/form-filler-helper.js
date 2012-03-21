/*
  -------------------------------------------------------------------------
		      FormFiller Scripts
              Part of Simfatic Forms software
					
	Copyright (C) 2008-2009 Simfatic Solutions. All rights reserved.
    This javascript code is installed as part of Simfatic Forms software.
	You may adapt this script for your own needs, provided these opening credit
    lines (down to the lower dividing line) are kept intact.
    You may not reprint or redistribute this code without permission from 
    Simfatic Solutions.
	http://www.simfatic.com/
    -------------------------------------------------------------------------  
*/
function SFM_SelectChkItem(objcheck,chkValue)
{
   if(objcheck.length)
	{
		for(var c=0;c < objcheck.length;c++)
		{
		   if(objcheck[c].value == chkValue)
		   {
		     objcheck[c].checked=true;
			 break;
		   }//if
		}//for
	}	
}

function SFM_SelectListItem(objList,listValue)
{
	for(var c=0;c < objList.options.length;c++)
	{
	   if(objList.options[c].value == listValue)
	   {
		 objList.options[c].selected=true;
		 break;
	   }//if
	}//for
}

/*
	Copyright (C) 2008-2009 Simfatic Solutions . All rights reserved.
*/