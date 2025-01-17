<?php
declare(strict_types=1);

/**
 * Copyright 2010 - 2019, Cake Development Corporation (https://www.cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2010 - 2019, Cake Development Corporation (https://www.cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
namespace CakeDC\Auth\Test\TestCase\Authenticator;

use Authentication\Authenticator\Result;
use Authentication\Identifier\IdentifierCollection;
use Cake\Core\Configure;
use Cake\Http\ServerRequestFactory;
use Cake\ORM\Entity;
use Cake\TestSuite\TestCase;
use CakeDC\Auth\Authenticator\SocialAuthenticator;
use CakeDC\Auth\Social\Mapper\Facebook as FacebookMapper;
use CakeDC\Auth\Social\Service\OAuth2Service;
use CakeDC\Auth\Social\Service\ServiceFactory;
use InvalidArgumentException;
use Laminas\Diactoros\Uri;
use League\OAuth2\Client\Provider\Facebook;
use League\OAuth2\Client\Provider\FacebookUser;
use League\OAuth2\Client\Token\AccessToken;
use UnexpectedValueException;

/**
 * Test Case for SocialAuthenticator class
 *
 * @package CakeDC\Auth\Test\TestCase\Authenticator
 */
class SocialAuthenticatorTest extends TestCase
{
    public array $fixtures = [
        'plugin.CakeDC/Auth.Users',
        'plugin.CakeDC/Auth.SocialAccounts',
    ];

    /**
     * @var \League\OAuth2\Client\Provider\Facebook
     */
    public $Provider;

    /**
     * @var \Cake\Http\ServerRequest
     */
    public $Request;

