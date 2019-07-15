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
		$this->RegisterTimer("RefreshTokenTimer", 0, 'HISENSEACSPLIT_RefreshToken($_IPS[\'TARGET\']);');
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
				{ "code": 202, "icon": "error", "caption": "Account is locked" },
				{ "code": 203, "icon": "error", "caption": "Unknown error" }
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

		$this->SendDebug("SignIn", "Request: ".$data_string, 0);

		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Content-Length: '.strlen($data_string)
		]);

		$result = curl_exec($ch);
		curl_close($ch);

		$this->SendDebug("SignIn", "Result: ".$result, 0);

		$resData = json_decode($result);

		if(property_exists($resData, 'error')){
			switch($resData->error){
				case 'Invalid email or password':
					$this->SetStatus(201);
					break;
				case 'Your account is locked.':
					$this->SetStatus(202);
					break;
				default:
					$this->SetStatus(203);
			}
			$this->WriteAttributeString("AuthToken", "");
			$this->WriteAttributeString("RefreshToken", "");
			$this->WriteAttributeInteger("TokenExpire", 0);
			$this->WriteAttributeInteger("LastSignIn", 0);
			$this->SetTimerInterval("RefreshTokenTimer", 0);
			return;
		}

		$this->WriteAttributeString("AuthToken", $resData->access_token);
		$this->WriteAttributeString("RefreshToken", $resData->refresh_token);
		$this->WriteAttributeInteger("TokenExpire", $resData->expires_in);
		$this->WriteAttributeInteger("LastSignIn", time());

		$this->SendDebug("SignIn", "Token: ".$this->ReadAttributeString("AuthToken"), 0);

		$this->SetStatus(102);
		$refresh = round($resData->expires_in * 0.9);
		$this->LogMessage("SignIn successful - next refresh in $refresh sec", KL_MESSAGE);
		$this->SetTimerInterval("RefreshTokenTimer", $refresh * 1000);
	}

	public function RefreshToken(){
		$ch = curl_init("https://user-field-eu.aylanetworks.com/users/refresh_token.json");
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$data = array("user" => array(
			"refresh_token" => $this->ReadAttributeString("RefreshToken")
		));
		$data_string = json_encode($data);

		$this->SendDebug("RefreshToken", "Request: ".$data_string, 0);

		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Content-Length: '.strlen($data_string)
		]);

		$result = curl_exec($ch);
		curl_close($ch);

		$this->SendDebug("RefreshToken", "Result: ".$result, 0);

		$resData = json_decode($result);

		if(property_exists($resData, 'error')){
			switch($resData->error){
				case 'Invalid email or password':
					$this->SetStatus(201);
					break;
				case 'Your account is locked.':
					$this->SetStatus(202);
					break;
				case 'Your refresh token is invalid':
					$this->LogMessage("Token invalid - SignIn", KL_WARNING);
					$this->SignIn();
					return;
				default:
					$this->SetStatus(203);
			}
			$this->WriteAttributeString("AuthToken", "");
			$this->WriteAttributeString("RefreshToken", "");
			$this->WriteAttributeInteger("TokenExpire", 0);
			$this->WriteAttributeInteger("LastSignIn", 0);
			$this->SetTimerInterval("RefreshTokenTimer", 0);
			return;
		}

		$this->WriteAttributeString("AuthToken", $resData->access_token);
		$this->WriteAttributeString("RefreshToken", $resData->refresh_token);
		$this->WriteAttributeInteger("TokenExpire", $resData->expires_in);
		$this->WriteAttributeInteger("LastSignIn", time());

		$this->SendDebug("RefreshToken", "Token: ".$this->ReadAttributeString("AuthToken"), 0);

		$this->SetStatus(102);
		$refresh = round($resData->expires_in * 0.9);
		$this->LogMessage("Token refresh successful - next refresh in $refresh sec", KL_MESSAGE);
		$this->SetTimerInterval("RefreshTokenTimer", $refresh * 1000);
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

		return $result;
	}

	private function GetProperties($DeviceKey, $Properties){
		$names = [];
		foreach($Properties as $property){
			$names[] = urlencode('names[]').'='.$property;
		}

		$ch = curl_init("https://ads-field-eu.aylanetworks.com/apiv1/devices/$DeviceKey/properties.json?".join("&", $names));
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Authorization: auth_token'.$this->ReadAttributeString("AuthToken")
		]);

		$result = curl_exec($ch);
		curl_close($ch);

		return $result;
	}

	private function SetDatapoint($Key, $Value){
		$ch = curl_init("https://ads-field-eu.aylanetworks.com/apiv1/properties/$Key/datapoints.json");
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$data = array("datapoint" => array(
			"value" => $Value
		));
		$data_string = json_encode($data);

		$this->SendDebug("SetDatapoint", "Request: ".$data_string, 0);

		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Authorization: auth_token'.$this->ReadAttributeString("AuthToken"),
			'Content-Type: application/json',
			'Content-Length: '.strlen($data_string)
		]);

		$result = curl_exec($ch);
		curl_close($ch);

		return $result;
	}

	public function ForwardData($JSONString) {
		$json = json_decode($JSONString);
		switch($json->DataID){
			case '{57FE6DCC-13F1-4BDA-DE85-D08D408BA58A}': //Configurator
				switch($json->command){
					case 'GetDevices':
						return $this->GetDevices();
				}
				break;

			case '{D1095CBF-91B6-27E5-A1CB-BB23267A1B33}': //Device
				switch($json->command){
					case 'GetProperties':
						return $this->GetProperties($json->DeviceKey, $json->Properties);
						break;

					case 'SetDatapoint':
						return $this->SetDatapoint($json->DatapointKey, $json->DatapointValue);
						break;
				}
				break;
		}

		$response = array();

		return json_encode($response);
	}
}