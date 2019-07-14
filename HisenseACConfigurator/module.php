<?
// Klassendefinition
class HisenseACConfigurator extends IPSModule {

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
	}

	// Überschreibt die intere IPS_ApplyChanges($id) Funktion
	public function ApplyChanges() {
		// Diese Zeile nicht löschen
		parent::ApplyChanges();
	}

	public function RequestAction($Ident, $Value) {
		switch($Ident) {				
			default:
				throw new Exception($this->Translate("Invalid Ident"));
		}
	}

	public function GetConfigurationForm(){
		return '{
			"elements":
			[
				{ "type": "Button", "caption": "An", "onClick": "$this->GetDevices();" }
			]
		}';
	}

	private function GetDevices(){
		$data = array("command" => "GetDevices");
		$data_string = json_encode($data);
		$this->SendDataToParent($data_string);
	}
}