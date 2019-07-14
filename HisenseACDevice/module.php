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

		$this->RegisterVariableFloat("t_temp_in", $this->Translate("t_temp_in"), "~Temperature.Room");
		$this->RegisterVariableFloat("t_temp", $this->Translate("t_temp"), "~Temperature.Room");
		$this->RegisterVariableBoolean("t_power", $this->Translate("t_power"), "~Switch");
		$this->RegisterVariableInteger("t_run_mode", $this->Translate("t_run_mode"), "");

		//Timer
		$this->RegisterTimer("UpdateTimer", 60000, 'HISENSEAC_Update($_IPS[\'TARGET\']);');
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
		switch($Ident) {				
			default:
				throw new Exception($this->Translate("Invalid Ident"));
		}
	}

	public function GetConfigurationForm(){
		return '{}';
	}

	public function Update(){
		$props = GetProperties([
			'f_temp_in', 		//Temperatursensor
			't_power',			//Ein/Aus
			't_run_mode',		//Betriebsmodus
			't_temp',			//Zieltemperatur
			't_backlight',		//Display
			't_eco',			//ECO Mode
			't_fan_leftright',	//Horizontal Swing
			't_fan_mute',		//Silent Mode
			't_fan_power',		//Lüfter Vollgas
			't_fan_speed',		//Lüfter Geschwindigkeit
			't_work_mode'
		]);

		$this->WriteVariableFloat("t_temp_in", FahrenheitToCelsius($props['t_temp_in']));
		$this->WriteVariableFloat("t_temp", FahrenheitToCelsius($props['t_temp']));
		$this->WriteVariableBoolean("t_power", $props['t_power']);
		$this->WriteVariableInteger("t_run_mode", $props['t_run_mode']);
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
		return $celsius;
	}
}