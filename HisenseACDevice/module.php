<?
// Klassendefinition
class HisenseACDevice extends IPSModule {

	private const AC_PROPERTIES = [
		'f_temp_in', 
		't_temp', 
		't_power', 
		't_fan_leftright', 
		't_fan_power', 
		't_fan_mute', 
		't_eco', 
		't_backlight', 
		't_work_mode', 
		't_fan_speed'
	];

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

		//We need to call the RegisterHook function on Kernel READY
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);

		$this->ConnectParent("{1BE067BC-6499-49F2-A8C9-BB6DB6B4ADF6}");

		//These lines are parsed on Symcon Startup or Instance creation
		//You cannot use variables here. Just static values.
		$this->RegisterPropertyInteger("DeviceKey", 0);
		$this->RegisterAttributeString('LANKey', '');
		$this->RegisterAttributeString('LANIP', '');
		$this->RegisterAttributeInteger('LANKeyId', 0);

		$this->SetBuffer("Registered", false);
		$this->SetJSONBuffer("CommandQueue", []);

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
			$this->SendDebug("Create", "Update on create");
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

		//Only call this in READY state. On startup the WebHook instance might not be available yet
        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->RegisterHook();
        }

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
		//Never delete this line!
		parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);
		
		if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) {
			$this->RegisterHook();
			return;
        }

		$roomTempId = $this->ReadPropertyInteger('RoomTemperature') > 0 ? $this->ReadPropertyInteger('RoomTemperature') : $this->GetIDForIdent('f_temp_in');

		switch($SenderID){
			case $this->GetSplitter():
				if($Message != IM_CHANGESTATUS) break;
				$ins = IPS_GetInstance($SenderID);
				if($ins['InstanceStatus'] == 102){
					$this->SetTimerInterval("UpdateTimer", 5000);
					//$this->Update();
				}else{
					$this->SetTimerInterval("UpdateTimer", 10000);
				}
				break;

			case $roomTempId:
			case $this->GetIDForIdent('AutoCooling'):
			case $this->ReadPropertyInteger('OutsideTemperature'):
			case $this->ReadPropertyInteger('PresenceVariable'):
				if($Message != VM_UPDATE || !$Data[1]) break; //$Data[1] = Variable changed
				//$this->CheckAutocool();
				break;

			default:
				$this->SendDebug("MessageSink", "Message from SenderID ".$SenderID." with Message ".$Message."\r\n Data: ".print_r($Data, true),0);
		}
	}

	private function GetJSONBuffer($name)
	{
		$raw = $this->GetBuffer($name);
		return json_decode($raw);
	}

	private function SetJSONBuffer($name, $value)
	{
		$this->SetBuffer($name, json_encode($value));
	}

	private function RegisterHook()
    {
		$WebHook = '/hook/HisenseACDevice/'.$this->ReadPropertyInteger("DeviceKey");

        $ids = IPS_GetInstanceListByModuleID('{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}');
        if (count($ids) > 0) {
            $hooks = json_decode(IPS_GetProperty($ids[0], 'Hooks'), true);
            $found = false;
            foreach ($hooks as $index => $hook) {
                if ($hook['Hook'] == $WebHook) {
                    if ($hook['TargetID'] == $this->InstanceID) {
                        return;
                    }
                    $hooks[$index]['TargetID'] = $this->InstanceID;
                    $found = true;
                }
            }
            if (!$found) {
                $hooks[] = ['Hook' => $WebHook, 'TargetID' => $this->InstanceID];
            }
            IPS_SetProperty($ids[0], 'Hooks', json_encode($hooks));
            IPS_ApplyChanges($ids[0]);
        }
    }

	private function CheckAutocool(){
		$this->SendDebug("CheckAutocool", "Start", 0);
		if(!$this->GetValue('AutoCooling')) return; //Autocooling disabled
		$outsideOn = $this->ReadPropertyInteger('OutsideTemperature') > 0 ? GetValueFloat($this->ReadPropertyInteger('OutsideTemperature')) > $this->ReadPropertyFloat('OutsideMinimumTemperature') : true;
		$roomTempId = $this->ReadPropertyInteger('RoomTemperature') > 0 ? $this->ReadPropertyInteger('RoomTemperature') : $this->GetIDForIdent('f_temp_in');
		$roomTemp = GetValueFloat($roomTempId);
		$upperTempReached = $roomTemp > ($this->GetValue('TargetTemperature') + $this->ReadPropertyFloat('OnHysteresis'));
		$lowerTempReached = $roomTemp < ($this->GetValue('TargetTemperature') - $this->ReadPropertyFloat('OffHysteresis'));
		$presenceOn = $this->ReadPropertyInteger('PresenceVariable') > 0 ? GetValueBoolean($this->ReadPropertyInteger('PresenceVariable')) : true;
		$currentPower = $this->GetValue('t_power');
		$this->SendDebug("CheckAutocool", "Power: $currentPower ;OutsideOn: $outsideOn ; UpperTempReached: $upperTempReached ; LowerTempReached: $lowerTempReached ; Presence: $presenceOn", 0);
		if($currentPower){
			//Device is on - check if we should turn off
			if(!$outsideOn || $lowerTempReached){
				$this->LogMessage("Switching AC off", KL_MESSAGE);
				$this->RequestAction('t_power', false, true);
			}else if(!$presenceOn && !$this->ReadAttributeBoolean('OffTimerEnabled')){
				$this->EnableOffTimer();
			}
		}else{
			//Device is off - check if we should turn on
			if(!$presenceOn || !$outsideOn || !$upperTempReached) return;
			$this->LogMessage("Switching AC on", KL_MESSAGE);
			$this->DisableOffTimer();
			$this->RequestAction('t_power', true, true);
			$this->RequestAction('t_work_mode', 2); //Cooling
			$this->RequestAction('t_temp', $this->GetValue('TargetTemperature'));
		}
	}

	private function EnableOffTimer(){
		$presTrail = $this->ReadPropertyInteger('PresenceTrailing');
		if($presTrail > 0){
			$this->LogMessage("Switching AC off delayed because not present anymore", KL_MESSAGE);
			$this->SetTimerInterval('OffTimer', $presTrail * 60000);
			$this->WriteAttributeBoolean('OffTimerEnabled', true);
		}else{
			$this->LogMessage("Switching AC off because not present anymore", KL_MESSAGE);
			$this->SetTimerInterval('OffTimer', 0);
			$this->WriteAttributeBoolean('OffTimerEnabled', false);
			$this->RequestAction('t_power', false, true);
		}
	}

	private function DisableOffTimer(){
		$this->SetTimerInterval('OffTimer', 0);
		$this->WriteAttributeBoolean('OffTimerEnabled', false);
	}

	public function RequestAction($Ident, $Value, $Automatic = false) {
		$splitter = $this->GetSplitter();
		$ins = IPS_GetInstance($splitter);
		if(!$this->GetBuffer('Registered') && $ins['InstanceStatus'] <> 102){
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
				return;

			default:
				throw new Exception("Invalid Ident");
		}

		$this->SetACProperty($Ident, $Value);
		$this->SetValue($Ident, $SetValue);
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
		$this->SendDebug("Update", "", 0);

		if(!$this->GetBuffer('Registered') && count($this->GetJSONBuffer('CommandQueue')) == 0){
			$this->GetACProperty('f_temp_in', false);
			$this->GetACProperty('t_temp', false);
			$this->GetACProperty('t_power', false);
			$this->GetACProperty('t_backlight', false);
			$this->GetACProperty('t_eco', false);
			$this->GetACProperty('t_fan_leftright', false);
			$this->GetACProperty('t_fan_mute', false);
			$this->GetACProperty('t_fan_power', false);
			$this->GetACProperty('t_fan_speed', false);
			$this->GetACProperty('t_work_mode', true);
		}else{
			$this->NotifyAC(count($this->GetJSONBuffer('CommandQueue')) == 0 ? false : true);
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

	private function GetDeviceIP()
	{
		$data = array(
			"DataID"	=> "{D1095CBF-91B6-27E5-A1CB-BB23267A1B33}",
			"command"	=> "GetDevices"
		);
		$data_string = json_encode($data);
		$result = $this->SendDataToParent($data_string);
		
		$jsonData = json_decode($result);

		if(!is_array($jsonData) && !is_object($jsonData)){
			$this->LogMessage("Failed to get devices.", KL_WARNING);
			return;
		}

		foreach($jsonData as $dev){
			$device = $dev->device;
			if(!$device->key == $this->ReadPropertyInteger("DeviceKey")) continue;
			
			$this->WriteAttributeString('LANIP', $device->lan_ip);
			$this->LogMessage("Got LAN IP ".$device->lan_ip, KL_MESSAGE);
			break;
		}
	}

	private function GetLANKey()
	{
		$data = array(
			"DataID"	=> "{D1095CBF-91B6-27E5-A1CB-BB23267A1B33}",
			"command"	=> "GetLANKey",
			'DeviceKey' => $this->ReadPropertyInteger("DeviceKey")
		);
		$data_string = json_encode($data);
		$result = $this->SendDataToParent($data_string);
		
		$jsonData = json_decode($result);

		if(!is_array($jsonData) && !is_object($jsonData)){
			$this->LogMessage("Failed to get devices.", KL_WARNING);
			return;
		}

		$this->WriteAttributeString('LANKey', $jsonData->lanip->lanip_key);
		$this->WriteAttributeString('LANKeyId', $jsonData->lanip->lanip_key_id);

		$this->LogMessage("Got LAN Key ".$jsonData->lanip->lanip_key_id.": ".$jsonData->lanip->lanip_key, KL_MESSAGE);
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

	private function NotifyAC($hasCmdsInQueue = false)
	{
		$this->SetTimerInterval("UpdateTimer", 10000);

		if(!$this->ReadAttributeString('LANIP')){
			$this->GetDeviceIP();
		}
		if(!$this->ReadAttributeInteger('LANKeyId')){
			$this->GetLANKey();
		}

		$ch = curl_init('http://'.$this->ReadAttributeString('LANIP').'/local_reg.json');
		if($this->GetBuffer('Registered')){
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
		} else {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		}
		
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$data = [
			'local_reg' => [
				'ip' 	=> '10.87.17.230',
				'port'	=> 80,
				'uri'	=> '/hook/HisenseACDevice/'.$this->ReadPropertyInteger("DeviceKey"),
				'notify'=> $hasCmdsInQueue ? 1 : 0
				]
			];
			
		$data_json = json_encode($data);
		$this->SendDebug("NotifyAC", $data_json, 0);

		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Content-Length: '.strlen($data_json)
		]);

		curl_exec($ch);
		curl_close($ch);
	}

	public function GetACProperty($property, $notify = true)
	{
		$this->SendDebug("GetACProperty", $property);
		IPS_SemaphoreEnter('HisenseACDevice'.$this->ReadPropertyInteger("DeviceKey"), 1000);
		try{
			$cmdId = $this->GetBuffer('NextCmdId');
			$cmd = [
				'cmds' => [
					[
						'cmd' => [
							'cmd_id'    => $cmdId,
							'method'    => 'GET',
							'resource'  => 'property.json?name='.$property,
							'data'      => '',
							'uri'       => '/hook/HisenseACDevice/'.$this->ReadPropertyInteger("DeviceKey").'/property/datapoint.json'
						]
					]
				]
			];
			$this->SetBuffer('NextCmdId', $cmdId + 1);

			$cmdQueue = $this->GetJSONBuffer("CommandQueue");
			array_push($cmdQueue, $cmd);
			$this->SetJSONBuffer("CommandQueue", $cmdQueue);
		} finally {
			IPS_SemaphoreLeave('HisenseACDevice'.$this->ReadPropertyInteger("DeviceKey"));
		}

		if($notify) $this->NotifyAC(true);
	}

	public function SetACProperty($property, $value)
	{
		$this->SendDebug("SetACProperty", $property);
		if(!in_array($property, AC_PROPERTIES)) throw new Exception('Unknown property');
		$baseType = 'boolean';
		switch($property){
			case 't_fan_speed':
			case 't_sleep':
			case 't_temp':
			case 't_work_mode':
				$baseType = 'integer';
				break;
		}

		$cmd = [
			'properties' => [
				[
					'property' => [
						'base_type' => $baseType,
						'value'  	=> $value,
						'name'      => $property
					]
				]
			]
		];
		
		IPS_SemaphoreEnter('HisenseACDevice'.$this->ReadPropertyInteger("DeviceKey"), 1000);
		try{
			$cmdQueue = $this->GetJSONBuffer("CommandQueue");
			array_push($cmdQueue, $cmd);
			$this->SetJSONBuffer("CommandQueue", $cmdQueue);
		} finally {
			IPS_SemaphoreLeave('HisenseACDevice'.$this->ReadPropertyInteger("DeviceKey"));
		}

		$this->NotifyAC(true);
	}

	/**
     * This function will be called by the hook control. Visibility should be protected!
     */
    protected function ProcessHookData()
    {
		$this->SendDebug("ProcessHookData", $_SERVER['REQUEST_URI']);
		$hookBase = '/hook/HisenseACDevice/'.$this->ReadPropertyInteger("DeviceKey").'/';
		$input = file_get_contents("php://input");
		$input_json = json_decode($input);

		switch($_SERVER['REQUEST_URI']){
			case $hookBase.'key_exchange.json':
				$result = ProcessKeyExchange($input_json->key_exchange->random_1, $input_json->key_exchange->time_1, $input_json->key_exchange->key_id);
				break;

			case $hookBase.'commands.json':
				$result = GetCommand();
				break;

			case $hookBase.'property/datapoint.json':
				ProcessDatapoint($input_json);
				break;

			case $hookBase.'fin.json':
				$this->SetBuffer('Registered', false);
				break;
		}
		
		if($result){
			$result_raw = json_encode($result, JSON_UNESCAPED_SLASHES);
			header("Content-type: application/json");
			echo $result_raw;
		}else{
			$result_raw = json_encode([]);
			header("HTTP/1.1 204 Empty");
			header("Content-type: application/json");
			echo $result_raw;
		}
	}

	private function ProcessDatapoint($input_json)
	{
		$this->SendDebug("ProcessDatapoint", "");
		IPS_SemaphoreEnter('HisenseACDevice'.$this->ReadPropertyInteger("DeviceKey"), 1000);
		try{
			$seq_raw = openssl_decrypt($input_json->enc, 'AES-256-CBC', $this->GetBuffer('KeyDevEnc'), OPENSSL_ZERO_PADDING, $this->GetBuffer('KeyDevIV'));
			//Set IV
			$this->SetBuffer('KeyDevIV', substr(base64_decode($input_json->enc), -16));

			//Signature not checked yet -> TODO

			$seq_json = rtrim($seq_raw, chr(0)); 
			$seq = json_decode($seq_json, false, 512, JSON_THROW_ON_ERROR);

			if(!in_array($seq->data->name, AC_PROPERTIES)) return;

			$val = $seq->data->value;

			switch($seq->data->name){
				case 'f_temp_in':
				case 't_temp':
					$oldVal = $val;
					$val = $this->FahrenheitToCelsius($oldVal);
					$this->SendDebug("Update", "Converted $propName $oldVal °F to $val °C");
					break;

			}

			$this->SetValue($seq->data->name, $val);
		} finally {
			IPS_SemaphoreLeave('HisenseACDevice'.$this->ReadPropertyInteger("DeviceKey"));
		}
	}
	
	private function GetCommand()
	{
		$this->SendDebug("GetCommand", "");
		if(!$this->GetBuffer("Registered")) return;

		IPS_SemaphoreEnter('HisenseACDevice'.$this->ReadPropertyInteger("DeviceKey"), 1000);
		try{
			$cmdQueue = $this->GetJSONBuffer("CommandQueue");
			if(count($cmdQueue) == 0) return;

			$cmd = array_shift($cmdQueue);
			$this->SetJSONBuffer("CommandQueue", $cmdQueue);

			$seqNo = $this->GetBuffer('NextSequenceNo');
			$this->SetBuffer('NextSequenceNo', $seqNo + 1);

			$seq = [
				'seq_no' => $seqNo,
				'data'   => $cmd
			];
			$seq_json = utf8_encode(json_encode($seq, JSON_UNESCAPED_SLASHES));
			$seq_enc = openssl_encrypt($this->Pad($seq_json.chr(0), 16), 'AES-256-CBC', $this->GetBuffer('KeyAppEnc'), OPENSSL_ZERO_PADDING, $this->GetBuffer('KeyAppIV'));
			//Set IV
			$this->SetBuffer('KeyAppIV', substr(base64_decode($seq_enc), -16));

			$sign = hash_hmac('sha256', $seq_json, $this->GetBuffer('KeyAppSign'), true);

			$result = [
				'enc'   => $seq_enc,
				'sign'  => base64_encode($sign)
			];

			return $result;
		} finally {
			IPS_SemaphoreLeave('HisenseACDevice'.$this->ReadPropertyInteger("DeviceKey"));
		}
	}

	private function ProcessKeyExchange($random1, $time1, $keyId)
	{
		$this->SendDebug("ProcessKeyExchange", "");
		IPS_SemaphoreEnter('HisenseACDevice'.$this->ReadPropertyInteger("DeviceKey"), 1000);
		try{
			$this->SetBuffer("Registered", false);

			if(!$keyId == $this->GetAttributeInteger('LANKeyId')){
				$this->SendDebug("ProcessKeyExchange", "Received unknown LAN Key Id while key exchange - renew keys via cloud");
				$this->GetLANKey();
			}

			$random2 = utf8_encode(generateRandomString(16));
			$time2 = utf8_encode(time());

			$secret = utf8_encode($this->GetAttributeString('LANKey'));

			$sign_app_seed = $random1.$random2.$time1.$time2.'0';
			$enc_app_seed  = $random1.$random2.$time1.$time2.'1';
			$iv_app_seed   = $random1.$random2.$time1.$time2.'2';

			$sign_app_key = hash_hmac('sha256', hash_hmac('sha256', $sign_app_seed, $secret, true).$sign_app_seed, $secret, true);
			$enc_app_key  = hash_hmac('sha256', hash_hmac('sha256', $enc_app_seed, $secret, true).$enc_app_seed, $secret, true);
			$iv_app_key   = hash_hmac('sha256', hash_hmac('sha256', $iv_app_seed, $secret, true).$iv_app_seed, $secret, true);

			$sign_dev_seed = $random2.$random1.$time2.$time1.'0';
			$enc_dev_seed  = $random2.$random1.$time2.$time1.'1';
			$iv_dev_seed   = $random2.$random1.$time2.$time1.'2';

			$sign_dev_key = hash_hmac('sha256', hash_hmac('sha256', $sign_dev_seed, $secret, true).$sign_dev_seed, $secret, true);
			$enc_dev_key  = hash_hmac('sha256', hash_hmac('sha256', $enc_dev_seed, $secret, true).$enc_dev_seed, $secret, true);
			$iv_dev_key   = hash_hmac('sha256', hash_hmac('sha256', $iv_dev_seed, $secret, true).$iv_dev_seed, $secret, true);

			$this->SetBuffer("NextSequenceNo", 0);
			$this->SetBuffer("NextCmdId", 0);
			$this->SetBuffer("KeyAppSign", $sign_app_key);
			$this->SetBuffer("KeyAppEnc", $enc_app_key);
			$this->SetBuffer("KeyAppIV", substr($iv_app_key, 0, 16));
			$this->SetBuffer("KeyDevSign", $sign_dev_key);
			$this->SetBuffer("KeyDevEnc", $enc_dev_key);
			$this->SetBuffer("KeyDevIV", substr($iv_dev_key, 0, 16));
			$this->SetBuffer("Registered", true);

			$return = [
				'random_2'  => $random2,
				'time_2'    => $time2
			];

			return $return;
		} finally {
			IPS_SemaphoreLeave('HisenseACDevice'.$this->ReadPropertyInteger("DeviceKey"));
		}	
	}
	
	private function Pad($text, $block_size = 16)
	{
		$rest = strlen($text) % $block_size;
		$to_pad = $rest == 0 ? 0 : $block_size - $rest;
		return str_pad($text, strlen($text) + $to_pad, chr(0));
	}

	private function GenerateRandomString($length = 16)
	{
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;
	}
}