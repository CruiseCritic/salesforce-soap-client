<?php

namespace Salesforce\SoapClient\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Salesforce\SoapClient\Client;
use Salesforce\SoapClient\Event\RequestEvent;
use Salesforce\SoapClient\Event\ResponseEvent;
use Salesforce\SoapClient\Exception\SaveException;
use Salesforce\SoapClient\Result\DeleteResult;
use Salesforce\SoapClient\Result\Error;
use Salesforce\SoapClient\Result\LoginResult;
use Salesforce\SoapClient\Result\MergeResult;
use Salesforce\SoapClient\Result\QueryResult;
use Salesforce\SoapClient\Result\SaveResult;
use Salesforce\SoapClient\Soap\SoapClient;
use Symfony\Component\EventDispatcher\EventDispatcher;

class ClientTest extends TestCase
{
    public function testDelete()
    {
        $deleteResult = $this
            ->getMockBuilder(DeleteResult::class)
            ->onlyMethods(['getId', 'isSuccess'])
            ->getMock();
        $deleteResult
            ->expects($this->once())
            ->method('isSuccess')
            ->willReturn(true);

        $result = new \stdClass();
        $result->result = [$deleteResult];

        $soapClient = $this->getSoapClient(['delete']);
        $soapClient->expects($this->once())
            ->method('delete')
            ->with(['ids' => ['001M0000008tWTFIA2']])
            ->willReturn($result);

        $this->getClient($soapClient)->delete(['001M0000008tWTFIA2']);
    }

    public function testQuery()
    {
        $soapClient = $this->getSoapClient(['query']);

        $queryResultMock = $this
            ->getMockBuilder(QueryResult::class)
            ->onlyMethods(['getSize', 'getRecords'])
            ->getMock();
        $queryResultMock
            ->expects($this->once())
            ->method('getSize')
            ->willReturn(1);
        $queryResultMock
            ->method('getRecords')
            ->willReturn([
                (object)[
                    'Id' => '001M0000008tWTFIA2',
                    'Name' => 'Company',
                ],
            ]);
        $result = new \stdClass();
        $result->result = $queryResultMock;

        $soapClient->expects($this->any())
            ->method('query')
            ->willReturn($result);

        $client = new Client($soapClient, 'username', 'password', 'token');
        $result = $client->query('Select Name from Account Limit 1');
        $this->assertInstanceOf('Salesforce\SoapClient\Result\RecordIterator', $result);
        $this->assertEquals(1, $result->count());
    }

