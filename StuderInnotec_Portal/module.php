<?php
declare(strict_types=1);
const ARCHIVE_CONTROL_MODULE_ID = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';
require_once __DIR__ . '/../libs/common.php';  // globale Funktionen

class StuderInnotecWeb extends IPSModule {
    var $moduleName = "StuderInnotecWeb";

    public function Create() {
        // Diese Zeile nicht löschen.
        parent::Create();
		
        //Config Profile
        $this->RegisterProfileFloat("Studer-Innotec.MWh", 	"Factory", "", " MWh", 0, 0, 0, 3);
        $this->RegisterProfileFloat("Studer-Innotec.kWh", 	"Electricity", "", " kWh", 0, 0, 0, 2);
        $this->RegisterProfileFloat("Studer-Innotec.kW", 	"Electricity", "", " kW", 0, 0, 0, 2);
        $this->RegisterProfileFloat("Studer-Innotec.Hz",	"Freqency", "", " Hz", 0, 0, 0, 2);
        $this->RegisterProfileFloat("Studer-Innotec.V",	    "Energy", "", " V", 0, 0, 0, 2);
		$this->RegisterProfileFloat("Studer-Innotec.Ah",	"Capacity", "", " Ah", 0, 0, 0, 0);
		$this->RegisterProfileFloat("Studer-Innotec.percent",	    "Percent", "", " %", 0, 0, 0, 1);
        
        // Config Variablen Property
		$this->RegisterPropertyInteger('ArchiveControlID', IPS_GetInstanceListByModuleID(ARCHIVE_CONTROL_MODULE_ID)[0]);
        $this->RegisterPropertyString("Variables", "");
		$this->RegisterPropertyString("activeDevices", "");
		$this->RegisterPropertyBoolean("Debug", false);
		$this->RegisterPropertyString('Username', '');
        $this->RegisterPropertyString('Password', '');
        $this->RegisterPropertyString("installationNumber", "");
		        
		// Config Variablen Attribute
		$this->RegisterAttributeString("url_api", "https://api.studer-innotec.com/api/v1");
		
		// Config Variablen Timer
		$this->RegisterTimer("UpdateTimer_2", 0, 'Studer_Update_2($_IPS[\'TARGET\']);');
		$this->RegisterTimer("UpdateTimer_5", 0, 'Studer_Update_5($_IPS[\'TARGET\']);');
		$this->RegisterTimer("UpdateTimer_60", 0, 'Studer_Update_60($_IPS[\'TARGET\']);');
		$this->RegisterTimer("UpdateTimer_360", 0, 'Studer_Update_60($_IPS[\'TARGET\']);');
		$this->RegisterTimer("UpdateTimer_720", 0, 'Studer_Update_60($_IPS[\'TARGET\']);');
    }
public function Destroy() {
    //Never delete this line!
    parent::Destroy();
}

public function ApplyChanges() {
	// Diese Zeile nicht löschen
	parent::ApplyChanges();
	//clear Timer
	$this->SetTimerInterval("UpdateTimer_5",0);
	$this->SetTimerInterval("UpdateTimer_60",0);
	if(empty($this->ReadPropertyString("Username"))){
		//Warung fehlender Username
		if ($this->ReadPropertyBoolean("Debug")){$this->LogMessage("Warung fehlender Username", KL_MESSAGE);}
		$this->SetStatus(201);
	}else {$this->SetStatus(102);}
	if(empty($this->ReadPropertyString("Password"))){
		//Warung fehlendes Passwort
		if ($this->ReadPropertyBoolean("Debug")){$this->LogMessage("Warung fehlendes Passwort" , KL_MESSAGE);}
		$this->SetStatus(202);
	}else {$this->SetStatus(102);}
	if(empty($this->ReadPropertyString("installationNumber"))){
		//Warung fehlende installationNumber
		if ($this->ReadPropertyBoolean("Debug")){$this->LogMessage("Warung fehlende installationNumber", KL_MESSAGE);}
		$this->SetStatus(203);
	}else {$this->SetStatus(102);}
	
	$treeData = json_decode($this->ReadPropertyString("Variables"));
	if(!empty($treeData)){
		$active = array();
		foreach ($treeData as $value) {
			if(($value->Active)==true){
				$active[]=array('ID'=>($value->ID), 'Intervall'=>($value->Intervall));
			}
		}
		$intervall_active = array_unique((array_column($active, 'Intervall')));
		foreach ($intervall_active as $value) {
			$this->SetTimerInterval(("UpdateTimer_".$value), $value*60000);
			//$this->SetTimerInterval(("UpdateTimer_".$value), $value*1000);
		}
	}
}
    
public function GetConfigurationForm(){
    $data = json_decode(file_get_contents(__DIR__ . "/form.json"), true);
	$var_element = json_decode(file_get_contents(__DIR__ . "/../libs/_param.json"),true);
	$data['elements'][6]['values'] = $var_element ;
	return json_encode($data);
}

public function Update_2() {
	$timer_var = '2';
	$this->call_Studer_from_Timer($timer_var);
}

public function Update_5() {
	$timer_var = '5';
	$this->call_Studer_from_Timer($timer_var);
}

public function Update_60() {
	$timer_var = '60';
	$this->call_Studer_from_Timer($timer_var);
}

public function Update_360() {
	$timer_var = '360';
	$this->call_Studer_from_Timer($timer_var);
}

public function Update_720() {
	$timer_var = '720';
	$this->call_Studer_from_Timer($timer_var);
}

private function call_Studer_from_Timer($timer){
$treeData = json_decode($this->ReadPropertyString("Variables"));
	foreach ($treeData as $value) {
		if((($value->Active)==true)and (($value->Intervall)== $timer)){
			$var_ID = 'ID_' . $value->ID ;
			$configpage = json_decode(IPS_GetConfigurationForm($this->InstanceID));			
			foreach ($configpage->elements[6]->values as $item) {
				if ($item->ID == $value->ID) {
					$unit =($item->Unit);
					$format=($item->Format);
					$varname= ($item->VarName);
					$type = ($item->Type);
				}
			}
			if (!@$this->GetIDForIdent($var_ID )) {
				$this->LogMessage("==>create Var: ". $var_ID , KL_MESSAGE );
                if(!$varname){
                    $var_name = $var_ID;
                }else {$var_name = $this->Translate($varname); }
                switch ($format) {
                    case "FLOAT":
                        $this->RegisterVariableFloat($var_ID , $var_name, 'Studer-Innotec.'. $unit);
                        $this->EnableAction($var_ID );
						if (($value->Archive)==true){
							AC_SetLoggingStatus($this->ReadPropertyInteger('ArchiveControlID'), $this->GetIDForIdent($var_ID), true);
							IPS_ApplyChanges($this->ReadPropertyInteger('ArchiveControlID'));
						}
                        break;
                    case "SHORT_ENUM":
					case "LONG_ENUM":
                        $this->RegisterVariableString($var_ID , $var_name);
                        $this->EnableAction($var_ID );
						break;
                    default :
                        $this->LogMessage("could not find var-Format for: " . $format, KL_MESSAGE);
                }
			}
            switch ($format) {
                case "FLOAT":
					SetValueFloat ($this->GetIDForIdent($var_ID ), json_decode($this->Studer_Read($value->ID,"Value",$type))->{'floatValue'});
					break;
				case "SHORT_ENUM":
				case "LONG_ENUM":
					//create Array from 'Unit' Paramter in form
					$chunks = array_chunk(preg_split('/(:|,)/', $unit), 2);
					$result = array_combine(array_column($chunks, 0), array_column($chunks, 1));
					$StuderState = json_decode($this->Studer_Read($value->ID,"Value",$type));         
					$objAsString = (string)$StuderState->floatValue; 
					SetValueString($this->GetIDForIdent($var_ID),$result[$objAsString]);
					break;
				default :
					$this->LogMessage("could not find Handler for: ". $value->Format , KL_MESSAGE);
            }
		}
	}
}

private function Studer_Read($infoId,$paramart,$device) {
    global $email;
    global $pwd;
    global $installationNumber;
	
	$curl = curl_init();

	curl_setopt_array($curl, array(
    CURLOPT_URL => $this->ReadAttributeString("url_api") .'/installation/user-info/'. $this->ReadPropertyString("installationNumber") . '?device='. $device. "&infoId=". $infoId .'&paramPart=Value',
	CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 10,
	CURLOPT_FOLLOWLOCATION => true,
	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => array(
		'PHASH:'. md5($this->ReadPropertyString("Password")),
		'UHASH:'. hash('sha256',$this->ReadPropertyString("Username"))
		),
    ));

   	$response = curl_exec($curl);
	$respCode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);  
	curl_close($curl);

