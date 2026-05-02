<?php

namespace App;

class WanConfigBuilder
{
    public static function buildParameterValues(array $device, int $connectionIndex, string $connectionType, array $parameters): array
    {
        $connectionType = strtolower($connectionType) === 'ppp' ? 'ppp' : 'ip';
        $normalized = self::normalizeInputParameters($parameters);
        $triples = [];

        foreach ($normalized as $key => $value) {
            $candidates = self::candidatePaths($connectionIndex, $connectionType, $key);
            if (empty($candidates)) {
                continue;
            }

            $selected = self::selectPaths($device, $candidates);
            $type = self::valueType($value);
            foreach ($selected as $path) {
                $triples[] = [$path, $value, $type];
            }
        }

        return self::dedupeTriples($triples);
    }

    public static function buildObjectPath(int $connectionIndex, string $connectionType): string
    {
        $connectionType = strtolower($connectionType) === 'ppp' ? 'ppp' : 'ip';
        $suffix = $connectionType === 'ppp' ? 'WANPPPConnection' : 'WANIPConnection';
        return "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.{$connectionIndex}.{$suffix}";
    }

    public static function buildDeleteTarget(int $connectionIndex, string $connectionType): string
    {
        return self::buildObjectPath($connectionIndex, $connectionType) . '.1';
    }

    private static function normalizeInputParameters(array $parameters): array
    {
        $normalized = [];

        foreach ($parameters as $key => $value) {
            $targetKey = match ($key) {
                'X_CT-COM_ServiceList', 'service_list', 'ServiceList' => 'ServiceList',
                'X_CT-COM_VLANID', 'vlan_id', 'VLANID' => 'VLANID',
                'X_CT-COM_LanInterface', 'lan_interface', 'LanInterface' => 'LanInterface',
                default => $key,
            };

            $normalized[$targetKey] = $value;
        }

        return $normalized;
    }

    private static function candidatePaths(int $connectionIndex, string $connectionType, string $key): array
    {
        $igdBase = "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.{$connectionIndex}." .
            ($connectionType === 'ppp' ? 'WANPPPConnection.1' : 'WANIPConnection.1');
        $tr181Base = $connectionType === 'ppp'
            ? "Device.PPP.Interface.{$connectionIndex}"
            : "Device.IP.Interface.{$connectionIndex}";
        $natBase = "Device.NAT.InterfaceSetting.{$connectionIndex}";

        return match ($key) {
            'Name' => [
                "{$igdBase}.Name",
                "{$tr181Base}.Alias",
                "{$tr181Base}.Name",
            ],
            'Enable' => [
                "{$igdBase}.Enable",
                "{$tr181Base}.Enable",
            ],
            'ConnectionType' => [
                "{$igdBase}.ConnectionType",
                "{$igdBase}.AddressingType",
            ],
            'Username' => $connectionType === 'ppp' ? [
                "{$igdBase}.Username",
                "{$tr181Base}.Username",
            ] : [],
            'Password' => $connectionType === 'ppp' ? [
                "{$igdBase}.Password",
                "{$tr181Base}.Password",
            ] : [],
            'NATEnabled' => [
                "{$igdBase}.NATEnabled",
                "{$natBase}.Enable",
            ],
            'ServiceList' => [
                "{$igdBase}.X_CT-COM_ServiceList",
                "{$igdBase}.X_HW_SERVICELIST",
                "{$igdBase}.X_CMCC_ServiceList",
                "{$igdBase}.X_FH_ServiceList",
            ],
            'VLANID' => [
                "{$igdBase}.X_CT-COM_VLANID",
                "{$igdBase}.X_HW_VLAN",
                "{$igdBase}.X_CMCC_VLANIDMark",
                "{$igdBase}.VLANID",
            ],
            'LanInterface' => [
                "{$igdBase}.X_CT-COM_LanInterface",
                "{$igdBase}.X_HW_LANBIND",
            ],
            default => [],
        };
    }

    private static function selectPaths(array $device, array $candidates): array
    {
        $existing = [];
        foreach ($candidates as $path) {
            if (self::pathExists($device, $path)) {
                $existing[] = $path;
            }
        }

        return !empty($existing) ? $existing : [reset($candidates)];
    }

    private static function pathExists(array $device, string $path): bool
    {
        $value = $device;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return false;
            }
            $value = $value[$segment];
        }

        return true;
    }

    private static function valueType(mixed $value): string
    {
        if (is_bool($value)) {
            return 'xsd:boolean';
        }
        if (is_int($value)) {
            return 'xsd:unsignedInt';
        }
        if (is_float($value)) {
            return 'xsd:decimal';
        }
        return 'xsd:string';
    }

    private static function dedupeTriples(array $triples): array
    {
        $seen = [];
        $output = [];

        foreach ($triples as $triple) {
            $key = json_encode($triple);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $output[] = $triple;
        }

        return $output;
    }
}
