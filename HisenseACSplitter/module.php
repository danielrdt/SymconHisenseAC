<?
// Klassendefinition
class HisenseACSplitter extends IPSModule {

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

		//These lines are parsed on Symcon Startup or Instance creation
		//You cannot use variables here. Just static values.
		$this->RegisterPropertyString("Username", "");
		$this->RegisterPropertyString("Password", "");
	
		$this->RegisterAttributeString("AuthToken", "");
		$this->RegisterAttributeString("RefreshToken", "");
		$this->RegisterAttributeInteger("TokenExpire", 0);
		$this->RegisterAttributeInteger("LastSignIn", 0);

		//Timer
		$this->RegisterTimer("UpdateTimer", 0, 'HisenseACSplitter_KeepAlive($_IPS[\'TARGET\']);');
	}

	// Überschreibt die intere IPS_ApplyChanges($id) Funktion
	public function ApplyChanges() {
		// Diese Zeile nicht löschen
		parent::ApplyChanges();

		if($this->ReadPropertyString("Username") !== '' && $this->ReadPropertyString("Password") !== ''){
			$this->SignIn();
		}

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
		return '{
			"elements":
			[
				{ "type": "ValidationTextBox", "name": "Username", "caption": "Username" },
				{ "type": "ValidationTextBox", "name": "Password", "caption": "Password" }
			],
			"status":
			[
				{ "code": 102, "icon": "active", "caption": "Signed in" },
				{ "code": 201, "icon": "error", "caption": "Authentication failed" },
				{ "code": 202, "icon": "error", "caption": "Account is locked" }
			]
		}';
	}

	private function SignIn() {
		$ch = curl_init("https://user-field-eu.aylanetworks.com/users/sign_in.json");
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$data = array("user" => array(
			"application" => array(
				"app_id" => "i-Hisense-oem-eu-field-id",
				"app_secret" => "i-Hisense-oem-eu-field-ZrzZq6B6FzOSBVwShr2-pRU3R2c-2oRgBE3SP5DuKg18HOnogLDmoUk"
			),
			"email" => $this->ReadPropertyString("Username"),
			"password" => $this->ReadPropertyString("Password")
			));
		$data_string = json_encode($data);

		$this->LogMessage("Request: ".$data_string, KL_MESSAGE);

		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Content-Length: '.strlen($data_string)
		]);

		$result = curl_exec($ch);
		curl_close($ch);

		$this->LogMessage("Result: ".$result, KL_MESSAGE);

		$resData = json_decode($result);

		if(property_exists($resData, 'error')){
			switch($resData->error){
				case 'Invalid email or password':
					$this->SetStatus(201);
					break;
				case 'Your account is locked.':
					$this->SetStatus(202);
					break;
			}
			$this->WriteAttributeString("AuthToken", "");
			$this->WriteAttributeString("RefreshToken", "");
			$this->WriteAttributeInteger("TokenExpire", 0);
			$this->WriteAttributeInteger("LastSignIn", 0);
			return;
		}

		$this->WriteAttributeString("AuthToken", $resData->access_token);
		$this->WriteAttributeString("RefreshToken", $resData->refresh_token);
		$this->WriteAttributeInteger("TokenExpire", $resData->expires_in);
		$this->WriteAttributeInteger("LastSignIn", time());

		$this->LogMessage("Token: ".$this->ReadAttributeString("AuthToken"), KL_MESSAGE);

		$this->SetStatus(102);
	}

	private function GetDevices(){
		$ch = curl_init("https://ads-field-eu.aylanetworks.com/apiv1/devices.json");
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Authorization: auth_token'.$this->ReadAttributeString("AuthToken")
		]);

		$result = curl_exec($ch);
		curl_close($ch);

		$this->LogMessage("Result: ".$result, KL_MESSAGE);

		$resData = json_decode($result);
	}

	public function ForwardData($JSONString) {
		$json = json_decode($JSONString);
		switch($json->command){
			case 'GetDevices':
				$this->GetDevices();
				break;
		}

		$response = array();

		return json_encode($response);
	}
}