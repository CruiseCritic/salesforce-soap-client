<?php

namespace Salesforce\SoapClient\Result;

class GetServerTimestampResult
{
    protected $timestamp;

    /**
     * @return \DateTime
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }
}
