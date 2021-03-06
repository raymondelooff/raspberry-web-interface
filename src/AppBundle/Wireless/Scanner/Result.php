<?php

namespace AppBundle\Wireless\Scanner;

class Result
{
    const OPEN = 'Open';
    const WEP = 'WEP';
    const WPA_PSK_TKIP = 'WPA-PSK (TKIP)';
    const WPA_PSK_AES = 'WPA-PSK (AES)';
    const WPA2_PSK_TKIP = 'WPA2-PSK (TKIP)';
    const WPA2_PSK_AES = 'WPA2-PSK (AES)';
    const WPA_WPA2_PSK_TKIP_AES = 'WPA/WPA2-PSK (TKIP/AES)';
    const WPA2_EAP_AES = 'WPA2-EAP (AES)';
    const KEY_MANAGEMENT_WPA_PSK = 'WPA-PSK';
    const KEY_MANAGEMENT_WPA_EAP = 'WPA-PSK';
    const KEY_MANAGEMENT_NONE = 'NONE';

    /**
     * @var string
     */
    private $bssid;

    /**
     * @var string
     */
    private $frequency;

    /**
     * @var string
     */
    private $signalLevel;

    /**
     * @var string
     */
    private $flags;

    /**
     * @var string
     */
    private $ssid;

    /**
     * @var string
     */
    private $security;

    /**
     * @var string
     */
    private $keyManagement;

    /**
     * @var bool
     */
    private $wpsEnabled;

    public function __construct($row)
    {
        // Format row
        $this->formatResultRow($row);
    }

    /**
     * Gets the value of bssid.
     *
     * @return string
     */
    public function getBssid()
    {
        return $this->bssid;
    }

    /**
     * Gets the value of frequency.
     *
     * @return string
     */
    public function getFrequency()
    {
        return $this->frequency;
    }

    /**
     * Gets the value of signalLevel.
     *
     * @return string
     */
    public function getSignalLevel()
    {
        return $this->signalLevel;
    }

    /**
     * Gets the value of signalLevel as a percentage.
     *
     * @see http://stackoverflow.com/a/15798024
     * @return string
     */
    public function getSignalLevelPercentage()
    {
        $signalLevel = $this->getSignalLevel();

        if (!is_null($signalLevel)) {
            if ($signalLevel < -100) {
                return 0;
            } elseif ($signalLevel > -50) {
                return 100;
            } else {
                return 2 * ($signalLevel + 100);
            }
        }

        return null;
    }

    /**
     * Gets the value of flags.
     *
     * @return string
     */
    public function getFlags()
    {
        return $this->flags;
    }

    /**
     * Gets the value of ssid.
     *
     * @return string
     */
    public function getSsid()
    {
        return $this->ssid;
    }

    /**
     * Returns the 2.4GHz channel of the network.
     *
     * @return int The channel of the network.
     */
    public function getChannel()
    {
        $frequency = 2412;
        $channel = 1;

        for ($channel = 1; $channel <= 13; $channel++) {
            if ($this->frequency == $frequency) {
                return $channel;
            }

            $frequency += 5;
        }

        return null;
    }

    /**
     * Returns the security setting of the network.
     *
     * @return string The security setting of the network.
     */
    public function getSecurity()
    {
        return $this->security;
    }

    /**
     * Returns the key management setting of the network.
     *
     * @return string The key management setting of the network.
     */
    public function getKeyManagement()
    {
        return $this->keyManagement;
    }

    /**
     * Returns true if the network has WPS enabled.
     *
     * @return boolean True if the network has WPS enabled, false otherwise.
     */
    public function hasWpsEnabled()
    {
        return $this->wpsEnabled;
    }

    /**
     * Returns an array of wireless connection details.
     *
     * @return array The wireless connection details of the interface.
     */
    public function getDetails()
    {
        return [
            'ssid' => $this->getSsid(),
            'bssid' => $this->getBssid(),
            'frequency' => $this->getFrequency(),
            'channel' => $this->getChannel(),
            'signal_level' => $this->getSignalLevelPercentage(),
            'security' => $this->getSecurity(),
            'key_management' => $this->getKeyManagement(),
            'wps' => $this->hasWpsEnabled(),
        ];
    }

