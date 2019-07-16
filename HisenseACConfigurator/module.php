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

	public function GetConfigurationForm(){
		$SplitterID = $this->GetSplitter();
        if ($SplitterID === false) {
            $Form['actions'][] = [
                "type"  => "PopupAlert",
                "popup" => [
                    "items" => [[
                    "type"    => "Label",
                    "caption" => "Not connected to Splitter."
                        ]]
                ]
            ];
		}
		
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if (IPS_GetInstance($SplitterID)['InstanceStatus'] != IS_ACTIVE) {
            $Form['actions'][] = [
                "type"  => "PopupAlert",
                "popup" => [
                    "items" => [[
                    "type"    => "Label",
                    "caption" => "Instance has no active parent."
                        ]]
                ]
            ];
		}

		$data = $this->GetDevices();
		
        foreach ($data as $dev) {
			$device = $dev->device;
            $Value = [
				'name'  => $device->product_name,
				'mac'	=> $device->mac,
				'state'	=> $device->connection_status,
                'create' => [
                    'moduleID'      => '{CAE274A0-7E22-9376-0884-4D5C3BAD5CDB}',
                    'configuration' => ['DeviceKey' => $device->key]
                ]
            ];
            $InstanzID = $this->SearchDeviceInstance($SplitterID, '{CAE274A0-7E22-9376-0884-4D5C3BAD5CDB}', $device->key);
            if ($InstanzID == false) {
                $Value['location'] = '';
            } else {
                $Value['name'] = IPS_GetName($InstanzID);
                $Value['location'] = stristr(IPS_GetLocation($InstanzID), IPS_GetName($InstanzID), true);
                $Value['instanceID'] = $InstanzID;
            }
            $Values[] = $Value;
        }
        
		$Form['actions'][0]['values'] = $Values;
		
        return json_encode($Form);
	}

	private function GetDevices(){
		$data = array(
			"DataID" => "{57FE6DCC-13F1-4BDA-DE85-D08D408BA58A}",
			"command" => "GetDevices"
		);
		$data_string = json_encode($data);
		$result = $this->SendDataToParent($data_string);

		$jsonData = json_decode($result);

		return $jsonData;
	}

	/**
     * Liefert den aktuell verbundenen Splitter.
     *
     * @access private
     * @return bool|int FALSE wenn kein Splitter vorhanden, sonst die ID des Splitter.
     */
    private function GetSplitter()
    {
        $SplitterID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($SplitterID == 0) {
            return false;
        }
        return $SplitterID;
	}
	
    private function SearchDeviceInstance(int $SplitterID, string $ModuleID, int $DeviceKey)
    {
        $InstanceIDs = IPS_GetInstanceListByModuleID($ModuleID);
        foreach ($InstanceIDs as $InstanceID) {
            if (IPS_GetInstance($InstanceID)['ConnectionID'] == $SplitterID) {
                if (IPS_GetProperty($InstanceID, 'DeviceKey') == $DeviceKey) {
                    return $InstanceID;
                }
            }
        }
        return false;
    }
}