<?php

namespace AppBundle\Network;

use Psr\Log\LoggerInterface;
use AppBundle\Command\Executor;
use AppBundle\Exception\NetworkException;
use AppBundle\Network\ConfigurationReader;
use AppBundle\Network\NetworkInterfaceWirelessConnection;

class NetworkInterface
{
    /**
     * @var array
     */
    private static $excludedInterfaces = [
        'lo'
    ];

    /**
     * @var Executor
     */
    private $commandExecutor;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $operationState;

    /**
     * @var string
     */
    private $macAddress;

    /**
     * @var string
     */
    private $ipAddress;

    /**
     * @var string
     */
    private $netmask;

    /**
     * @var string
     */
    private $rxPacketsCount;

    /**
     * @var string
     */
    private $txPacketsCount;

    /**
     * @var string
     */
    private $rxBytesCount;

    /**
     * @var string
     */
    private $txBytesCount;

    /**
     * @var NetworkInterfaceWirelessConnection
     */
    private $wirelessConnection;

    public function __construct($interfaceName, Executor $commandExecutor)
    {
        $this->commandExecutor = $commandExecutor;
        $this->name = $interfaceName;
    }

    /**
     * Gets the value of name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the operation state of the interface.
     *
     * @see https://www.kernel.org/doc/Documentation/networking/operstates.txt
     * @return array The operation state of the interface with the label class.
     */
    public function getOperationState()
    {
        $operationStates = [
            'up' => ['Up and running!', 'success'],
            'dormant' => ['Waiting...', 'warning'],
            'notPresent' => ['Not present', 'warning'],
            'down' => ['Not running', 'danger'],
        ];

        if (is_null($this->operationState)) {
            $this->populateInterfaceConfiguration();
        }

        if (isset($operationStates[$this->operationState])) {
            return $operationStates[$this->operationState];
        }

        return null;
    }

    /**
     * Returns the MAC address of the interface.
     *
     * @return string The MAC address of the interface.
     */
    public function getMacAddress()
    {
        if (is_null($this->macAddress)) {
            $this->populateInterfaceConfiguration();
        }

        return $this->macAddress;
    }

    /**
     * Returns the IP address of the interface.
     *
     * @return string The IP address of the interface.
     */
    public function getIpAddress()
    {
        if (is_null($this->ipAddress)) {
            $this->populateInterfaceConfiguration();
        }

        return $this->ipAddress;
    }

    /**
     * Returns the netmask of the interface.
     *
     * @return string The netmask of the interface.
     */
    public function getNetmask()
    {
        if (is_null($this->netmask)) {
            $this->populateInterfaceConfiguration();
        }

        return $this->netmask;
    }

    /**
     * Returns the RX packets count of the interface.
     *
     * @return string The RX packets count of the interface.
     */
    public function getRxPacketsCount()
    {
        if (is_null($this->rxPacketsCount)) {
            $this->populateInterfaceConfiguration();
        }

        if (is_array($this->rxPacketsCount) && isset($this->rxPacketsCount[1])) {
            return $this->rxPacketsCount[1];
        }

        return null;
    }

    /**
     * Returns the TX packets count of the interface.
     *
     * @return string The TX packets count of the interface.
     */
    public function getTxPacketsCount()
    {
        if (is_null($this->txPacketsCount)) {
            $this->populateInterfaceConfiguration();
        }

        if (is_array($this->txPacketsCount) && isset($this->txPacketsCount[1])) {
            return $this->txPacketsCount[1];
        }

        return null;
    }

    /**
     * Returns the RX bytes count of the interface.
     *
     * @return string The RX bytes count of the interface.
     */
    public function getRxBytesCount()
    {
        if (is_null($this->rxBytesCount)) {
            $this->populateInterfaceConfiguration();
        }

        if (is_array($this->rxBytesCount) && isset($this->rxBytesCount[2])) {
            return $this->rxBytesCount[2];
        }

        return null;
    }

    /**
     * Returns the TX bytes count of the interface.
     *
     * @return string The TX bytes count of the interface.
     */
    public function getTxBytesCount()
    {
        if (is_null($this->txBytesCount)) {
            $this->populateInterfaceConfiguration();
        }

        if (is_array($this->txBytesCount) && isset($this->txBytesCount[2])) {
            return $this->txBytesCount[2];
        }

        return null;
    }

    /**
     * Returns true if the interface is a wireless interface.
     *
     * @return boolean True if the interface is a wireless interface.
     */
    public function isWireless()
    {
        $wirelessConnection = $this->getWirelessConnection();

        return ($wirelessConnection != null);
    }

