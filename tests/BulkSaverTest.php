<?php
namespace Salesforce\SoapClient\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Salesforce\SoapClient\BulkSaver;
use Salesforce\SoapClient\Client;

class BulkSaverTest extends TestCase
{
    public function testCreate()
    {
        $client = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $client
            ->expects($this->exactly(3))
            ->method('create')
            ->with($this->isType('array'), $this->equalTo('Account'));

        $bulkSaver = new BulkSaver($client);

        for ($i = 0; $i < 401; $i++) {
            $record = new \stdClass();
            $record->Name = 'An account';
            $bulkSaver->save($record, 'Account');
        }
        $bulkSaver->flush();
    }

    public function testUpdate()
    {
        $client = $this->getClient();

        $client
            ->expects($this->exactly(2))
            ->method('update')
            ->with($this->isType('array'), $this->equalTo('Account'));

        $bulkSaver = new BulkSaver($client);

        for ($i = 0; $i < 400; $i++) {
            $record = new \stdClass();
            $record->Name = 'An account';
            $record->Id = 123;
            $bulkSaver->save($record, 'Account');
        }
        $bulkSaver->flush();
    }

    public function testDelete()
    {
        $tasks = array();
        for ($i = 0; $i < 202; $i++) {
            $task = new \stdClass();
            $task->Id = $i+1;
            $tasks[] = $task;
        }

        $client = $this->getClient();
        $matcher = $this->exactly(2);
        $client->expects($matcher)
            ->method('delete')
            ->willReturnCallback(function ($ids) use ($client, $matcher) {
                switch ($matcher->numberOfInvocations()) {
                    case 1:
                        $this->assertEquals(range(1, 200), $ids);
                        break;
                    case 2:
                        $this->assertEquals(range(201, 202), $ids);
                        break;
                }
                return $client;
            });

        $bulkSaver = new BulkSaver($client);
        foreach ($tasks as $task) {
            $bulkSaver->delete($task);
        }
        $bulkSaver->flush();
    }

    public function testDeleteWithoutIdThrowsException()
    {
        $client = $this->getClient();
        $bulkSaver = new BulkSaver($client);
        $invalidRecord = new \stdClass();
        $this->expectException(InvalidArgumentException::class);
        $bulkSaver->delete($invalidRecord);
    }

    public function testUpsert()
    {
        $client = $this->getClient();
        $client->expects($this->exactly(2))
            ->method('upsert')
            ->with('Name', $this->isType('array'), 'Account');

        $account = new \stdClass();
        $account->Name = 'Upsert this';
        $account->BillingPostalCode = '1234 AB';
        $bulkSaver = new BulkSaver($client);

        for ($i = 0; $i < 201; $i++) {
            $bulkSaver->save($account, 'Account', 'Name');
        }
        $bulkSaver->flush();
    }

    public function testFlushEmpty()
    {
        $bulkSaver = new BulkSaver($this->getClient());
        $this->assertEquals([], $bulkSaver->flush());
    }

    protected function getClient()
    {
        return $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
