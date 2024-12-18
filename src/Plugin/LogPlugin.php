<?php
namespace Salesforce\SoapClient\Plugin;

use Psr\Log\LoggerInterface;
use Salesforce\SoapClient\Event\FaultEvent;
use Salesforce\SoapClient\Event\RequestEvent;
use Salesforce\SoapClient\Event\ResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A plugin that logs messages
 *
 *  */
class LogPlugin implements EventSubscriberInterface
{
    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function onClientRequest(RequestEvent $event)
    {
        $this->logger->info(sprintf(
            '[phpforce/soap-client] request: call "%s" with params %s',
            $event->getMethod(),
            \json_encode($event->getParams())
        ));
    }

    public function onClientResponse(ResponseEvent $event)
    {
        $this->logger->info(sprintf(
            '[phpforce/soap-client] response: %s',
            \print_r($event->getResponse(), true)
        ));
    }

    public function onClientFault(FaultEvent $event)
    {
        $this->logger->error(sprintf(
            '[phpforce/soap-client] fault "%s" for request "%s" with params %s',
            $event->getSoapFault()->getMessage(),
            $event->getRequestEvent()->getMethod(),
            \json_encode($event->getRequestEvent()->getParams())
        ));
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            'phpforce.soap_client.request'  => 'onClientRequest',
            'phpforce.soap_client.response' => 'onClientResponse',
            'phpforce.soap_client.fault'    => 'onClientFault'
        );
    }
}
