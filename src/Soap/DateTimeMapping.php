<?php

namespace Pace\Soap;

use Carbon\Carbon;
use SimpleXMLElement;
use Pace\Contracts\Soap\TypeMapping;

class DateTimeMapping implements TypeMapping
{
    /**
     * The format used by the web service.
     *
     * @var string
     */
    protected $xmlFormat = 'Y-m-d\TH:i:s.u\Z';

    /**
     * Get the name of the SOAP data type.
     *
     * @return string
     */
    public function getTypeName()
    {
        return 'dateTime';
    }

    /**
     * Get the type namespace.
     *
     * @return string
     */
    public function getTypeNamespace()
    {
        return 'http://www.w3.org/2001/XMLSchema';
    }

    /**
     * Convert the supplied XML string to a Carbon instance.
     *
     * @param string $xml
     * @return Carbon
     */
    public function fromXml($xml)
    {
        return Carbon::createFromFormat($this->xmlFormat, new SimpleXMLElement($xml), 'UTC');
    }

    /**
     * Convert the supplied Carbon instance to an XML string.
     *
     * @param Carbon $php
     * @return string
     */
    public function toXml($php)
    {
        return sprintf('<%1$s>%2$s</%1$s>', $this->getTypeName(), $php->format($this->xmlFormat));
    }
}