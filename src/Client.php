<?php

namespace Pace;

use SoapFault;
use Pace\Contracts\Soap\Factory as SoapFactory;

class Client
{
    /**
     * The object context services.
     */
    const CREATE_SERVICE = 'CreateObject';
    const DELETE_SERVICE = 'DeleteObject';
    const CLONE_SERVICE = 'CloneObject';
    const FIND_SERVICE = 'FindObjects';
    const READ_SERVICE = 'ReadObject';
    const UPDATE_SERVICE = 'UpdateObject';

    /**
     * The system services.
     */
    const ATTACHMENT_SERVICE = 'AttachmentService';
    const FIND_COMPANY_SERVICE = 'FindCompany';
    const GEOLOCATE_SERVICE = 'GeoLocate';
    const INSPECT_SERVICE = 'SystemInspector';
    const INVOKE_ACTION_SERVICE = 'InvokeAction';
    const INVOKE_CONNECT_SERVICE = 'InvokePaceConnect';
    const INVOKE_PROCESS_SERVICE = 'InvokeProcess';
    const VERSION_SERVICE = 'Version';

    /**
     * The primary key field.
     */
    const PRIMARY_KEY = 'primaryKey';

    /**
     * Object types which are not pretty.
     *
     * @var array
     */
    protected $irregularTypes = [
        'csr' => ['cSR', 'CSR'],
        'glLocation' => ['gLLocation', 'GLLocation'],
        'uomType' => ['uOMType', 'UOMType'],
        'crmStatus' => ['cRMStatus', 'CRMStatus'],
        'glSchedule' => ['gLSchedule', 'GLSchedule'],
        'glAccountBalanceSummary' => ['gLAccountBalanceSummary', 'GLAccountBalanceSummary'],
        'glJournalEntryAudit' => ['gLJournalEntryAudit', 'GLJournalEntryAudit'],
        'rssChannel' => ['rSSChannel', 'RSSChannel'],
        'faSetup' => ['fASetup', 'FASetup'],
        'arSetup' => ['aRSetup', 'ARSetup'],
        'glScheduleLine' => ['gLScheduleLine', 'GLScheduleLine'],
        'uom' => ['uOM', 'UOM'],
        'jmfReceivedMessageTransaction' => ['jMFReceivedMessageTransaction', 'JMFReceivedMessageTransaction'],
        'uomRange' => ['uOMRange', 'UOMRange'],
        // TODO: finish this list.
    ];

    /**
     * Cached services (SOAP clients).
     *
     * @var array
     */
    protected $services = [];

    /**
     * The SOAP client factory.
     *
     * @var SoapFactory
     */
    protected $soapFactory;

    /**
     * The Pace services URL.
     *
     * @var string
     */
    protected $url;

    /**
     * Create a new instance.
     *
     * @param SoapFactory $soapFactory
     * @param string $host
     * @param string $login
     * @param string $password
     * @param string $scheme
     */
    public function __construct(SoapFactory $soapFactory, $host, $login, $password, $scheme = 'https')
    {
        $soapFactory->setOptions(compact('login', 'password'));
        $this->soapFactory = $soapFactory;

        $this->url = sprintf('%s://%s/rpc/services/', $scheme, $host);
    }

    /**
     * Dynamically retrieve the specified model.
     *
     * @param string $type
     * @return Model
     */
    public function __get($type)
    {
        return $this->model($type);
    }

    /**
     * Get the camel-cased object type.
     *
     * @param string $type
     * @return string
     */
    public function camelCase($type)
    {
        if (array_key_exists($type, $this->irregularTypes)) {
            return $this->irregularTypes[$type][0];
        }

        return $type;
    }

