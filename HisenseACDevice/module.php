<?
// Klassendefinition
class HisenseACDevice extends IPSModule {

	private $insId = 0;

	// Der Konstruktor des Moduls
	// Überschreibt den Standard Kontruktor von IPS
	public function __construct($InstanceID) {
		// Diese Zeile nicht löschen
		parent::__construct($InstanceID);
		// Selbsterstellter Code
		$this->insId = $InstanceID;
	}

	// Überschreibt die interne IPS_Create($id) Funktion
	public function Create() {
		// Diese Zeile nicht löschen.
		parent::Create();

		$this->ConnectParent("{1BE067BC-6499-49F2-A8C9-BB6DB6B4ADF6}");

		//These lines are parsed on Symcon Startup or Instance creation
		//You cannot use variables here. Just static values.
		$this->RegisterPropertyInteger("DeviceKey", 0);

		$workModeProfile = "HISENSEAC.".$this->insId.".WorkMode";
		if(!IPS_VariableProfileExists($workModeProfile)) {
			IPS_CreateVariableProfile($workModeProfile, 1);
			IPS_SetVariableProfileValues($workModeProfile, 0, 4, 1);
			IPS_SetVariableProfileIcon($workModeProfile, "Information");
			IPS_SetVariableProfileAssociation($workModeProfile, 0, $this->Translate("fanOnly"), "", 0xC0C0C0);
			IPS_SetVariableProfileAssociation($workModeProfile, 1, $this->Translate("heating"), "", 0xFF0000);
			IPS_SetVariableProfileAssociation($workModeProfile, 2, $this->Translate("cooling"), "", 0x0000FF);
			IPS_SetVariableProfileAssociation($workModeProfile, 3, $this->Translate("drying"), "", 0xCCCCCC);
			IPS_SetVariableProfileAssociation($workModeProfile, 4, $this->Translate("auto"), "", 0x00FF00);
		}

		$fanSpeedProfile = "HISENSEAC.".$this->insId.".FanSpeed";
		if(!IPS_VariableProfileExists($fanSpeedProfile)) {
			IPS_CreateVariableProfile($fanSpeedProfile, 1);
			IPS_SetVariableProfileValues($fanSpeedProfile, 0, 9, 1);
			IPS_SetVariableProfileIcon($fanSpeedProfile, "Ventilation");
			IPS_SetVariableProfileAssociation($fanSpeedProfile, 0, $this->Translate("auto"), "", 0x00FF00);
			IPS_SetVariableProfileAssociation($fanSpeedProfile, 1, "1", "", 0xC0C0C0);
			IPS_SetVariableProfileAssociation($fanSpeedProfile, 2, "2", "", 0xC0C0C0);
			IPS_SetVariableProfileAssociation($fanSpeedProfile, 3, "3", "", 0xC0C0C0);
			IPS_SetVariableProfileAssociation($fanSpeedProfile, 4, "4", "", 0xC0C0C0);
			IPS_SetVariableProfileAssociation($fanSpeedProfile, 5, "5", "", 0xC0C0C0);
			IPS_SetVariableProfileAssociation($fanSpeedProfile, 6, "6", "", 0xC0C0C0);
			IPS_SetVariableProfileAssociation($fanSpeedProfile, 7, "7", "", 0xC0C0C0);
			IPS_SetVariableProfileAssociation($fanSpeedProfile, 8, "8", "", 0xC0C0C0);
			IPS_SetVariableProfileAssociation($fanSpeedProfile, 9, "9", "", 0xC0C0C0);
		}

		$roomTempId = $this->RegisterVariableFloat("f_temp_in", $this->Translate("f_temp_in"), "~Temperature.Room", 30);
		$this->RegisterVariableFloat("t_temp", $this->Translate("t_temp"), "~Temperature.Room", 20);
		$powerId = $this->RegisterVariableBoolean("t_power", $this->Translate("t_power"), "~Switch", 10);
		$this->RegisterVariableBoolean("t_fan_leftright", $this->Translate("t_fan_leftright"), "~Switch", 60);
		$this->RegisterVariableBoolean("t_fan_power", $this->Translate("t_fan_power"), "~Switch", 50);
		$this->RegisterVariableBoolean("t_fan_mute", $this->Translate("t_fan_mute"), "~Switch", 80);
		$this->RegisterVariableBoolean("t_eco", $this->Translate("t_eco"), "~Switch", 70);
		$this->RegisterVariableBoolean("t_backlight", $this->Translate("t_backlight"), "~Switch", 90);
		$this->RegisterVariableInteger("t_work_mode", $this->Translate("t_work_mode"), $workModeProfile, 15);
		$this->RegisterVariableInteger("t_fan_speed", $this->Translate("t_fan_speed"), $fanSpeedProfile, 40);

		$this->RegisterAttributeInteger("f_temp_in", 0);
		$this->RegisterAttributeInteger("t_temp", 0);
		$this->RegisterAttributeInteger("t_power", 0);
		$this->RegisterAttributeInteger("t_fan_leftright", 0);
		$this->RegisterAttributeInteger("t_fan_power", 0);
		$this->RegisterAttributeInteger("t_fan_mute", 0);
		$this->RegisterAttributeInteger("t_eco", 0);
		$this->RegisterAttributeInteger("t_backlight", 0);
		$this->RegisterAttributeInteger("t_work_mode", 0);
		$this->RegisterAttributeInteger("t_fan_speed", 0);
		$this->RegisterAttributeBoolean("OffTimerEnabled", false);

		//Automation
		$this->RegisterVariableBoolean("AutoCooling", $this->Translate("AutoCooling"), "~Switch", 4);
		$this->RegisterVariableFloat("TargetTemperature", $this->Translate("TargetTemperature"), "~Temperature.Room", 5);
		$this->RegisterPropertyFloat("OnHysteresis", 0.5);
		$this->RegisterPropertyFloat("OffHysteresis", 2);
		$this->RegisterPropertyFloat("OutsideMinimumTemperature", 18.0);
		$this->RegisterPropertyInteger("RoomTemperature", 0);
		$this->RegisterPropertyInteger("OutsideTemperature", 0);
		$this->RegisterPropertyInteger("PresenceVariable", 0);
		$this->RegisterPropertyInteger("PresenceTrailing", 0);

		//Timer
		$this->RegisterTimer("UpdateTimer", 0, 'HISENSEAC_Update($_IPS[\'TARGET\']);');
		$this->RegisterTimer("OffTimer", 0, "RequestAction($powerId, false, true);");

		$this->EnableAction("t_temp");
		$this->EnableAction("t_power");
		$this->EnableAction("t_fan_leftright");
		$this->EnableAction("t_fan_power");
		$this->EnableAction("t_fan_mute");
		$this->EnableAction("t_eco");
		$this->EnableAction("t_backlight");
		$this->EnableAction("t_work_mode");
		$this->EnableAction("t_fan_speed");

		$this->EnableAction("AutoCooling");
		$this->EnableAction("TargetTemperature");

		$splitter = $this->GetSplitter();
		$this->RegisterMessage($splitter, IM_CHANGESTATUS);
		$ins = IPS_GetInstance($splitter);
		if($ins['InstanceStatus'] == 102){
			$this->SendDebug("Create", "Update on create", 0);
			$this->SetTimerInterval("UpdateTimer", 5000);
		}
	}

