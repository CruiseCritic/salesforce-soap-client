<?php

namespace Salesforce\SoapClient\EventListener;

use Psr\Log\LoggerInterface;
use Salesforce\SoapClient\Event;

class LogTransactionListener
{
    /**
     * Logger
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Whether logging is enabled
     *
     * @var boolean
     */
    private $logging;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function onSalesforceClientResponse(\Salesforce\SoapClient\Event\ResponseEvent $event)
    {
        if (true === $this->logging) {
            $this->logger->debug('[Salesforce] response:', array($event->getResponse()));
        }
    }

    public function onSalesforceClientSoapFault(Event\FaultEvent $event)
    {
        $this->logger->error('[Salesforce] fault: ' . $event->getSoapFault()->getMessage());
    }

    public function onSalesforceClientError(Event\ErrorEvent $event)
    {
        $error = $event->getError();
        $this->logger->error('[Salesforce] error: ' . $error->statusCode . ' - ' . $error->message, get_object_vars($error));
    }

    public function setLogging($logging)
    {
        $this->logging = $logging;
    }

    public function getLogging()
    {
        return $this->logging;
    }
}