    switch ($respCode){
		case "401" :
			$this->LogMessage("Access not allowed", KL_MESSAGE);
			break;
		case "500" :
			$this->LogMessage("An error occured while retrieving data.", KL_MESSAGE);
			break;
		case "200" :
			if ($this->ReadPropertyBoolean("Debug")){$this->LogMessage("Connect to Studer OK", KL_MESSAGE);}
			$status = strval(json_decode($response)->{'status'});
			if(!preg_match("/OK/i", $status)){
				$this->LogMessage(((json_decode($response)->{'status'})) . " ID: " . $infoId, KL_MESSAGE);
				exit;
			}
			else{
				return $response;
			}
		default :
			exit;
    }
}

private function RegisterProfileFloat($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits){
	#Function orignal from https://github.com/Joey-1970/
	if (!IPS_VariableProfileExists($Name)) {
		IPS_CreateVariableProfile($Name, 2);
	}
	else {
		$profile = IPS_GetVariableProfile($Name);
		if ($profile['ProfileType'] != 2)
			throw new Exception("Variable profile type does not match for profile " . $Name);
		}
	IPS_SetVariableProfileIcon($Name, $Icon);
	IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
	IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
	IPS_SetVariableProfileDigits($Name, $Digits);
}

public function validateDevices () {
	echo "not implementet";
}

