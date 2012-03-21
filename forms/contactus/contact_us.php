<?PHP
/*
Simfatic Forms Main Form processor script

This script does all the server side processing. 
(Displaying the form, processing form submissions,
displaying errors, making CAPTCHA image, and so on.) 

All pages (including the form page) are displayed using 
templates in the 'templ' sub folder. 

The overall structure is that of a list of modules. Depending on the 
arguments (POST/GET) passed to the script, the modules process in sequence. 

Please note that just appending  a header and footer to this script won't work.
To embed the form, use the 'Copy & Paste' code in the 'Take the Code' page. 
To extend the functionality, see 'Extension Modules' in the help.

*/
require_once("./includes/contact_us-lib.php");
$formmailobj =  new FormMail("contact_us");
$formmailobj->setFormPage(sfm_readfile("./templ/contact_us_form_page.txt"));
$formmailobj->setFormID("b1c18047-b9c9-4e08-9643-331e7df0bbc7");
$formmailobj->setFormKey("586feb47-2893-40f2-a9fa-eaf0e98db4d1");
$formmailobj->setEmailFormatHTML(true);
$formmailobj->EnableLogging(false);
$formmailobj->SetDebugMode(false);
$formmailobj->SetFromAddress("info@wearegoodfellaz.com");
$formmailobj->SetCommonDateFormat("m-d-Y");
$formmailobj->SetSingleBoxErrorDisplay(true);
$formmailobj->InitSMTP("smtpout.secureserver.net","info@wearegoodfellaz.com","79E4CE3803D0BED98C4D5D23B5A602DA",25);
$fm_installer =  new FM_FormInstaller();
$formmailobj->addModule($fm_installer);

$formmailobj->setIsInstalled(true);
$formfiller =  new FM_FormFillerScriptWriter();
$formmailobj->addModule($formfiller);

$formmailobj->AddElementInfo("Name","text");
$formmailobj->AddElementInfo("Email","text");
$formmailobj->AddElementInfo("Reason","listbox");
$formmailobj->AddElementInfo("Message","multiline");
$page_renderer =  new FM_FormPageRenderer();
$formmailobj->addModule($page_renderer);

$validator =  new FM_FormValidator();
$validator->addValidation("Name","req","Please fill in Name");
$validator->addValidation("Name","maxlen=50","The length of the input for Name should not exceed 50");
$validator->addValidation("Name","alpha","The input for Name should be a valid alphabetic value");
$validator->addValidation("Email","email","The input for Email should be a valid email value");
$validator->addValidation("Email","req","Please fill in Email");
$validator->addValidation("Email","maxlen=50","The length of the input for Email should not exceed 50");
$validator->addValidation("Message","maxlen=10240","The length of the input for Message should not exceed 10240");
$formmailobj->addModule($validator);

$data_email_sender =  new FM_FormDataSender(sfm_readfile("./templ/contact_us_email_subj.txt"),sfm_readfile("./templ/contact_us_email_body.txt"),"%Email%");
$data_email_sender->AddToAddr("Info<Info@WeAreGoodFellaz.com>");
$formmailobj->addModule($data_email_sender);

$autoresp =  new FM_AutoResponseSender(sfm_readfile("./templ/contact_us_resp_subj.txt"),sfm_readfile("./templ/contact_us_resp_body.txt"));
$autoresp->SetToVariables("Name","Email");
$formmailobj->addModule($autoresp);

$tupage =  new FM_ThankYouPage(sfm_readfile("./templ/contact_us_thank_u.txt"));
$formmailobj->addModule($tupage);

$formmailobj->ProcessForm();

?>