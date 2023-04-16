<?php
/*
 * This file is part of the LightSAML SP-Bundle package.
 *
 * (c) Milos Tomic <tmilos@lightsaml.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace LightSaml\SpBundle\Controller;

use LightSaml\Builder\Profile\Metadata\MetadataProfileBuilder;
use LightSaml\Builder\Profile\WebBrowserSso\Sp\SsoSpSendAuthnRequestProfileBuilderFactory;
use LightSaml\SymfonyBridgeBundle\Bridge\Container\BuildContainer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends AbstractController
{

	private MetadataProfileBuilder $metadataProfileBuilder;
	private BuildContainer $buildContainer;
	private SsoSpSendAuthnRequestProfileBuilderFactory $ssoSpSendAuthnRequestProfileBuilderFactory;

	public function __construct(MetadataProfileBuilder $metadataProfileBuilder, BuildContainer $buildContainer, SsoSpSendAuthnRequestProfileBuilderFactory $ssoSpSendAuthnRequestProfileBuilderFactory)
	{
		$this->metadataProfileBuilder = $metadataProfileBuilder;
		$this->buildContainer = $buildContainer;
		$this->ssoSpSendAuthnRequestProfileBuilderFactory = $ssoSpSendAuthnRequestProfileBuilderFactory;
	}

    public function metadataAction()
    {
        $context = $this->metadataProfileBuilder->buildContext();
        $action = $this->metadataProfileBuilder->buildAction();

        $action->execute($context);

        return $context->getHttpResponseContext()->getResponse();
    }

    public function discoveryAction()
    {
        $parties = $this->buildContainer->getPartyContainer()->getIdpEntityDescriptorStore()->all();

        if (count($parties) == 1) {
            return $this->redirectToRoute('lightsaml_sp.login', ['idp' => $parties[0]->getEntityID()]);
        }

        return $this->render('@LightSamlSp/discovery.html.twig', [
            'parties' => $parties,
        ]);
    }

    public function loginAction(Request $request)
    {
        $user = $this->getUser();

        if (null !== $user) {
            return $this->redirectToRoute('homepage');
        }

        $idpEntityId = $request->get('idp');
        if (null === $idpEntityId) {
            return $this->redirectToRoute($this->getParameter('lightsaml_sp.route.discovery'));
        }

        $profile = $this->ssoSpSendAuthnRequestProfileBuilderFactory->get($idpEntityId);
        $context = $profile->buildContext();
        $action = $profile->buildAction();

        $action->execute($context);

        return $context->getHttpResponseContext()->getResponse();
    }

    public function sessionsAction()
    {
        $ssoState = $this->buildContainer->getStoreContainer()->getSsoStateStore()->get();

        return $this->render('@LightSamlSp/sessions.html.twig', [
            'sessions' => $ssoState->getSsoSessions(),
        ]);
    }
}
