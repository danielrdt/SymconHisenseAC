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
		$this->RegisterPropertyString("LocalAddress", '');
		$this->RegisterAttributeString('LANKey', '');
		$this->RegisterAttributeString('LANIP', '');
		$this->RegisterAttributeInteger('LANKeyId', 0);

		$this->SetJSONBuffer("Registered", false);
		$this->SetJSONBuffer("CommandQueue", []);
		$this->SetJSONBuffer("NextCmdId", 0);

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
		$this->RegisterPropertyFloat("Offset", 0.0);
		$this->RegisterPropertyFloat("OnHysteresis", 0.5);
		$this->RegisterPropertyFloat("OffHysteresis", 2);
		$this->RegisterPropertyFloat("OutsideMinimumTemperature", 18.0);
		$this->RegisterPropertyInteger("RoomTemperature", 0);
		$this->RegisterPropertyInteger("OutsideTemperature", 0);
		$this->RegisterPropertyInteger("PresenceVariable", 0);
		$this->RegisterPropertyInteger("PresenceTrailing", 0);

		//Timer
		$this->RegisterTimer("UpdateTimer", 0, 'HISENSEAC_Update($_IPS[\'TARGET\']);');
		$this->RegisterTimer("UpdatePropertiesTimer", 0, 'HISENSEAC_UpdateProperties($_IPS[\'TARGET\']);');
		$this->RegisterTimer("OfflineTimer", 30000, 'HISENSEAC_SetOffline($_IPS[\'TARGET\']);');
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
					$this->SetTimerInterval("UpdateTimer", 1000);
				}else{
					$this->SetTimerInterval("UpdateTimer", 10000);
				}
				break;

			case $roomTempId:
			case $this->GetIDForIdent('AutoCooling'):
			case $this->GetIDForIdent('TargetTemperature'):
			case $this->ReadPropertyInteger('OutsideTemperature'):
			case $this->ReadPropertyInteger('PresenceVariable'):
				if($Message != VM_UPDATE || !$Data[1]) break; //$Data[1] = Variable changed
				$this->CheckAutocool();
				break;

			default:
				$this->SendDebug("MessageSink", "Message from SenderID ".$SenderID." with Message ".$Message."\r\n Data: ".print_r($Data, true),0);
		}
	}

	private function GetJSONBuffer($name)
	{
		$raw = $this->GetBuffer($name);
		$data = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
		if($data->binary){
			return base64_decode($data->data);
		}
		return $data->data;
	}

	private function SetJSONBuffer($name, $value, $binary = false)
	{
		if($binary) $value = base64_encode($value);
		$data = [
			'data' 	=> $value,
			'binary'=> $binary
		];
		$json = json_encode($data, JSON_THROW_ON_ERROR);
		$this->SetBuffer($name, $json);
	}

	private function RegisterHook()
    {
		$WebHook = '/hook/'.$this->ReadPropertyInteger("DeviceKey");

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
				$this->LogMessage("Switching AC ".IPS_GetLocation($this->insId)." off by automatic", KL_NOTIFY);
				$this->RequestAction('t_power', false, true);
			}else if(!$presenceOn && !$this->ReadAttributeBoolean('OffTimerEnabled')){
				$this->EnableOffTimer();
			}
		}else{
			//Device is off - check if we should turn on
			if(!$presenceOn || !$outsideOn || !$upperTempReached) return;
			$this->LogMessage("Switching AC ".IPS_GetLocation($this->insId)." on", KL_NOTIFY);
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
		if(!$this->GetJSONBuffer('Registered') && $ins['InstanceStatus'] <> 102){
			$this->LogMessage("Could not send command because cloud not connected", KL_ERROR);
			throw new Exception($this->Translate("Cloud not connected"));
		}

		$SetValue = $Value;
		switch($Ident) {
			case 't_temp':
				$Value = round($this->CelsiusToFahrenheit($Value));
				$SetValue = $this->FahrenheitToCelsius($Value);
				$Value -= $this->ReadPropertyFloat('Offset');
				break;

			case 't_power':
				if(!$Automatic){
					$this->SetValue('AutoCooling', false);
					$this->DisableOffTimer();
				}
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
		$form = '{
			"elements":
			[
				{ "type": "Label", "caption": "LocalAddressLabel" },
				{ "type": "ValidationTextBox", "name": "LocalAddress", "caption": "LocalAddress" },
				{ "type": "Label", "caption": "OffsetLabel" },
				{ "type": "NumberSpinner", "name": "Offset", "caption": "Offset", "digits": 1, "suffix": "°C" },
				{ "type": "ExpansionPanel", "caption": "AutoRoomTemperature", "items": [
					{ "type": "SelectVariable", "name": "RoomTemperature", "caption": "RoomTemperatureOverride" },
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
		}';
		return $form;
	}

	/**
	 * Update AC -> If offline also request values for all properties
	 */
	public function Update(){
		if(empty($this->ReadPropertyString('LocalAddress'))) return;

		if(!$this->GetJSONBuffer('Registered') && count($this->GetJSONBuffer('CommandQueue')) == 0){
			$this->SetTimerInterval("UpdatePropertiesTimer", 60000);
			foreach(self::AC_PROPERTIES as $prop){
				$this->GetACProperty($prop, false);
			}
			$this->NotifyAC(true);
		}else{
			$this->NotifyAC(count($this->GetJSONBuffer('CommandQueue')) == 0 ? false : true);
		}
	}

	/**
	 * Update Properties -> If online also request values for all properties
	 */
	public function UpdateProperties(){
		if(empty($this->ReadPropertyString('LocalAddress'))) return;

		if($this->GetJSONBuffer('Registered')){
			foreach(self::AC_PROPERTIES as $prop){
				$this->GetACProperty($prop, false);
			}
			$this->NotifyAC(true);
		}
	}

	/**
	 * Set AC offline -> will force reestablish session
	 */
	public function SetOffline(){
		$this->SetTimerInterval("UpdatePropertiesTimer", 0);
		$this->LogMessage(IPS_GetLocation($this->insId)." now offline.", KL_WARNING);
		$this->SetTimerInterval("OfflineTimer", 0);
		$this->SetJSONBuffer('Registered', false);
	}

	/**
	 * Set AC offline and delete all stored information (CommandQueue, LAN IP, LAN Key, ...)
	 * Cloud Access is needed to establish communication again
	 */
	public function Reset(){
		$this->SetTimerInterval("UpdatePropertiesTimer", 0);
		$this->SetTimerInterval("OfflineTimer", 0);
		$this->SetJSONBuffer('Registered', false);
		$this->SetJSONBuffer("CommandQueue", []);
		$this->SetJSONBuffer("NextCmdId", 0);
		$this->WriteAttributeString('LANIP', '');
		$this->WriteAttributeInteger('LANKeyId', 0);
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
			if($device->key <> $this->ReadPropertyInteger("DeviceKey")) continue;
			
			$this->WriteAttributeString('LANIP', $device->lan_ip);
			$this->LogMessage("Got LAN IP for ".$this->ReadPropertyInteger("DeviceKey")." ".$device->lan_ip, KL_MESSAGE);
			break;
		}
	}

	private function GetLocalPort()
	{
		$webservers = IPS_GetInstanceListByModuleID('{D83E9CCF-9869-420F-8306-2B043E9BA180}');
		$port = 0;
		foreach($webservers as $server){
			if(IPS_GetProperty($server, 'EnableSSL')) continue; //Module may not able to use SSL
			if(IPS_GetProperty($server, 'Server') == '0.0.0.0' || IPS_GetProperty($server, 'Server') == $this->ReadPropertyString('LocalAddress')){
				return IPS_GetProperty($server, 'Port');
			}else{
				$port = IPS_GetProperty($server, 'Port');
			}
		}
		return $port;
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
		$this->WriteAttributeInteger('LANKeyId', $jsonData->lanip->lanip_key_id);

		$this->LogMessage("Got LAN Key ".$jsonData->lanip->lanip_key_id.": ".$jsonData->lanip->lanip_key, KL_MESSAGE);
	}

	private function FahrenheitToCelsius($given_value){
		$celsius = ($given_value - 32) * 5 / 9;
		return round($celsius);
	}

	private function CelsiusToFahrenheit($given_value){
		$fahrenheit = $given_value * 1.8 + 32;
		return round($fahrenheit);
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
		if($this->GetJSONBuffer('Registered')){
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
		} else {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		}
		
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$data = [
			'local_reg' => [
				'ip' 	=> $this->ReadPropertyString('LocalAddress'),
				'port'	=> $this->GetLocalPort(),
				'uri'	=> '/hook/'.$this->ReadPropertyInteger("DeviceKey"),
				'notify'=> $hasCmdsInQueue ? 1 : 0
				]
			];
			
		$data_json = json_encode($data, JSON_UNESCAPED_SLASHES);
		$this->SendDebug("NotifyAC", ($this->GetJSONBuffer('Registered') ? 'PUT ' : 'POST ').$data_json.' -> '.$this->ReadAttributeString('LANIP'), 0);

		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Content-Length: '.strlen($data_json)
		]);

		curl_exec($ch);

		$respCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		if($respCode === 202){
			//Accepted - reset offline timer
			$this->SetTimerInterval("OfflineTimer", 30000);
		}

		curl_close($ch);
	}

	/**
     * Request current property value from air condition
     *
     * @param string $property Name of property.
	 * @param bool $notify Should the ac notified about new command?
     */
	public function GetACProperty(string $property, bool $notify = true)
	{
		$this->SendDebug("GetACProperty", $property, 0);
		IPS_SemaphoreEnter('HisenseACDevice'.$this->ReadPropertyInteger("DeviceKey"), 1000);
		try{
			$cmdId = $this->GetJSONBuffer('NextCmdId');
			$cmdId = $cmdId ? $cmdId : 0;
			$cmd = [
				'cmds' => [
					[
						'cmd' => [
							'cmd_id'    => $cmdId,
							'method'    => 'GET',
							'resource'  => 'property.json?name='.$property,
							'data'      => '',
							'uri'       => '/hook/'.$this->ReadPropertyInteger("DeviceKey").'/property/datapoint.json'
						]
					]
				]
			];
			$this->SetJSONBuffer('NextCmdId', $cmdId + 1);

			$cmdQueue = $this->GetJSONBuffer("CommandQueue");
			array_push($cmdQueue, $cmd);
			$this->SetJSONBuffer("CommandQueue", $cmdQueue);
		} finally {
			IPS_SemaphoreLeave('HisenseACDevice'.$this->ReadPropertyInteger("DeviceKey"));
		}

		if($notify) $this->NotifyAC(true);
	}

	/**
     * Set property of air condition
     *
     * @param string $property Name of property.
	 * @param int $notify Should the ac notified about new command?
     */
	public function SetACProperty(string $property, int $value)
	{
		$this->SendDebug("SetACProperty", $property, 0);
		if(!in_array($property, self::AC_PROPERTIES)) throw new Exception('Unknown property');
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
		try{
			$this->SendDebug("ProcessHookData", $_SERVER['REQUEST_URI'], 0);
			$hookBase = '/hook/'.$this->ReadPropertyInteger("DeviceKey").'/';
			$input = file_get_contents("php://input");
			$input_json = json_decode($input);

			switch($_SERVER['REQUEST_URI']){
				case $hookBase.'key_exchange.json':
					$result = $this->ProcessKeyExchange($input_json->key_exchange->random_1, $input_json->key_exchange->time_1, $input_json->key_exchange->key_id);
					$result_raw = json_encode($result, JSON_UNESCAPED_SLASHES);
					header("HTTP/1.1 200 OK");
					header("Content-type: application/json");
					echo utf8_encode($result_raw);
					return;

				case $hookBase.'commands.json':
					$result = $this->GetCommand();
					if($result){
						$result_raw = json_encode($result, JSON_UNESCAPED_SLASHES);
						if(count($this->GetJSONBuffer("CommandQueue")) > 0){
							header("HTTP/1.1 206 Partial Content");
						}else{
							header("HTTP/1.1 200 OK");
						}
						header("Content-type: application/json");
						echo utf8_encode($result_raw);
					}else{
						$result_raw = json_encode([]);
						header("HTTP/1.1 204 Empty");
						header("Content-type: application/json");
						echo utf8_encode($result_raw);
						$this->SendDebug("ProcessHookData", "Emtpy Result", 0);
					}
					return;

				case $hookBase.'property/datapoint.json':
					$this->ProcessDatapoint($input_json);
					header("HTTP/1.1 200 OK");
					return;

				case $hookBase.'fin.json':
					$this->SetJSONBuffer('Registered', false);
					header("HTTP/1.1 200 OK");
					return;
			}
		} catch (Exception $e) {
			$this->SendDebug("ProcessHookData Error", $e->getMessage(), 0);
		}
	}

	private function ProcessDatapoint($input_json)
	{
		IPS_SemaphoreEnter('HisenseACDevice'.$this->ReadPropertyInteger("DeviceKey"), 1000);
		try{
			$seq_raw = openssl_decrypt($input_json->enc, 'AES-256-CBC', $this->GetJSONBuffer('KeyDevEnc'), OPENSSL_ZERO_PADDING, $this->GetJSONBuffer('KeyDevIV'));
			//Set IV
			$this->SetJSONBuffer('KeyDevIV', substr(base64_decode($input_json->enc), -16), true);

			//Signature not checked yet -> TODO

			$seq_json = rtrim($seq_raw, chr(0)); 
			$seq = json_decode($seq_json, false, 512, JSON_THROW_ON_ERROR);

			if(!in_array($seq->data->name, self::AC_PROPERTIES)){
				$this->SendDebug("ProcessDatapoint", "Invalid Datapoint (".$seq->data->name.")", 0);
				return;
			}

			$val = $seq->data->value;

			switch($seq->data->name){
				case 'f_temp_in':
				case 't_temp':
					$oldVal = $val;
					$val = $this->FahrenheitToCelsius($oldVal);
					$this->SendDebug("Update", "Converted ".$seq->data->name." $oldVal °F to $val °C", 0);
					$val += $this->ReadPropertyFloat('Offset');
					break;
				
				case 't_power':
				case 't_fan_leftright':
				case 't_fan_power':
				case 't_fan_mute':
				case 't_eco':
				case 't_backlight':
					$val = boolval($val);
					break;
			}

			$this->SendDebug("ProcessDatapoint", $seq->data->name." -> ".$val, 0);

			$this->SetValue($seq->data->name, $val);
		} finally {
			IPS_SemaphoreLeave('HisenseACDevice'.$this->ReadPropertyInteger("DeviceKey"));
		}
	}
	
	private function GetCommand()
	{
		$this->SendDebug("GetCommand", "", 0);
		if(!$this->GetJSONBuffer("Registered")) return;

		IPS_SemaphoreEnter('HisenseACDevice'.$this->ReadPropertyInteger("DeviceKey"), 1000);
		try{
			$cmdQueue = $this->GetJSONBuffer("CommandQueue");

			$cmd = [];
			if(count($cmdQueue) > 0){
				$cmd = array_shift($cmdQueue);
				$this->SetJSONBuffer("CommandQueue", $cmdQueue);
				//$this->SendDebug("GetCommand", "Removed command from queue:".print_r($cmd, true), 0);
			}

			$seqNo = $this->GetJSONBuffer('NextSequenceNo');
			$seqNo = $seqNo ? $seqNo : 0;
			$this->SetJSONBuffer('NextSequenceNo', $seqNo == 65536 ? 0 : $seqNo + 1); //Roll over because by documentation this is UInt16

			$seq = [
				'seq_no' => $seqNo,
				'data'   => $cmd
			];
			$seq_json = utf8_encode(json_encode($seq, JSON_UNESCAPED_SLASHES));
			$this->SendDebug("GetCommand", "Prepared sequence: ".$seq_json, 0);
			$seq_enc = openssl_encrypt($this->Pad($seq_json.chr(0), 16), 'AES-256-CBC', $this->GetJSONBuffer('KeyAppEnc'), OPENSSL_ZERO_PADDING, $this->GetJSONBuffer('KeyAppIV'));
			//Set IV
			$this->SetJSONBuffer('KeyAppIV', substr(base64_decode($seq_enc), -16), true);

			$sign = hash_hmac('sha256', $seq_json, $this->GetJSONBuffer('KeyAppSign'), true);

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
		$this->SendDebug("ProcessKeyExchange", "", 0);
		IPS_SemaphoreEnter('HisenseACDevice'.$this->ReadPropertyInteger("DeviceKey"), 1000);
		try{
			$this->SetJSONBuffer("Registered", false);

			if(!$keyId == $this->ReadAttributeInteger('LANKeyId')){
				$this->SendDebug("ProcessKeyExchange", "Received unknown LAN Key Id while key exchange - renew keys via cloud", 0);
				$this->GetLANKey();
			}

			$random2 = utf8_encode($this->GenerateRandomString(16));
			$time2 = utf8_encode(time());

			$secret = utf8_encode($this->ReadAttributeString('LANKey'));

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

			$this->SetJSONBuffer("NextSequenceNo", 0);
			$this->SetJSONBuffer("NextCmdId", 0);
			$this->SetJSONBuffer("KeyAppSign", $sign_app_key, true);
			$this->SetJSONBuffer("KeyAppEnc", $enc_app_key, true);
			$this->SetJSONBuffer("KeyAppIV", substr($iv_app_key, 0, 16), true);
			$this->SetJSONBuffer("KeyDevSign", $sign_dev_key, true);
			$this->SetJSONBuffer("KeyDevEnc", $enc_dev_key, true);
			$this->SetJSONBuffer("KeyDevIV", substr($iv_dev_key, 0, 16), true);
			$this->SetJSONBuffer("Registered", true);

			$return = [
				'random_2'  => $random2,
				'time_2'    => $time2
			];

			$this->LogMessage("Session for ".IPS_GetLocation($this->insId)." established", KL_NOTIFY);
			$this->SetTimerInterval("UpdatePropertiesTimer", 60000);

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