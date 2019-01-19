<?php
/**
 * Created by PhpStorm.
 * User: drupsys
 * Date: 19/01/19
 * Time: 12:35.
 */

namespace MVF\Servicer\Tests\Queues;

use AspectMock\Test;
use Aws\Result;
use Codeception\Stub\Expected;
use MVF\Servicer\Clients\SqsClient;
use MVF\Servicer\Config;
use MVF\Servicer\EventHandler;
use MVF\Servicer\Queues\SqsQueue;

class SqsQueueTest extends \Codeception\Test\Unit
{
    private $messages;

    public function _after()
    {
        Test::clean();
    }

    public function _before()
    {
        $this->messages = [
            ['ReceiptHandle' => 'test'],
        ];
    }

    public function testThatTheGetEventsReturnsTheInjectedEventsObject()
    {
        $config = $this->makeEmpty(Config::class);
        $actions = $this->make(EventHandler::class);
        $queue = new SqsQueue($config, $actions);
        self::assertEquals($actions, $queue->getEvents());
    }

    public function testCircuitBreakerWorks()
    {
        $config = $this->makeEmpty(Config::class, ['isCircuitBreakerClosed' => true]);
        $actions = $this->make(EventHandler::class);
        $queue = new SqsQueue($config, $actions);

        $client = $this->make(SqsClient::class, ['receiveMessage' => Expected::never()]);
        Test::double(SqsClient::class, ['instance' => $client]);
        $queue->listen();
    }

    public function testThatHeadersArePassedToAction()
    {
        $config = $this->make(Config::class);
        $triggerAction = function (\stdClass $headers, \stdClass $body) {
            self::assertEquals('1.0.0', $headers->version);
        };

        $actions = $this->make(EventHandler::class, ['triggerAction' => $triggerAction]);
        $queue = new SqsQueue($config, $actions);

        $this->messages[0]['MessageAttributes'] = [
            'Version' => ['DataType' => 'String', 'StringValue' => '1.0.0'],
        ];

        $result = $this->make(Result::class, ['get' => $this->messages]);
        $client = $this->makeEmpty(SqsClient::class, ['receiveMessage' => $result]);
        Test::double(SqsClient::class, ['instance' => $client]);
        $queue->listen();
    }

    public function testThatEmptyHeadersAreAlwaysPassedToAction()
    {
        $config = $this->make(Config::class);
        $triggerAction = function (\stdClass $headers, \stdClass $body) {
            self::assertEquals((object)[], $headers);
        };

        $actions = $this->make(EventHandler::class, ['triggerAction' => $triggerAction]);
        $queue = new SqsQueue($config, $actions);

        $result = $this->make(Result::class, ['get' => $this->messages]);
        $client = $this->makeEmpty(SqsClient::class, ['receiveMessage' => $result]);
        Test::double(SqsClient::class, ['instance' => $client]);
        $queue->listen();
    }

    public function testCaseWhereNoMessagesWereReceived()
    {
        $config = $this->make(Config::class);
        $actions = $this->make(EventHandler::class, ['triggerAction' => Expected::never()]);
        $queue = new SqsQueue($config, $actions);

        $result = $this->make(Result::class, ['get' => null]);
        $client = $this->makeEmpty(SqsClient::class, ['receiveMessage' => $result]);
        Test::double(SqsClient::class, ['instance' => $client]);
        $queue->listen();
    }

    public function testThatBodyIsPassedToAction()
    {
        $config = $this->make(Config::class);
        $triggerAction = function (\stdClass $headers, \stdClass $body) {
            self::assertEquals('john', $body->name);
        };

        $actions = $this->make(EventHandler::class, ['triggerAction' => $triggerAction]);
        $queue = new SqsQueue($config, $actions);

        $this->messages[0]['Body'] = '{"name":"john"}';
        $result = $this->make(Result::class, ['get' => $this->messages]);
        $client = $this->makeEmpty(SqsClient::class, ['receiveMessage' => $result]);
        Test::double(SqsClient::class, ['instance' => $client]);
        $queue->listen();
    }

    public function testThatEmptyBodyIsAlwaysPassedToAction()
    {
        $config = $this->make(Config::class);
        $triggerAction = function (\stdClass $headers, \stdClass $body) {
            self::assertEquals((object)[], $body);
        };

        $actions = $this->make(EventHandler::class, ['triggerAction' => $triggerAction]);
        $queue = new SqsQueue($config, $actions);

        $result = $this->make(Result::class, ['get' => $this->messages]);
        $client = $this->makeEmpty(SqsClient::class, ['receiveMessage' => $result]);
        Test::double(SqsClient::class, ['instance' => $client]);
        $queue->listen();
    }
}