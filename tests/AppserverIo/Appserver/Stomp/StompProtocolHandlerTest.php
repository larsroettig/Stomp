<?php
/**
 * \AppserverIo\Appserver\Stomp\StompProtocolHandlerTest
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @category   Library
 * @package    TechDivision_StompProtocol
 * @author     Lars Roettig <l.roettig@techdivision.com>
 * @copyright  2014 TechDivision GmbH <info@techdivision.com>
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link       https://github.com/appserver-io/appserver
 * @link       https://github.com/stomp/stomp-spec/blob/master/src/stomp-specification-1.1.md
 */

namespace AppserverIo\Appserver\Stomp;

use AppserverIo\MessageQueueClient\QueueSender;
use PDepend\TextUI\Command;
use AppserverIo\Appserver\Stomp\Exception\StompProtocolException;
use AppserverIo\Appserver\Stomp\Protocol\ClientCommands;
use AppserverIo\Appserver\Stomp\Protocol\CommonValues;
use AppserverIo\Appserver\Stomp\Protocol\Headers;
use AppserverIo\Appserver\Stomp\Protocol\ServerCommands;


/**
 * Implementation to test handle stomp handler.
 *
 * @category   Library
 * @package    TechDivision_StompProtocol
 * @author     Lars Roettig <l.roettig@techdivision.com>
 * @copyright  2014 TechDivision GmbH <info@techdivision.com>
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link       https://github.com/appserver-io/appserver
 * @link       https://github.com/stomp/stomp-spec/blob/master/src/stomp-specification-1.1.md
 */
class StompProtocolHandlerTest extends HelperTestCase
{

    /**
     * @var \AppserverIo\Appserver\Stomp\StompProtocolHandler
     */
    protected $handler;

    /**
     * Initializes the configuration instance to test.
     *
     * @return void
     */
    public function setUp()
    {
        // init new stomp protocol handler
        $this->handler = new StompProtocolHandler();

        // init new authenticator mock object
        /** @var \AppserverIo\Appserver\Stomp\Interfaces\Authenticator $authenticator */
        $authenticator = $this->getMockBuilder('AppserverIo\Appserver\Stomp\Interfaces\AuthenticatorInterface')->getMock();

        $authenticator->expects($this->any())
            ->method('connect')
            ->will($this->returnValue(md5(rand())));

        $authenticator->expects($this->any())
            ->method('connect')
            ->will($this->returnCallback(function ($name, $password) {
                if ($name == "Fo" && $password == "bar") {
                    return md5(rand());
                } else {
                    throw new StompProtocolException("");
                }
            }));

        $authenticator->expects($this->any())
            ->method("getIsAuthenticated")
            ->will($this->returnValue(true));


        // inject the authenticator mock object
        $this->handler->injectAuthenticator($authenticator);
    }

    /**
     *
     * @return void
     */
    public function testConnectSuccessfully()
    {
        // create some test data
        $stompFrame = new StompFrame(ClientCommands::CONNECT, array(
            Headers::ACCEPT_VERSION => CommonValues::V1_0,
            Headers::LOGIN => "Fo",
            Headers::PASSCODE => "bar"
        ));

        // call the function we want test
        $this->handler->handle($stompFrame);
    }

    /**
     * @expectedException \AppserverIo\Appserver\Stomp\Exception\StompProtocolException
     *
     *
     * @return void
     */
    public function testConnectWithProtocolVersionException()
    {
        // create some test data
        $stompFrame = new StompFrame(ClientCommands::CONNECT, array(
            Headers::ACCEPT_VERSION => "2.0",
        ));

        // call the function we want test
        $this->handler->handle($stompFrame);
    }


    /**
     * @expectedException \AppserverIo\Appserver\Stomp\Exception\StompProtocolException
     *
     * @return void
     */
    public function testConnectWithAuthenticatorException()
    {

        // create some test data
        $stompFrame = new StompFrame(ClientCommands::CONNECT, array(
            Headers::ACCEPT_VERSION => CommonValues::V1_0,
            Headers::LOGIN => "Fo",
        ));

        // call the function we want test
        $this->handler->handle($stompFrame);
    }

    /**
     * @return  void
     */
    public function testDisConnect()
    {
        // create some test data
        $disConnect = new StompFrame(ClientCommands::DISCONNECT);

        // call the function we want test
        $this->handler->handle($disConnect);

        $response = $this->handler->getResponseStompFrame();
        $this->assertTrue($this->handler->getMustConnectionClose());
        $this->assertEquals($response, new StompFrame(ServerCommands::RECEIPT));
    }

    /**
     * @return  void
     */
    public function testHandleError()
    {

        $message = "foo bar";
        // call the function we want test
        $this->handler->setErrorState($message);

        $response = $this->handler->getResponseStompFrame();
        $this->assertTrue($this->handler->getMustConnectionClose());
        $this->assertEquals($response, new StompFrame(ServerCommands::ERROR, array(), $message));
    }

    /**
     * @expectedException \AppserverIo\Appserver\Stomp\Exception\StompProtocolException
     */
    public function testHandleSendNotAuthenticated()
    {
        // create some test data
        $stompFrame = new StompFrame(ClientCommands::SEND, array(
            Headers::ACCEPT_VERSION => "1.0",
        ));

        $authenticator = $this->getMockBuilder('AppserverIo\Appserver\Stomp\Interfaces\AuthenticatorInterface')->getMock();

        $authenticator->expects($this->any())
            ->method("getIsAuthenticated")
            ->will($this->returnValue(false));


        // inject the authenticator mock object
        $this->handler->injectAuthenticator($authenticator);

        $this->handler->handle($stompFrame);
    }

    /**
     *
     */
    public function testHandleSendAuthenticated()
    {
        // create some test data
        $stompFrame = new StompFrame(ClientCommands::SEND, array(
            Headers::ACCEPT_VERSION => "1.0",
        ));


        $stompFrame->setBody("bar foo 1234");

        $stubQueueSession = $this->getMockBuilder('AppserverIo\MessageQueueClient\QueueSession')
            ->disableOriginalConstructor()
            ->getMock();

        $stubQueueSender = $this->getMockBuilder('AppserverIo\MessageQueueClient\QueueSender')
            ->disableOriginalConstructor()
            ->getMock();

        $stubQueueSession->expects($this->any())
            ->method('createSender')
            ->will($this->returnValue($stubQueueSender));

        $isEqual = false;
        $stubQueueSender->expects($this->any())
            ->method('send')
            ->will($this->returnCallback(function ($message) use (&$isEqual) {
                if ($message->getMessage() === "bar foo 1234") {
                    $isEqual = true;
                }
            }));

        $this->handler->setSession($stubQueueSession);

        $this->handler->handle($stompFrame);

        $this->assertTrue($isEqual);
    }
}
