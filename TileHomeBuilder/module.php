<?php

declare(strict_types=1);

class TileHomeBuilder extends IPSModule
{
    private const MULTI_ROOM_MODULE = '{5D1D3B42-2B8E-4C5E-AE9A-4E8E2C00E9F4}';
    private const ROOM_MODULE = '{0E33BB6A-B5A7-4D25-883A-EDF0551DB5C3}';

    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyInteger('RoomRootID', 0);
        $this->RegisterAttributeString('Status', 'Bitte die Raumwurzel auswählen und die Erstellung starten.');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        if (!$this->isWithinTileHome($this->InstanceID)) {
            $this->updateStatusMessage('Bitte diese Generator-Instanz innerhalb Tile Home (52221) ablegen.');
            return;
        }
        $this->updateStatusMessage('Bereit. Die Generierung verändert ausschließlich Objekte innerhalb Tile Home (52221).');
    }

    public function Generate(): void
    {
        try {
            $result = $this->generateTiles();
            $message = $result['rooms'] . ' Räume mit ' . $result['items'] . ' Statusvariablen aktualisiert.';
            $this->updateStatusMessage($message);
        } catch (Throwable $error) {
            $this->updateStatusMessage('Fehler: ' . $error->getMessage());
            $this->SendDebug(__FUNCTION__, $error->getMessage(), 0);
        }
    }

    private function generateTiles(): array
    {
        $visualizationId = 42 * 1000 + 569;
        $tileHomeId = 52 * 1000 + 221;
        if (!IPS_ObjectExists($visualizationId) || !IPS_ObjectExists($tileHomeId)) {
            throw new RuntimeException('Visualization (42569) oder Tile Home (52221) wurde nicht gefunden.');
        }
        $visualization = IPS_GetObject($visualizationId);
        $tileHome = IPS_GetObject($tileHomeId);
        if (($visualization['ObjectName'] ?? '') !== 'Visualization'
            || ($tileHome['ObjectName'] ?? '') !== 'Tile Home'
            || (int)($tileHome['ParentID'] ?? 0) !== $visualizationId
            || !$this->isWithinTileHome($this->InstanceID)) {
            throw new RuntimeException('Die erlaubte Zielhierarchie ist nicht vorhanden oder die Generatorinstanz liegt außerhalb davon.');
        }

        $rootId = $this->ReadPropertyInteger('RoomRootID');
        if ($rootId <= 0 || !IPS_ObjectExists($rootId) || (int)IPS_GetObject($rootId)['ObjectType'] !== 0) {
            throw new RuntimeException('Bitte eine bestehende Kategorie als Raumwurzel auswählen.');
        }
        $moduleIds = IPS_GetModuleList();
        if (!in_array(self::MULTI_ROOM_MODULE, $moduleIds, true)
            || !in_array(self::ROOM_MODULE, $moduleIds, true)) {
            throw new RuntimeException('Bitte das Community-Modul Room Tiles mit Multi Room Tile und Room Tile installieren.');
        }

        $houseId = $this->findChildCategory($tileHomeId, 'Haus');
        if ($houseId <= 0) {
            throw new RuntimeException('Unter Tile Home wurde die Kategorie Haus nicht gefunden.');
        }
        $prepared = [];
        $totalItems = 0;
        foreach (IPS_GetChildrenIDs($rootId) as $childId) {
            $room = IPS_GetObject($childId);
            if ((int)($room['ObjectType'] ?? -1) !== 0) {
                continue;
            }
            $variables = [];
            $this->collectVariables((int)$childId, $variables);
            if ($variables === []) {
                continue;
            }
            $items = [];
            foreach ($variables as $variableId) {
                $items[] = $this->makeInfoItem((int)$variableId);
            }
            $items = array_values(array_filter($items));
            if ($items === []) {
                continue;
            }
            $prepared[] = ['key'=>(string)($room['ObjectIdent'] ?: $childId),
                'name'=>(string)$room['ObjectName'], 'items'=>$items];
            $totalItems += count($items);
        }
        if ($prepared === []) {
            throw new RuntimeException('Unter der gewählten Kategorie wurden keine Räume mit Variablen gefunden.');
        }

        $detailRoot = $this->ensureCategory($houseId, 'THB_RoomDetails', 'Raumdetails');
        $overviewRows = [];
        foreach ($prepared as $index => $room) {
            $roomIdent = 'THB_Room_' . substr(sha1($room['key']), 0, 16);
            $roomCategory = $this->ensureCategory($detailRoot, $roomIdent, $room['name']);
            $roomTile = $this->ensureInstance($roomCategory, self::ROOM_MODULE, $roomIdent . '_Tile', 'Status');
            $this->applyProperties($roomTile, [
                'RoomName'=>$room['name'],
                'Target'=>$tileHomeId,
                'InfoItems'=>json_encode($room['items'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'MenuItems'=>'[]',
                'InfoTopCentered'=>true,
            ]);
            $overviewRows[] = [
                'RoomName'=>$room['name'],
                'Target'=>$roomCategory,
                'BackgroundImage'=>0,
                'InfoItems'=>json_encode($room['items'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'MenuItems'=>'[]',
                'InfoTopCentered'=>false,
                'ShowRoomName'=>true,
            ];
            IPS_SetPosition($roomCategory, $index);
        }

        $overview = $this->ensureInstance($tileHomeId, self::MULTI_ROOM_MODULE,
            'THB_RoomStatusOverview', 'Raumstatus');
        $this->applyProperties($overview, [
            'Rooms'=>json_encode($overviewRows, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'Columns'=>0,
            'MinWidth'=>260,
            'MinHeight'=>210,
            'Gap'=>12,
            'BorderRadius'=>12,
            'UseFullTileHeight'=>true,
        ]);
        return ['rooms'=>count($prepared), 'items'=>$totalItems];
    }

    private function collectVariables(int $categoryId, array &$variables, int $depth = 0): void
    {
        if ($depth > 20) {
            return;
        }
        foreach (IPS_GetChildrenIDs($categoryId) as $childId) {
            $object = IPS_GetObject($childId);
            $type = (int)($object['ObjectType'] ?? -1);
            if ($type === 2) {
                $variables[] = (int)$childId;
            } elseif ($type === 0) {
                $this->collectVariables((int)$childId, $variables, $depth + 1);
            }
        }
    }

    private function makeInfoItem(int $variableId): ?array
    {
        if (!IPS_VariableExists($variableId)) {
            return null;
        }
        $variable = IPS_GetVariable($variableId);
        $name = (string)(IPS_GetObject($variableId)['ObjectName'] ?? IPS_GetName($variableId));
        return [
            'Id'=>'thb' . substr(sha1((string)$variableId), 0, 12),
            'Area'=>(($variableId % 2) === 0 ? 'left' : 'right'),
            'VariableId'=>$variableId,
            'ShowName'=>true,
            'ShowIcon'=>true,
            'ShowValue'=>true,
            'UseVarColor'=>(int)($variable['VariableType'] ?? -1) === 0,
            'ColorTrue'=>-1,
            'ColorFalse'=>-1,
            'AltName'=>mb_substr($name, 0, 42),
        ];
    }

    private function findChildCategory(int $parentId, string $name): int
    {
        foreach (IPS_GetChildrenIDs($parentId) as $childId) {
            $object = IPS_GetObject($childId);
            if ((int)($object['ObjectType'] ?? -1) === 0 && (string)$object['ObjectName'] === $name) {
                return (int)$childId;
            }
        }
        return 0;
    }

    private function ensureCategory(int $parentId, string $ident, string $name): int
    {
        foreach (IPS_GetChildrenIDs($parentId) as $childId) {
            $object = IPS_GetObject($childId);
            if ((string)($object['ObjectIdent'] ?? '') === $ident) {
                if ((int)($object['ObjectType'] ?? -1) !== 0) {
                    throw new RuntimeException('Ein Generator-Kennzeichen ist bereits durch ein anderes Objekt belegt.');
                }
                if ((string)$object['ObjectName'] !== $name) {
                    IPS_SetName($childId, $name);
                }
                return (int)$childId;
            }
        }
        $id = IPS_CreateCategory();
        IPS_SetParent($id, $parentId);
        IPS_SetName($id, $name);
        IPS_SetIdent($id, $ident);
        return $id;
    }

    private function ensureInstance(int $parentId, string $moduleId, string $ident, string $name): int
    {
        foreach (IPS_GetChildrenIDs($parentId) as $childId) {
            $object = IPS_GetObject($childId);
            if ((string)($object['ObjectIdent'] ?? '') !== $ident) {
                continue;
            }
            $instance = IPS_GetInstance($childId);
            if ((int)($object['ObjectType'] ?? -1) !== 1
                || (string)($instance['ModuleInfo']['ModuleID'] ?? '') !== $moduleId) {
                throw new RuntimeException('Ein Generator-Kennzeichen ist bereits durch eine andere Instanz belegt.');
            }
            if ((string)$object['ObjectName'] !== $name) {
                IPS_SetName($childId, $name);
            }
            return (int)$childId;
        }
        $id = IPS_CreateInstance($moduleId);
        IPS_SetParent($id, $parentId);
        IPS_SetName($id, $name);
        IPS_SetIdent($id, $ident);
        return $id;
    }

    private function applyProperties(int $instanceId, array $properties): void
    {
        foreach ($properties as $name => $value) {
            if (!IPS_SetProperty($instanceId, (string)$name, $value)) {
                throw new RuntimeException('Die Room-Tiles-Eigenschaft ' . $name . ' konnte nicht gesetzt werden.');
            }
        }
        if (!IPS_ApplyChanges($instanceId)) {
            throw new RuntimeException('Die Room-Tiles-Konfiguration konnte nicht übernommen werden.');
        }
    }

    private function isWithinTileHome(int $objectId): bool
    {
        $tileHomeId = 52 * 1000 + 221;
        $current = $objectId;
        while ($current > 0 && IPS_ObjectExists($current)) {
            if ($current === $tileHomeId) {
                return true;
            }
            $current = (int)(IPS_GetObject($current)['ParentID'] ?? 0);
        }
        return false;
    }

    private function updateStatusMessage(string $status): void
    {
        $this->WriteAttributeString('Status', $status);
        $this->UpdateFormField('Status', 'caption', $status);
    }
}