    /**
     * Setup the test case, backup the static object values so they can be restored.
     * Specifically backs up the contents of Configure and paths in App if they have
     * not already been backed up.
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->Provider = $this->getMockBuilder(Facebook::class)->setConstructorArgs([
            [
                'graphApiVersion' => 'v2.8',
                'redirectUri' => '/auth/facebook',
                'linkSocialUri' => '/link-social/facebook',
                'callbackLinkSocialUri' => '/callback-link-social/facebook',
                'clientId' => '10003030300303',
                'clientSecret' => 'secretpassword',
            ],
            [],
        ])->setMethods([
            'getAccessToken', 'getState', 'getAuthorizationUrl', 'getResourceOwner',
        ])->getMock();

        $config = [
            'service' => OAuth2Service::class,
            'className' => $this->Provider,
            'mapper' => FacebookMapper::class,
            'options' => [
                'state' => '__TEST_STATE__',
                'graphApiVersion' => 'v2.8',
                'redirectUri' => '/auth/facebook',
                'linkSocialUri' => '/link-social/facebook',
                'callbackLinkSocialUri' => '/callback-link-social/facebook',
                'clientId' => '10003030300303',
                'clientSecret' => 'secretpassword',
            ],
            'collaborators' => [],
            'signature' => null,
            'mapFields' => [],
            'path' => [
                'plugin' => 'CakeDC/Auth',
                'controller' => 'Users',
                'action' => 'socialLogin',
                'prefix' => null,
            ],
        ];
        Configure::write('OAuth.providers.facebook', $config);

        $this->Request = ServerRequestFactory::fromGlobals();
    }

    /**
     * Test authenticate method without social service
     *
     * @return void
     */
    public function testAuthenticateNoSocialService()
    {
        $uri = new Uri('/auth/facebook');
        $this->Request = $this->Request->withUri($uri);
        $this->Request = $this->Request->withQueryParams([
            'code' => 'ZPO9972j3092304230',
            'state' => '__TEST_STATE__',
        ]);
        $this->Request = $this->Request->withAttribute('params', [
            'plugin' => 'CakeDC/Auth',
            'controller' => 'Users',
            'action' => 'socialLogin',
            'provider' => 'facebook',
        ]);
        $this->Request->getSession()->write('oauth2state', '__TEST_STATE__');

        $identifiers = new IdentifierCollection([
            'CakeDC/Auth.Social',
        ]);
        $Authenticator = new SocialAuthenticator($identifiers);
        $result = $Authenticator->authenticate($this->Request);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::FAILURE_CREDENTIALS_MISSING, $result->getStatus());
        $actual = $result->getData();
        $this->assertEmpty($actual);
    }

    /**
     * Test authenticate method with successfull authentication
     *
     * @return void
     */
    public function testAuthenticateSuccessfullyAuthenticated()
    {
        $uri = new Uri('/auth/facebook');
        $this->Request = $this->Request->withUri($uri);
        $this->Request = $this->Request->withQueryParams([
            'code' => 'ZPO9972j3092304230',
            'state' => '__TEST_STATE__',
        ]);
        $this->Request = $this->Request->withAttribute('params', [
            'plugin' => 'CakeDC/Auth',
            'controller' => 'Users',
            'action' => 'socialLogin',
            'provider' => 'facebook',
        ]);
        $this->Request->getSession()->write('oauth2state', '__TEST_STATE__');

        $Token = new AccessToken([
            'access_token' => 'test-token',
            'expires' => 1490988496,
        ]);

        $user = new FacebookUser([
            'id' => '1',
            'name' => 'Test User',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => '4@example.com',
            'hometown' => [
                'id' => '108226049197930',
                'name' => 'Madrid',
            ],
            'picture' => [
                'data' => [
                    'url' => 'https://scontent.xx.fbcdn.net/v/test.jpg',
                    'is_silhouette' => false,
                ],
            ],
            'cover' => [
                'source' => 'https://scontent.xx.fbcdn.net/v/test.jpg',
                'id' => '1',
            ],
            'gender' => 'male',
            'locale' => 'en_US',
            'link' => 'https://www.facebook.com/app_scoped_user_id/1/',
            'timezone' => -5,
            'age_range' => [
                'min' => 21,
            ],
            'bio' => 'I am the best test user in the world.',
            'picture_url' => 'https://scontent.xx.fbcdn.net/v/test.jpg',
            'is_silhouette' => false,
            'cover_photo_url' => 'https://scontent.xx.fbcdn.net/v/test.jpg',
        ]);

        $this->Provider->expects($this->never())
            ->method('getAuthorizationUrl');

        $this->Provider->expects($this->never())
            ->method('getState');

        $this->Provider->expects($this->any())
            ->method('getAccessToken')
            ->with(
                $this->equalTo('authorization_code'),
                $this->equalTo(['code' => 'ZPO9972j3092304230'])
            )
            ->will($this->returnValue($Token));

        $this->Provider->expects($this->any())
            ->method('getResourceOwner')
            ->with(
                $this->equalTo($Token)
            )
            ->will($this->returnValue($user));

        $service = (new ServiceFactory())->createFromProvider('facebook');
        $this->Request = $this->Request->withAttribute('socialService', $service);
        $identifiers = new IdentifierCollection([
            'CakeDC/Auth.Social',
        ]);
        $Authenticator = new SocialAuthenticator($identifiers);

        $result = $Authenticator->authenticate($this->Request);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::SUCCESS, $result->getStatus());
        $actual = $result->getData();
        $this->assertInstanceOf(Entity::class, $actual);
        $this->assertEquals('4@example.com', $actual->email);
        $this->assertEquals('00000000-0000-0000-0000-000000000004', $actual->id);
    }

    /**
     * Test authenticate method with error, getRawData is null
     *
     * @return void
     */
    public function testAuthenticateGetRawDataNull()
    {
        $uri = new Uri('/auth/facebook');
        $this->Request = $this->Request->withUri($uri);
        $this->Request = $this->Request->withQueryParams([
            'code' => 'ZPO9972j3092304230',
            'state' => '__TEST_STATE__',
        ]);
        $this->Request = $this->Request->withAttribute('params', [
            'plugin' => 'CakeDC/Auth',
            'controller' => 'Users',
            'action' => 'socialLogin',
            'provider' => 'facebook',
        ]);
        $this->Request->getSession()->write('oauth2state', '__TEST_STATE__');

        $Token = new AccessToken([
            'access_token' => 'test-token',
            'expires' => 1490988496,
        ]);

        $this->Provider->expects($this->never())
            ->method('getAuthorizationUrl');

        $this->Provider->expects($this->never())
            ->method('getState');

        $this->Provider->expects($this->any())
            ->method('getAccessToken')
            ->with(
                $this->equalTo('authorization_code'),
                $this->equalTo(['code' => 'ZPO9972j3092304230'])
            )
            ->will($this->returnValue($Token));

        $this->Provider->expects($this->any())
            ->method('getResourceOwner')
            ->with(
                $this->equalTo($Token)
            )
            ->will($this->throwException(new UnexpectedValueException('User not found')));

        $service = (new ServiceFactory())->createFromProvider('facebook');
        $this->Request = $this->Request->withAttribute('socialService', $service);
        $identifiers = new IdentifierCollection([
            'CakeDC/Auth.Social',
        ]);
        $Authenticator = new SocialAuthenticator($identifiers);
        $result = $Authenticator->authenticate($this->Request);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::FAILURE_IDENTITY_NOT_FOUND, $result->getStatus());
        $actual = $result->getData();
        $this->assertEmpty($actual);
    }

    /**
     * Test authenticate method with error, getRawData is null
     *
     * @return void
     */
    public function testAuthenticateGetRawDataNotExpectedException()
    {
        $uri = new Uri('/auth/facebook');
        $this->Request = $this->Request->withUri($uri);
        $this->Request = $this->Request->withQueryParams([
            'code' => 'ZPO9972j3092304230',
            'state' => '__TEST_STATE__',
        ]);
        $this->Request = $this->Request->withAttribute('params', [
            'plugin' => 'CakeDC/Auth',
            'controller' => 'Users',
            'action' => 'socialLogin',
            'provider' => 'facebook',
        ]);
        $this->Request->getSession()->write('oauth2state', '__TEST_STATE__');

        $Token = new AccessToken([
            'access_token' => 'test-token',
            'expires' => 1490988496,
        ]);

        $this->Provider->expects($this->never())
            ->method('getAuthorizationUrl');

        $this->Provider->expects($this->never())
            ->method('getState');

        $this->Provider->expects($this->any())
            ->method('getAccessToken')
            ->with(
                $this->equalTo('authorization_code'),
                $this->equalTo(['code' => 'ZPO9972j3092304230'])
            )
            ->will($this->returnValue($Token));

        $this->Provider->expects($this->any())
            ->method('getResourceOwner')
            ->with(
                $this->equalTo($Token)
            )
            ->will($this->throwException(
                new InvalidArgumentException('Invalid argument at getResourceOwner')
            ));

        $service = (new ServiceFactory())->createFromProvider('facebook');
        $this->Request = $this->Request->withAttribute('socialService', $service);
        $identifiers = new IdentifierCollection([
            'CakeDC/Auth.Social',
        ]);
        $Authenticator = new SocialAuthenticator($identifiers);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid argument at getResourceOwner');
        $Authenticator->authenticate($this->Request);
    }

    /**
     * Test authenticate method when social identifier return null
     *
     * @return void
     */
    public function testAuthenticateIdentifierReturnedNull()
    {
        $uri = new Uri('/auth/facebook');
        $this->Request = $this->Request->withUri($uri);
        $this->Request = $this->Request->withQueryParams([
            'code' => 'ZPO9972j3092304230',
            'state' => '__TEST_STATE__',
        ]);
        $this->Request = $this->Request->withAttribute('params', [
            'plugin' => 'CakeDC/Auth',
            'controller' => 'Users',
            'action' => 'socialLogin',
            'provider' => 'facebook',
        ]);
        $this->Request->getSession()->write('oauth2state', '__TEST_STATE__');

        $Token = new AccessToken([
            'access_token' => 'test-token',
            'expires' => 1490988496,
        ]);

        $user = new FacebookUser([
            'id' => '1',
            'name' => 'Test User',
            'first_name' => 'Test',
            'last_name' => 'User',
            'hometown' => [
                'id' => '108226049197930',
                'name' => 'Madrid',
            ],
            'picture' => [
                'data' => [
                    'url' => 'https://scontent.xx.fbcdn.net/v/test.jpg',
                    'is_silhouette' => false,
                ],
            ],
            'cover' => [
                'source' => 'https://scontent.xx.fbcdn.net/v/test.jpg',
                'id' => '1',
            ],
            'gender' => 'male',
            'locale' => 'en_US',
            'link' => 'https://www.facebook.com/app_scoped_user_id/1/',
            'timezone' => -5,
            'age_range' => [
                'min' => 21,
            ],
            'bio' => 'I am the best test user in the world.',
            'picture_url' => 'https://scontent.xx.fbcdn.net/v/test.jpg',
            'is_silhouette' => false,
            'cover_photo_url' => 'https://scontent.xx.fbcdn.net/v/test.jpg',
        ]);

        $this->Provider->expects($this->never())
            ->method('getAuthorizationUrl');

        $this->Provider->expects($this->never())
            ->method('getState');

        $this->Provider->expects($this->any())
            ->method('getAccessToken')
            ->with(
                $this->equalTo('authorization_code'),
                $this->equalTo(['code' => 'ZPO9972j3092304230'])
            )
            ->will($this->returnValue($Token));

        $this->Provider->expects($this->any())
            ->method('getResourceOwner')
            ->with(
                $this->equalTo($Token)
            )
            ->will($this->returnValue($user));

        $service = (new ServiceFactory())->createFromProvider('facebook');
        $this->Request = $this->Request->withAttribute('socialService', $service);
        $identifiers = new IdentifierCollection([]);
        $Authenticator = new SocialAuthenticator($identifiers);
        $Authenticator->authenticate($this->Request);
        $result = $Authenticator->authenticate($this->Request);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::FAILURE_IDENTITY_NOT_FOUND, $result->getStatus());
        $actual = $result->getData();
        $this->assertEmpty($actual);
    }
}