    /**
     * Clone an object.
     *
     * @param string $type
     * @param object|array $object
     * @param object|array $newObject
     * @param string|int $newPrimaryKey
     * @param object|array $newParent
     * @return \stdClass
     */
    public function cloneObject($type, $object, $newObject, $newPrimaryKey = null, $newParent = null)
    {
        $type = $this->studlyCase($type);
        $method = 'clone' . $type;

        return $this->soapClient(Client::CLONE_SERVICE)->$method([
            $type => $object,
            $type . 'AttributesToOverride' => $newObject,
            'newPrimaryKey' => $newPrimaryKey,
            'newParent' => $newParent
        ])->out;
    }

    /**
     * Create an object.
     *
     * @param string $type
     * @param object|array $object
     * @return \stdClass
     */
    public function createObject($type, $object)
    {
        $method = 'create' . $this->studlyCase($type);

        return $this->soapClient(Client::CREATE_SERVICE)
            ->$method([$this->camelCase($type) => $object])
            ->out;
    }

    /**
     * Delete an object by its primary key.
     *
     * @param string $type
     * @param int|string $key
     */
    public function deleteObject($type, $key)
    {
        return $this->soapClient(Client::DELETE_SERVICE)
            ->deleteObject(['in0' => $this->studlyCase($type), 'in1' => $key]);
    }

    /**
     * Find primary keys for the specified object using a filter (and optionally sort).
     *
     * @param string $type
     * @param string $filter
     * @param array $sort
     * @return mixed
     */
    public function findObjects($type, $filter, array $sort = null)
    {
        if (!empty($sort)) {
            $response = $this->soapClient(Client::FIND_SERVICE)
                ->findAndSort(['in0' => $this->studlyCase($type), 'in1' => $filter, 'in2' => $sort])
                ->out;
        } else {
            $response = $this->soapClient(Client::FIND_SERVICE)
                ->find(['in0' => $this->studlyCase($type), 'in1' => $filter])
                ->out;
        }

        return isset($response->string) ? $response->string : null;
    }

    /**
     * Get a model instance.
     *
     * @param string $type
     * @return Model
     */
    public function model($type)
    {
        return new Model($this, $type);
    }

    /**
     * Read the specified object type by its primary key.
     *
     * @param string $type
     * @param mixed $key
     * @return \stdClass|null
     * @throws SoapFault if an unexpected SOAP error occurs.
     */
    public function readObject($type, $key)
    {
        $method = 'read' . $this->studlyCase($type);

        try {
            return $this->soapClient(Client::READ_SERVICE)
                ->$method([$this->camelCase($type) => [self::PRIMARY_KEY => $key]])
                ->out;
        } catch (SoapFault $e) {
            if (strpos($e->getMessage(), 'Unable to locate object') !== 0) {
                throw $e;
            }
        }
    }

    /**
     * Get a SOAP client for the specified service.
     *
     * @param $service
     * @return \SoapClient
     */
    public function soapClient($service)
    {
        if (!isset($this->services[$service])) {
            $this->services[$service] = $this->soapFactory->make($this->getServiceWsdl($service));
        }

        return $this->services[$service];
    }

    /**
     * Get the studly-cased object type.
     *
     * @param string $type
     * @return string
     */
    public function studlyCase($type)
    {
        if (array_key_exists($type, $this->irregularTypes)) {
            return $this->irregularTypes[$type][1];
        }

        return ucfirst($type);
    }

    /**
     * Update an object.
     *
     * @param string $type
     * @param \stdClass|array $object
     * @return mixed
     */
    public function updateObject($type, $object)
    {
        $method = 'update' . $this->studlyCase($type);

        return $this->soapClient(Client::UPDATE_SERVICE)
            ->$method([$this->camelCase($type) => $object])
            ->out;
    }

    /**
     * Check the version of Pace running on the server.
     *
     * @return string
     */
    public function version()
    {
        return $this->soapClient(self::VERSION_SERVICE)->getVersion()->out;
    }

    /**
     * Assemble the specified service's WSDL.
     *
     * @param $service
     * @return string
     */
    protected function getServiceWsdl($service)
    {
        return $this->url . $service . '?wsdl';
    }
}