	/**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function Destroy()
    {
        if (IPS_GetKernelRunlevel() <> KR_READY) {
            return parent::Destroy();
        }
        if (!IPS_InstanceExists($this->insId)) {
            //Profile löschen
			$this->UnregisterProfile("HISENSEAC.".$this->insId.".WorkMode");
			$this->UnregisterProfile("HISENSEAC.".$this->insId.".FanSpeed");
        }
        parent::Destroy();
    }

	// Überschreibt die intere IPS_ApplyChanges($id) Funktion
	public function ApplyChanges() {
		// Diese Zeile nicht löschen
		parent::ApplyChanges();

		$this->RegisterMessage($this->GetIDForIdent('AutoCooling'), VM_UPDATE);
		if($this->ReadPropertyInteger('RoomTemperature') > 0){
			$this->RegisterMessage($this->ReadPropertyInteger('RoomTemperature'), VM_UPDATE);
		}else{
			$this->RegisterMessage($this->GetIDForIdent('f_temp_in'), VM_UPDATE);
		}

		if($this->ReadPropertyInteger('PresenceVariable') > 0) $this->RegisterMessage($this->ReadPropertyInteger('PresenceVariable'), VM_UPDATE);
		if($this->ReadPropertyInteger('OutsideTemperature') > 0) $this->RegisterMessage($this->ReadPropertyInteger('OutsideTemperature'), VM_UPDATE);
	}

	public function MessageSink($TimeStamp, $SenderID, $Message, $Data){
		$this->SendDebug("MessageSink", "Message from SenderID ".$SenderID." with Message ".$Message."\r\n Data: ".print_r($Data, true),0);

		$roomTempId = $this->ReadPropertyInteger('RoomTemperature') > 0 ? $this->ReadPropertyInteger('RoomTemperature') : $this->GetIDForIdent('f_temp_in');

		switch($SenderID){
			case $this->GetSplitter():
				if($Message != IM_CHANGESTATUS) break;
				$ins = IPS_GetInstance($SenderID);
				if($ins['InstanceStatus'] == 102){
					$this->SetTimerInterval("UpdateTimer", 5000);
					$this->Update();
				}else{
					$this->SetTimerInterval("UpdateTimer", 0);
				}
				break;

			case $roomTempId:
			case $this->GetIDForIdent('AutoCooling'):
			case $this->ReadPropertyInteger('OutsideTemperature'):
			case $this->ReadPropertyInteger('PresenceVariable'):
				if($Message != VM_UPDATE) break;
				$this->CheckAutocool();
				break;
		}
	}

	private function CheckAutocool(){
		$this->SendDebug("CheckAutocool", "Start", 0);
		if(!$this->GetValue('AutoCooling')) return; //Autocooling disabled
		$outsideOn = $this->ReadPropertyInteger('OutsideTemperature') > 0 ? GetValueFloat($this->ReadPropertyInteger('OutsideTemperature')) < $this->ReadPropertyInteger('OutsideMinimumTemperature') : true;
		$roomTempId = $this->ReadPropertyInteger('RoomTemperature') > 0 ? $this->ReadPropertyInteger('RoomTemperature') : $this->GetIDForIdent('f_temp_in');
		$roomTemp = GetValueFloat($roomTempId);
		$upperTempReached = $roomTemp > ($this->GetValue('TargetTemperature') + $this->ReadPropertyInteger('OnHysteresis'));
		$lowerTempReached = $roomTemp < ($this->GetValue('TargetTemperature') - $this->ReadPropertyInteger('OffHysteresis'));
		$presenceOn = $this->ReadPropertyInteger('PresenceVariable') > 0 ? GetValueBoolean($this->ReadPropertyInteger('PresenceVariable')) : true;
		if($this->GetValue('t_power')){
			//Device is on - check if we should turn off
			if(!$outsideOn || $lowerTempReached){
				$this->LogMessage("Switching AC off", KL_INFO);
				$this->RequestAction('t_power', false, true);
			}else if(!$presenceOn && !$this->ReadAttributeBoolean('OffTimerEnabled')){
				$this->EnableOffTimer();
			}
		}else{
			//Device is off - check if we should turn on
			if(!$presenceOn || !$outsideOn || !$upperTempReached) return;
			$this->LogMessage("Switching AC on", KL_INFO);
			$this->DisableOffTimer();
			$this->RequestAction('t_power', true, true);
			$this->RequestAction('t_temp', $this->GetValue('TargetTemperature'));
			$this->RequestAction('t_work_mode', 2); //Cooling
		}
	}

	private function EnableOffTimer(){
		$presTrail = $this->ReadPropertyInteger('PresenceTrailing');
		if($presTrail > 0){
			$this->LogMessage("Switching AC off delayed because not present anymore", KL_INFO);
			$this->SetTimerIntervall('OffTimer', $presTrail * 60000);
			$this->WriteAttributeBoolean('OffTimerEnabled', true);
		}else{
			$this->LogMessage("Switching AC off because not present anymore", KL_INFO);
			$this->SetTimerIntervall('OffTimer', 0);
			$this->WriteAttributeBoolean('OffTimerEnabled', false);
			$this->RequestAction('t_power', false, true);
		}
	}

	private function DisableOffTimer(){
		$this->SetTimerIntervall('OffTimer', 0);
		$this->WriteAttributeBoolean('OffTimerEnabled', false);
	}

	public function RequestAction($Ident, $Value, $Automatic = false) {
		$splitter = $this->GetSplitter();
		$ins = IPS_GetInstance($splitter);
		if($ins['InstanceStatus'] <> 102){
			$this->LogMessage("Could not send command because cloud not connected", KL_ERROR);
			throw new Exception($this->Translate("Cloud not connected"));
		}

		$SetValue = $Value;
		switch($Ident) {
			case 't_temp':
				$Value = round($this->CelsiusToFahrenheit($Value));
				$SetValue = $this->FahrenheitToCelsius($Value);
				break;

			case 't_power':
				if(!$Automatic) $this->SetValue('AutoCooling', false);
			case 't_backlight':
			case 't_eco':
			case 't_fan_leftright':
			case 't_fan_mute':
			case 't_fan_power':
				$Value = $Value ? 1 : 0;
				break;
			
			case 't_fan_speed':
			case 't_work_mode':
				break;

			case "AutoCooling":
			case "TargetTemperature":
				$this->SetValue($Ident, $SetValue);
				$this->CheckAutocool();
				return;

			default:
				throw new Exception("Invalid Ident");
		}

		$this->SetDatapoint($this->ReadAttributeInteger($Ident), $Value);
		$this->SetValue($Ident, $SetValue);
		$this->SetTimerInterval("UpdateTimer", 5000);
	}

	public function GetConfigurationForm(){
		return '{
			"elements":
			[
				{ "type": "ExpansionPanel", "caption": "AutoRoomTemperature", "items": [
					{ "type": "SelectVariable", "name": "RoomTemperature", "caption": "RoomTemperature" },
					{ "type": "NumberSpinner", "name": "OnHysteresis", "caption": "OnHysteresis", "digits": 1, "suffix": "°C" },
					{ "type": "NumberSpinner", "name": "OffHysteresis", "caption": "OffHysteresis", "digits": 1, "suffix": "°C" }
				]},
				{ "type": "ExpansionPanel", "caption": "AutoOutsideTemperature", "items": [
					{ "type": "SelectVariable", "name": "OutsideTemperature", "caption": "OutsideTemperature" },
					{ "type": "NumberSpinner", "name": "OutsideMinimumTemperature", "caption": "OutsideMinimumTemperature", "digits": 1, "suffix": "°C" }
					
				]},
				{ "type": "ExpansionPanel", "caption": "AutoPresence", "items": [
					{ "type": "SelectVariable", "name": "PresenceVariable", "caption": "PresenceVariable" },
					{ "type": "NumberSpinner", "name": "PresenceTrailing", "caption": "PresenceTrailing", "suffix": "min" }
				]}
			]
		}';;
	}

	public function Update(){
		$this->SetTimerInterval("UpdateTimer", 60000);

		$props = $this->GetProperties([
			'f_temp_in', 		//Temperatursensor
			't_temp',			//Zieltemperatur
			't_power',			//Ein/Aus
			't_backlight',		//Display
			't_eco',			//ECO Mode
			't_fan_leftright',	//Horizontal Swing
			't_fan_mute',		//Quiet Mode
			't_fan_power',		//Vertikal Swing
			't_fan_speed',		//Lüfter Geschwindigkeit Max 9
			't_work_mode'		//Betriebsmodus
		]);

		foreach($props as $propName => $prop){
			//Cache the Property Key
			$this->WriteAttributeInteger($propName, $prop->key);
			$val = $prop->value;
			switch($propName){
				case 'f_temp_in':
				case 't_temp':
					$val = $this->FahrenheitToCelsius($val);
					break;
			}
			$this->SetValue($propName, $val);
		}
	}

	/**
     * Liefert den aktuell verbundenen Splitter.
     *
     * @access private
     * @return bool|int FALSE wenn kein Splitter vorhanden, sonst die ID des Splitter.
     */
    private function GetSplitter()
    {
        $SplitterID = IPS_GetInstance($this->insId)['ConnectionID'];
        if ($SplitterID == 0) {
            return false;
        }
        return $SplitterID;
	}

