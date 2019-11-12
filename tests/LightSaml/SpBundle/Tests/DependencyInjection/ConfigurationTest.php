<?php

namespace LightSaml\SpBundle\Tests\DependencyInjection;

use LightSaml\ClaimTypes;
use LightSaml\SpBundle\DependencyInjection\Configuration;
use LightSaml\SpBundle\Security\User\SimpleUsernameMapper;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends \PHPUnit_Framework_TestCase
{
    public function testAllowEmptyConfiguration()
    {
        $emptyConfig = array();
        $this->processConfiguration($emptyConfig);
    }

    public function testAllowSetUsernameMapperScalarArray()
    {
        $config = [
            'light_saml_sp' => [
                'username_mapper' => [
                    'a', 'b', 'c',
                ],
            ],
        ];
        $this->processConfiguration($config);
    }

    public function testAllowSetEntityIdResolverScalar()
    {
        $config = [
            'light_saml_sp' => [
                'entity_id_provider' => 'entity_id_provider_name'
            ],
        ];
        $this->processConfiguration($config);
    }

    public function testSetsDefaultUsernameMapper()
    {
        $config = ['light_saml_sp' => []];
        $processedConfig = $this->processConfiguration($config);
        $this->assertArrayHasKey('username_mapper', $processedConfig);
        $this->assertTrue(is_array($processedConfig['username_mapper']));
        $this->assertEquals(
            [
                ClaimTypes::EMAIL_ADDRESS,
                ClaimTypes::ADFS_1_EMAIL,
                ClaimTypes::COMMON_NAME,
                ClaimTypes::WINDOWS_ACCOUNT_NAME,
                'urn:oid:0.9.2342.19200300.100.1.3',
                'uid',
                'urn:oid:1.3.6.1.4.1.5923.1.1.1.6',
                SimpleUsernameMapper::NAME_ID,
            ],
            $processedConfig['username_mapper']
        );
    }

    public function testAllowToConfigureIdpDataMetadataProvider()
    {
        $config = [
            'light_saml_sp' => [
                'idp_data_metadata_provider' => [
                    'enabled' => 1,
                    'idp_data_url' => 'idp-data',
                    'domain_resolver_url' => 'domain-resolver'
                ]
            ],
        ];
        $this->processConfiguration($config);
    }

    public function testItThrowsExceptionIfIdpDataUrlIsNotDefined()
    {
        $this->expectException(InvalidConfigurationException::class);
        $config = [
            'light_saml_sp' => [
                'idp_data_metadata_provider' => [
                    'enabled' => 1,
                    'domain_resolver_url' => 'domain-resolver'
                ]
            ],
        ];
        $this->processConfiguration($config);
    }

    public function testItThrowsExceptionIfDomainResolverUrlIsNotDefined()
    {
        $this->expectException(InvalidConfigurationException::class);
        $config = [
            'light_saml_sp' => [
                'idp_data_metadata_provider' => [
                    'enabled' => 1,
                    'idp_data_url' => 'idp-data'
                ]
            ],
        ];
        $this->processConfiguration($config);
    }

    public function testItThrowsExceptionIfIdpDataUrlIsEmpty()
    {
        $this->expectException(InvalidConfigurationException::class);
        $config = [
            'light_saml_sp' => [
                'idp_data_metadata_provider' => [
                    'enabled' => 1,
                    'idp_data_url' => '',
                    'domain_resolver_url' => 'domain-resolver'
                ]
            ],
        ];
        $this->processConfiguration($config);
    }

    public function testItThrowsExceptionIfDomainResolverUrlIsEmpty()
    {
        $this->expectException(InvalidConfigurationException::class);
        $config = [
            'light_saml_sp' => [
                'idp_data_metadata_provider' => [
                    'enabled' => 1,
                    'idp_data_url' => 'idp-data',
                    'domain_resolver_url' => ''
                ]
            ],
        ];
        $this->processConfiguration($config);
    }

    public function testEnablingIdpDataMetadataProviderIsOptional()
    {
        $config = [
            'light_saml_sp' => [
                'idp_data_metadata_provider' => [
                    'idp_data_url' => 'idp-data',
                    'domain_resolver_url' => 'domain-resolver'
                ]
            ],
        ];
        $this->processConfiguration($config);
    }

    /**
     * @param array $configs
     *
     * @return array
     */
    protected function processConfiguration(array $configs)
    {
        $configuration = new Configuration();
        $processor = new Processor();

        return $processor->processConfiguration($configuration, $configs);
    }
}
