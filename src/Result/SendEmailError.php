<?php

namespace Salesforce\SoapClient\Result;

class SendEmailError extends Error
{
    protected $targetObjectId;

    public function getTargetObjectId()
    {
        return $this->targetObjectId;
    }
}
