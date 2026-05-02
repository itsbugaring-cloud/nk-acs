<?php
namespace App;

/**
 * GenieACS API Client
 */
class GenieACS {
    private $host;
    private $port;
    private $username;
    private $password;
    private $baseUrl;
    private const ALLOWED_TASK_NAMES = [
        'getParameterValues',
        'setParameterValues',
        'refreshObject',
        'reboot',
        'factoryReset',
        'download',
        'addObject',
        'deleteObject',
        'provisions',
    ];

    public function __construct($host = null, $port = 7557, $username = null, $password = null) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->baseUrl = "http://{$this->host}:{$this->port}";
    }

    /**
     * Make HTTP request to GenieACS API
     */
    private function request($endpoint, $method = 'GET', $data = null) {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // Increased to 300 seconds (5 minutes) for large datasets (400+ devices)
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // Connection timeout 30 seconds

        // Add authentication if provided
        if ($this->username && $this->password) {
            curl_setopt($ch, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
        }

        // Set method and data
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'data' => json_decode($response, true),
            'http_code' => $httpCode
        ];
    }

    private function isAllowedTaskName($taskName) {
        if (!is_string($taskName)) {
            return false;
        }

        return in_array($taskName, self::ALLOWED_TASK_NAMES, true);
    }

    private function isListArray(array $array): bool {
        if (function_exists('array_is_list')) {
            return array_is_list($array);
        }

        $index = 0;
        foreach ($array as $key => $_) {
            if ($key !== $index) {
                return false;
            }
            $index++;
        }
        return true;
    }

    private function inferXsdType($value): string {
        if (is_bool($value)) {
            return 'xsd:boolean';
        }

        if (is_int($value)) {
            return $value >= 0 ? 'xsd:unsignedInt' : 'xsd:int';
        }

        if (is_float($value)) {
            return 'xsd:decimal';
        }

        return 'xsd:string';
    }

    private function normalizeParameterValues($parameters): array {
        if (!is_array($parameters) || empty($parameters)) {
            return [
                'success' => false,
                'error' => 'Parameter list is empty or invalid',
            ];
        }

        $normalized = [];
        $isList = $this->isListArray($parameters);

        if ($isList) {
            foreach ($parameters as $entry) {
                if (is_array($entry) && $this->isListArray($entry)) {
                    if (count($entry) < 2) {
                        return [
                            'success' => false,
                            'error' => 'Invalid setParameterValues entry: expected at least [path, value]',
                        ];
                    }

                    $path = isset($entry[0]) ? trim((string) $entry[0]) : '';
                    $value = $entry[1] ?? null;
                    $type = isset($entry[2]) && is_string($entry[2]) && trim($entry[2]) !== ''
                        ? trim($entry[2])
                        : $this->inferXsdType($value);
                } elseif (is_array($entry) && isset($entry['path'])) {
                    $path = trim((string) $entry['path']);
                    $value = $entry['value'] ?? null;
                    $type = isset($entry['type']) && is_string($entry['type']) && trim($entry['type']) !== ''
                        ? trim($entry['type'])
                        : $this->inferXsdType($value);
                } else {
                    return [
                        'success' => false,
                        'error' => 'Invalid setParameterValues entry format',
                    ];
                }

                if ($path === '') {
                    return [
                        'success' => false,
                        'error' => 'Parameter path cannot be empty',
                    ];
                }

                $normalized[] = [$path, $value, $type];
            }
        } else {
            foreach ($parameters as $path => $value) {
                $path = trim((string) $path);
                if ($path === '') {
                    continue;
                }
                $normalized[] = [$path, $value, $this->inferXsdType($value)];
            }
        }

        if (empty($normalized)) {
            return [
                'success' => false,
                'error' => 'No valid parameter paths to set',
            ];
        }

        return [
            'success' => true,
            'parameterValues' => $normalized,
        ];
    }

    private function getIndexedParameter(array $device, array $templates, int $start = 1, int $end = 8) {
        for ($i = $start; $i <= $end; $i++) {
            foreach ($templates as $template) {
                $path = sprintf($template, $i);
                $keys = explode('.', $path);
                $value = $device;

                foreach ($keys as $key) {
                    if (isset($value[$key])) {
                        $value = $value[$key];
                    } else {
                        $value = null;
                        break;
                    }
                }

                if (is_array($value) && isset($value['_value'])) {
                    $value = $value['_value'];
                } elseif (is_array($value)) {
                    $value = null;
                }

                if ($value !== null && (!is_string($value) || trim($value) !== '')) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Test connection to GenieACS
     */
    public function testConnection() {
        $result = $this->request('/devices?limit=1');
        return $result['success'];
    }

    /**
     * Get all devices
     * @param array $query - MongoDB query
     * @param int $limit - Maximum number of devices to return (0 = no limit)
     * @param int $skip - Number of devices to skip (for pagination)
     */
    public function getDevices($query = [], $limit = 0, $skip = 0) {
        $params = [];

        if (!empty($query)) {
            $params[] = 'query=' . urlencode(json_encode($query));
        }

        if ($limit > 0) {
            $params[] = 'limit=' . $limit;
        }

        if ($skip > 0) {
            $params[] = 'skip=' . $skip;
        }

        $queryString = !empty($params) ? '?' . implode('&', $params) : '';
        return $this->request('/devices/' . $queryString);
    }

    /**
     * Iterate devices in small batches to avoid exhausting PHP memory on large payloads.
     *
     * @param callable $callback Receives raw device array
     * @param array $query MongoDB query
     * @param int $batchSize Page size for GenieACS requests
     * @return array
     */
    public function walkDevices(callable $callback, array $query = [], int $batchSize = 50): array {
        $skip = 0;
        $processed = 0;
        $batchSize = max(1, $batchSize);

        while (true) {
            $result = $this->getDevices($query, $batchSize, $skip);
            if (!$result['success']) {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Failed to fetch devices',
                    'processed' => $processed,
                ];
            }

            $devices = $result['data'] ?? [];
            if (!is_array($devices) || empty($devices)) {
                break;
            }

            foreach ($devices as $device) {
                $callback($device);
                $processed++;
            }

            if (count($devices) < $batchSize) {
                break;
            }

            $skip += $batchSize;
        }

        return [
            'success' => true,
            'processed' => $processed,
        ];
    }

    /**
     * Get task queue items
     */
    public function getTasks($query = [], $limit = 0, $skip = 0) {
        $params = [];

        if (!empty($query)) {
            $params[] = 'query=' . urlencode(json_encode($query));
        }

        if ($limit > 0) {
            $params[] = 'limit=' . $limit;
        }

        if ($skip > 0) {
            $params[] = 'skip=' . $skip;
        }

        $queryString = !empty($params) ? '?' . implode('&', $params) : '';
        return $this->request('/tasks/' . $queryString);
    }

    /**
     * Get fault items
     */
    public function getFaults($query = [], $limit = 0, $skip = 0) {
        $params = [];

        if (!empty($query)) {
            $params[] = 'query=' . urlencode(json_encode($query));
        }

        if ($limit > 0) {
            $params[] = 'limit=' . $limit;
        }

        if ($skip > 0) {
            $params[] = 'skip=' . $skip;
        }

        $queryString = !empty($params) ? '?' . implode('&', $params) : '';
        return $this->request('/faults/' . $queryString);
    }

    /**
     * Get total device count
     */
    public function getDeviceCount($query = []) {
        $queryString = empty($query) ? '' : '?query=' . urlencode(json_encode($query));
        $result = $this->request('/devices/' . $queryString);

        if ($result['success'] && isset($result['data'])) {
            return ['success' => true, 'count' => count($result['data'])];
        }

        return ['success' => false, 'count' => 0];
    }

    /**
     * Get device by ID
     */
    public function getDevice($deviceId) {
        $query = ['_id' => $deviceId];
        $result = $this->request('/devices/?query=' . urlencode(json_encode($query)));

        if ($result['success'] && !empty($result['data'])) {
            return ['success' => true, 'data' => $result['data'][0]];
        }

        return ['success' => false, 'error' => 'Device not found'];
    }

    /**
     * Get device parameters
     */
    public function getDeviceParameters($deviceId) {
        return $this->getDevice($deviceId);
    }

    /**
     * Execute task on device
     */
    public function executeTask($deviceId, $taskName, $params = []) {
        if (!$this->isAllowedTaskName($taskName)) {
            return [
                'success' => false,
                'error' => "Invalid task name '{$taskName}'",
                'http_code' => 400,
            ];
        }

        $endpoint = "/devices/{$deviceId}/tasks";
        $data = [
            'name' => $taskName
        ];

        if (!empty($params)) {
            $data['parameterValues'] = $params;
        }

        return $this->request($endpoint, 'POST', $data);
    }

    /**
     * Execute task using connection request so device is nudged immediately.
     */
    public function executeTaskWithConnectionRequest($deviceId, $taskName, $extraPayload = [], $timeout = 3000) {
        if (!$this->isAllowedTaskName($taskName)) {
            return [
                'success' => false,
                'error' => "Invalid task name '{$taskName}'",
                'http_code' => 400,
            ];
        }

        $encodedId = rawurlencode($deviceId);
        $endpoint = "/devices/{$encodedId}/tasks?timeout={$timeout}&connection_request";

        $data = array_merge([
            'name' => $taskName
        ], $extraPayload);

        return $this->request($endpoint, 'POST', $data);
    }

    /**
     * Summon device (connection request)
     */
    public function summonDevice($deviceId) {
        return $this->executeTaskWithConnectionRequest(
            $deviceId,
            'refreshObject',
            ['objectName' => 'InternetGatewayDevice'],
            3000
        );
    }

    /**
     * Refresh device inform (force device to connect to ACS)
     */
    public function refreshInform($deviceId) {
        return $this->summonDevice($deviceId);
    }

    /**
     * Add refresh task for specific parameter
     * This forces GenieACS to fetch the parameter value from device
     */
    public function addRefreshTask($deviceId, $parameterPath) {
        $encodedId = rawurlencode($deviceId);
        $endpoint = "/devices/{$encodedId}/tasks?timeout=3000&connection_request";

        $data = [
            'name' => 'refreshObject',
            'objectName' => $parameterPath
        ];

        return $this->request($endpoint, 'POST', $data);
    }

    /**
     * Get parameter values from device (force fetch from device)
     * This creates a task to fetch specific parameters from the device
     *
     * @param string $deviceId Device ID
     * @param array $parameterNames Array of parameter names to fetch
     * @param int $timeout Timeout in milliseconds (default: 3000)
     * @return array Response with task ID
     */
    public function getParameterValues($deviceId, $parameterNames, $timeout = 3000) {
        $encodedId = rawurlencode($deviceId);
        $endpoint = "/devices/{$encodedId}/tasks?timeout={$timeout}&connection_request";

        $data = [
            'name' => 'getParameterValues',
            'parameterNames' => $parameterNames
        ];

        return $this->request($endpoint, 'POST', $data);
    }

    public function addObject($deviceId, $objectName, $timeout = 3000) {
        $encodedId = rawurlencode($deviceId);
        $endpoint = "/devices/{$encodedId}/tasks?timeout={$timeout}&connection_request";

        return $this->request($endpoint, 'POST', [
            'name' => 'addObject',
            'objectName' => $objectName
        ]);
    }

    public function deleteObject($deviceId, $objectName, $timeout = 3000) {
        $encodedId = rawurlencode($deviceId);
        $endpoint = "/devices/{$encodedId}/tasks?timeout={$timeout}&connection_request";

        return $this->request($endpoint, 'POST', [
            'name' => 'deleteObject',
            'objectName' => $objectName
        ]);
    }

    /**
     * Summon device and fetch admin credentials (VirtualParameters)
     * This is a convenience method that:
     * 1. Summons the device (connection request)
     * 2. Refreshes all VirtualParameters so GenieACS evaluates superAdmin/superPassword
     *
     * VirtualParameters are computed by GenieACS from actual device parameters.
     * Refreshing the VirtualParameters object triggers evaluation of ALL VirtualParameters,
     * including superAdmin and superPassword which read admin credentials from various device parameters.
     *
     * @param string $deviceId Device ID
     * @return array Response with success status
     */
    public function summonAndFetchAdminCredentials($deviceId) {
        $encodedId = rawurlencode($deviceId);

        // Connection request + Refresh VirtualParameters object
        $endpoint = "/devices/{$encodedId}/tasks?timeout=3000&connection_request";

        // Refresh all VirtualParameters - this triggers evaluation of superAdmin/superPassword
        $data = [
            'name' => 'refreshObject',
            'objectName' => 'VirtualParameters'
        ];

        return $this->request($endpoint, 'POST', $data);
    }

    /**
     * Reboot device
     */
    public function rebootDevice($deviceId) {
        return $this->executeTaskWithConnectionRequest($deviceId, 'reboot');
    }

    /**
     * Factory reset device
     */
    public function factoryResetDevice($deviceId) {
        return $this->executeTaskWithConnectionRequest($deviceId, 'factoryReset');
    }

    /**
     * Set parameter values on device
     *
     * @param string $deviceId Device ID
     * @param array $parameters Array of parameters to set [['path', 'value', 'type'], ...]
     * @param int $timeout Timeout in milliseconds (default: 3000)
     * @return array Response with success status
     *
     * Example:
     * $parameters = [
     *     ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID', 'NewSSID', 'xsd:string'],
     *     ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase', 'NewPassword', 'xsd:string']
     * ];
     */
    public function setParameterValues($deviceId, $parameters, $timeout = 3000) {
        $normalized = $this->normalizeParameterValues($parameters);
        if (empty($normalized['success'])) {
            return [
                'success' => false,
                'error' => $normalized['error'] ?? 'Invalid parameterValues payload',
                'http_code' => 400,
            ];
        }

        // URL encode device ID to handle special characters
        $encodedId = rawurlencode($deviceId);
        $endpoint = "/devices/{$encodedId}/tasks?timeout={$timeout}&connection_request";

        $data = [
            'name' => 'setParameterValues',
            'parameterValues' => $normalized['parameterValues']
        ];

        return $this->request($endpoint, 'POST', $data);
    }

    /**
     * Set WiFi configuration (SSID, Password, and Security Mode)
     *
     * @param string $deviceId Device ID
     * @param string $ssid New WiFi SSID
     * @param string $password New WiFi Password (optional for Open network)
     * @param int $wlanIndex WLAN Configuration index (default: 1)
     * @param string $securityMode Security mode (WPA2PSK, WPAPSK, WPA2PSKWPAPSK, None)
     * @return array Response with success status
     */
    public function setWiFiConfig($deviceId, $ssid, $password = '', $wlanIndex = 1, $securityMode = 'WPA2PSK') {
        $parameters = [];

        // Try multiple parameter paths for different ONU vendors
        $ssidPaths = [
            "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$wlanIndex}.SSID",
            "Device.WiFi.SSID.{$wlanIndex}.SSID"
        ];

        $securityPaths = [
            "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$wlanIndex}.BeaconType",
            "Device.WiFi.AccessPoint.{$wlanIndex}.Security.ModeEnabled"
        ];

        $passwordPaths = [
            "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$wlanIndex}.KeyPassphrase",
            "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$wlanIndex}.PreSharedKey.1.KeyPassphrase",
            "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$wlanIndex}.PreSharedKey.1.PreSharedKey",
            "Device.WiFi.AccessPoint.{$wlanIndex}.Security.KeyPassphrase"
        ];

        // For now, use the most common TR-098 paths
        // 1. Set SSID
        $parameters[] = [$ssidPaths[0], $ssid, 'xsd:string'];

        // 2. Set Security Mode (BeaconType)
        // Map security mode to BeaconType values
        $beaconTypeMap = [
            'WPA2PSK' => '11i',
            'WPAPSK' => 'WPA',
            'WPA2PSKWPAPSK' => 'WPAand11i',
            'None' => 'Basic'  // or 'None' depending on device
        ];

        $beaconType = isset($beaconTypeMap[$securityMode]) ? $beaconTypeMap[$securityMode] : '11i';
        $parameters[] = [$securityPaths[0], $beaconType, 'xsd:string'];

        // 3. Set Password (only if security mode is not Open)
        if ($securityMode !== 'None' && !empty($password)) {
            $parameters[] = [$passwordPaths[0], $password, 'xsd:string'];

            // Also set authentication mode for WPA/WPA2
            $authModePath = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$wlanIndex}.WPAAuthenticationMode";
            $parameters[] = [$authModePath, 'PSKAuthentication', 'xsd:string'];

            // Set encryption method
            $encryptionPath = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$wlanIndex}.WPAEncryptionModes";
            $encryptionMode = ($securityMode === 'WPA2PSK' || $securityMode === 'WPA2PSKWPAPSK') ? 'AESEncryption' : 'TKIPEncryption';
            $parameters[] = [$encryptionPath, $encryptionMode, 'xsd:string'];
        }

        return $this->setParameterValues($deviceId, $parameters);
    }

    /**
     * Get device statistics
     */
    public function getDeviceStats() {
        $total = 0;
        $online = 0;
        $offline = 0;

        $result = $this->walkDevices(function ($device) use (&$total, &$online, &$offline) {
            $total++;

            $lastInform = $device['_lastInform'] ?? null;
            $isOnline = false;

            if ($lastInform) {
                $lastInformTimestamp = strtotime($lastInform);
                if ($lastInformTimestamp !== false) {
                    $isOnline = (time() - $lastInformTimestamp) < 900;
                }
            }

            if ($isOnline) {
                $online++;
            } else {
                $offline++;
            }
        }, [], 50);

        if (!$result['success']) {
            return ['success' => false, 'error' => 'Failed to fetch devices'];
        }

        return [
            'success' => true,
            'data' => [
                'total' => $total,
                'online' => $online,
                'offline' => $offline
            ]
        ];
    }

    /**
     * Parse device data for display
     */
    public function parseDeviceData($device) {
        $data = [];

        // Helper function to get nested parameter value
        $getParam = function($path) use ($device) {
            $keys = explode('.', $path);
            $value = $device;

            foreach ($keys as $key) {
                if (isset($value[$key])) {
                    $value = $value[$key];
                } else {
                    return null;
                }
            }

            // GenieACS uses object format with _value field
            if (is_array($value) && isset($value['_value'])) {
                return $value['_value'];
            }

            // Fallback for direct values
            return is_array($value) ? null : $value;
        };

        $firstNonEmpty = function (...$values) {
            foreach ($values as $value) {
                if ($value === null) {
                    continue;
                }

                if (is_string($value)) {
                    $trimmed = trim($value);
                    if ($trimmed === '' || strtoupper($trimmed) === 'N/A') {
                        continue;
                    }

                    return $trimmed;
                }

                return $value;
            }

            return null;
        };

        $normalizeOpticalValue = function ($value, $temperature = false) {
            if ($value === null || $value === '' || strtoupper((string) $value) === 'N/A') {
                return 'N/A';
            }

            if (is_string($value) && preg_match('/-?\d+(?:\.\d+)?/', $value, $matches)) {
                $value = $matches[0];
            }

            if (!is_numeric($value)) {
                return (string) $value;
            }

            $numeric = floatval($value);

            if ($temperature && $numeric > 1000) {
                $numeric = $numeric / 256;
            }

            if (!$temperature && $numeric > 100) {
                $numeric = ($numeric / 100) - 40;
            }

            return $temperature ? number_format($numeric, 1) : number_format($numeric, 2);
        };

        $formatValue = function ($value) {
            if ($value === null) {
                return 'N/A';
            }
            if (is_string($value)) {
                $trimmed = trim($value);
                return ($trimmed === '' || strtoupper($trimmed) === 'N/A') ? 'N/A' : $trimmed;
            }

            return $value;
        };

        $normalizeBooleanState = function ($value, $trueLabel = 'Yes', $falseLabel = 'No') {
            if ($value === null) {
                return 'N/A';
            }

            if (is_bool($value)) {
                return $value ? $trueLabel : $falseLabel;
            }

            $normalized = strtolower(trim((string) $value));
            if ($normalized === '') {
                return 'N/A';
            }
            if (in_array($normalized, ['1', 'true', 'yes', 'on', 'up', 'connected', 'online', 'reachable'], true)) {
                return $trueLabel;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off', 'down', 'disconnected', 'offline', 'unreachable'], true)) {
                return $falseLabel;
            }

            return (string) $value;
        };

        $splitDnsServers = function ($dnsValue) {
            if ($dnsValue === null) {
                return ['N/A', 'N/A'];
            }

            if (is_array($dnsValue)) {
                $clean = array_values(array_filter(array_map(static fn($v) => trim((string) $v), $dnsValue), static fn($v) => $v !== ''));
            } else {
                $clean = preg_split('/\s*,\s*|\s+/', trim((string) $dnsValue));
                $clean = array_values(array_filter($clean, static fn($v) => $v !== ''));
            }

            $primary = $clean[0] ?? 'N/A';
            $secondary = $clean[1] ?? 'N/A';
            return [$primary, $secondary];
        };

        // Basic info
        $data['device_id'] = $device['_id'] ?? 'N/A';
        $data['serial_number'] = $getParam('_deviceId._SerialNumber') ?? $getParam('InternetGatewayDevice.DeviceInfo.SerialNumber') ?? 'N/A';

        // MAC Address - try multiple paths
        $macAddress = $getParam('InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.1.MACAddress') ??
                     $getParam('InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.MACAddress') ??
                     $getParam('InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.BSSID') ??
                     $getParam('Device.Ethernet.Interface.1.MACAddress') ??
                     $getParam('_deviceId._MACAddress');

        // If MAC still not found, try to construct from OUI and serial number
        if (empty($macAddress) || $macAddress === 'N/A') {
            $oui = $getParam('_deviceId._OUI');
            $serial = $getParam('_deviceId._SerialNumber');

            // Some devices have MAC embedded in serial number (last 6 chars)
            if ($oui && $serial && strlen($serial) >= 6) {
                $lastSixChars = substr($serial, -6);
                // Check if last 6 chars are hex
                if (ctype_xdigit($lastSixChars)) {
                    // Format OUI properly (F86CE1 -> F8:6C:E1)
                    $ouiFormatted = strtoupper(substr($oui, 0, 2) . ':' .
                                               substr($oui, 2, 2) . ':' .
                                               substr($oui, 4, 2));

                    $macAddress = $ouiFormatted . ':' .
                                 strtoupper(substr($lastSixChars, 0, 2)) . ':' .
                                 strtoupper(substr($lastSixChars, 2, 2)) . ':' .
                                 strtoupper(substr($lastSixChars, 4, 2));
                }
            }
        }

        $data['mac_address'] = $macAddress ?? 'N/A';
        $data['manufacturer'] = $getParam('_deviceId._Manufacturer') ?? $getParam('InternetGatewayDevice.DeviceInfo.Manufacturer') ?? 'N/A';
        $data['oui'] = $getParam('_deviceId._OUI') ?? $getParam('InternetGatewayDevice.DeviceInfo.ManufacturerOUI') ?? 'N/A';
        $data['product_class'] = $getParam('_deviceId._ProductClass') ?? $getParam('InternetGatewayDevice.DeviceInfo.ProductClass') ?? 'N/A';
        $data['hardware_version'] = $getParam('InternetGatewayDevice.DeviceInfo.HardwareVersion') ?? 'N/A';
        $data['software_version'] = $getParam('InternetGatewayDevice.DeviceInfo.SoftwareVersion') ?? 'N/A';

        // Status
        $lastInform = isset($device['_lastInform']) ? $device['_lastInform'] : null;
        $lastInformTimestamp = null;

        if ($lastInform) {
            $lastInformTimestamp = strtotime($lastInform);
            if ($lastInformTimestamp !== false) {
                $data['last_inform'] = date('Y-m-d H:i:s', $lastInformTimestamp);
                $data['status'] = (time() - $lastInformTimestamp) < 900 ? 'online' : 'offline';
            } else {
                $data['last_inform'] = 'N/A';
                $data['status'] = 'offline';
            }
        } else {
            $data['last_inform'] = 'N/A';
            $data['status'] = 'offline';
        }

        // Ping/Latency - try to get actual ping from VirtualParameters
        // GenieACS stores ping result in VirtualParameters.Ping
        $ping = $getParam('VirtualParameters.Ping') ??
                $getParam('VirtualParameters.ping') ??
                $getParam('VirtualParameters.PingResult');

        if ($data['status'] === 'online') {
            // If ping value exists and is numeric, use it
            if ($ping !== null && is_numeric($ping)) {
                $data['ping'] = intval($ping);
            } else {
                // Fallback: estimate based on inform freshness if actual ping not available
                if ($lastInformTimestamp) {
                    $timeSinceInform = time() - $lastInformTimestamp;

                    if ($timeSinceInform < 30) {
                        $data['ping'] = rand(1, 5);
                    } elseif ($timeSinceInform < 60) {
                        $data['ping'] = rand(5, 15);
                    } elseif ($timeSinceInform < 120) {
                        $data['ping'] = rand(15, 50);
                    } else {
                        $data['ping'] = rand(50, 200);
                    }
                } else {
                    $data['ping'] = null;
                }
            }
        } else {
            $data['ping'] = null;
        }

        // Network info
        $connectionUrl = $getParam('InternetGatewayDevice.ManagementServer.ConnectionRequestURL') ??
                        $getParam('Device.ManagementServer.ConnectionRequestURL') ?? 'N/A';

        $data['ip_tr069'] = $connectionUrl;

        // Extract IP address from ConnectionRequestURL
        $ipAddress = 'N/A';
        if ($connectionUrl && $connectionUrl !== 'N/A') {
            // Extract IP from URL format: http://IP:PORT/path or https://IP:PORT/path
            if (preg_match('/https?:\/\/([^:\/]+)/', $connectionUrl, $matches)) {
                $ipAddress = $matches[1];
            }
        }

        // Also try WAN IP if available
        if ($ipAddress === 'N/A') {
            $ipAddress = $getParam('InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ExternalIPAddress') ??
                        $getParam('Device.IP.Interface.1.IPv4Address.1.IPAddress') ?? 'N/A';
        }

        $data['ip_address'] = $ipAddress;
        $data['uptime'] = $getParam('InternetGatewayDevice.DeviceInfo.UpTime') ??
                         $getParam('Device.DeviceInfo.UpTime') ?? 'N/A';

        // WiFi info - try multiple paths and WLAN configurations
        $wifiSsid = $firstNonEmpty(
            $getParam('VirtualParameters.SSID1-Name'),
            $getParam('VirtualParameters.SSID5-Name'),
            $this->getIndexedParameter($device, [
                'InternetGatewayDevice.LANDevice.1.WLANConfiguration.%d.SSID',
                'InternetGatewayDevice.LANDevice.1.WLANConfiguration.%d.X_HW_SSIDName',
                'Device.WiFi.SSID.%d.SSID',
            ])
        );

        $data['wifi_ssid'] = $wifiSsid ?? 'N/A';

        $wifiPassword = $firstNonEmpty(
            $getParam('VirtualParameters.SSID1-Password'),
            $getParam('VirtualParameters.SSID5-Password'),
            $getParam('VirtualParameters.WlanPassword'),
            $this->getIndexedParameter($device, [
                'InternetGatewayDevice.LANDevice.1.WLANConfiguration.%d.KeyPassphrase',
                'InternetGatewayDevice.LANDevice.1.WLANConfiguration.%d.PreSharedKey.1.KeyPassphrase',
                'InternetGatewayDevice.LANDevice.1.WLANConfiguration.%d.PreSharedKey.1.PreSharedKey',
                'InternetGatewayDevice.LANDevice.1.WLANConfiguration.%d.X_CMS_KeyPassphrase',
                'InternetGatewayDevice.LANDevice.1.WLANConfiguration.%d.X_HW_KeyPassphrase',
                'Device.WiFi.AccessPoint.%d.Security.KeyPassphrase',
                'Device.WiFi.AccessPoint.%d.Security.PreSharedKey',
            ])
        );

        $hasWifiPassword = $wifiPassword !== null
            && (!is_string($wifiPassword) || trim($wifiPassword) !== '')
            && strtoupper(trim((string) $wifiPassword)) !== 'N/A';
        $data['wifi_password'] = $hasWifiPassword ? '********' : 'N/A';
        $data['wifi_password_set'] = $hasWifiPassword;

        // Optical info
        $rxPower = $firstNonEmpty(
            $getParam('VirtualParameters.OpticalRXPower'),
            $getParam('VirtualParameters.RXPower'),
            $getParam('InternetGatewayDevice.WANDevice.1.X_CT-COM_EponInterfaceConfig.RXPower'),
            $getParam('InternetGatewayDevice.WANDevice.1.X_CT-COM_GponInterfaceConfig.RXPower'),
            $getParam('InternetGatewayDevice.WANDevice.1.X_GponInterafceConfig.RXPower'),
            $getParam('InternetGatewayDevice.WANDevice.1.WANGponInterfaceConfig.RXPower'),
            $getParam('Device.Optical.Interface.1.RxPower'),
            $getParam('InternetGatewayDevice.X_Tenda_PON.OpticalRxPower')
        );
        $data['rx_power'] = $normalizeOpticalValue($rxPower, false);

        // Temperature
        $temperature = $firstNonEmpty(
            $getParam('VirtualParameters.OpticalTemperature'),
            $getParam('VirtualParameters.gettemp'),
            $getParam('VirtualParameters.Temperature'),
            $getParam('InternetGatewayDevice.WANDevice.1.X_CT-COM_EponInterfaceConfig.TransceiverTemperature'),
            $getParam('InternetGatewayDevice.WANDevice.1.X_CT-COM_GponInterfaceConfig.TransceiverTemperature'),
            $getParam('InternetGatewayDevice.WANDevice.1.X_GponInterafceConfig.TransceiverTemperature'),
            $getParam('InternetGatewayDevice.WANDevice.1.WANGponInterfaceConfig.TransceiverTemperature'),
            $getParam('InternetGatewayDevice.DeviceInfo.Temperature'),
            $getParam('InternetGatewayDevice.X_Tenda_PON.Temperature')
        );
        $data['temperature'] = $normalizeOpticalValue($temperature, true);

        $opticalTxPower = $firstNonEmpty(
            $getParam('VirtualParameters.OpticalTXPower'),
            $getParam('InternetGatewayDevice.WANDevice.1.X_CT-COM_GponInterfaceConfig.TXPower'),
            $getParam('InternetGatewayDevice.WANDevice.1.WANGponInterfaceConfig.TXPower')
        );
        $data['optical_tx_power'] = $normalizeOpticalValue($opticalTxPower, false);
        $data['optical_voltage'] = $formatValue($getParam('VirtualParameters.OpticalVoltage'));
        $data['optical_bias_current'] = $formatValue($getParam('VirtualParameters.OpticalBiasCurrent'));

        $lastInformAgeSec = $firstNonEmpty($getParam('VirtualParameters.LastInformAgeSec'));
        if (($lastInformAgeSec === null || $lastInformAgeSec === 'N/A') && $lastInformTimestamp !== null) {
            $lastInformAgeSec = max(0, time() - $lastInformTimestamp);
        }
        $data['last_inform_age_sec'] = is_numeric($lastInformAgeSec) ? intval($lastInformAgeSec) : $formatValue($lastInformAgeSec);
        $data['online_state'] = strtoupper((string) $firstNonEmpty($getParam('VirtualParameters.OnlineState'), $data['status']));
        $data['periodic_inform_interval_actual'] = $formatValue($firstNonEmpty(
            $getParam('VirtualParameters.PeriodicInformIntervalActual'),
            $getParam('InternetGatewayDevice.ManagementServer.PeriodicInformInterval'),
            $getParam('Device.ManagementServer.PeriodicInformInterval')
        ));
        $data['connection_request_reachable'] = $normalizeBooleanState(
            $firstNonEmpty(
                $getParam('VirtualParameters.ConnectionRequestReachable'),
                $connectionUrl !== 'N/A' ? true : null
            ),
            'Reachable',
            'Unreachable'
        );
        $informJitter = $firstNonEmpty($getParam('VirtualParameters.InformJitterSec'));
        if (($informJitter === null || $informJitter === 'N/A') && is_numeric($lastInformAgeSec) && is_numeric($data['periodic_inform_interval_actual'])) {
            $informJitter = abs(intval($lastInformAgeSec) - intval($data['periodic_inform_interval_actual']));
        }
        $data['inform_jitter_sec'] = is_numeric($informJitter) ? intval($informJitter) : $formatValue($informJitter);
        $data['consecutive_inform_miss'] = $formatValue($firstNonEmpty(
            $getParam('VirtualParameters.ConsecutiveInformMiss')
        ));
        $data['task_failure_count_24h'] = $formatValue($firstNonEmpty(
            $getParam('VirtualParameters.TaskFailureCount24h')
        ));
        $data['los_count_24h'] = $formatValue($firstNonEmpty(
            $getParam('VirtualParameters.LOSCount24h')
        ));
        $data['fec_error_rate'] = $formatValue($firstNonEmpty(
            $getParam('VirtualParameters.FECErrorRate'),
            $getParam('InternetGatewayDevice.WANDevice.1.WANGponInterfaceConfig.Stats.FECError')
        ));
        $data['crc_error_rate'] = $formatValue($firstNonEmpty(
            $getParam('VirtualParameters.CRCErrorRate'),
            $getParam('InternetGatewayDevice.WANDevice.1.WANGponInterfaceConfig.Stats.CRCErrors'),
            $getParam('InternetGatewayDevice.WANDevice.1.WANGponInterfaceConfig.Stats.CRCError'),
            $getParam('InternetGatewayDevice.WANDevice.1.WANGponInterfaceConfig.Stats.HECError')
        ));
        $data['link_flap_count_24h'] = $formatValue($firstNonEmpty(
            $getParam('VirtualParameters.LinkFlapCount24h')
        ));

        // WAN Details - try multiple connection types and device numbers
        $wanDetails = [];

        // Helper function to check if WAN connection exists
        $checkWANExists = function($path) use ($device) {
            $keys = explode('.', $path);
            $value = $device;

            foreach ($keys as $key) {
                if (isset($value[$key])) {
                    $value = $value[$key];
                } else {
                    return false;
                }
            }

            // Check if this is an actual connection object (has _object or parameters)
            if (is_array($value)) {
                // If it has _object field and it's true, or has connection parameters
                if (isset($value['_object']) || isset($value['ConnectionStatus']) ||
                    isset($value['Enable']) || isset($value['Name'])) {
                    return true;
                }
            }

            return false;
        };

        // Helper function to detect active WLAN/LAN interfaces
        $detectActiveInterfaces = function() use ($getParam) {
            $activeInterfaces = [];

            // Check WLAN configurations (1-4)
            for ($i = 1; $i <= 4; $i++) {
                $wlanBase = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$i}";
                $wlanEnable = $getParam("{$wlanBase}.Enable");
                $wlanStatus = $getParam("{$wlanBase}.Status");
                $wlanSSID = $getParam("{$wlanBase}.SSID");
                $wlanVLAN = $getParam("{$wlanBase}.X_CT-COM_VLAN");

                // WLAN is active if enabled and status is "Up" or has SSID
                if (($wlanEnable === true || $wlanStatus === 'Up') && $wlanSSID) {
                    $activeInterfaces[] = [
                        'type' => 'WLAN',
                        'number' => $i,
                        'interface' => "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$i}",
                        'ssid' => $wlanSSID,
                        'vlan' => $wlanVLAN ?? 'N/A'
                    ];
                }
            }

            // Check LAN Ethernet configurations (1-4)
            for ($i = 1; $i <= 4; $i++) {
                $lanBase = "InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.{$i}";
                $lanEnable = $getParam("{$lanBase}.Enable");
                $lanStatus = $getParam("{$lanBase}.Status");
                $lanVLAN = $getParam("{$lanBase}.X_CT-COM_VLAN");

                // LAN is active if enabled or has status other than "NoLink"
                if ($lanEnable === true || ($lanStatus && $lanStatus !== 'NoLink')) {
                    $activeInterfaces[] = [
                        'type' => 'LAN Ethernet',
                        'number' => $i,
                        'interface' => "InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.{$i}",
                        'vlan' => $lanVLAN ?? 'N/A'
                    ];
                }
            }

            return $activeInterfaces;
        };

        // Try WANPPPConnection (most common for PPPoE)
        for ($i = 1; $i <= 8; $i++) {
            $basePath = "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.{$i}.WANPPPConnection.1";

            // Check if this connection exists
            if (!$checkWANExists($basePath)) {
                continue;
            }

            $name = $getParam("{$basePath}.Name");
            $externalIP = $getParam("{$basePath}.ExternalIPAddress");
            $serviceList = $getParam("{$basePath}.X_CT-COM_ServiceList");
            $connectionStatus = $getParam("{$basePath}.ConnectionStatus");
            $lanInterface = $getParam("{$basePath}.X_CT-COM_LanInterface");

            // If ConnectionStatus is not available, try to determine from Enable flag
            if (!$connectionStatus || $connectionStatus === 'Unknown') {
                $enabled = $getParam("{$basePath}.Enable");
                if ($enabled !== null) {
                    $connectionStatus = $enabled ? 'Connected' : 'Disconnected';
                } else {
                    $connectionStatus = 'Unknown';
                }
            }

            // Parse LAN interface binding
            $bindingInfo = 'N/A';
            if ($lanInterface !== null && $lanInterface !== '') {
                // Extract interface type and number
                // e.g., "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1" -> "WLAN 1"
                // e.g., "InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.1" -> "LAN Ethernet 1"
                if (preg_match('/WLANConfiguration\.(\d+)/', $lanInterface, $matches)) {
                    $bindingInfo = "WLAN " . $matches[1];
                } elseif (preg_match('/LANEthernetInterfaceConfig\.(\d+)/', $lanInterface, $matches)) {
                    $bindingInfo = "LAN Ethernet " . $matches[1];
                } elseif (preg_match('/LANHostConfigManagement/', $lanInterface)) {
                    $bindingInfo = "All LAN Ports";
                } else {
                    $bindingInfo = $lanInterface;
                }
            }

            // If binding info is still N/A, try to infer from active interfaces
            if ($bindingInfo === 'N/A') {
                $activeInterfaces = $detectActiveInterfaces();

                if (!empty($activeInterfaces)) {
                    $bindingList = [];
                    foreach ($activeInterfaces as $iface) {
                        if ($iface['type'] === 'WLAN') {
                            $bindingList[] = "WLAN {$iface['number']}";
                        }
                    }

                    if (!empty($bindingList)) {
                        $bindingInfo = implode(', ', $bindingList);
                    }
                }
            }

            // Only add if we have at least a name, IP, or service identifier
            if ($name || $externalIP || $serviceList) {
                // Generate name if not available
                if (!$name) {
                    $name = $serviceList ? "WAN_{$serviceList}_{$i}" : "WAN_PPP_Connection_{$i}";
                }

                $wanDetails[] = [
                    'type' => 'PPPoE',
                    'name' => $name,
                    'status' => $connectionStatus,
                    'connection_type' => $getParam("{$basePath}.ConnectionType") ?? 'N/A',
                    'external_ip' => $externalIP ?? 'N/A',
                    'gateway' => $getParam("{$basePath}.RemoteIPAddress") ?? $getParam("{$basePath}.DefaultGateway") ?? 'N/A',
                    'subnet_mask' => $getParam("{$basePath}.SubnetMask") ?? 'N/A',
                    'dns_servers' => $getParam("{$basePath}.DNSServers") ?? 'N/A',
                    'mac_address' => $getParam("{$basePath}.MACAddress") ?? 'N/A',
                    'username' => $getParam("{$basePath}.Username") ?? 'N/A',
                    'uptime' => $getParam("{$basePath}.Uptime") ?? 'N/A',
                    'last_error' => $getParam("{$basePath}.LastConnectionError") ?? 'N/A',
                    'mru_size' => $getParam("{$basePath}.MaxMRUSize") ?? 'N/A',
                    'binding' => $bindingInfo,
                ];
            }
        }

        // Try WANIPConnection (for DHCP/Static IP)
        for ($i = 1; $i <= 8; $i++) {
            $basePath = "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.{$i}.WANIPConnection.1";

            // Check if this connection exists
            if (!$checkWANExists($basePath)) {
                continue;
            }

            $name = $getParam("{$basePath}.Name");
            $externalIP = $getParam("{$basePath}.ExternalIPAddress");
            $serviceList = $getParam("{$basePath}.X_CT-COM_ServiceList");
            $connectionStatus = $getParam("{$basePath}.ConnectionStatus");
            $lanInterface = $getParam("{$basePath}.X_CT-COM_LanInterface");

            // If ConnectionStatus is not available, try to determine from Enable flag
            if (!$connectionStatus || $connectionStatus === 'Unknown') {
                $enabled = $getParam("{$basePath}.Enable");
                if ($enabled !== null) {
                    $connectionStatus = $enabled ? 'Connected' : 'Disconnected';
                } else {
                    $connectionStatus = 'Unknown';
                }
            }

            // Parse LAN interface binding
            $bindingInfo = 'N/A';
            if ($lanInterface !== null && $lanInterface !== '') {
                if (preg_match('/WLANConfiguration\.(\d+)/', $lanInterface, $matches)) {
                    $bindingInfo = "WLAN " . $matches[1];
                } elseif (preg_match('/LANEthernetInterfaceConfig\.(\d+)/', $lanInterface, $matches)) {
                    $bindingInfo = "LAN Ethernet " . $matches[1];
                } elseif (preg_match('/LANHostConfigManagement/', $lanInterface)) {
                    $bindingInfo = "All LAN Ports";
                } else {
                    $bindingInfo = $lanInterface;
                }
            }

            // If binding info is still N/A, try to infer from active interfaces
            if ($bindingInfo === 'N/A') {
                $activeInterfaces = $detectActiveInterfaces();

                if (!empty($activeInterfaces)) {
                    $bindingList = [];
                    foreach ($activeInterfaces as $iface) {
                        if ($iface['type'] === 'WLAN') {
                            $bindingList[] = "WLAN {$iface['number']}";
                        }
                    }

                    if (!empty($bindingList)) {
                        $bindingInfo = implode(', ', $bindingList);
                    }
                }
            }

            // Only add if we have at least a name, IP, or service identifier
            if ($name || $externalIP || $serviceList) {
                // Generate name if not available
                if (!$name) {
                    $name = $serviceList ? "WAN_{$serviceList}_{$i}" : "WAN_IP_Connection_{$i}";
                }

                $wanDetails[] = [
                    'type' => 'IP',
                    'name' => $name,
                    'status' => $connectionStatus,
                    'connection_type' => $getParam("{$basePath}.ConnectionType") ?? 'N/A',
                    'external_ip' => $externalIP ?? 'N/A',
                    'gateway' => $getParam("{$basePath}.DefaultGateway") ?? 'N/A',
                    'subnet_mask' => $getParam("{$basePath}.SubnetMask") ?? 'N/A',
                    'dns_servers' => $getParam("{$basePath}.DNSServers") ?? 'N/A',
                    'mac_address' => $getParam("{$basePath}.MACAddress") ?? 'N/A',
                    'addressing_type' => $getParam("{$basePath}.AddressingType") ?? 'N/A',
                    'uptime' => $getParam("{$basePath}.Uptime") ?? 'N/A',
                    'binding' => $bindingInfo,
                    'username' => 'N/A', // IP connections don't have username
                    'last_error' => 'N/A', // IP connections don't have last error
                    'mru_size' => 'N/A', // IP connections don't have MRU size
                ];
            }
        }

        // If no WAN connections found, try to create virtual WAN details from active interfaces
        if (empty($wanDetails)) {
            $activeInterfaces = $detectActiveInterfaces();

            if (!empty($activeInterfaces)) {
                // Group interfaces by VLAN to create logical WAN connections
                $vlanGroups = [];

                foreach ($activeInterfaces as $iface) {
                    $vlan = $iface['vlan'] !== 'N/A' && $iface['vlan'] !== '' ? $iface['vlan'] : 'default';

                    if (!isset($vlanGroups[$vlan])) {
                        $vlanGroups[$vlan] = [];
                    }
                    $vlanGroups[$vlan][] = $iface;
                }

                // Create WAN detail for each VLAN group
                $connIndex = 1;
                foreach ($vlanGroups as $vlan => $interfaces) {
                    $bindingList = [];

                    foreach ($interfaces as $iface) {
                        if ($iface['type'] === 'WLAN') {
                            $bindingList[] = "WLAN {$iface['number']} ({$iface['ssid']})";
                        } else {
                            $bindingList[] = "{$iface['type']} {$iface['number']}";
                        }
                    }

                    $bindingInfo = implode(', ', $bindingList);

                    // Use device IP as external IP if available
                    $externalIP = $data['ip_address'] ?? 'N/A';

                    $wanDetails[] = [
                        'type' => 'Bridge',
                        'name' => $vlan !== 'default' ? "Bridge_VLAN_{$vlan}" : "Bridge_Connection",
                        'status' => 'Connected',
                        'connection_type' => 'Bridged',
                        'external_ip' => $externalIP,
                        'gateway' => 'N/A',
                        'subnet_mask' => 'N/A',
                        'dns_servers' => 'N/A',
                        'mac_address' => $data['mac_address'] ?? 'N/A',
                        'addressing_type' => 'Bridged',
                        'uptime' => $data['uptime'] ?? 'N/A',
                        'binding' => $bindingInfo,
                        'username' => 'N/A',
                        'last_error' => 'N/A',
                        'mru_size' => 'N/A',
                    ];

                    $connIndex++;
                }
            }
        }

        $data['wan_details'] = $wanDetails;

        // Extract PPPoE username from first PPPoE connection (for devices.php display)
        $pppoeUsername = 'N/A';
        foreach ($wanDetails as $wan) {
            if ($wan['type'] === 'PPPoE' && isset($wan['username']) && $wan['username'] !== 'N/A' && $wan['username'] !== '') {
                $pppoeUsername = $wan['username'];
                break; // Use first found PPPoE username (non-empty)
            }
        }
        $data['pppoe_username'] = $pppoeUsername;

        $pppLastError = $firstNonEmpty(
            $getParam('VirtualParameters.PPPLastError'),
            isset($wanDetails[0]['last_error']) ? $wanDetails[0]['last_error'] : null
        );
        $data['ppp_last_error'] = $formatValue($pppLastError);
        $data['ppp_session_drops_24h'] = $formatValue($firstNonEmpty(
            $getParam('VirtualParameters.PPPSessionDrops24h')
        ));

        $pppLastUpAt = $firstNonEmpty(
            $getParam('VirtualParameters.PPPLastUpAt')
        );
        if (($pppLastUpAt === null || $pppLastUpAt === 'N/A')) {
            $pppUptimeSec = $firstNonEmpty(
                $getParam('InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Uptime'),
                $getParam('InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.2.Uptime'),
                $getParam('InternetGatewayDevice.WANDevice.1.WANConnectionDevice.2.WANPPPConnection.1.Uptime'),
                $getParam('Device.PPP.Interface.1.Uptime'),
                isset($wanDetails[0]['uptime']) ? $wanDetails[0]['uptime'] : null
            );
            if (is_numeric($pppUptimeSec) && intval($pppUptimeSec) >= 0) {
                $pppLastUpAt = date('c', time() - intval($pppUptimeSec));
            }
        }
        $data['ppp_last_up_at'] = $formatValue($pppLastUpAt);

        $defaultGateway = $firstNonEmpty(
            $getParam('VirtualParameters.DefaultGateway'),
            $getParam('InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.DefaultGateway'),
            $getParam('InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.RemoteIPAddress'),
            $getParam('Device.Routing.Router.1.IPv4Forwarding.1.GatewayIPAddress')
        );
        $data['default_gateway'] = $formatValue($defaultGateway);

        $dnsRaw = $firstNonEmpty(
            $getParam('VirtualParameters.PrimaryDNS'),
            $getParam('InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.DNSServers'),
            $getParam('InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.DNSServers'),
            $getParam('Device.DNS.Client.Server.1.DNSServer')
        );
        [$dnsPrimary, $dnsSecondary] = $splitDnsServers($dnsRaw);

        $vpPrimaryDns = $formatValue($getParam('VirtualParameters.PrimaryDNS'));
        $vpSecondaryDns = $formatValue($getParam('VirtualParameters.SecondaryDNS'));
        $data['primary_dns'] = $vpPrimaryDns !== 'N/A' ? $vpPrimaryDns : $dnsPrimary;
        $data['secondary_dns'] = $vpSecondaryDns !== 'N/A' ? $vpSecondaryDns : $dnsSecondary;
        $data['ipv6_wan'] = $formatValue($firstNonEmpty(
            $getParam('VirtualParameters.IPv6WAN'),
            $getParam('Device.IP.Interface.1.IPv6Address.1.IPAddress'),
            $getParam('InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.X_CT-COM_IPv6IPAddress')
        ));

        $data['wifi_channel'] = $formatValue($firstNonEmpty(
            $getParam('VirtualParameters.WiFiChannel'),
            $this->getIndexedParameter($device, [
                'InternetGatewayDevice.LANDevice.1.WLANConfiguration.%d.Channel',
                'Device.WiFi.Radio.%d.Channel',
            ])
        ));
        $data['wifi_bandwidth'] = $formatValue($firstNonEmpty(
            $getParam('VirtualParameters.WiFiBandwidth'),
            $this->getIndexedParameter($device, [
                'InternetGatewayDevice.LANDevice.1.WLANConfiguration.%d.X_HW_ChannelBandwidth',
                'Device.WiFi.Radio.%d.OperatingChannelBandwidth',
            ])
        ));
        $data['wifi_tx_power'] = $formatValue($firstNonEmpty(
            $getParam('VirtualParameters.WiFiTxPower'),
            $this->getIndexedParameter($device, [
                'InternetGatewayDevice.LANDevice.1.WLANConfiguration.%d.X_HW_TxPower',
                'Device.WiFi.Radio.%d.TransmitPower',
            ])
        ));
        $data['guest_ssid_state'] = $normalizeBooleanState(
            $firstNonEmpty(
                $getParam('VirtualParameters.GuestSSIDState'),
                $getParam('InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.Enable'),
                $getParam('Device.WiFi.AccessPoint.5.Enable')
            ),
            'Enabled',
            'Disabled'
        );

        $customiseName = $firstNonEmpty(
            $getParam('InternetGatewayDevice.DeviceInfo.X_TDTC_CustomiseName')
        );
        if ($customiseName !== null && strcasecmp((string) $customiseName, (string) ($data['product_class'] ?? '')) === 0) {
            $customiseName = null;
        }

        $friendlyName = $firstNonEmpty(
            $customiseName,
            $getParam('VirtualParameters.PPPUsername'),
            $pppoeUsername !== 'N/A' ? $pppoeUsername : null,
            $wifiSsid
        );
        $data['customer_name'] = $friendlyName ?? 'N/A';
        $data['ont_name'] = $friendlyName ?? 'N/A';

        // Connected Devices (LAN Hosts)
        $connectedDevices = [];

        // Get hosts from LANDevice.1.Hosts.Host
        $hostsBase = 'InternetGatewayDevice.LANDevice.1.Hosts.Host';

        // Get device's last inform time for comparison
        $deviceLastInformTime = null;
        if ($lastInform) {
            $deviceLastInformTime = strtotime($lastInform);
        }

        // Try to get hosts object
        if (isset($device['InternetGatewayDevice']['LANDevice']['1']['Hosts']['Host'])) {
            $hosts = $device['InternetGatewayDevice']['LANDevice']['1']['Hosts']['Host'];

            // Iterate through all host entries
            foreach ($hosts as $hostId => $hostData) {
                // Skip metadata fields
                $hostId = is_string($hostId) ? $hostId : (string) $hostId;
                if ($hostId !== '' && strpos($hostId, '_') === 0) {
                    continue;
                }

                // Get host details
                $ipAddress = isset($hostData['IPAddress']['_value']) ? $hostData['IPAddress']['_value'] : null;
                $macAddress = isset($hostData['MACAddress']['_value']) ? $hostData['MACAddress']['_value'] : null;
                $hostName = isset($hostData['HostName']['_value']) ? $hostData['HostName']['_value'] : '';
                $interfaceType = isset($hostData['InterfaceType']['_value']) ? $hostData['InterfaceType']['_value'] : 'Unknown';
                $active = isset($hostData['Active']['_value']) ? $hostData['Active']['_value'] : null;
                $timestamp = isset($hostData['_timestamp']) ? $hostData['_timestamp'] : null;

                // Only add devices with valid IP and MAC
                if ($ipAddress && $macAddress) {
                    // Filter strategy: Only count hosts that were updated recently relative to device last inform
                    // This filters out old/disconnected devices from GenieACS historical data
                    $isRecentlyActive = true; // Default to true if no timestamp

                    if ($timestamp && $deviceLastInformTime) {
                        $hostTimestamp = strtotime($timestamp);
                        if ($hostTimestamp !== false) {
                            // Strategy: Count host as active if:
                            // 1. Host timestamp is within 3 hours before OR after device last inform
                            // 2. This catches hosts that were active around the time of last inform
                            //    (accounts for clock drift and DHCP lease refresh timing)
                            $threeHoursBefore = $deviceLastInformTime - (3 * 3600);
                            $threeHoursAfter = $deviceLastInformTime + (3 * 3600);
                            $isRecentlyActive = ($hostTimestamp >= $threeHoursBefore && $hostTimestamp <= $threeHoursAfter);
                        }
                    }

                    // Skip hosts that are not recently active
                    if (!$isRecentlyActive) {
                        continue;
                    }

                    // Determine interface type (WiFi/LAN)
                    $connectionType = 'LAN';
                    if ($interfaceType === '802.11') {
                        $connectionType = 'WiFi';
                    } elseif ($interfaceType === 'Ethernet') {
                        $connectionType = 'Ethernet';
                    }

                    // Get MAC vendor name
                    $vendorName = getMACVendor($macAddress, $hostName);

                    // If hostname is empty and vendor found, use vendor name
                    // Otherwise use "Unknown Device"
                    if (empty($hostName) || trim($hostName) === '') {
                        $hostName = $vendorName;
                    }

                    $connectedDevices[] = [
                        'hostname' => $hostName,
                        'vendor' => $vendorName,
                        'ip_address' => $ipAddress,
                        'mac_address' => $macAddress,
                        'interface_type' => $connectionType,
                        'active' => $active ?? true, // Default to active if not specified
                    ];
                }
            }
        }

        $data['connected_devices'] = $connectedDevices;
        $connectedDevicesCount = count($connectedDevices);
        $fallbackStations = $getParam('VirtualParameters.TotalStations');
        $fallbackActiveDevices = $getParam('VirtualParameters.activedevices');
        $hostEntryCount = $getParam('InternetGatewayDevice.LANDevice.1.Hosts.HostNumberOfEntries');
        if ($connectedDevicesCount <= 0) {
            if (is_numeric($fallbackStations) && intval($fallbackStations) > 0) {
                $connectedDevicesCount = intval($fallbackStations);
            } elseif (is_numeric($fallbackActiveDevices) && intval($fallbackActiveDevices) > 0) {
                $connectedDevicesCount = intval($fallbackActiveDevices);
            } elseif (is_numeric($hostEntryCount) && intval($hostEntryCount) > 0) {
                $connectedDevicesCount = intval($hostEntryCount);
            }
        }
        $data['connected_devices_count'] = $connectedDevicesCount;

        // DHCP Server Configuration
        $dhcpServer = [];
        $dhcpBase = 'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement';

        // Check if DHCP capability exists by checking for any DHCP parameter
        // (not just DHCPServerEnable, as it may not have _value if not configured)
        $hasdhcpCapability = false;

        // Check multiple DHCP parameters to determine if device supports DHCP
        $dhcpEnabled = $getParam("{$dhcpBase}.DHCPServerEnable");
        $dhcpLeaseTime = $getParam("{$dhcpBase}.DHCPLeaseTime");

        // Device has DHCP capability if any DHCP parameter is present
        if ($dhcpEnabled !== null || $dhcpLeaseTime !== null) {
            $hasdhcpCapability = true;
        }

        if ($hasdhcpCapability) {
            // Extract DHCP parameters (use false/N/A as defaults if not configured)
            $dhcpServer['enabled'] = $dhcpEnabled ?? false;
            $dhcpServer['configurable'] = $getParam("{$dhcpBase}.DHCPServerConfigurable") ?? true;
            $dhcpServer['min_address'] = $getParam("{$dhcpBase}.MinAddress") ?? 'N/A';
            $dhcpServer['max_address'] = $getParam("{$dhcpBase}.MaxAddress") ?? 'N/A';
            $dhcpServer['subnet_mask'] = $getParam("{$dhcpBase}.SubnetMask") ?? 'N/A';
            $dhcpServer['gateway'] = $getParam("{$dhcpBase}.IPRouters") ?? 'N/A';
            $dhcpServer['dns_servers'] = $getParam("{$dhcpBase}.DNSServers") ?? 'N/A';
            $dhcpServer['lease_time'] = $dhcpLeaseTime ?? 86400; // Default to 24 hours

            $data['dhcp_server'] = $dhcpServer;
        } else {
            // Device does not support DHCP - set to null
            $data['dhcp_server'] = null;
        }

        // === OLT Area Mapping (berdasarkan subnet IP ONT) ===
        $oltAreas = [
            ['prefix' => '172.16.200.', 'area' => 'Cicaheum',         'olt_ip' => '10.88.0.4'],
            ['prefix' => '172.16.100.', 'area' => 'Rusun',            'olt_ip' => '10.88.0.8'],
            ['prefix' => '172.16.110.', 'area' => 'Singaparna',       'olt_ip' => '10.88.0.20'],
            ['prefix' => '172.16.98.',  'area' => 'Tasikmalaya',      'olt_ip' => '10.88.0.14'],
            ['prefix' => '192.168.101.','area' => 'Cikalong Wetan',   'olt_ip' => '10.88.0.2'],
            ['prefix' => '172.11.101.', 'area' => 'Pangalengan 1',    'olt_ip' => '10.88.0.61'],
            ['prefix' => '120.100.',    'area' => 'Batujaya',         'olt_ip' => '10.88.0.25'],
            ['prefix' => '172.41.141.', 'area' => 'Bojong Asih',      'olt_ip' => '10.88.0.62'],
            ['prefix' => '172.16.95.',  'area' => 'Jamblang',         'olt_ip' => '10.88.0.17'],
            ['prefix' => '172.16.130.', 'area' => 'Garut/Pamengpeuk', 'olt_ip' => '10.88.0.12'],
            ['prefix' => '192.168.5.',  'area' => 'Sumedang',         'olt_ip' => '10.88.0.7'],
            ['prefix' => '172.15.200.', 'area' => 'Pangalengan 2',    'olt_ip' => '10.88.0.33'],
            ['prefix' => '172.22.100.', 'area' => 'Kalangsuria',      'olt_ip' => '172.21.100.254'],
        ];

        $oltArea = 'Unknown';
        $oltRouterIp = 'N/A';
        $ipAddress = is_string($ipAddress) ? trim($ipAddress) : '';
        if ($ipAddress !== '' && $ipAddress !== 'N/A' && $ipAddress !== '0.0.0.0') {
            foreach ($oltAreas as $area) {
                $prefix = isset($area['prefix']) ? (string) $area['prefix'] : '';
                if ($prefix !== '' && str_starts_with($ipAddress, $prefix)) {
                    $oltArea     = $area['area'];
                    $oltRouterIp = $area['olt_ip'];
                    break;
                }
            }
        }
        $data['olt_area']      = $oltArea;
        $data['olt_router_ip'] = $oltRouterIp;

        // Web Admin URL — langsung ke interface web ONT
        $data['web_admin_url'] = ($ipAddress !== 'N/A' && $ipAddress !== '0.0.0.0')
            ? "http://{$ipAddress}"
            : 'N/A';

        // Admin Web Access Credentials
        $data['admin_user']       = $firstNonEmpty(
            $getParam('VirtualParameters.superAdmin'),
            $getParam('VirtualParameters.WebAdmin-User'),
            $getParam('VirtualParameters.userAdmin')
        ) ?? 'N/A';
        $data['admin_password']   = $firstNonEmpty(
            $getParam('VirtualParameters.superPassword'),
            $getParam('VirtualParameters.WebAdmin-Pass'),
            $getParam('VirtualParameters.userPassword')
        ) ?? 'N/A';
        $data['telecom_password'] = $getParam('InternetGatewayDevice.DeviceInfo.X_CT-COM_TeleComAccount.Password') ?? 'N/A';

        $tags = $device['_tags'] ?? [];
        $provisionVersionTag = 'N/A';
        if (is_array($tags)) {
            foreach ($tags as $tag) {
                if (strpos((string) $tag, 'netking_provision_version_') === 0) {
                    $provisionVersionTag = (string) $tag;
                    break;
                }
            }
        }

        $data['first_inform_at'] = $formatValue($firstNonEmpty(
            $getParam('VirtualParameters.FirstInformAt'),
            $data['last_inform']
        ));
        $data['bootstrap_status'] = $formatValue($firstNonEmpty(
            $getParam('VirtualParameters.BootstrapStatus'),
            in_array('netking_bootstrap_seen', $tags, true) ? 'seen' : 'unknown'
        ));
        $data['provision_version'] = $formatValue($firstNonEmpty(
            $getParam('VirtualParameters.ProvisionVersion'),
            $provisionVersionTag
        ));
        $data['last_provision_result'] = $formatValue($getParam('VirtualParameters.LastProvisionResult'));
        $data['firmware_version_normalized'] = $formatValue($firstNonEmpty(
            $getParam('VirtualParameters.FirmwareVersionNormalized'),
            $data['software_version']
        ));
        $data['firmware_target'] = $formatValue($getParam('VirtualParameters.FirmwareTarget'));
        $data['upgrade_state'] = $formatValue($getParam('VirtualParameters.UpgradeState'));

        // Tags
        $data['tags'] = $tags;

        return $data;
    }
}
