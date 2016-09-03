<?php

namespace Drupal\Tests\subpathauto\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Language\Language;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\subpathauto\PathProcessor;

/**
 * @coversDefaultClass \Drupal\subpathauto\PathProcessor
 * @group subpathauto
 */
class SubPathautoTest extends UnitTestCase {

  /**
   * @var \Drupal\Core\PathProcessor\PathProcessorAlias|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $aliasProcessor;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $configFactory;

  /**
   * @var \Drupal\Core\Language\LanguageManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $languageManager;

  /**
   * @var \Drupal\language\Annotation\LanguageNegotiation|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $languageNegotiation;

  /**
   * @var \Drupal\Core\Path\PathValidatorInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $pathValidator;

  /**
   * The container.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * The service under testing.
   *
   * @var \Drupal\subpathauto\PathProcessor
   */
  protected $sut;

  /**
   * List of aliases used in the tests.
   *
   * @var string[]
   */
  protected $aliases = [
    '/content/first-node' => '/node/1',
    '/content/first-node-test' => '/node/1/test',
    '/malicious-path' => '/admin',
    '' => '<front>',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->aliasProcessor = $this->getMockBuilder('Drupal\Core\PathProcessor\PathProcessorAlias')
      ->disableOriginalConstructor()
      ->getMock();

    $this->languageNegotiation = $this->getMockBuilder('Drupal\language\Annotation\LanguageNegotiation')
      ->disableOriginalConstructor()
      ->getMock();

    $this->configFactory = $this->getMock('Drupal\Core\Config\ConfigFactoryInterface');
    $this->configFactory->expects($this->any())
      ->method('get')
      ->with('language.negotiation')
      ->willReturn($this->languageNegotiation);

    $this->languageManager = $this->getMock('Drupal\Core\Language\LanguageManagerInterface');
    $this->languageManager->expects($this->any())
      ->method('getCurrentLanguage')
      ->willReturn(new Language());

    $this->pathValidator = $this->getMock('Drupal\Core\Path\PathValidatorInterface');

    $this->sut = new PathProcessor($this->aliasProcessor, $this->configFactory, $this->languageManager);
    $this->sut->setPathValidator($this->pathValidator);
  }

  /**
   * @covers ::processInbound
   */
  public function testInboundSubPath() {
    $this->aliasProcessor->expects($this->any())
      ->method('processInbound')
      ->will($this->returnCallback([$this, 'pathAliasCallback']));
    $this->pathValidator->expects($this->any())
      ->method('getUrlIfValidWithoutAccessCheck')
      ->willReturn(new Url('any_route'));

    // Look up a subpath of the 'content/first-node' alias.
    $processed = $this->sut->processInbound('/content/first-node/a', Request::create('content/first-node/a'));
    $this->assertEquals('/node/1/a', $processed);

    // Look up a multilevel subpath of the '/content/first-node' alias.
    $processed = $this->sut->processInbound('/content/first-node/kittens/more-kittens', Request::create('content/first-node/kittens/more-kittens'));
    $this->assertEquals('/node/1/kittens/more-kittens', $processed);

    // Look up a subpath of the 'content/first-node-test' alias.
    $processed = $this->sut->processInbound('/content/first-node-test/a', Request::create('content/first-node-test/a'));
    $this->assertEquals('/node/1/test/a', $processed);

    // Look up an admin sub-path of the 'content/first-node' alias without
    // disabling sub-paths for admin.
    $processed = $this->sut->processInbound('/content/first-node/edit', Request::create('content/first-node/edit'));
    $this->assertEquals('/node/1/edit', $processed);

    // Look up an admin sub-path without disabling sub-paths for admin.
    $processed = $this->sut->processInbound('/malicious-path/modules', Request::create('malicious-path/modules'));
    $this->assertEquals('/admin/modules', $processed);
  }

  /**
   * @covers ::processInbound
   */
  public function testInboundAlreadyProcessed() {
    // The subpath processor should ignore this and not pass it on to the
    // alias processor.
    $processed = $this->sut->processInbound('node/1', Request::create('content/first-node'));
    $this->assertEquals('node/1', $processed);
  }

  /**
   * @covers ::processOutbound
   */
  public function testOutboundSubPath() {
    $this->aliasProcessor->expects($this->any())
      ->method('processOutbound')
      ->will($this->returnCallback([$this, 'aliasByPathCallback']));

    // Look up a subpath of the 'content/first-node' alias.
    $processed = $this->sut->processOutbound('/node/1/a');
    $this->assertEquals('/content/first-node/a', $processed);

    // Look up a multilevel subpath of the '/content/first-node' alias.
    $processed = $this->sut->processOutbound('/node/1/kittens/more-kittens');
    $this->assertEquals('/content/first-node/kittens/more-kittens', $processed);

    // Look up a subpath of the 'content/first-node-test' alias.
    $processed = $this->sut->processOutbound('/node/1/test/a');
    $this->assertEquals('/content/first-node-test/a', $processed);

    // Look up an admin sub-path of the 'content/first-node' alias without
    // disabling sub-paths for admin.
    $processed = $this->sut->processOutbound('/node/1/edit');
    $this->assertEquals('/content/first-node/edit', $processed);

    // Look up an admin sub-path without disabling sub-paths for admin.
    $processed = $this->sut->processOutbound('/admin/modules');
    $this->assertEquals('/malicious-path/modules', $processed);
  }

  /**
   * @covers ::processOutbound
   */
  public function testOutboundAbsoluteUrl() {
    // The subpath processor should ignore this and not pass it on to the
    // alias processor.
    $options = ['absolute' => TRUE];
    $processed = $this->sut->processOutbound('node/1', $options);
    $this->assertEquals('node/1', $processed);
  }

  /**
   * Return value callback for getSystemPath() method on the mock alias manager.
   *
   * Ensures that by default the call to getPathAlias() will return the first
   * argument that was passed in. We special-case the paths for which we wish it
   * to return an actual alias.
   *
   * @param string $path
   *   The path.
   *
   * @return string
   */
  public function pathAliasCallback($path) {
    return isset($this->aliases[$path]) ? $this->aliases[$path] : $path;
  }

  /**
   * Return value callback for getAliasByPath() method on the alias manager.
   *
   * @param string $path
   *   The path.
   *
   * @return string
   */
  public function aliasByPathCallback($path) {
    $aliases = array_flip($this->aliases);
    return isset($aliases[$path]) ? $aliases[$path] : $path;
  }

}