public function validateAccount() {
	$curl = curl_init();

	curl_setopt_array($curl, array(
		CURLOPT_URL => $this->ReadAttributeString("url_api") .'/installation/installations',
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => '',
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 10,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => 'GET',
		CURLOPT_HTTPHEADER => array(
		'PHASH:'. md5($this->ReadPropertyString("Password")),
		'UHASH:'. hash('sha256',$this->ReadPropertyString("Username"))
		),
	));

	$response = curl_exec($curl);
	$respCode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);  
	curl_close($curl);

	$xml = json_decode($response, true);
	
	switch ($respCode){
		case "401" :
			echo "Access not allowed.";
			break;
		case "500" :
			echo "An error occured while retrieving data.";
			break;
		case "200" :
			echo "Studer-Innotec has the following ID's for you: \n" ;	
			foreach ($xml as $installation) {				
				echo $installation['id']," => ", $installation['name'], PHP_EOL;
			}
		default :
			exit;
    }
}

public function reCheckVar() {
//function to check if a Variable what exists is active. if not active delete the Variable
	$treeData = json_decode($this->ReadPropertyString("Variables"));
	foreach ($treeData as $value) {
		$var_ID = 'ID_' . $value->ID ;
		if (@$this->GetIDForIdent($var_ID )) {
			if (!$value->Active==true){
				IPS_DeleteVariable($this->GetIDForIdent($var_ID ));
				echo "deleted " . $var_ID . "\n";
			}
			if ($value->Active==true){
				//
				if (($value->Archive)==true){
					AC_SetLoggingStatus($this->ReadPropertyInteger('ArchiveControlID'), $this->GetIDForIdent($var_ID), true);
					IPS_ApplyChanges($this->ReadPropertyInteger('ArchiveControlID'));
				}
				else {
					AC_SetLoggingStatus($this->ReadPropertyInteger('ArchiveControlID'), $this->GetIDForIdent($var_ID), false);
					IPS_ApplyChanges($this->ReadPropertyInteger('ArchiveControlID'));
					
				}
			}
		}
	}
}

public function CheckSofwareVersion() {
	$treeDataDevices = json_decode($this->ReadPropertyString("activeDevices"));
	if (!$treeDataDevices){exit;} //omits the error if the function is called before any saving
	foreach ($treeDataDevices as $value) {
		if(($value->Active)==true){
			$chunks = array_chunk(preg_split('/(:|,)/', ($value->Software_msb_lsb)), 2);
			$result = array_combine(array_column($chunks, 0), array_column($chunks, 1));
			
			$infoId_msb = $result["msb"];
			$infoId_lsb = $result["lsb"];
			$DeviceCat = $value->DeviceTypeID . "1";
			if (($value->DeviceTypeID)=="XT") {
				$xt_type = (float) $this->Studer_Read("3124","Value",$DeviceCat)->FloatValue;
				switch ($xt_type) {
					case "1":
						$DeviceType = "XTH";
						break;
					case "256":
						$DeviceType = "XTM";
						break;
					case "512":
						$DeviceType = "XTS";
						break;
					default :
						exit;
				}
				
			}
			else {
				switch ($value->DeviceTypeID) {
				case "VS" :
					$DeviceType = "VARIOSTRING";
					break;
				case "VT" :
					$DeviceType = "VARIOTRACK";
				case "BSP" :
					exit;
				}
			}
			
			$msb = (float) $this->Studer_Read($infoId_msb,"Value",$DeviceCat)->FloatValue;
			$lsb = (float) $this->Studer_Read($infoId_lsb,"Value",$DeviceCat)->FloatValue;
	
			$studer_version = json_decode((file_get_contents(__DIR__ . "/../libs/studer-version.json")),true);

			if (($studer_version['versions'][$DeviceType])==(($msb >>8) . "." . ($lsb >>8) . "." . ($lsb & 0xFF))){
				echo "found a active ". $DeviceType ." and no update needed \n\n";
			}
			else {
				echo "the installed Version of your ". $DeviceType .": \n". (($msb >>8) . "." . ($lsb >>8) . "." . ($lsb & 0xFF)) . "\ndiffers from the actual known version: \n" . ($studer_version['versions'][$DeviceType]) ."\n" ;
			}
		}
	}
}
}