    public function testInvalidQueryThrowsSoapFault()
    {
        $soapClient = $this->getSoapClient(['query']);
        $soapClient
            ->expects($this->once())
            ->method('query')
            ->will($this->throwException(new \SoapFault('C', "INVALID_FIELD:
Select aId, Name from Account LIMIT 1
       ^
ERROR at Row:1:Column:8
No such column 'aId' on entity 'Account'. If you are attempting to use a custom field, be sure to append the '__c' after the custom field name. Please reference your WSDL or the describe call for the appropriate names.")));

        $client = $this->getClient($soapClient);

        $this->expectException('\SoapFault');
        $client->query('Select NonExistingField from Account');
    }

    public function testInvalidUpdateResultsInError()
    {
        $error = $this->getMockBuilder(Error::class)
            ->onlyMethods(['getMessage'])
            ->getMock();
        $error
            ->expects($this->once())
            ->method('getMessage')
            ->willReturn('Account ID: id value of incorrect type: 001M0000008tWTFIA3');

        $saveResult = $this->getMockBuilder(SaveResult::class)
            ->onlyMethods(['getErrors', 'isSuccess'])
            ->getMock();
        $saveResult
            ->expects($this->once())
            ->method('getErrors')
            ->willReturn([$error]);
        $saveResult
            ->expects($this->once())
            ->method('isSuccess')
            ->willReturn(false);

        $result = new \stdClass();
        $result->result = [$saveResult];

        $soapClient = $this->getSoapClient(['update']);
        $soapClient
            ->expects($this->once())
            ->method('update')
            ->willReturn($result);

        $this->expectException(SaveException::class);
        $this->getClient($soapClient)->update(
            [
                (object)[
                    'Id' => 'invalid-id',
                    'Name' => 'Some name'
                ]
            ],
            'Account'
        );
    }

    public function testMergeMustThrowException()
    {
        $soapClient = $this->getSoapClient(array('merge'));
        $this->expectException('\InvalidArgumentException', 'must be an instance of');
        $this->getClient($soapClient)->merge(array(new \stdClass), 'Account');
    }

    public function testMerge()
    {
        $soapClient = $this->getSoapClient(array('merge'));

        $mergeRequest = new \Salesforce\SoapClient\Request\MergeRequest();
        $masterRecord = new \stdClass();
        $masterRecord->Id = '001M0000007UvSjIAK';
        $masterRecord->Name = 'This will be the new name';
        $mergeRequest->masterRecord = $masterRecord;
        $mergeRequest->recordToMergeIds = array('001M0000008uw8JIAQ');

        $mergeResult = $this->getMockBuilder(MergeResult::class)
            ->getMock();

        $result = new \stdClass();
        $result->result = [$mergeResult];

        $soapClient
            ->expects($this->once())
            ->method('merge')
            ->with(['request' => [$mergeRequest]])
            ->willReturn($result);

        $this->getClient($soapClient)->merge([$mergeRequest], 'Account');
    }

    public function testWithEventDispatcher()
    {
        $response = new \stdClass();

        $error = $this->getMockBuilder(Error::class)
            ->onlyMethods(['getMessage'])
            ->getMock();
        $error
            ->expects($this->once())
            ->method('getMessage')
            ->willReturn('Account ID: id value of incorrect type: 001M0000008tWTFIA3');

        $saveResult = $this->getMockBuilder(SaveResult::class)
            ->onlyMethods(['getErrors', 'isSuccess'])
            ->getMock();
        $saveResult
            ->expects($this->once())
            ->method('getErrors')
            ->willReturn([$error]);
        $saveResult
            ->expects($this->once())
            ->method('isSuccess')
            ->willReturn(false);

        $response->result = [$saveResult];

        $soapClient = $this->getSoapClient(['create']);
        $soapClient
            ->expects($this->once())
            ->method('create')
            ->willReturn($response);

        $client = $this->getClient($soapClient);

        $dispatcher = $this
            ->getMockBuilder(EventDispatcher::class)
            ->disableOriginalConstructor()
            ->getMock();

        $c = new \stdClass();
        $c->AccountId = '123';

        $params = array(
            'sObjects' => array(new \SoapVar($c, SOAP_ENC_OBJECT, 'Contact', Client::SOAP_NAMESPACE))
        );

        $matcher = $this->exactly(2);
        $dispatcher
            ->expects($matcher)
            ->method('dispatch')
            ->willReturnCallback(function (\Symfony\Contracts\EventDispatcher\Event $event, string $eventName) use ($params, $matcher, $saveResult) {
                switch ($matcher->numberOfInvocations()) {
                    case 1:
                        $this->assertEquals('phpforce.soap_client.request', $eventName);
                        $this->assertInstanceOf(RequestEvent::class, $event);
                        assert ($event instanceof RequestEvent);
                        $this->assertEquals('create', $event->getMethod());
                        $this->assertEquals($params, $event->getParams());
                        break;
                    case 2:
                        $this->assertEquals('phpforce.soap_client.response', $eventName);
                        $this->assertInstanceOf(ResponseEvent::class, $event);
                        assert ($event instanceof ResponseEvent);
                        $this->assertEquals('create', $event->getRequestEvent()->getMethod());
                        $this->assertEquals($params, $event->getRequestEvent()->getParams());
                        $this->assertEquals([$saveResult], $event->getResponse());
                        break;
                }
                return $event;
            });
        $client->setEventDispatcher($dispatcher);

        $this->expectException(SaveException::class);
        $client->create([$c], 'Contact');
    }

    protected function getClient(\SoapClient $soapClient)
    {
        return new Client($soapClient, 'username', 'password', 'token');
    }

    protected function getSoapClient(array $methods)
    {
        $soapClient = $this
            ->getMockBuilder(SoapClient::class)
            ->addMethods(array_merge($methods, ['login']))
            ->setConstructorArgs([__DIR__ . '/Fixtures/sandbox.enterprise.wsdl.xml'])
            ->getMock();


        $loginMock = $this
            ->getMockBuilder(LoginResult::class)
            ->onlyMethods(['getSessionId', 'getServerUrl'])
            ->getMock();
        $loginMock
            ->expects($this->any())
            ->method('getSessionId')
            ->willReturn('123');
        $loginMock
            ->expects($this->any())
            ->method('getServerUrl')
            ->willReturn('http://dinges');

        $result = new \stdClass();
        $result->result = $loginMock;

        $soapClient
            ->expects($this->any())
            ->method('login')
            ->willReturn($result);

        return $soapClient;
    }

    /**
     * Set a protected property on an object for testing purposes
     *
     * @param object $object Object
     * @param string $property Property name
     * @param mixed $value Value
     */
    protected function setProperty($object, $property, $value)
    {
        $reflClass = new ReflectionClass($object);
        $reflProperty = $reflClass->getProperty($property);
        $reflProperty->setAccessible(true);
        $reflProperty->setValue($object, $value);

        return $this;
    }
}