    /**
     * Returns the connection of the interface.
     *
     * @return string The connection of the interface.
     */
    public function getWirelessConnection()
    {
        $command = $this->commandExecutor->execute('/sbin/iwconfig '.$this->name);

        if (!$command->isValid()) {
            return null;
        }

        if ($command->isValid()) {
            $output = implode(' ', $command->getOutput());

            if (empty($output) || strpos($output, 'no wireless extensions') !== false) {
                return null;
            }
        }

        if (is_null($this->wirelessConnection)) {
            $this->wirelessConnection = new NetworkInterfaceWirelessConnection($this->name, $this->commandExecutor);
        }

        return $this->wirelessConnection;
    }

    /**
     * Returns an array of wireless connection details.
     *
     * @return array The wireless connection details of the interface.
     */
    public function getWirelessConnectionDetails()
    {
        $wirelessConnection = $this->getWirelessConnection();

        if (!is_null($wirelessConnection)) {
            return [
                'ssid' => $wirelessConnection->getNetworkSsid(),
                'bssid' => $wirelessConnection->getNetworkBssid(),
                'bitrate' => $wirelessConnection->getNetworkBitrate(),
                'frequency' => $wirelessConnection->getNetworkFrequency(),
                'link_quality' => $wirelessConnection->getNetworkLinkQualityPercentage(),
                'signal_level' => $wirelessConnection->getNetworkSignalLevelPercentage(),
            ];
        }

        return null;
    }

    /**
     * Populates the interface configuration details.
     *
     * @return bool True if the population succeeded, false if not.
     */
    private function populateInterfaceConfiguration()
    {
        $command = $this->commandExecutor->execute('cat /sys/class/net/'.$this->name.'/operstate');

        if ($command->isValid()) {
            $output = $command->getOutput();

            if (isset($output[0])) {
                $this->operationState = $output[0];
            }
        }

        $command = $this->commandExecutor->execute('/sbin/ifconfig '.$this->name);

        if ($command->isValid()) {
            $ifconfig = implode(' ', $command->getOutput());
            $ifconfig = preg_replace('/\s\s+/', ' ', $ifconfig);

            $configurationReader = new ConfigurationReader($ifconfig);

            $this->macAddress = $configurationReader->read('/HWaddr ([0-9a-f:]+)/i');
            $this->ipAddress = $configurationReader->read('/inet addr:([0-9.]+)/i');
            $this->netmask = $configurationReader->read('/Mask:([0-9.]+)/i');
            $this->rxPacketsCount = $configurationReader->read('/RX packets:(\d+)/');
            $this->txPacketsCount = $configurationReader->read('/TX packets:(\d+)/');
            $this->rxBytesCount = $configurationReader->read('/RX bytes:(\d+) \((\d+.\d+ [K|M|G]iB)\)/i');
            $this->txBytesCount = $configurationReader->read('/TX Bytes:(\d+) \((\d+.\d+ [K|M|G]iB)\)/i');

            return true;
        }

        return false;
    }

    /**
     * Disables the interface.
     *
     * @return bool True if the interface is succesfully shutted down, false if not.
     */
    public function down()
    {
        $command = $this->commandExecutor->execute(sprintf('sudo ifdown %s', $this->name));

        return $command->isValid();
    }

    /**
     * Enables the interface.
     *
     * @return bool True if the interface is succesfully started, false if not.
     */
    public function up()
    {
        $command = $this->commandExecutor->execute(sprintf('sudo ifup %s', $this->name));

        return $command->isValid();
    }

    /**
     * Restarts the interface.
     *
     * @return bool True if the interface is succesfully restarted, false if not.
     */
    public function restart()
    {
        if ($this->down()) {
            if ($this->up()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns true if the interface is an excluded interface.
     *
     * @param bool $interfaceName The interface name to check.
     * @return boolean True if the interface is an excluded interface.
     */
    private static function isExcludedInterface($interfaceName)
    {
        return in_array($interfaceName, NetworkInterface::$excludedInterfaces);
    }

    /**
     * Returns the interface with the given name.
     *
     * @param string $interfaceName The interface name, example: eth0 or wlan0.
     * @param Executor $commandExecutor The command executor service.
     * @return NetworkInterface The network interface object.
     */
    public static function get($interfaceName, Executor $commandExecutor)
    {
        if (NetworkInterface::isExcludedInterface($interfaceName)) {
            throw new NetworkException('Interface "'.$interfaceName.'" is an excluded interface');
        }

        return new NetworkInterface($interfaceName, $commandExecutor);
    }

    /**
     * Returns all network interfaces.
     *
     * @param Executor $commandExecutor The command executor service.
     * @return array|bool An array with NetworkInterface objects.
     */
    public static function getAll(Executor $commandExecutor)
    {
        $command = $commandExecutor->execute('ls /sys/class/net -1');

        if ($command->isValid()) {
            $interfaces = array();

            foreach ($command->getOutput() as $interfaceName) {
                if (!NetworkInterface::isExcludedInterface($interfaceName)) {
                    $interfaces[] = NetworkInterface::get($interfaceName, $commandExecutor);
                }
            }

            return $interfaces;
        }

        return false;
    }
}
