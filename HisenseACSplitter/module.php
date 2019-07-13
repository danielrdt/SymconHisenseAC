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
				{ "code": 201, "icon": "error", "caption": "Authentication failed" }
			]
		}';
	}

	private function SignIn() {
		$ch = curl_init("https://user-field-eu.aylanetworks.com/users/sign_in.json");
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");

		$data = array("user" => array(
			"application" => array(
				"app_id" => "i-Hisense-oem-eu-field-id",
				"app_secret" => "i-Hisense-oem-eu-field-ZrzZq6B6FzOSBVwShr2-pRU3R2c-2oRgBE3SP5DuKg18HOnogLDmoUk"
			),
			"email" => $this->ReadPropertyString("Username"),
			"password" => $this->ReadPropertyString("Password")
			));
		$data_string = json_encode($data);

		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Content-Length: '.strlen($data_string)
		]);

		$result = curl_exec($ch);
		curl_close($ch);

		$this->LogMessage("Result: ".$result);

		$resData = json_decode($result);

		$this->WriteAttributeString("AuthToken", $resData['auth_token']);
		$this->WriteAttributeString("RefreshToken", $resData['refresh_token']);
		$this->WriteAttributeInteger("TokenExpire", $resData['expires_in']);
		$this->WriteAttributeInteger("LastSignIn", time());

		$this->LogMessage("Token: ".$this->ReadAttributeString("AuthToken"));

		$this->SetStatus(101);
	}

	/**
	* Die folgenden Funktionen stehen automatisch zur Verfügung, wenn das Modul über die "Module Control" eingefügt wurden.
	* Die Funktionen werden, mit dem selbst eingerichteten Prefix, in PHP und JSON-RPC wiefolgt zur Verfügung gestellt:
	*
	* ABC_MeineErsteEigeneFunktion($id);
	*
	*/
	public function Update() {
		try{
			$this->Connect([
				'batPct',
				'bin',
				'cleanMissionStatus',
				'pose',
				'dock'
			]);

			$presence = false;
			$presenceId = $this->ReadPropertyString('PresenceVariable');
			if($presenceId !== "" && IPS_VariableExists($presenceId)){
				$presence = GetValueBoolean($presenceId);
			}

			//Abwesend & freigabe zur Reinigung & Roomba ist bereit & Reinigung läuft noch nicht & letzte Reinigung ist min 12 Std. her
			if(!$presence AND
				GetValueBoolean($this->GetIDForIdent('CleanBySchedule')) AND
				GetValueInteger($this->GetIDForIdent('State')) == 0 AND
				(GetValueInteger($this->GetIDForIdent('CleanMissionStatus')) == 1 OR GetValueInteger($this->GetIDForIdent('CleanMissionStatus')) == 2) AND
				(GetValueInteger($this->GetIDForIdent('LastAutostart')) + ($this->ReadPropertyInteger('TimeBetweenMission') * 3600)) < time()){
				//Zeit zwischen Reinigung min. Stunden x 3600 Sek Sek
		
				$roomba->Start();
				SetValueInteger($this->GetIDForIdent('LastAutostart'), time());
			}

			$this->roomba->loop();

			if($this->roomba->ContainsValue('batPct')){
				SetValueInteger($this->GetIDForIdent("BatPct"), $this->roomba->GetValue('batPct'));
			}
			
			if($this->roomba->ContainsValue('bin')){
				if($this->roomba->GetValue('bin')->present){
					if($this->roomba->GetValue('bin')->full){
						SetValueInteger($this->GetIDForIdent("Bin"), 2);
					}else{
						SetValueInteger($this->GetIDForIdent("Bin"), 1);
					}
				}else{
					SetValueInteger($this->GetIDForIdent("Bin"), 0);
				}
			}
			
			$this->CheckMissionStatus();
			$this->Disconnect();
		}finally{
			SetValueInteger($this->GetIDForIdent("Control"), 0);
		}
	}
}