    /**
     * Checks if the network has the given flag.
     *
     * @param $flag string The flag to check.
     * @return bool True if the network has the given flag, false otherwise.
     */
    private function hasFlag($flag)
    {
        preg_match('/(?P<wpa_psk>\[WPA-PSK(?P<wpa_psk_aes>-CCMP)?((\+|\-)(?P<wpa_psk_tkip>TKIP))?(\-preauth)?\])?(?P<wpa2_psk>\[WPA2-PSK(?P<wpa2_psk_aes>-CCMP)?((\+|\-)(?P<wpa2_psk_tkip>TKIP))?(\-preauth)?\])?(?P<wpa2_eap>\[WPA2-EAP(?P<wpa2_eap_aes>-CCMP)?\])?(?P<wps>\[WPS\])?(?P<wep>\[WEP\])?/i', $this->flags, $matches);

        return array_key_exists($flag, $matches) && !empty($matches[$flag]);
    }

    /**
     * Checks if the network has the given array of flags.
     *
     * @param $flags array The array of flags to check.
     * @return bool True if the network has the given flags, false otherwise.
     */
    private function hasFlags(array $flags)
    {
        foreach ($flags as $flag) {
            if (!$this->hasFlag($flag)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parses the network flags.
     *
     * @return void
     */
    public function parseFlags()
    {
        // WPS
        $this->wpsEnabled = $this->hasFlag('wps');

        // Check security
        if ($this->hasFlag('wep')) {
            // This network uses WEP :O
            $this->security = self::WEP;
            $this->keyManagement = self::KEY_MANAGEMENT_NONE;
            return;
        }

        // WPA/WPA2-PSK (TKIP/AES)
        if (
            $this->hasFlags(['wpa_psk', 'wpa2_psk']) &&
            ($this->hasFlag('wpa_psk_tkip') || $this->hasFlag('wpa_psk_aes')) &&
            ($this->hasFlag('wpa2_psk_tkip') || $this->hasFlag('wpa2_psk_aes'))
        ) {
            $this->security = self::WPA_WPA2_PSK_TKIP_AES;
            $this->keyManagement = self::KEY_MANAGEMENT_WPA_PSK;
            return;
        }

        // WPA-PSK
        if ($this->hasFlag('wpa_psk') && !$this->hasFlag('wpa2_psk')) {
            // WPA-PSK-AES
            if ($this->hasFlag('wpa_psk_aes')) {
                $this->security = self::WPA_PSK_AES;
                $this->keyManagement = self::KEY_MANAGEMENT_WPA_PSK;
                return;
            }

            // WPA-PSK-TKIP
            if ($this->hasFlag('wpa_psk_tkip')) {
                $this->security = self::WPA_PSK_TKIP;
                $this->keyManagement = self::KEY_MANAGEMENT_WPA_PSK;
                return;
            }
        }

        // WPA2-PSK
        if (!$this->hasFlag('wpa_psk') && $this->hasFlag('wpa2_psk')) {
            // WPA2-PSK-AES
            if ($this->hasFlag('wpa2_psk_aes')) {
                $this->security = self::WPA2_PSK_AES;
                $this->keyManagement = self::KEY_MANAGEMENT_WPA_PSK;
                return;
            }

            // WPA2-PSK-TKIP
            if ($this->hasFlag('wpa2_psk_tkip')) {
                $this->security = self::WPA2_PSK_TKIP;
                $this->keyManagement = self::KEY_MANAGEMENT_WPA_PSK;
                return;
            }
        }

        // WPA2-EAP
        if ($this->hasFlag('wpa2_eap')) {
            if ($this->hasFlag('wpa2_eap_aes')) {
                $this->security = self::WPA2_EAP_AES;
                $this->keyManagement = self::KEY_MANAGEMENT_WPA_EAP;
                return;
            }
        }

        // No security
        if (!$this->hasFlags(['wep', 'wpa_psk', 'wpa2_psk', 'wpa2_eap'])) {
            $this->security = self::OPEN;
            $this->keyManagement = self::KEY_MANAGEMENT_NONE;
            return;
        }
    }

    /**
     * Formats the given result row of the scanner.
     *
     * @param string $row A result row of the scanner.
     * @return void
     */
    public function formatResultRow($row)
    {
        $row = preg_split('/[\t]+/', $row);

        $this->bssid = $row[0];
        $this->frequency = $row[1];
        $this->signalLevel = $row[2];
        $this->flags = $row[3];
        $this->ssid = $row[4];

        // Parse flags
        $this->parseFlags();
    }
}