	private function SetDatapoint($Key, $Value){
		$data = array(
			"DataID"		=> "{D1095CBF-91B6-27E5-A1CB-BB23267A1B33}",
			"command"		=> "SetDatapoint",
			"DatapointKey"	=> $Key,
			"DatapointValue"=> $Value
		);
		$data_string = json_encode($data);
		$result = $this->SendDataToParent($data_string);
	}

	private function GetProperties($Properties){
		$data = array(
			"DataID"	=> "{D1095CBF-91B6-27E5-A1CB-BB23267A1B33}",
			"command"	=> "GetProperties",
			"DeviceKey"	=> $this->ReadPropertyInteger("DeviceKey"),
			"Properties"=> $Properties
		);
		$data_string = json_encode($data);
		$result = $this->SendDataToParent($data_string);
		
		$jsonData = json_decode($result);

		$props = [];
		foreach($jsonData as $prop){
			$propObj = $prop->property;
			$props[$propObj->name] = $propObj;
		}

		return $props;
	}

	private function FahrenheitToCelsius($given_value){
		$celsius=5/9*($given_value-32);
		return round($celsius*2)/2;
	}

	private function CelsiusToFahrenheit($given_value){
		$fahrenheit=$given_value*9/5+32;
		return $fahrenheit ;
	}

	/**
     * Löscht ein Variablenprofile, sofern es nicht außerhalb dieser Instanz noch verwendet wird.
     *
     * @param string $Name Name des zu löschenden Profils.
     */
    protected function UnregisterProfile(string $Name)
    {
        if (!IPS_VariableProfileExists($Name)) {
            return;
        }
        foreach (IPS_GetVariableList() as $VarID) {
            if (IPS_GetParent($VarID) == $this->InstanceID) {
                continue;
            }
            if (IPS_GetVariable($VarID)['VariableCustomProfile'] == $Name) {
                return;
            }
            if (IPS_GetVariable($VarID)['VariableProfile'] == $Name) {
                return;
            }
        }
        IPS_DeleteVariableProfile($Name);
    }
}