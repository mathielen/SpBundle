<?php
/*
 * This file is part of the LightSAML SP-Bundle package.
 *
 * (c) Milos Tomic <tmilos@lightsaml.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace LightSaml\SpBundle\Tests\Functional;

use LightSaml\SpBundle\Tests\Integration\ContainerAwareTestCase;
use LightSaml\State\Sso\SsoSessionState;
use LightSaml\State\Sso\SsoState;
use LightSaml\Store\Sso\SsoStateStoreInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class FunctionalTest extends ContainerAwareTestCase
{
    const OWN_ENTITY_ID = 'https://localhost/lightSAML/SPBundle';

    public function testMetadata()
    {
        $this->client->request('GET', '/saml/metadata.xml');

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $xml = $this->client->getResponse()->getContent();

        $root = new \SimpleXMLElement($xml);
        $this->assertEquals('EntityDescriptor', $root->getName());
        $this->assertEquals(self::OWN_ENTITY_ID, $root['entityID']);
        $this->assertEquals(1, $root->SPSSODescriptor->count());
        $this->assertEquals(2, $root->SPSSODescriptor->KeyDescriptor->count());
        $this->assertEquals(1, $root->SPSSODescriptor->AssertionConsumerService->count());
    }

    public function testDiscovery()
    {
        $crawler = $this->client->request('GET', '/saml/discovery');

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $idpCrawler = $crawler->filter('a[data-idp]');
        $this->assertEquals(3, $idpCrawler->count());
        $arr = [];
        foreach ($idpCrawler as $idp) {
            $arr[$idp->getAttribute('data-idp')] = 1;
        }
        $this->assertArrayHasKey('https://openidp.feide.no', $arr);
        $this->assertArrayHasKey('https://localhost/lightSAML/lightSAML-IDP', $arr);
        $this->assertArrayHasKey('https://idp.testshib.org/idp/shibboleth', $arr);
    }

    public function testLogin()
    {
        $this->client->getContainer()->set('session', $sessionMock = $this->getMockBuilder(SessionInterface::class)->getMock());

        $crawler = $this->client->request('GET', '/saml/login?idp=https://localhost/lightSAML/lightSAML-IDP');

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $crawlerForm = $crawler->filter('form');
        $this->assertEquals(1, $crawlerForm->count());
        $this->assertEquals('https://localhost/lightsaml/lightSAML-IDP/web/idp/login.php', $crawlerForm->first()->attr('action'));

        $crawlerSamlRequest = $crawler->filter('input[name="SAMLRequest"]');
        $this->assertEquals(1, $crawlerSamlRequest->count());
        $code = $crawlerSamlRequest->first()->attr('value');
        $xml = base64_decode($code);

        $root = new \SimpleXMLElement($xml);
        $this->assertEquals('AuthnRequest', $root->getName());
        $this->assertEquals('https://localhost/lightsaml/lightSAML-IDP/web/idp/login.php', $root['Destination']);
        $this->assertEquals(1, $root->children('saml', true)->Issuer->count());
        $this->assertEquals(self::OWN_ENTITY_ID, (string) $root->children('saml', true)->Issuer);
    }

    public function testSessions()
    {
        $ssoState = new SsoState();
        $ssoState->addSsoSession((new SsoSessionState())->setIdpEntityId('idp1')->setSpEntityId('sp1'));
        $ssoState->addSsoSession((new SsoSessionState())->setIdpEntityId('idp2')->setSpEntityId('sp2'));

        $ssoStateStoreMock = $this->getMockBuilder(SsoStateStoreInterface::class)->getMock();
        $ssoStateStoreMock->method('get')
            ->willReturn($ssoState);

        $this->client->getContainer()->set('lightsaml.store.sso_state', $ssoStateStoreMock);

        $crawler = $this->client->request('GET', '/saml/sessions');

        $crawlerSessions = $crawler->filter('ul[data-session]');
        $this->assertEquals(2, $crawlerSessions->count());

        $this->assertEquals('idp1', $crawlerSessions->first()->filter('li[data-idp]')->attr('data-idp'));
        $this->assertEquals('sp1', $crawlerSessions->first()->filter('li[data-sp]')->attr('data-sp'));
        $this->assertEquals('idp2', $crawlerSessions->last()->filter('li[data-idp]')->attr('data-idp'));
        $this->assertEquals('sp2', $crawlerSessions->last()->filter('li[data-sp]')->attr('data-sp'));
    }

    public function testLogout()
    {
        $ssoState = new SsoState();
        $ssoState->addSsoSession(
            (new SsoSessionState())
                ->setIdpEntityId('idp1')
                ->setSpEntityId('sp1')
                ->setNameId('nameId')
                ->setNameIdFormat('nameIdFormat')
        );

        $ssoStateStoreMock = $this->getMockBuilder(SsoStateStoreInterface::class)->getMock();
        $ssoStateStoreMock->method('get')
            ->willReturn($ssoState);

        $this->client->getContainer()->set('lightsaml.store.sso_state', $ssoStateStoreMock);
        $this->client->getContainer()->set('session', $sessionMock = $this->getMockBuilder(SessionInterface::class)->getMock());

        $this->client->request('GET', '/saml/logout');

        $logoutRequestUrl = $this->client->getResponse()->headers->get('location');
        $samlRequestParam = explode('&', parse_url($logoutRequestUrl, PHP_URL_QUERY))[0];
        $samlRequest = gzinflate(base64_decode(urldecode(explode('=', $samlRequestParam)[1])));

        $root = new \SimpleXMLElement($samlRequest);

        $this->assertEquals(302, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('LogoutRequest', $root->getName());
        $this->assertEquals('https://openidp.feide.no/simplesaml/saml2/idp/SingleLogoutService.php', $root['Destination']);
    }
}
