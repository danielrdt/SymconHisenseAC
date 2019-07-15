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
		}

		$this->RegisterVariableFloat("f_temp_in", $this->Translate("f_temp_in"), "~Temperature.Room", 30);
		$this->RegisterVariableFloat("t_temp", $this->Translate("t_temp"), "~Temperature.Room", 20);
		$this->RegisterVariableBoolean("t_power", $this->Translate("t_power"), "~Switch", 10);
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

		//Timer
		$this->RegisterTimer("UpdateTimer", 60000, 'HISENSEAC_Update($_IPS[\'TARGET\']);');

		$this->EnableAction("t_temp");
		$this->EnableAction("t_power");
		$this->EnableAction("t_fan_leftright");
		$this->EnableAction("t_fan_power");
		$this->EnableAction("t_fan_mute");
		$this->EnableAction("t_eco");
		$this->EnableAction("t_backlight");
		$this->EnableAction("t_work_mode");
		$this->EnableAction("t_fan_speed");
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

		//if($this->ReadPropertyBoolean("AutomaticUpdate")){
		//	$this->SetTimerInterval("UpdateTimer", $this->ReadPropertyInteger("UpdateInterval") * 60000);
		//}else{
		//	$this->SetTimerInterval("UpdateTimer", 0);
		//}
	}

	public function RequestAction($Ident, $Value) {
		$SetValue = $Value;
		switch($Ident) {
			case 't_temp':
				$Value = round($this->CelsiusToFahrenheit($Value));
				$SetValue = $this->FahrenheitToCelsius($Value);
				break;
		}

		$this->SetDatapoint($this->ReadAttributeInteger($Ident), $Value);
		$this->SetValue($Ident, $SetValue);
	}

	public function GetConfigurationForm(){
		return '{}';
	}

	public function Update(){
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

	function FahrenheitToCelsius($given_value){
		$celsius=5/9*($given_value-32);
		return round($celsius*2)/2;
	}

	function CelsiusToFahrenheit($given_value){
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